<?php

namespace App\Console\Commands\Affiliates;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Affiliates\AwinController;
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
                            { --option= : Options: stores }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Command description";

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

            $url          = $this->getDomain( $store[ "displayUrl" ] );
            $awinStores[] = [
                "id"         => $store[ "id" ],
                "name"       => "awin",
                "domain"     => $url,
                "commission" => $cashback,
            ];
        }

        echo "Command : End Awin Stores processing\n";
        Log::channel( "affiliate" )->info( "Stores", [ "End Awin Stores processing" ] );

        // =============================================================================

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

            $url        = $this->getDomain( $store[ "link" ] );
            $ssStores[] = [
                "id"         => $store[ "id" ],
                "name"       => "socialsoul",
                "domain"     => $url,
                "commission" => $cashback,
            ];
        }

        echo "Command : End SocialSoul Stores processing\n";
        Log::channel( "affiliate" )->info( "Stores", [ "End SocialSoul Stores processing" ] );

        // =============================================================================
        //
        // Outros programas de afiliados a serem adicionados
        //
        // =============================================================================

        $merge = $this->mergeStores( $awinStores, $ssStores );
        $data  = $this->prepareForInsert( $merge );

        DB::table( "affiliates_stores" )->insert( $data );
    }

    private function getDomain( string $url )
    {
        $parse  = parse_url( $url, PHP_URL_HOST );
        preg_match( "/[a-z0-9\-]{1,63}\.[a-z\.]{2,6}$/", $parse, $domain );

        $domain = reset( $domain );
        $domain = str_replace( "www.", "", $domain );

        return $domain;
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
