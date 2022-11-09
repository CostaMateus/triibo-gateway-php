<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class UpdateStockFastShopAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "sku:update_all";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Update all the SKUs of Fast Shop";

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
        $env       = config( "fastshop.env"             );
        $fsUrl     = config( "fastshop.$env.fs.url"     );
        $fsHeaders = config( "fastshop.$env.fs.headers" );

        $ended     = false;
        $page      = 1;
        $per       = 100;

        $update    = [];

        do
        {
            try
            {
                $search  = [
                    "per_page"     => $per,
                    "page"         => $page,
                    "manage_stock" => true,
                ];

                $products = Product::all( $search );

                if ( $products->count() < 100 ) $ended = true;

                $page++;

                foreach ( $products as $p )
                {
                    // not FS
                    if ( strpos( $p->sku, "FS-" ) === false ) continue;

                    $wcData   = [
                        "postalCode" => "01218-011",
                        "items"      => [ [
                            "sku"      => $p->sku,
                            "quantity" => 1
                        ] ]
                    ];

                    $body     = DataTranslator::translate( "freightCalcFs", $wcData );
                    $url      = "$fsUrl/pvt/orderForms/simulation";
                    $response = Http::withHeaders( $fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

                    if ( !$response->successful() )
                    {
                        $data = [
                            "errorCode" => "5001",
                            "message"   => "Something wrong when consulting the delivery price",
                            "data"      => $wcData[ "items" ][ 0 ]
                        ];
                    }
                    else
                    {
                        $fsData                = $response->json();
                        $aWcData               = DataTranslator::translate( "freightCalcWc", $fsData );

                        $aWcData[ "sku"      ] = isset( $aWcData[ "sku"      ] ) ? $aWcData[ "sku"      ] : $wcData[ "items" ][ 0 ][ "sku" ];
                        $aWcData[ "quantity" ] = isset( $aWcData[ "quantity" ] ) ? $aWcData[ "quantity" ] : 1;

                        $code                  = $aWcData[ "code"    ];
                        $message               = $aWcData[ "message" ];

                        unset( $aWcData[ "code" ], $aWcData[ "message" ] );

                        $data                  = [
                            "errorCode" => $code,
                            "message"   => $message,
                            "data"      => $aWcData,
                        ];
                    }

                    if ( $data[ "errorCode" ] != null )
                    {
                        $value = "-";
                        $class = "fast-shop";
                        $index = array_search( "_fastshop_hub", array_column( $p->meta_data, "key" ) );

                        if ( $index !== false )
                            if ( $p->meta_data[ $index ]->value == "-" )
                                continue;
                    }
                    else
                    {
                        $value = $data[ "data" ][ "hub" ];
                        $hub   = explode( "_", $value );
                        $class = "fast-shop-" . $hub[ 2 ];
                    }


                    if ( $value == "-" )
                    {
                        $stock_qntt = $data[ "data" ][ "stock" ];
                        $update[]   = [
                            "id"             => $p->id,
                            "shipping_class" => $class,
                            "stock_status"   => ( $stock_qntt > 0 ) ? "instock" : "outofstock",
                            "stock_quantity" => $stock_qntt,
                            "meta_data"      => [ [
                                "key"   => "_fastshop_hub",
                                "value" => $value,
                            ] ]
                        ];

                        echo "ID: {$p->id} | SKU: {$p->sku} | {$class}\n";
                    }
                    else // ( $data[ "data" ][ "hub" ] != "" )
                    {
                        $stock_qntt = $data[ "data" ][ "stock" ];
                        $update[]   = [
                            "id"             => $p->id,
                            "shipping_class" => $class,
                            "stock_status"   => ( $stock_qntt > 0 ) ? "instock" : "outofstock",
                            "stock_quantity" => $stock_qntt,
                            "regular_price"  => $data[ "data" ][ "regular_price" ],
                            "sale_price"     => $data[ "data" ][ "sale_price"    ],
                            "meta_data"      => [ [
                                "key"   => "_fastshop_hub",
                                "value" => $value,
                            ] ]
                        ];

                        echo "ID: {$p->id} | SKU: {$p->sku} | {$class}\n";
                    }

                    if ( count( $update ) == 100 )
                    {
                        Product::batch( [
                            "update" => $update
                        ] );

                        echo "100 products has updated\n";

                        $update = [];
                    }
                }
            }
            catch ( \Exception $e )
            {
                $ended = false;
            }
        }
        while ( !$ended );

        try
        {
            Product::batch( [
                "update" => $update
            ] );

            echo count( $update ) . " products has updated\n";
        }
        catch ( \Exception $e )
        {
            echo $e->getMessage();
        }

        echo "ENDED\n";
    }
}
