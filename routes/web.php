<?php

use App\Helpers\Helper;
use App\Models\User;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\UserController;
use App\Notifications\WebhookMarketplace;
use Triibo\Mautic\Models\MauticConsumer;
use App\Http\Integrations\MauticIntegration;

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
    })->name("update.stages");

    Route::resource("users", UserController::class);

});





Route::get("/mautic", function () {
    dd("mautic");
});

Route::get("/pipedrive", function () {
    // $token   = config("pipedrive.token");
    // $pipeApi = new Pipedrive($token);

    // try
    // {
    //     // // encontra o campo pelo ID "Fonte do Lead"
    //     // $data = $pipeApi->OrganizationFields->find(4028)->getData();
    //     // $data = $pipeApi->OrganizationFields->all()->getData();
    //     // $data = $pipeApi->organizations->persons(2079)->getData();
    //     // $data = $pipeApi->organizations->find(1512)->getData();
    //     // $data = $pipeApi->organizations->all()->getData();
    //     // $data = $pipeApi->DealFields->find(12464)->getData();

    //     $objs = $pipeApi->organizations->all(["start" =>    0, "limit" =>  500])->getData();
    //     foreach ($objs as $obj) MauticIntegration::updateOrCreate("contacts", json_decode(json_encode($obj), true), false);
    // }
    // catch(Exception $e)
    // {
    //     dd($e);
    // }
});
