<?php

use Illuminate\Support\Facades\{ Auth, Route, Artisan };
use App\Services\Vtex\{ ConnectorService, FulfillmentService };
use Codexshaper\WooCommerce\Facades\{ Order, Product, Customer, Subscription };
use App\Http\Controllers\{ HomeController, MarketplaceController, UserController };

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

Route::get( "/",            [ HomeController::class, "welcome"    ] )->name( "welcome"     );
Route::get( "/clear-cache", [ HomeController::class, "clearCache" ] )->name( "clear.cache" );

Route::group( [ "middleware" => "auth" ], function() {

    Route::get( "/home",         [ HomeController::class, "index"         ] )->name( "home"       );
    Route::get( "/documentacao", [ HomeController::class, "documentation" ] )->name( "docs.index" );

    Route::resource( "users", UserController::class );

    Route::prefix( "marketplace" )->name( "mkt." )->group( function ()
    {
        Route::get(  "/pedidos",         [ MarketplaceController::class, "orders"       ] )->name( "index"         );
        Route::post( "/importar",        [ MarketplaceController::class, "importOrders" ] )->name( "orders.import" );

        Route::post( "/pedido/criar",    [ MarketplaceController::class, "orderCreate"  ] )->name( "order.create"  );
        Route::post( "/pedido/aprovar",  [ MarketplaceController::class, "orderApprove" ] )->name( "order.approve" );
        Route::post( "/pedido/cancelar", [ MarketplaceController::class, "orderCancel"  ] )->name( "order.cancel"  );
    } );
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
            // "meta_data"            => [
            //     [
            //         "key"   => "_triibo_id",
            //         "value" => "m5RFeCkwJBNdhHOtBVp9OfqmjGG3"
            //     ],[
            //         "key"   => "_billing_cellphone",
            //         "value" => "+5511949289507"
            //     ],[
            //         "key"   => "_billing_phone",
            //         "value" => "+5511949289507"
            //     ],[
            //         "key"   => "_triibo_phone",
            //         "value" => "+5511949289507"
            //     ]
            // ],
            'billing'              => [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'address_1'  => '969 Market',
                'address_2'  => '',
                'city'       => 'San Francisco',
                'state'      => 'CA',
                'postcode'   => '94103',
                'country'    => 'US',
                'email'      => 'john.doe@example.com',
                'phone'      => '(555) 555-5555',
            ],
            'shipping'             => [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'address_1'  => '969 Market',
                'address_2'  => '',
                'city'       => 'San Francisco',
                'state'      => 'CA',
                'postcode'   => '94103',
                'country'    => 'US',
            ],
            "line_items"           => [ [
                "product_id" => 1021687,
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

Route::prefix( "fs" )->group( function ()
{
    Route::get( "/get-order/{id}", function( $id )
    {
        $response = vtex_get_order( "marketplace", "fast_shop", $id );

        dd( $response );
    } );

    Route::get( "/get-freight", function()
    {
        $seller    = "fast_shop";
        $connector = new ConnectorService( $seller );
        $service   = new FulfillmentService( $connector );

        $req_data  = [
            "items" => [
                [
                    "id"       => "15695",
                    "quantity" => 1,
                    "seller"   => "1",
                ]
            ],
            "isCheckedIn" => false,
            "country"     => "BRA",
            "selectedSla" => "Normal",
            "postalCode"  => "01218-011",
        ];

        $response  = $service->simulate( $req_data );

        if ( $response[ 0 ] != 200 )
            dd( $response );

        $translated = vtex_freight_to_woo( $response[ 1 ][ "data" ] );
        dd( $translated );
    } );


    Route::get( "/create-order", function()
    {
        $data = [
            "customer_id"          => 1717,
            "set_paid"             => true,
            "status"               => "processing",
            "payment_method"       => "triibo_payments_billet",
            "payment_method_title" => "Triibo Boleto",
            "billing"              => [
                "first_name"   => "Empresa",
                "last_name"    => "LTDA",
                "company"      => "Empresa LTDA",
                "email"        => "email@empresa.com",
                "phone"        => "+5511900000000",
                "cpf"          => "",
                "cnpj"         => "12.345.678/0001-99",
                "ie"           => "123.456.789.000",
                "postcode"     => "01234-000",
                "address_1"    => "Av Pacaembu, 1000",
                "number"       => "1000",
                "address_2"    => "Sala 110",
                "neighborhood" => "Pacaembu",
                "city"         => "São Paulo",
                "state"        => "SP",
                "country"      => "BR",
            ],
            "shipping"             => [
                "first_name"   => "John",
                "last_name"    => "Doe",
                "company"      => "",
                "email"        => "email@cliente.com",
                "phone"        => "+5511999999999",
                "cpf"          => "009.876.543-21",
                "cnpj"         => "",
                "ie"           => "",
                "postcode"     => "01218-011",
                "address_1"    => "Rua Fulano, 1000",
                "number"       => "1000",
                "address_2"    => "Apto 123A",
                "neighborhood" => "Pacaembu",
                "city"         => "São Paulo",
                "state"        => "SP",
                "country"      => "BR",
            ],
            "line_items"           => [ [
                "product_id" => 1021687, // buscar o _fs_product_id (15695)
                "quantity"   => 1
            ] ],
            "shipping_lines"       => [ [
                "method_id"    => "triibo-fast-shop-shipping",
                "method_title" => "Fast Shop",
                "total"        => "20.72"
            ] ]
        ];

        $order = Order::create( $data );

        dd( $data, $order );
    } );

    // recuperar produto no marketplace
    // consultar e validar frete
    // criar pedido com frete no marketplace
    // criar pedido na fast shop
    // aprovar pedido na fast shop
} );
