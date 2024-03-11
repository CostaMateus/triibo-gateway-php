<?php

// use App\Models\User;
// use Mautic\MauticApi;
// use App\Helpers\Helper;
// use Mautic\Auth\ApiAuth;
// use Devio\Pipedrive\Pipedrive;
// use Triibo\Mautic\Facades\Mautic;
// use App\Helpers\Helper;
// use Gregwar\Image\Image;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
// use Triibo\Mautic\Models\MauticConsumer;
// use App\Notifications\WebhookMarketplace;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\UserController;
// use Codexshaper\WooCommerce\Facades\Note;
// use Codexshaper\WooCommerce\Facades\Term;
use Codexshaper\WooCommerce\Facades\Order;
// use Doctrine\Inflector\Rules\Substitution;
// use Codexshaper\WooCommerce\Facades\Coupon;
// use App\Http\Controllers\ChatGuruController;
use Codexshaper\WooCommerce\Facades\Coupon;
use App\Http\Integrations\MauticIntegration;
use Codexshaper\WooCommerce\Facades\Customer;
// use App\Http\Controllers\FastshopController;
// use App\Http\Integrations\FastshopIntegration\DataTranslator;
// use Codexshaper\WooCommerce\Facades\Product;
// use Codexshaper\WooCommerce\Facades\Category;
// use Kreait\Laravel\Firebase\Facades\Firebase;
// use Codexshaper\WooCommerce\Facades\Attribute;
// use Codexshaper\WooCommerce\Facades\Subscription;
// use App\Http\Controllers\Affiliates\AwinController;
// use Codexshaper\WooCommerce\Facades\PaymentGateway;
// use App\Http\Integrations\FastshopIntegration\DataTranslator;
// use App\Http\Integrations\AffiliatesIntegration\AwinIntegration;
// use App\Http\Integrations\AffiliatesIntegration\SocialSoulIntegration;

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

Route::group( ["middleware" => "auth" ], function() {

    Route::get( "/home", function ()
    {
        return view( "home" );
    } )->name( "home" );

    Route::get( "/clear-caches", function()
    {
        Artisan::call( "optimize:clear" );
        return view( "welcome", [
            "showToast" => true,
            "message"   => __( "marketplace.cache" )
        ] );
    } )->name( "clear.caches" );

    Route::get( "/update-stages", function()
    {
        MauticIntegration::updateStages();

        return view( "welcome", [
            "showToast" => true,
            "message"   => __( "marketplace.update.all.stages" )
        ] );
    } )->name( "update.stages" );

    Route::get( "/sync-ids", function()
    {
        MauticIntegration::syncIdsMauticPipe();

        return view( "welcome", [
            "showToast" => true,
            "message"   => __( "marketplace.sync.id" )
        ] );
    } )->name( "sync.ids" );

    Route::resource( "users", UserController::class );

} );



// =========================================
// ============ ROTAS DE TESTE =============
// =========================================
Route::prefix( "mkt" )->group( function () {

    Route::get( "/get-customer", function()
    {
        $customer = Customer::all( [
            "per_page" => 100,
            "search"   => "costa.mack95@hotmail.com"
        ] );

        dd( $customer );
    } );

    Route::get( "/get-order/{id}", function( $id )
    {
        $order = Order::find( $id );

        dd( $order );
    } );

    Route::get( "/update-order", function()
    {
        $order_id = "972533";
        $data     = [
            "customer_id" => 4591,
        ];

        $order    = Order::update( $order_id, $data );

        dd( $data, $order );
    } );

    Route::get( "/create-order", function()
    {
        $data = [
            // "customer_id"          => 1717,
            "payment_method"       => "triibo_assinaturas",
            "payment_method_title" => "Triibo Assinaturas",
            "status"               => "on-hold", // pedido entra como aguardando
            "set_paid"             => false,     // e nao pago
            "billing"              => [
                "first_name" => "Mateus",
                "last_name"  => "Costa",
                "address_1"  => "Al Eduardo Prado",
                "address_2"  => "170",
                "city"       => "Sao Paulo",
                "state"      => "SP",
                "postcode"   => "01218011",
                "country"    => "Brasil",
                "email"      => "mateus.costa@triibo.com.br",
                "phone"      => "11949289507",
            ],
            "shipping"             => [
                "first_name" => "Mateus",
                "last_name"  => "Costa",
                "address_1"  => "Al Eduardo Prado",
                "address_2"  => "170",
                "city"       => "Sao Paulo",
                "state"      => "SP",
                "postcode"   => "01218011",
                "country"    => "Brasil",
            ],
            "line_items"           => [ [
                "product_id" => 972529,
                "quantity"   => 1,
            ] ],
        ];

        $order = Order::create( $data );

        dd( $data, $order );
    } );

} );

Route::get( "/fs", function () {

    // $parent = null;
    // $data   = Order::all( [ "parent" => 968447 ] );

    // if ( $data->count() != 0 )
    // {
    //     $parent = Order::find( 968447 );
    // }
    // else
    // {
    //     $data   = [ Order::find( 968447 ) ];

    //     if ( ( $id = $data[ 0 ][ "parent_id" ] ) != 0 )
    //     {
    //         $parent = Order::find( $id );

    //         do
    //         {
    //             $has_parent = $parent[ "parent_id" ] != 0;

    //             if ( $has_parent )
    //             {
    //                 $parent = Order::find( $parent[ "parent_id" ] );
    //             }
    //         }
    //         while ( $has_parent );
    //     }
    // }

    // $data = [
    //     "order"  => json_decode( json_encode( $data   ), true ),
    //     "parent" => json_decode( json_encode( $parent ), true )
    // ];

    // // retira os pedidos que não são da FastShop
    // foreach ( $data[ "order" ] as $key => $order )
    // {
    //     // id da FS no dev-marketplace
    //     $id = config( "fastshop.production.wc.storeId" );

    //     if ( $order[ "store" ][ "id" ] != $id )
    //         unset( $data[ "order" ][ $key ] );
    // }

    // $data[ "order" ] = $data[ "order" ][ key( $data[ "order" ] ) ];

    // $c = new FastshopController();

    // // 960724
    // // 954047
    // // 950362
    // // 955957
    // // 949305

    // // 968493
    // // 968496

    // $data = $c->getFsOrder( "968496" );

    // $data[ "order" ] = $c->getOrderFreight( $data[ "order" ][ key( $data[ "order" ] ) ] );

    // $body = DataTranslator::translate( "newOrderFs", $data );

    // dd( $data, $body  );


    // $db          = Firebase::database();
    // $fbRef       = $db->getReference( "/Store" );
    // $fbPromos    = $fbRef->getValue();

    // foreach ( $fbPromos as $key => $fbPromo )
    // {
    //     foreach ( $fbPromo as $fbKey => $fbValue )
    //     {
    //         if ( $fbKey == "tags" )
    //             dd( $key, $fbPromo );
    //     }
    // }

    // dd( "not found" );


    // ============================================================


    // $data   = Storage::get( "firebase_gateway_token_hml.json" );
    // $origin = "https://dev-marketplace.triibo.com.br";
    // $data   = json_decode( $data, true );
    // $new    = [];

    // foreach ( $data as $value )
    // {
    //     foreach ( $value[ "tokens" ] as &$tokens )
    //         foreach ( $tokens as &$token )
    //             $token[ "origin" ] = $origin;

    //     foreach( $value[ "payments" ] as $gateway => $payments )
    //     {
    //         $newPays = [];

    //         foreach( $payments as $payment )
    //             $newPays[ $payment[ "id" ] ] = $payment;

    //         $value[ "payments" ][ $gateway ] = $newPays;
    //     }

    //     $new[ $value[ "uId" ] ] = $value;
    // }

    // dd( ( $new ) );


    // ============================================================


    // $coupons = Coupon::all();
    // dd( $coupons );

    // $data  = [
    //     "payment_method"   => "triibo_assinaturas",
    //     "set_paid"         => true,
    //     "customer_id"      => 1717,
    //     "line_items"           => [
    //         [
    //             "product_id" => 330551,
    //             "quantity"   => 1,
    //             "subtotal"   => "10.00",
    //             "total"      => "10.00",
    //         ],
    //     ],
    // ];

    // $order = Order::create( $data );

    // $data[ "parent_id"         ] = $order[ "id" ];
    // $data[ "status"            ] = "active";
    // $data[ "billing_period"    ] = "month";
    // $data[ "billing_interval"  ] = 1;
    // $data[ "start_date"        ] = "2023-07-14 17:00:00";
    // $data[ "next_payment_date" ] = "2023-08-14 17:00:00";
    // $data[ "meta_data"         ] = [
    //     [
    //         "key"   => "_triibo_assinatura_payment_token",
    //         "value" => 12,
    //     ]
    // ];
    // $data[ "coupon_lines"      ] = [
    //     [
    //         "code" => "teste-290"
    //     ]
    // ];

    // $sub   = Subscription::create( $data );

    // $coup  = [
    //     "coupon_lines" => [
    //         [
    //             "code" => "teste-290"
    //         ]
    //     ]
    // ];

    // $order = Order::update( $order[ "id" ], $coup );

    // dd( $order, $sub );



    // $products = Storage::get( "1-fs-data.json" );
    // $products = json_decode( $products, true );

    // dump( $products[4], $products[5] );

    // $s0     = json_encode( $products[4] );
    // $s1     = json_encode( $products[5] );

    // dump( $s0, $s1 );

    // similar_text( $s0, $s1, $perc );

    // dd( $perc );



    // $fsData  = $products[0];
    // $sku     = ( $fsData[ "ProductId" ] == $fsData[ "SellerStockKeepingUnitId" ] ) ? $fsData[ "ProductId" ] : $fsData[ "SellerStockKeepingUnitId" ];

    // $founded = DB::table( "fastshop_products" )->where( "product_sku", "FS-{$sku}" )->first();

    // if ( !$founded )
    // {
    //     unset( $fsData[ "Pricing" ], $fsData[ "AvailableQuantity" ] );

    //     $data  = [
    //         "product_sku" => "FS-" . $sku,
    //         "action"      => "create",
    //         "status"      => "unprocessed",
    //         "data"        => json_encode( $fsData ),
    //         "created_at"  => now(),
    //         "updated_at"  => now(),
    //     ];

    //     $saved = DB::table( "fastshop_products" )->insert( $data );

    //     if ( !$saved ) throw new Exception( "Unable to save the product data" );

    //     dd( $saved );
    // }
    // else
    // {
    //     $fsData[ "Width" ] = 97000;
    //     $fsDataEnc         = json_encode( $fsData );

    //     similar_text( $fsDataEnc, $founded->data, $perc );

    //     if ( $perc != 100 )
    //     {
    //         $updated = DB::table( "fastshop_products" )
    //                     ->where( "id", $founded->id )
    //                     ->update( [
    //                         "action"     => "update",
    //                         "status"     => "unprocessed",
    //                         "data"       => json_encode( $fsData ),
    //                         "updated_at" => now()
    //                     ] );

    //         dd( $fsData, $updated );
    //     }
    // }

    // dd( "ok" );

} );
