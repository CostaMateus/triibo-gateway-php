<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class WithoutHubFastShop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "sku:hub";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get SKUs of Fast Shop with stock without HUB";

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
                $aux = Product::all( [
                    "per_page"          => $per,
                    "page"              => $page,
                    "shipping_class_id" => 3970,
                    // "manage_stock"      => true,
                    // "shipping_class"    => "fast-shop",
                    // "stock_status"      => "outofstock",
                ] );

                if ( $aux->count() < 100 ) $ended = true;

                $page++;

                foreach ( $aux as $p )
                {
                    echo "{$p->sku}\n";

                    $append = date( "Y-m-d H:i:s" ) . " | {$p->sku} | ";

                    $wcData = [
                        "postalCode" => "01218-011",
                        "items"      => [ [
                            "sku"      => $p->sku,
                            "quantity" => 1
                        ] ]
                    ];

                    $body     = DataTranslator::translate( "freightCalcFs", $wcData );
                    $url      = "$fsUrl/pvt/orderForms/simulation";
                    $response = Http::retry( 3, 100 )
                                    ->withHeaders( $fsHeaders )
                                    ->withBody( json_encode( $body ), "application/json" )
                                    ->post( $url );

                    if ( !$response->successful() )
                    {
                        $append .= "Falha na consulta";
                        Storage::append( "fs-errors.txt", $append );
                        continue;
                    }

                    $fsData = $response->json();
                    $wcData = DataTranslator::translate( "freightCalcWc", $fsData );

                    if ( $wcData[ "stock" ] > 0 && $wcData[ "hub" ] == "" )
                    {
                        $append .= "Estoque: {$wcData[ "stock" ]} | Hub: ''";
                        Storage::append( "fs-without-hub.txt", $append );
                        continue;
                    }
                    else
                    {
                        $append .= "Estoque: {$wcData[ "stock" ]} | Hub: {$wcData[ "hub" ]}";
                        Storage::append( "fs-with-hub.txt", $append );
                        continue;
                    }
                }
            }
            catch ( \Exception $e )
            {
                $ended = false;
            }
        }
        while ( !$ended );
    }
}
