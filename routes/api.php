<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatGuruController;
use App\Http\Controllers\FastshopController;
use App\Http\Controllers\PipedriveWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware("auth:api")->get("/user", function (Request $request) {
//     return $request->user();
// });


/**
 * Integração Pipedrive => Mautic
 */
Route::prefix("pipedrive")->group(function () {

    // Route::prefix("organization")->group(function () {
    //     Route::post("/added",   [PipedriveWebhookController::class, "organization"])->name("pipe.org.added");
    //     Route::post("/updated", [PipedriveWebhookController::class, "organization"])->name("pipe.org.updated");
    //     Route::post("/deleted", [PipedriveWebhookController::class, "organization"])->name("pipe.org.deleted");
    // });

    Route::post("/organization",  [PipedriveWebhookController::class, "organization"])->name("pipe.organization");
    Route::post("/person",        [PipedriveWebhookController::class, "person"])->name("pipe.person");
    Route::post("/stage",         [PipedriveWebhookController::class, "stage"])->name("pipe.stage");
    Route::post("/deal",          [PipedriveWebhookController::class, "deal"])->name("pipe.deal");

});


/**
 * Integração Triibo => ChatGuru
 */
Route::prefix("triibo")->group(function () {

    Route::post("/chatguru", [ChatGuruController::class, "index"])->name("triibo.chatguru");

});


/**
 *Integração MarketplaceTriibo <=> Fastshop
 */
Route::prefix("marketplace")->group(function () {

    // Route::post("/changenotification", [FastshopController::class, "changeNotification"])->name("marketplace.changenotification");
    Route::post(
        "/changenotification/api/catalog_system/pvt/skuseller/changenotification/{sellerId}/{sellerSkuId}",
        [FastshopController::class, "changeNotification"]
    )->name("marketplace.changenotification");

    // Route::put("/skusuggestion", [FastshopController::class, "skuSuggestion"])->name("marketplace.skusuggestion");
    Route::put(
        "/skusuggestion/suggestions/{sellerId}/{sellerSkuId}",
        [FastshopController::class, "skuSuggestion"]
    )->name("marketplace.skusuggestion");

    // Route::put("/skuupdate", [FastshopController::class, "skuUpdate"])->name("marketplace.skuupdate");
    Route::put(
        "/skuupdate/suggestions/{sellerId}/{sellerSkuId}/update",
        [FastshopController::class, "skuUpdate"]
    )->name("marketplace.skuupdate");

});


// // TESTES
// Route::post("/gateway/teste", [HomeController::class, 'gateway'])->name('gateway');
