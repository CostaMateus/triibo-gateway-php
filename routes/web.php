<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use Codexshaper\WooCommerce\Facades\Order;
use Codexshaper\WooCommerce\Facades\Customer;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Subscription;

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

Auth::routes( [ "register" => false ] );

Route::get( "/", function ()
{
    return view( "welcome", [
        "showToast" => false,
        "message"   => ""
    ] );
} )->name( "welcome" );

Route::get( "/clear-caches", function()
{
    Artisan::call( "optimize:clear" );
    return view( "welcome", [ "showToast" => true, "message" => __( "marketplace.cache" ) ] );

} )->name( "clear.caches" );

Route::group( [ "middleware" => "auth" ], function() {

    Route::get( "/home", [ HomeController::class, "index" ] )->name( "home" );

    // Route::get( "/update-stages", function()
    // {
    //     MauticIntegration::updateStages();
    //     return view( "welcome", [ "showToast" => true,"message" => __( "marketplace.update.all.stages" )] );
    // } )->name( "update.stages" );
    //
    // Route::get( "/sync-ids", function()
    // {
    //     MauticIntegration::syncIdsMauticPipe();
    //     return view( "welcome", [ "showToast" => true, "message" => __( "marketplace.sync.id" ) ] );
    // } )->name( "sync.ids" );

    Route::resource( "users", UserController::class );

} );



// =========================================
// ============ ROTAS DE TESTE =============
// =========================================
Route::prefix( "mkt" )->group( function ()
{
    Route::get( "/get-customer/{id}", function( $id )
    {
        $customer = Customer::find( $id );

        dd( $customer );
    } );

    Route::get( "/get-order/{id}", function( $id )
    {
        try
        {
            $order = Order::find( $id );
            dd( $order );
        }
        catch ( \Exception $e )
        {
            dd( $e->getMessage() );
        }
    } );

    Route::get( "/create-order", function()
    {
        $data = [
            "customer_id"          => 1717,
            "set_paid"             => false,
            "status"               => "on-hold",
            "payment_method"       => "triibo_assinaturas",
            "payment_method_title" => "Recorrente",
            "meta_data"            => [
                [
                    "key"   => "_triibo_id",
                    "value" => "m5RFeCkwJBNdhHOtBVp9OfqmjGG3"
                ],[
                    "key"   => "_billing_cellphone",
                    "value" => "+5511949289507"
                ],[
                    "key"   => "_billing_phone",
                    "value" => "+5511949289507"
                ],[
                    "key"   => "_triibo_phone",
                    "value" => "+5511949289507"
                ]
            ],
            "line_items"           => [ [
                "product_id" => 986791,
                "quantity"   => 1
            ] ],
        ];

        $order = Order::create( $data );

        dd( $data, $order );
    } );

    Route::get( "/update-order/{id}", function( $id )
    {
        $data  = [
            "customer_id" => 1717,
            "status"      => "processing",
        ];

        $order = Order::update( $id, $data );

        dd( $data, $order );
    } );

    Route::get( "/create-subscription/{id}", function( $id )
    {
        $data = [
            "payment_method"       => "triibo_assinaturas",
            "payment_method_title" => "Recorrente",
            "customer_id"          => 1717,
            "parent_id"            => $id,
            "status"               => "active",
            "billing_period"       => "month",
            "billing_interval"     => 1,
            "start_date"           => "2024-10-01 17:13:07",
            "next_payment_date"    => "2024-11-01 17:13:07",
            "meta_data" => [
                [
                    "key"   => "_triibo_assinatura_payment_token",
                    "value" => "0"
                ],[
                    "key"   => "_triibo_assinatura_payment_id",
                    "value" => "ee2d9758-0395-46bb-94bd-2d4d8c651cb7"
                ],[
                    "key"   => "_triibo_id",
                    "value" => "m5RFeCkwJBNdhHOtBVp9OfqmjGG3"
                ],[
                    "key"   => "_billing_cellphone",
                    "value" => "+5511949289507"
                ],[
                    "key"   => "_billing_phone",
                    "value" => "+5511949289507"
                ],[
                    "key"   => "_triibo_phone",
                    "value" => "+5511949289507"
                ]
            ],
            "payment_details" => [
                "post_meta" => [
                    [ "_triibo_id" => "m5RFeCkwJBNdhHOtBVp9OfqmjGG3" ],
                    [ "_triibo_assinatura_payment_token" => "0" ]
                ]
            ],
            "line_items" => [
                [
                    "product_id" => 986791,
                    "quantity"   => 1
                ]
            ]
        ];

        $order = Subscription::create( $data );

        dd( $data, $order );
    } );

    Route::get( "/get-product/{id}", function( $id )
    {
        $product = Product::find( $id );

        dd( $product );

        $meta = [];

        $keys = [
            "_subscription_period",
            "_subscription_period_interval",
            "_subscription_length",
            "_subscription_limit"
        ];

        foreach ( $product[ "meta_data" ] as $value )
            if ( in_array( $value->key, $keys ) )
                $meta[] = $value;

        dd( $meta );
    } );

    Route::get( "/create-product", function()
    {
        $data = [
            'name'              => 'Premium Quality 5',
            'type'              => 'simple',
            'regular_price'     => '25.99',
            'description'       => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.',
            'short_description' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.',
            'author'            => 1717
        ];

        $product = Product::create( $data );

        dd( $product );
    } );

    Route::get( "/products", function()
    {
        $page        = 1;
        $wc_products = [];

        try
        {
            // do
            // {
            //     $products = WC_Product::all( [
            //         "status"       => "publish",
            //         // "manage_stock" => false,
            //         "per_page"     => 100,
            //         "page"         => $page,
            //     ] )->toArray();

            //     $count    = count( $products );

            //     foreach ( $products as $key => $product )
            //     {
            //         if ( $product->store->id != 4265 )
            //         {
            //             unset( $products[ $key ] );
            //             continue;
            //         }
            //     }

            //     if ( count( $products ) != 0 )
            //         $wc_products = array_merge( $wc_products, $products );

            //     $page++;
            // }
            // while ( $count == 100 );
        }
        catch ( Throwable $e )
        {
            dd( $e->getMessage() );
        }

        dd( "Count: " . count( $wc_products ) );
    } );
} );

Route::get( "/fs", function ()
{
    // teste FS
} );
