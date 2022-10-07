<?php

// use App\Models\User;
// use Mautic\MauticApi;
// use App\Helpers\Helper;
// use Mautic\Auth\ApiAuth;
// use Devio\Pipedrive\Pipedrive;
// use Triibo\Mautic\Facades\Mautic;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
// use Triibo\Mautic\Models\MauticConsumer;
// use App\Notifications\WebhookMarketplace;
use App\Http\Controllers\UserController;
use Codexshaper\WooCommerce\Facades\Note;
use Codexshaper\WooCommerce\Facades\Term;
use Codexshaper\WooCommerce\Facades\Order;
use Codexshaper\WooCommerce\Facades\Coupon;
use App\Http\Integrations\MauticIntegration;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;
use Codexshaper\WooCommerce\Facades\Attribute;
use Codexshaper\WooCommerce\Facades\PaymentGateway;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes([
    "register" => false
]);

Route::get("/", function () {
    return view("welcome", [
        "showToast" => false,
        "message"   => ""
    ]);
})->name("welcome");

Route::group(["middleware" => "auth"], function() {

    Route::get("/home", function () {
        return view("home");
    })->name("home");

    Route::get("/clear-caches", function() {
        Artisan::call("optimize:clear");
        return view("welcome", [ "showToast" => true, "message" => __("marketplace.cache") ]);
    })->name("clear.caches");

    Route::get("/update-stages", function() {
        MauticIntegration::updateStages();
        return view("welcome", [ "showToast" => true, "message" => __("marketplace.update.all.stages") ]);
    })->name("update.stages");

    Route::get("/sync-ids", function() {
        MauticIntegration::syncIdsMauticPipe();
        return view("welcome", [ "showToast" => true, "message" => __("marketplace.sync.id") ]);
    })->name("sync.ids");

    Route::resource("users", UserController::class);

});





Route::get( "/wc", function () {

    $env       = config( "fastshop.env" );
    $fsUrl     = config( "fastshop.$env.fs.url" );
    $fsHeaders = config( "fastshop.$env.fs.headers" );

    $ended     = false;
    $page      = 1;
    $per       = 100;

    $hubs      = [];
    $y         = 0;

    do
    {
        try
        {
            $aux = Product::all( [
                "per_page"          => $per,
                "page"              => $page,
                "manage_stock"      => true,
                "shipping_class_id" => 3970,
                "stock_status"      => "outofstock",
            ] );



            if ( $aux->count() < 100 ) $ended = true;

            $page++;

            foreach ( $aux as $p )
            {
                $y++;

                $wcData = [
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

                    $data = [
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

                // if ( $class == "fast-shop" )
                // {
                //     $index = array_search( "_fastshop_hub", array_column( $p->meta_data, "key" ) );
                //
                //     if ( $index !== false )
                //     {
                //         if ( $p->meta_data[ $index ]->value == "fast-shop" )
                //         {
                //             echo "ID: {$p->id} | SKU: {$p->sku} | {$class} | skip 3\n";
                //             continue;
                //         }
                //     }
                // }

                if ( $data[ "data" ][ "hub" ] != "" )
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
                }

                // if ( $y % 5 == 0 ) sleep( 1 );
            }
        }
        catch (Exception $e)
        {
            $ended = false;
        }
    }
    while ( !$ended );
} );
