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

    $env       = config("fastshop.env");
    $fsUrl     = config("fastshop.$env.fs.url");
    $fsHeaders = config("fastshop.$env.fs.headers");

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
                "per_page" => $per,
                "page"     => $page,
            ] );

            if ( $aux->count() < 100 ) $ended = true;

            $page++;

            foreach ( $aux as $p )
            {
                if ( strpos( $p->sku, "FS-" ) !== false )
                {
                    $y++;

                    $wcData = [
                        "postalCode" => "01218-011",
                        "items"      => [ [
                            "sku"      => $p->sku,
                            "quantity" => 1
                        ] ]
                    ];

                    $body   = DataTranslator::translate( "freightCalcFs", $wcData );

                    // Consulta valor do frete na FS
                    $url      = "$fsUrl/pvt/orderForms/simulation";
                    $response = Http::withHeaders( $fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

                    // falha na consulta
                    // não altera nada no MKT
                    // não exibe frete
                    if ( !$response->successful() )
                    {
                        Log::freightCalculation( $body );

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
                        $value  = $data[ "data" ][ "hub" ];

                        if ( array_search( $value, $hubs ) === false )
                            $hubs[] = $value;

                        $hub   = explode( "_", $data[ "data" ][ "hub" ] );
                        $class = "fast-shop-" . $hub[ 2 ];
                    }

                    Product::update( $p->id, [
                        "shipping_class" => $class,
                        "meta_data"      => [ [
                            "key"   => "_fastshop_hub",
                            "value" => $value,
                        ] ]
                    ] );

                    if ( $y % 5 == 0 ) sleep( 1 );
                }
            }
        }
        catch (Exception $e)
        {
            $ended = false;
        }
    }
    while ( !$ended );

    Storage::put( "hubs-" . date( "y-m-d" ) . ".json", json_encode( $hubs ) );

    dd( "final", $hubs );
} );
