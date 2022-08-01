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

    $prd = Product::all( [ "sku" => "FS-SGQN55QN85BAGB" ] )->first();
    dump( $prd );
    Product::update( $prd->id, [ "status" => "private" ] );
    $prd = Product::all( [ "sku" => "FS-SGQN55QN85BAGB" ] );
    dd( $prd );


    // $pdts  = 0;
    // $ended = false;
    // $page  = 1;
    // $per   = 100;

    // do
    // {
    //     try
    //     {
    //         $aux = Product::all([
    //             "per_page" => $per,
    //             "page"     => $page,
    //         ]);

    //         if ( $aux->count() < 100 ) $ended = true;

    //         $page++;

    //         foreach ( $aux as $p )
    //         {
    //             if ( strpos( $p->sku, "FS-" ) !== false )
    //             {
    //                 if ( $p->status == "private" )
    //                 {
    //                     Product::update( $p->id, [ "status" => "publish" ]);

    //                     dump( $p->id );

    //                     $pdts++;
    //                 }
    //             }
    //         }
    //     }
    //     catch (Exception $e)
    //     {
    //         $ended = false;
    //     }
    // }
    // while ( !$ended );

    // dd( $pdts );






    // $data = [
    //     "Eletrodomésticos/Cooktops/acima de 5 bocas",
    //     // "Casa e Decoração/Eletrodomésticos/Cooktops/gás/acima de 5 bocas",

    //     "Eletrodomésticos/Fornos e Fogões/5 bocas/Eletrodomésticos Planejados/Cooktops/gás/acima de 5 bocas",

    //     "Informática e Digitais/Notebook/intel® core i3",
    //     "Ar e Ventilação/Ar condicionado/Ar quente e frio/Ar Split Parede/split/quente / frio/até 30000 Btus",
    //     "Informática e Digitais/Notebook/intel® core i3",
    // ];

    // foreach ( $data as $str )
    // {
    //     $auxData    = explode("/", $str);
    //     $categories = [];
    //     $slugs = [];

    //     foreach ($auxData as $key => $ctg)
    //     {
    //         $slug           = Str::slug($ctg, "-");

    //         $searchCategory = Category::all( [ "slug" => $slug ] );

    //         if ( !$searchCategory->first() )
    //         {
    //             $new          = Category::create([
    //                 "name"   => $ctg,
    //                 "parent" => ( $key == 0 ) ? 0 : $categories[ $key - 1 ]
    //             ]);

    //             $categories[] = $new[ "id" ];
    //         }
    //         else
    //         {
    //             $categories[] = $searchCategory->first()->id;
    //         }

    //         $slugs[] = $slug;
    //     }

    //     dump( $str, $slugs, $categories );
    //     echo "<br><br><br><br>";
    // }
});
