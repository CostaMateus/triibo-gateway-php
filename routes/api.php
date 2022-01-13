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
Route::name("api.marketplace.")->prefix("marketplace")->group(function () {

    /**
     * APIs chamadas pela Fastshop
     */
    // Route-api: php.triibo.com.br/api/marketplace/changenotification
    Route::post(
        "/changenotification/api/catalog_system/pvt/skuseller/changenotification/{sellerId}/{sellerSkuId}",
        [FastshopController::class, "changeNotification"]
    )->name("changenotification");

    // Route-api: php.triibo.com.br/api/marketplace/skusuggestion
    Route::put(
        "/skusuggestion/suggestions/{sellerId}/{sellerSkuId}",
        [FastshopController::class, "skuSuggestion"]
    )->name("skusuggestion");

    // Route-api: php.triibo.com.br/api/marketplace/updatesku
    Route::put(
        "/updatesku/suggestions/{sellerId}/{sellerSkuId}/update",
        [FastshopController::class, "updateSku"]
    )->name("updatesku");

    // Route-api: php.triibo.com.br/api/marketplace/invoice
    Route::post(
        "/invoice/api/oms/pub/orders/{orderId}/invoice",
        [FastshopController::class, "invoiceNotification"]
    )->name("invoicenotification");

    // Route-api: php.triibo.com.br/api/marketplace/delivered
    Route::post(
        "/delivered/api/oms/pub/orders/{orderId}/delivered",
        [FastshopController::class, "deliveredNotification"]
    )->name("deliverednotification");

    // Route-api: php.triibo.com.br/api/marketplace/cancel
    Route::post(
        "/cancel/api/oms/pub/orders/{orderId}/cancel",
        [FastshopController::class, "cancellationNotification"]
    )->name("cancellationnotification");

    /**
     * APIs chamadas pelo marketplace
     */
    Route::name("wc.")->prefix("wc")->group(function () {

        // Route-api: php.triibo.com.br/api/marketplace/wc/freightcalculation
        // API chamada a partir do ../plugins/triibo-fastshop-shipping/
        Route::post(
            "/freightcalculation",
            [FastshopController::class, "freightCalculation"]
        )->name("freightcalculation");

        // Route-api: php.triibo.com.br/api/marketplace/wc/mkt-updatesku/{sku}
        // API chamada a partir do ../plugins/triibo-fastshop-shipping/
        Route::post(
            "/updatesku/{sku}",
            [FastshopController::class, "updateSkuWc"]
        )->name("updatesku");

        // Route-api: php.triibo.com.br/api/marketplace/wc/ordercreation/{orderId}
        // API chamada a partir do ../themes/martfury-child/functions.php
        Route::post(
            "/ordercreation/{orderId}",
            [FastshopController::class, "orderCreation"]
        )->name("ordercreation");

        // Route-api: php.triibo.com.br/api/marketplace/wc/orderapproved/{orderId}
        // API chamada a partir do ../themes/martfury-child/functions.php           // confirmar
        Route::post(
            "/orderapproved/{orderId}",
            [FastshopController::class, "orderApproved"]
        )->name("orderapproved");

        //Route-api: php.triibo.com.br/api/marketplace/wc/ordercancelation/{orderId}
        // API chamada a partir do ../themes/martfury-child/functions.php           // confirmar
        Route::post(
            "/ordercancelation/{orderId}",
            [FastshopController::class, "orderCancelation"]
        )->name("ordercancelation");
    });

});


// // TESTES
// Route::post("/gateway/teste", [HomeController::class, 'gateway'])->name('gateway');
