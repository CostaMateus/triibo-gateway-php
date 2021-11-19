<?php

// use App\Models\User;
// use Mautic\MauticApi;
// use App\Helpers\Helper;
// use Mautic\Auth\ApiAuth;
// use Devio\Pipedrive\Pipedrive;
// use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\UserController;
// use Triibo\Mautic\Models\MauticConsumer;
// use App\Notifications\WebhookMarketplace;
use Codexshaper\WooCommerce\Facades\Coupon;
use App\Http\Integrations\MauticIntegration;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\PaymentGateway;

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





Route::get("/wc", function () {

    // $data = [
    //     'name' => 'Simple Product',
    //     'type' => 'simple',
    //     'regular_price' => '10.00',
    //     'description' => 'Simple product full description.',
    //     'short_description' => 'Simple product short description.',
    //     'categories' => [
    //         [
    //             'id' => 1
    //         ],
    //         [
    //             'id' => 3
    //         ],
    //         [
    //             'id' => 5
    //         ]
    //     ],
    //     'images' => [
    //         [
    //             'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_front.jpg'
    //         ],
    //         [
    //             'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_back.jpg'
    //         ]
    //     ]
    // ];
    // $product = Product::create($data);



    $product = Product::all([
        "sku" => "TRIIBO-DOCKINGSTATION"
    ]);

    dd($product->first()->id);

});
