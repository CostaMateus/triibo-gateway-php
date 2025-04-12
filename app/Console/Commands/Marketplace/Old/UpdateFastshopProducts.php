<?php

namespace App\Console\Commands\Marketplace\Old;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class UpdateFastshopProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:update_fastshop_products
                            {--stock=instock : Status of stock. Options: instock or outofstock}
                            {--hub=*         : Hub ID. Options: 00, 05, 14 or 33}";

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
        $env = config( "fastshop.env" );
        $fsUrl = config( "fastshop.{$env}.fs.url" );
        $fsHeaders = config( "fastshop.{$env}.fs.headers" );

        $ended = false;
        $page = 1;
        $per = 100;
        $update = [];

        $_hubs = [
            "00" => 3970, // padrao
            "05" => 4309, // 05 A
            "14" => 4310, // 14 B
            "33" => 4311, // 33 C
        ];

        $stock = $this->option( "stock" );
        $hub = $this->option( "hub" );
        $hubs = [];

        if ( count( $hub ) === 0 )
        {
            $hubs = [
                "05" => 4309, // 05 A
                "14" => 4310, // 14 B
                "33" => 4311, // 33 C
            ];
        }
        else
        {
            foreach ( $hub as $h )
                if ( array_key_exists( $h, $_hubs ) )
                    $hubs[ $h ] = $_hubs[ $h ];
        }

        if ( count( $hubs ) === 0 )
        {
            echo "Hub id not found. ENDED\n";

            return;
        }

        $msg = "";

        foreach ( $hubs as $i => $h )
        {
            $msg .= $i;

            if ( next( $hubs ) === true ) $msg .= ", ";
        }

        echo "Performing update to HUBs [{$msg}] with stock_status ".mb_strtoupper( $stock )."\n";

        // PERFORMING
        foreach ( $hubs as $i => $hub )
        {
            echo "Starting with HUB {$i}\n";

            do
            {
                try
                {
                    $search = [
                        "per_page" => $per,
                        "page" => $page,
                        "manage_stock" => true,
                        "stock_status" => $stock,
                        "shipping_class_id" => $hub,
                    ];

                    $aux = Product::all( $search );

                    if ( $aux->count() < 100 ) $ended = true;

                    $page++;

                    foreach ( $aux as $p )
                    {
                        // not FS product
                        if ( ! str_contains( $p->sku, "FS-" ) ) continue;

                        // not the same stock_status as searched
                        if ( $p->stock_status !== $stock ) continue;

                        // not the same shipping_class_id as searched
                        if ( $p->shipping_class_id !== $hub ) continue;

                        // not the same class as searched
                        if ( $i === "00" && $p->shipping_class !== "fast-shop" ) continue;

                        if ( $p->shipping_class !== "fast-shop-{$i}" ) continue;

                        $wcData = [
                            "postalCode" => "04024-020", // Triibo
                            "items" => [[
                                "sku" => $p->sku,
                                "quantity" => 1
                            ]]
                        ];

                        $body = DataTranslator::translate( "freightCalcFs", $wcData );
                        $url = "{$fsUrl}/pvt/orderForms/simulation";
                        $response = Http::withHeaders( $fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

                        // handling response
                        if ( ! $response->successful() )
                        {
                            $data = [
                                "error_code" => "5001",
                                "message" => "Something wrong when consulting the delivery price",
                                "data" => $wcData[ "items" ][ 0 ]
                            ];
                        }
                        else
                        {
                            $fsData = $response->json();
                            $aWcData = DataTranslator::translate( "freightCalcWc", $fsData );
                            $aWcData[ "sku"      ] ??= $wcData[ "items" ][ 0 ][ "sku" ];
                            $aWcData[ "quantity" ] ??= 1;
                            $code = $aWcData[ "code"    ];
                            $message = $aWcData[ "message" ];

                            unset( $aWcData[ "code" ], $aWcData[ "message" ] );

                            $data = [
                                "error_code" => $code,
                                "message" => $message,
                                "data" => $aWcData,
                            ];
                        }

                        // handling data
                        if ( $data[ "error_code" ] !== null )
                        {
                            $value = "-";
                            $class = "fast-shop";
                        }
                        else
                        {
                            $value = $data[ "data" ][ "hub" ];
                            $p_hub = explode( "_", $data[ "data" ][ "hub" ] );
                            $class = "fast-shop-".end( $p_hub );
                        }

                        if ( $class === "fast-shop" )
                        {
                            $index = array_search( "_fastshop_hub", array_column( $p->meta_data, "key" ) );

                            if ( $index !== false )
                                if ( $p->meta_data[ $index ]->value === "fast-shop" )
                                    if ( $p->stock_quantity === 0 )
                                        continue;
                        }

                        if ( ! isset( $data[ "data" ][ "hub" ] ) || empty( $data[ "data" ][ "hub" ] ) )
                        {
                            $update[] = [
                                "id" => $p->id,
                                "shipping_class" => "fast-shop",
                                "stock_status" => "outofstock",
                                "stock_quantity" => "0",
                                "meta_data" => [[
                                    "key" => "_fastshop_hub",
                                    "value" => "-",
                                ]]
                            ];

                            echo "ID: {$p->id} | SKU: {$p->sku} | {$class} | 0\n";
                        }
                        else // ( !empty( $data[ "data" ][ "hub" ] ) )
                        {
                            $stock_qntt = $data[ "data" ][ "stock" ];
                            $update[] = [
                                "id" => $p->id,
                                "shipping_class" => $class,
                                "stock_status" => ( $stock_qntt > 0 ) ? "instock" : "outofstock",
                                "stock_quantity" => $stock_qntt,
                                "regular_price" => $data[ "data" ][ "regular_price" ],
                                "sale_price" => $data[ "data" ][ "sale_price"    ],
                                "meta_data" => [[
                                    "key" => "_fastshop_hub",
                                    "value" => $value,
                                ]]
                            ];

                            echo "ID: {$p->id} | SKU: {$p->sku} | {$class} | {$stock_qntt}\n";
                        }

                        if ( count( $update ) === 100 )
                        {
                            Product::batch( [
                                "update" => $update
                            ] );

                            $update = [];
                        }
                    }
                }
                catch ( Exception $e )
                {
                    $ended = false;
                }
            }
            while ( ! $ended );

            try
            {
                Product::batch( [
                    "update" => $update
                ] );
            }
            catch ( Exception $e )
            {
                echo $e->getMessage()."\n";
            }

            echo "HUB {$i} finished\n\n";
        }
    }
}
