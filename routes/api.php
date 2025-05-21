<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ChatGuruController, FastshopController, PipedriveWebhookController};
use App\Http\Controllers\Vtex\{CategoryController as VtexCategoryController, OrderController as VtexOrderController, ProductController as VtexProductController, SimulateController as VtexSimulateController};

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

Route::group( attributes: ["middleware" => "log.route"], routes: static function () : void
{

    // Integração Pipedrive => Mautic
    Route::prefix( prefix: "pipedrive" )->group( callback: static function () : void
    {
        // Route::prefix( "organization" )->group( function () {
        //     Route::post( "/added",   [ PipedriveWebhookController::class, "organization" ] )->name( "pipe.org.added"   );
        //     Route::post( "/updated", [ PipedriveWebhookController::class, "organization" ] )->name( "pipe.org.updated" );
        //     Route::post( "/deleted", [ PipedriveWebhookController::class, "organization" ] )->name( "pipe.org.deleted" );
        // } );

        Route::post( uri: "/organization", action: [PipedriveWebhookController::class, "organization"] )->name( name: "pipe.organization" );
        Route::post( uri: "/person", action: [PipedriveWebhookController::class, "person"] )->name( name: "pipe.person" );
        Route::post( uri: "/stage", action: [PipedriveWebhookController::class, "stage"] )->name( name: "pipe.stage" );
        Route::post( uri: "/deal", action: [PipedriveWebhookController::class, "deal"] )->name( name: "pipe.deal" );
    } );

    // Integração Triibo => ChatGuru
    Route::prefix( prefix: "triibo" )->group( callback: static function () : void
    {
        Route::post( uri: "/chatguru", action: [ChatGuruController::class, "index"] )->name( name: "triibo.chatguru" );
    } );

    /*
     * Integração MarketplaceTriibo <=> Fastshop
     * ->middleware( "log.route" )
     */
    Route::name( value: "api.marketplace." )->prefix( prefix: "marketplace" )->group( callback: static function () : void
    {
        // APIs chamadas pelo marketplace
        Route::name( value: "wc." )->prefix( prefix: "wc" )->group( callback: static function () : void
        {
            /*
             * Route-api: php.triibo.com.br/api/marketplace/wc/freightcalculation
             * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-shipping
             */
            Route::post( uri: "/freightcalculation", action: [VtexSimulateController::class, "freightCalculation"] )->name( name: "freightcalculation" );

            /*
             * Route-api: php.triibo.com.br/api/marketplace/wc/mkt-updatesku/{sku}
             * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop
             */
            Route::post( uri: "/updatesku/{sku}", action: [FastshopController::class, "updateSkuWc"] )->name( name: "updatesku" );

            /*
             * Route-api: php.triibo.com.br/api/marketplace/wc/ordercreation/{order_id}
             * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
             */
            Route::post( uri: "/ordercreation/{order_id}", action: [VtexOrderController::class, "orderCreation"] )->name( name: "ordercreation" );

            /*
             * Route-api: php.triibo.com.br/api/marketplace/wc/orderapproval/{order_id}
             * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
             */
            Route::post( uri: "/orderapproval/{order_id}", action: [VtexOrderController::class, "orderApproval"] )->name( name: "orderapproval" );

            /*
             * Route-api: php.triibo.com.br/api/marketplace/wc/ordercancellation/{order_id}
             * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
             */
            Route::post( uri: "/ordercancellation/{order_id}", action: [VtexOrderController::class, "orderCancellation"] )->name( name: "ordercancellation" );
        } );

        // APIs VTEX / Fast Shop
        Route::name( value: "vtex." )->prefix( prefix: "vtex" )->group( callback: static function () : void
        {
            Route::name( value: "mapper." )->prefix( prefix: "mapper" )->group( callback: static function () : void
            {
                Route::get( uri: "/notification", action: [VtexCategoryController::class, "notification"] )->name( name: "notification" );
                Route::get( uri: "/categories", action: [VtexCategoryController::class, "categories"] )->name( name: "categories" );
                Route::post( uri: "/mapping", action: [VtexCategoryController::class, "mapping"] )->name( name: "mapping" );
            } );

            Route::post( uri: "/notification", action: [VtexProductController::class, "updateOrCreateSku"] )->name( name: "notification.sku" );

            Route::name( value: "order." )->prefix( prefix: "order" )->group( callback: static function () : void
            {
                Route::post( uri: "/pub/orders/{order_id}/invoice", action: [VtexOrderController::class, "invoiceNotification"] )->name( name: "notification.invoice" );
                Route::post( uri: "/pvt/orders/{order_id}/cancel", action: [VtexOrderController::class, "cancellationNotification"] )->name( name: "notification.cancel" );
            } );

        } );
    } );

} );
