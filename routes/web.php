<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Http\Controllers\Vtex\OrderController;
use Illuminate\Support\Facades\{ Auth, Route };
use App\Http\Controllers\Vtex\ProductController;
use Codexshaper\WooCommerce\Facades\PaymentGateway;
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

Route::group( [ "middleware" => "auth" ], function()
{
    Route::get( "/home",         [ HomeController::class, "index"         ] )->name( "home"       );
    Route::get( "/documentacao", [ HomeController::class, "documentation" ] )->name( "docs.index" );

    Route::resource( "users", UserController::class );

    Route::prefix( "marketplace" )->name( "mkt." )->group( function ()
    {
        Route::get(  "/pedidos",          [ MarketplaceController::class, "orders"        ] )->name( "index"          );
        Route::post( "/importar",         [ MarketplaceController::class, "importOrders"  ] )->name( "orders.import"  );

        Route::post( "/pedido/criar",     [ MarketplaceController::class, "orderCreate"   ] )->name( "order.create"   );
        Route::post( "/pedido/aprovar",   [ MarketplaceController::class, "orderApproval" ] )->name( "order.approval" );
        Route::post( "/pedido/cancelar",  [ MarketplaceController::class, "orderCancel"   ] )->name( "order.cancel"   );
        Route::post( "/pedido/atualizar", [ MarketplaceController::class, "orderUpdate"   ] )->name( "order.update"   );

        Route::get(  "/fs/{order_id}",    [ MarketplaceController::class, "getFsOrder"    ] )->name( "order.fs"       );
        Route::get(  "/mkt/{order_id}",   [ MarketplaceController::class, "getMktOrder"   ] )->name( "order.mkt"      );
        Route::get(  "/mkt/{id}/ext",     [ MarketplaceController::class, "redirectToMkt" ] )->name( "external"       );
    } );

    Route::get( "/fast-shop/product/{sku}", [ ProductController::class, "getProductData" ] );

} );






// =========================================
// ============ ROTAS DE TESTE =============
// =========================================
Route::prefix( "mkt" )->group( function ()
{
    Route::get( "/create-customer", function()
    {
        $data = [
            'email'      => 'john10.doe.9.7.1@example.com',
            'first_name' => 'John 10',
            'last_name'  => 'Doe 9.7.1',
            'username'   => 'john10.doe.9.7.1',
            'password'   => 'password',
            'meta_data'   => [ [
                'key'   => '_triibo_id',
                'value' => "11111111111111"
            ], [
                'key'   => '_triibo_phone',
                'value' => "+5511911112222"
            ] ]
        ];

        $customer = Customer::create( $data );

        // dump( $customer );

        // $customer = Customer::update( $customer[ "id" ], [ "meta_data" => $data[ "meta_data" ] ] );

        dd( $customer );
    } );

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

    Route::get( "/get-orders/{status}", function( $status = "pending" )
    {
        try
        {
            $orders = Order::where( "status", $status )->get();

            dd( $orders );
        }
        catch ( \Exception $e )
        {
            dd( $e->getMessage(), $e );
        }
    } );

    Route::get( "/create-order", function()
    {
        $data = [
            "customer_id"          => 1717,
            "set_paid"             => false,
            "status"               => "on-hold",
            "payment_method"       => "triibo_payments_pix",
            "payment_method_title" => "Triibo PIX",
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
                "product_id" => 1023758,
                "quantity"   => 2
            ] ],
        ];

        $order = Order::create( $data );

        dd( $data, $order );
    } );

    Route::get( "/update-order/{id}", function( $id )
    {
        $headers  = [
            "Accept"    => "*/*",
            "x-api-key" => "54723162306C6F6A7052646D4C6170693030",
        ];

        $url      = "https://marketplace.triibo.com.br/wp-json/triibo-payments/v1/orders/update-payment";
        $params   = "?status=RECEIVED&orderId={$id}&approved=true";

        $response = Http::withHeaders( headers: $headers ) ->get( url: $url . $params );

        dd( $response->json() );

        // $data  = [
        //     "customer_id" => 1717,
        //     "status"      => "processing",
        // ];

        // $order = Order::update( $id, $data );

        // dd( $data, $order );
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

    Route::get( "/get-product/{id}/{delete}", function( $id, $delete )
    {
        if ( $delete === "true" )
        {
            // $product = Product::delete( $id, [ "force" => false ] );
            // dd( $product );

            $products = Product::batch( [
                "delete" => [
                    1059027, // => [ "force" => true ],
                    1058625, // => [ "force" => true ],
                ]
            ] );

            dd( $products );
        }
        else
        {
            $product = Product::find( $id );
            dd( $product );
        }

        // dd( $id, $delete );

        // $product_id = $id ;
        // // $options = [];
        // $options = ['force' => true];
        // $product = Product::delete( $product_id, $options );

        // dd( $product );

        // $product = Product::batch( [ "delete" => [ $id ] ] );
        // dd( $product );



        // $meta = [];

        // $keys = [
        //     "_subscription_period",
        //     "_subscription_period_interval",
        //     "_subscription_length",
        //     "_subscription_limit"
        // ];

        // foreach ( $product[ "meta_data" ] as $value )
        //     if ( in_array( $value->key, $keys ) )
        //         $meta[] = $value;

        // dd( $meta );
    } );

    Route::get( "/create-product", function()
    {
        $data = [
            'name'              => 'Premium Quality 7',
            'type'              => 'simple',
            'regular_price'     => '25.99',
            'description'       => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.',
            'short_description' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.',
            'images'            => [
                [
                    "src"  => "https://help.rangeme.com/hc/article_attachments/360006928633/what_makes_a_good_product_image.jpg",
                    "name" => "name_1",
                    "alt"  => "name_1",
                ],[
                    "src"  => "https://cdn.shopify.com/s/files/1/2303/2711/files/2_e822dae0-14df-4cb8-b145-ea4dc0966b34.jpg",
                    "name" => "name_2",
                    "alt"  => "name_2",
                ],
            ],
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

    Route::get( "/get-gateways", function()
    {
        $paymentGateways = PaymentGateway::all();

        dd( $paymentGateways );
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

    Route::get( "/create-order/{id}", function( $id )
    {
        $config   = config( "woocommerce.plugins" );
        $vtex     = new OrderController();
        $request  = new Request();

        $request->headers->set( key: "seller",    values: "fast_shop"        );
        $request->headers->set( key: "api-key",   values: $config[ "key"   ] );
        $request->headers->set( key: "api-token", values: $config[ "token" ] );

        $response = $vtex->orderCreation( request: $request, order_id: $id );

        dd( $response );
    } );

    // recuperar produto no marketplace
    // consultar e validar frete
    // criar pedido com frete no marketplace
    // criar pedido na fast shop
    // aprovar pedido na fast shop
} );

Route::prefix( "fb" )->group( function ()
{
    Route::get( "/get", function()
    {
        // // Initialize Firebase database references
        $db             = Firebase::database();
        $establishments = $db->getReference( path: "/Estabelecimento-v2" );
        $blacklist      = [ "-awin", "-cofry", "-socialsoul"   ];
        $fields         = [ "partnerEstablishmentId", "origin" ];
        $establishments = filter_data( data: $establishments->getValue(), blacklist: $blacklist, fields: $fields, origin: "awin" );
        // Storage::put( "awin/establishments_fb_prod.json", json_encode( $establishments ) );

        // $establis_split = Storage::get( "awin/establishments_fb_prod_split.json" );
        // $establis_split = json_decode( $establis_split, true );
        // $temp           = [];

        // foreach ( $establis_split[ "published" ] as $push_id => $estab )
        //     if ( Str::contains( Str::lower( $estab[ "nome" ] ), "samsung" ) )
        //         $temp[ $push_id ] = $estab;

        // dd( $temp );

        // $establishments = Storage::get( "awin/establishments_fb_prod.json" );
        // $establishments = json_decode( $establishments, true );

        $text  = "";
        $count = [
            0 => 0,
            1 => 0,
        ];

        $disab = [];
        $cur_disab = [];
        $enab = [];
        $cur_enab = [];

        foreach ( $establishments as $push_id => $estab )
        {
            if ( $estab[ "isPublished" ] )
            {
                $p = "✅";
                $count[ 1 ]++;
                $enab[] = $push_id;
            }
            else
            {
                $p = "❌";
                $count[ 0 ]++;
                $disab[] = $push_id;
            }

            $p     = $estab[ "isPublished" ] ? "✅" : "❌";
            $text .= "{$push_id} | {$p} | " . $estab[ "nome" ] . "\n";
        }

        foreach ( $disab as $push_id )
        {
            $approval = $db->getReference( path: "/Estabelecimento-Aprovacoes/{$push_id}" )->getValue();

            if ( $approval && $approval[ "status" ] == "aberto" )
                $cur_disab[ $push_id ] = $approval;
        }

        foreach ( $enab as $push_id )
        {
            $approval = $db->getReference( path: "/Estabelecimento-Aprovacoes/{$push_id}" )->getValue();

            if ( $approval && $approval[ "status" ] == "aberto" )
                $cur_enab[ $push_id ] = $approval;
        }

        Storage::put( "curadoria-disab.json", json_encode( $cur_disab ) );
        Storage::put( "curadoria-enab.json", json_encode( $cur_enab ) );

        dd( $text, $count, $cur_disab, $cur_enab );









        // $dd             = [];

        // $false = 0;
        // $true  = 0;

        // foreach ( $establishments as $push_id => $estab )
        // {
        //     $dd[ $estab[ "nome" ] ][] = [
        //         "push_id"     => $push_id,
        //         "nome"        => $estab[ "nome"        ],
        //         "orgin"       => $estab[ "origin"      ],
        //         "isPublished" => $estab[ "isPublished" ],
        //     ];

        //     if ( $estab[ "isPublished" ] )
        //         $true++;
        //     else
        //         $false++;
        // }

        // dd(
        //     env( "TRIIBO_ENV" ),
        //     "true = {$true} | false = {$false}",
        //     $dd
        // );
    } );

    Route::get( "/test", function()
    {
        $partners      = config( "vtex.partners" );

        dd( $partners );
    } );
} );
