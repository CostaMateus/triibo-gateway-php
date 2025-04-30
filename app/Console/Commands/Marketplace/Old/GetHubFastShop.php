<?php

namespace App\Console\Commands\Marketplace\Old;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Http, Storage};

class GetHubFastShop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mkt:get_hub_fs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get HUB FS';
    protected $fsUrl;
    protected $fsHeaders;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $env = config( "fastshop.env" );
        $this->fsUrl = config( "fastshop.{$env}.fs.url" );
        $this->fsHeaders = config( "fastshop.{$env}.fs.headers" );
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $status = "active";
        $cep = "04024-020";

        $logs = [
            "error" => 0,
            "success" => [],
            "outofstock" => 0,
            "delivery-unavailable" => 0,
        ];

        $products = DB::table( "marketplace_product_skus" )->where( "status", $status )->get();

        $body = [
            "postalCode" => $cep,
            "country" => "BRA",
            "geoCoordinates" => [],
            "items" => []
        ];

        foreach ( $products as $p )
        {
            $sku = explode( "FS-", $p->product_sku )[ 1 ];

            $body[ "items" ][ 0 ] = [
                "id" => $sku,
                "quantity" => "1",
                "seller" => "FSH",
            ];

            $this->callFs( $body, $sku, $logs );
        }

        Storage::put( "fs-hubs-{$status}-" . date( "Ymd-his" ) . ".json", json_encode( $logs ) );

        dd( "ok" );
    }

    private function callFs( $body, $sku, &$logs )
    {
        try
        {

            $response = Http::withHeaders( $this->fsHeaders )
                ->withBody( json_encode( $body ), "application/json" )
                ->post( $this->fsUrl . "/pvt/orderForms/simulation" );

            if ( ! $response->successful() )
            {
                if ( $response->status() === 429 )
                {
                    sleep( 5 );

                    return $this->callFs( $body, $sku, $logs );
                }

                $logs[ "error" ]++; // [ $sku ] = $response->status();

                echo "{$sku} : " . $response->status() . "\n";

                return false;
            }

            $data = $response->json();

            if ( empty( $data[ "logisticsInfo" ] ) )
            {
                $logs[ "outofstock" ]++; // [] = $sku;

                echo "{$sku} : outofstock\n";

                return true;
            }

            if ( empty( $data[ "logisticsInfo" ][ 0 ][ "slas" ] ) )
            {
                // if ( $body[ "postalCode" ] == "01218-011" )
                // {
                //     $body[ "postalCode" ] = "04024-020";
                // }
                // else if ( $body[ "postalCode" ] == "04024-020" )
                // {
                //     $body[ "postalCode" ] = "01234-000";
                // }
                // else
                // {
                $logs[ "delivery-unavailable" ]++; // [] = $sku;

                echo "{$sku} : delivery-unavailable\n";

                return true;
                // }

                // return $this->callFs( $body, $sku, $logs );
            }

            $hub = $data[ "logisticsInfo" ][ 0 ][ "slas" ][ 0 ][ "id" ];

            if ( isset( $logs[ "success" ][ $hub ] ) )
                $logs[ "success" ][ $hub ]++;
            else $logs[ "success" ][ $hub ] = 1;

            echo "{$sku} : {$hub}\n";

            return true;
        }
        catch ( Exception $e )
        {
            $logs[ "error" ]++; // [ $sku ] = $e->getMessage();

            echo "{$sku} : " . $e->getMessage() . "\n";

            return false;
        }
    }
}
