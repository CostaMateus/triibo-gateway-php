<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class UpdateStockFastShop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "sku:update {--stock=instock : Status of stock. Options: instock or outofstock}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Update the SKUs of Fast Shop";

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

        $hubs      = [];

        do
        {
            try
            {
                $search = [
                    "per_page"          => $per,
                    "page"              => $page,
                    "manage_stock"      => true,
                ];

                $stock  = $this->option( "stock" );

                $search[ "stock_status" ] = $stock;

                if ( $stock == "outofstock" )
                    $search[ "shipping_class_id" ] = ( $env == "local" ) ? 4453 : 3970;

                $aux    = Product::all( $search );

                echo "Get {$aux->count()} products " . ( $stock == "outofstock" ? "- only FS\n" : "\n" );

                $y      = 0;
                $count  = $aux->count();

                if ( $aux->count() < 100 ) $ended = true;

                $page++;

                foreach ( $aux as $p )
                {
                    // not FS
                    if ( $stock == "instock" && strpos( $p->sku, "FS-" ) === false )
                    {
                        $count--;
                        continue;
                    }

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
                    }
                    else
                    {
                        $value = $data[ "data" ][ "hub" ];

                        if ( array_search( $value, $hubs ) === false )
                            $hubs[] = $value;

                        $hub   = explode( "_", $data[ "data" ][ "hub" ] );
                        $class = "fast-shop-" . $hub[ 2 ];
                    }


                    if ( $class == "fast-shop" )
                    {
                        $index = array_search( "_fastshop_hub", array_column( $p->meta_data, "key" ) );

                        if ( $index !== false )
                            if ( $p->meta_data[ $index ]->value == "fast-shop" )
                                continue;
                    }


                    if ( $data[ "data" ][ "hub" ] == "" )
                    {
                        Product::update( $p->id, [
                            "shipping_class" => $class,
                            "stock_status"   => "outofstock",
                            "stock_quantity" => $data[ "data" ][ "stock" ],
                            "meta_data"      => [ [
                                "key"   => "_fastshop_hub",
                                "value" => $value,
                            ] ]
                        ] );

                        $y++;

                        echo "ID: {$p->id} | SKU: {$p->sku} | {$class}\n";
                    }
                    else // ( $data[ "data" ][ "hub" ] != "" )
                    {
                        Product::update( $p->id, [
                            "shipping_class" => $class,
                            "stock_status"   => "instock",
                            "stock_quantity" => $data[ "data" ][ "stock"         ],
                            "regular_price"  => $data[ "data" ][ "regular_price" ],
                            "sale_price"     => $data[ "data" ][ "sale_price"    ],
                            "meta_data"      => [ [
                                "key"   => "_fastshop_hub",
                                "value" => $value,
                            ] ]
                        ] );

                        $y++;

                        echo "ID: {$p->id} | SKU: {$p->sku} | {$class}\n";
                    }
                }

                echo "{$y} of {$count} products has updated\n";
            }
            catch ( \Exception $e )
            {
                $ended = false;
            }
        }
        while ( !$ended );
    }
}
