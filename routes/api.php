<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatGuruController;
use App\Http\Controllers\FastshopController;
use App\Http\Controllers\PipedriveWebhookController;
use App\Http\Controllers\Vtex\OrderController as VtexOrderController;
use App\Http\Controllers\Vtex\ProductController as VtexProductController;
use App\Http\Controllers\Vtex\CategoryController as VtexCategoryController;
use App\Http\Controllers\Vtex\SimulateController as VtexSimulateController;

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

// Route::middleware( "auth:api" )->get( "/user", function (Request $request) {
//     return $request->user();
// } );


/**
 * Integração Pipedrive => Mautic
 */
Route::prefix( "pipedrive" )->group( function ()
{
    // Route::prefix( "organization" )->group( function () {
    //     Route::post( "/added",   [ PipedriveWebhookController::class, "organization" ] )->name( "pipe.org.added"   );
    //     Route::post( "/updated", [ PipedriveWebhookController::class, "organization" ] )->name( "pipe.org.updated" );
    //     Route::post( "/deleted", [ PipedriveWebhookController::class, "organization" ] )->name( "pipe.org.deleted" );
    // } );

    Route::post( "/organization",  [ PipedriveWebhookController::class, "organization" ] )->name( "pipe.organization" );
    Route::post( "/person",        [ PipedriveWebhookController::class, "person"       ] )->name( "pipe.person"       );
    Route::post( "/stage",         [ PipedriveWebhookController::class, "stage"        ] )->name( "pipe.stage"        );
    Route::post( "/deal",          [ PipedriveWebhookController::class, "deal"         ] )->name( "pipe.deal"         );
} );


/**
 * Integração Triibo => ChatGuru
 */
Route::prefix( "triibo" )->group( function ()
{
    Route::post( "/chatguru", [ ChatGuruController::class, "index" ] )->name( "triibo.chatguru" );
} );


/**
 * Integração MarketplaceTriibo <=> Fastshop
 * ->middleware( "log.route" )
 */
Route::name( "api.marketplace." )->prefix( "marketplace" )->group( function ()
{
    /**
     * APIs chamadas pelo marketplace
     */
    Route::name( "wc." )->prefix( "wc" )->group( function ()
    {
        /**
         * Route-api: php.triibo.com.br/api/marketplace/wc/freightcalculation
         * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-shipping
         */
        Route::post( "/freightcalculation",           [ VtexSimulateController::class, "freightCalculation" ] )->name( "freightcalculation" );

        /**
         * Route-api: php.triibo.com.br/api/marketplace/wc/mkt-updatesku/{sku}
         * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop
         */
        Route::post( "/updatesku/{sku}",              [ FastshopController::class, "updateSkuWc" ] )->name( "updatesku" );

        /**
         * Route-api: php.triibo.com.br/api/marketplace/wc/ordercreation/{order_id}
         * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
         */
        Route::post( "/ordercreation/{order_id}",     [ VtexOrderController::class, "orderCreation" ] )->name( "ordercreation" );

        /**
         * Route-api: php.triibo.com.br/api/marketplace/wc/orderapproval/{order_id}
         * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
         */
        Route::post( "/orderapproval/{order_id}",     [ VtexOrderController::class, "orderApproval" ] )->name( "orderapproval" );

        /**
         * Route-api: php.triibo.com.br/api/marketplace/wc/ordercancellation/{order_id}
         * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
         */
        Route::post( "/ordercancellation/{order_id}", [ VtexOrderController::class, "orderCancellation" ] )->name( "ordercancellation" );
    } );


    /**
     * APIs VTEX / Fast Shop
     */
    Route::name( "vtex." )->prefix( "vtex" )->group( function ()
    {
        Route::name( "mapper." )->prefix( "mapper" )->group( function ()
        {
            Route::get(  "/notification", [ VtexCategoryController::class, "notification" ] )->name( "notification" );
            Route::get(  "/categories",   [ VtexCategoryController::class, "categories"   ] )->name( "categories"   );
            Route::post( "/mapping",      [ VtexCategoryController::class, "mapping"      ] )->name( "mapping"      );
        } );

        Route::post( "/notification", [ VtexProductController::class, "updateOrCreateSku" ] )->name( "notification.sku" );

        Route::name( "order." )->prefix( "order" )->group( function ()
        {
            Route::post( "/pub/orders/{order_id}/invoice", [ VtexOrderController::class, "invoiceNotification"      ] )->name( "notification.invoice" );
            Route::post( "/pvt/orders/{order_id}/cancel",  [ VtexOrderController::class, "cancellationNotification" ] )->name( "notification.cancel"  );
        } );

    } );
} );
