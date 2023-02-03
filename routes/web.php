<?php

// use App\Models\User;
// use Mautic\MauticApi;
// use App\Helpers\Helper;
// use Mautic\Auth\ApiAuth;
// use Devio\Pipedrive\Pipedrive;
// use Triibo\Mautic\Facades\Mautic;
use App\Helpers\Helper;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
// use Triibo\Mautic\Models\MauticConsumer;
// use App\Notifications\WebhookMarketplace;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\UserController;
use Codexshaper\WooCommerce\Facades\Note;
use Codexshaper\WooCommerce\Facades\Term;
use Codexshaper\WooCommerce\Facades\Order;
use Codexshaper\WooCommerce\Facades\Coupon;
use App\Http\Controllers\ChatGuruController;
use App\Http\Integrations\MauticIntegration;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;
use Codexshaper\WooCommerce\Facades\Attribute;
use App\Http\Controllers\Affiliates\AwinController;
use Codexshaper\WooCommerce\Facades\PaymentGateway;
use App\Http\Integrations\FastshopIntegration\DataTranslator;
use App\Http\Integrations\AffiliatesIntegration\AwinIntegration;
use App\Http\Integrations\AffiliatesIntegration\SocialSoulIntegration;

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



Route::get( "/fs", function () {

    $products = Storage::get( "1-fs-data.json" );
    $products = json_decode( $products, true );

    // dump( $products[4], $products[5] );

    // $s0     = json_encode( $products[4] );
    // $s1     = json_encode( $products[5] );

    // dump( $s0, $s1 );

    // similar_text( $s0, $s1, $perc );

    // dd( $perc );



    $fsData  = $products[0];
    $sku     = ( $fsData[ "ProductId" ] == $fsData[ "SellerStockKeepingUnitId" ] ) ? $fsData[ "ProductId" ] : $fsData[ "SellerStockKeepingUnitId" ];

    $founded = DB::table( "fastshop_products" )->where( "product_sku", "FS-{$sku}" )->first();

    if ( !$founded )
    {
        unset( $fsData[ "Pricing" ], $fsData[ "AvailableQuantity" ] );

        $data  = [
            "product_sku" => "FS-" . $sku,
            "action"      => "create",
            "status"      => "unprocessed",
            "data"        => json_encode( $fsData ),
            "created_at"  => now(),
            "updated_at"  => now(),
        ];

        $saved = DB::table( "fastshop_products" )->insert( $data );

        if ( !$saved ) throw new Exception( "Unable to save the product data" );

        dd( $saved );
    }
    else
    {
        $fsData[ "Width" ] = 97000;
        $fsDataEnc         = json_encode( $fsData );

        similar_text( $fsDataEnc, $founded->data, $perc );

        if ( $perc != 100 )
        {
            $updated = DB::table( "fastshop_products" )
                        ->where( "id", $founded->id )
                        ->update( [
                            "action"     => "update",
                            "status"     => "unprocessed",
                            "data"       => json_encode( $fsData ),
                            "updated_at" => now()
                        ] );

            dd( $fsData, $updated );
        }
    }

    dd( "ok" );

} );
