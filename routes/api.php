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

    /**
     * APIs chamadas pela Fastshop
     */
    // Route-api: php.triibo.com.br/api/marketplace/changenotification
    Route::post(
        "/changenotification/api/catalog_system/pvt/skuseller/changenotification/{sellerId}/{sellerSkuId}",
        [FastshopController::class, "changeNotification"]
    )->name("marketplace.changenotification");

    // Route-api: php.triibo.com.br/api/marketplace/skusuggestion
    Route::put(
        "/skusuggestion/suggestions/{sellerId}/{sellerSkuId}",
        [FastshopController::class, "skuSuggestion"]
    )->name("marketplace.skusuggestion");

    // Route-api: php.triibo.com.br/api/marketplace/updatesku
    Route::put(
        "/updatesku/suggestions/{sellerId}/{sellerSkuId}/update",
        [FastshopController::class, "updateSku"]
    )->name("marketplace.updatesku");


    /**
     * APIs chamadas pelo marketplace
     */

    // Route-api: php.triibo.com.br/api/marketplace/freightcalculation
    Route::post(
        "/freightcalculation",
        [FastshopController::class, "freightCalculation"]
    )->name("marketplace.freightcalculation");

    // Route-api: php.triibo.com.br/api/marketplace/ordercreation
    Route::post(
        "/ordercreation",
        [FastshopController::class, "orderCreation"]
    )->name("marketplace.ordercreation");

});


// // TESTES
// Route::post("/gateway/teste", [HomeController::class, 'gateway'])->name('gateway');
