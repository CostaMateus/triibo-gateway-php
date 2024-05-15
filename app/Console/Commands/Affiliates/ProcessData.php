<?php

namespace App\Console\Commands\Affiliates;

use App\Helpers\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Integrations\AffiliatesIntegration\AwinIntegration;
use App\Http\Integrations\AffiliatesIntegration\SocialSoulIntegration;

class ProcessData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "affiliate:process
                            { --option=stores : Options: stores }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Save stores from affiliate programs locally without duplication";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $opts   = [
            "stores",
        ];

        $option = $this->option( "option" );

        if ( !in_array( $option, $opts ) )
        {
            echo "Invalid option, terminating process!\n";
            return;
        }

        $this->{$option}();
    }

    private function stores()
    {
        /**
         * Awin
         */
        $awinStores = $this->awin();

        /**
         * SocialSoul
         */
        $ssStores   = $this->socialsoul();

        /**
         * Outros programas de afiliados a serem adicionados
         */
        //

        $merge      = $this->mergeStores( $awinStores, $ssStores );
        $data       = $this->prepareForInsert( $merge );

        DB::table( "affiliates_stores" )->truncate();
        DB::table( "affiliates_stores" )->insert( $data );
    }

    private function awin()
    {
        echo "Command : Start processing of Awin Stores\n";
        Log::channel( "affiliate" )->info( "Stores", [ "Start processing of Awin Stores" ]);

        $awin       = new AwinIntegration();
        $stores     = $awin->schGetStores();
        $awinStores = [];

        foreach ( $stores as $store )
        {
            $cashback     = null;
            $commission   = $awin->searchStoresDetails( ( int ) $store[ "id" ] );
            $commission   = reset( $commission );

            if ( $commission )
            {
                $cashback = ( float ) $commission[ "max" ] / 2;
                $cashback = ( float ) number_format( $cashback, 1, ".", "" );
            }

            $url          = triibo_parse_url( $store[ "displayUrl" ] );
            $awinStores[] = [
                "id"         => $store[ "id" ],
                "name"       => "awin",
                "domain"     => $url,
                "commission" => $cashback,
            ];
        }

        echo "Command : End Awin Stores processing\n";
        Log::channel( "affiliate" )->info( "Stores", [ "End Awin Stores processing" ] );

        return $awinStores;
    }

    private function socialsoul()
    {
        echo "Command : Start processing of SocialSoul Stores\n";
        Log::channel( "affiliate" )->info( "Stores", [ "Start processing of SocialSoul Stores" ]);

        $socialSoul = new SocialSoulIntegration();
        $stores     = $socialSoul->schGetStores();
        $ssStores   = [];
        ksort( $stores, SORT_NUMERIC );

        foreach ( $stores as $store )
        {
            $cashback   = null;
            $cashback   = ( float ) $store[ "commission" ] / 2;
            $cashback   = ( float ) number_format( $cashback, 1, ".", "" );

            $url        = triibo_parse_url( $store[ "link" ] );
            $ssStores[] = [
                "id"         => $store[ "id" ],
                "name"       => "socialsoul",
                "domain"     => $url,
                "commission" => $cashback,
            ];
        }

        echo "Command : End SocialSoul Stores processing\n";
        Log::channel( "affiliate" )->info( "Stores", [ "End SocialSoul Stores processing" ] );

        return $ssStores;
    }

    private function mergeStores( array ...$args )
    {
        $aux = $args[ 0 ];
        unset( $args[ 0 ] );

        foreach ( $args as $stores )
        {
            foreach ( $stores as $store )
            {
                $key = array_search( $store[ "domain" ], array_column( $aux, "domain" ) );

                if ( $key === false ) $aux[] = $store;
            }
        }

        return $aux;
    }

    private function prepareForInsert( array $stores )
    {
        $data = [];

        foreach ( $stores as $store )
        {
            $data[] = [
                "affiliate_id"   => $store[ "id"         ],
                "affiliate_name" => $store[ "name"       ],
                "domain"         => $store[ "domain"     ],
                "commission"     => $store[ "commission" ],
                "created_at"     => now(),
                "updated_at"     => now()
            ];
        }

        return $data;
    }
}
