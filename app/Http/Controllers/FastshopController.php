<?php

namespace App\Http\Controllers;

use Exception;
use App\Helpers\Helper;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Codexshaper\WooCommerce\Facades\Note;
use Codexshaper\WooCommerce\Facades\Order;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;
use App\Http\Integrations\FastshopIntegration\Log;
use App\Http\Integrations\FastshopIntegration\Notification;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class FastshopController extends Controller
{
    private $env;
    private $fsUrl;
    private $fsHeaders;

    public function __construct()
    {
        $this->env       = config( "fastshop.env"                   );
        $this->fsUrl     = config( "fastshop.$this->env.fs.url"     );
        $this->fsHeaders = config( "fastshop.$this->env.fs.headers" );
    }

    // --------- METODOS ABAIXO SÂO CONSUMIDOS PELA FASTSHOP
    /**
     * Notificação de catálogo
     * Fastshop sugere novos SKUs a serem vendidos no Marketplace e
     * informa ao Marketplace sobre as mudanças em seus SKUs que já foram integrados
     * como alterações de cadastro, estoque e preço. Ambas as ações começam com uma notificação de catálogo.
     *
     * @param Request $request
     * @param string $sellerId
     * @param string $sellerSkuId
     * @return Response
     */
    public function changeNotification( Request $request, $sellerId, $sellerSkuId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request ) ) !== TRUE ) return $verify;

        try
        {
            if ( $sellerSkuId )
            {
                // Procura produto no Marketplace pelo SKU
                $product = Product::all( [ "sku" => "FS-" . strtoupper( $sellerSkuId ) ] )->first();

                if ( !$product )
                {
                    return response()->json( [
                        "errorCode" => "404",
                        "message"   => "Seller StockKeepingUnit {$sellerSkuId} not found for this seller id {$sellerId}",
                    ], 404 );
                }
            }

            return response()->json( [
                "errorCode" => null,
                "message"   => "Ok",
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "FS - changeNotification", $e, $sellerSkuId );

            return response()->json( [
                "errorCode" => "500",
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Registro de catálogo
     * Recebe sugestão de um novo produto para o Marketplace.
     * Toda vez que changeNotification() retornar o status 404.
     *
     * @param Request $request
     * @param string $sellerId
     * @param string $sellerSkuId
     * @return Response
     */
    public function skuSuggestion( Request $request, $sellerId, $sellerSkuId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request ) ) !== TRUE ) return $verify;

        try
        {
            // Recupera dados do produto
            $fsData    = $request->all();

            $sku       = $fsData[ "ProductId" ] == $fsData[ "SellerStockKeepingUnitId" ] ? $fsData[ "ProductId" ] : $fsData[ "SellerStockKeepingUnitId" ];

            if ( $sellerSkuId && $sellerSkuId == $sku )
            {
                $fsData[ "CategoryFullPath" ] = ( is_null( $fsData[ "CategoryFullPath" ] ) ) ? [] : self::getCategoriesId( $fsData[ "CategoryFullPath" ] );

                // Converte produto da FS para WC
                $wcData    = DataTranslator::translate( "newProduct", $fsData );

                // Atualiza produto no Marketplace
                $searchSku = Product::all( [ "sku" => $wcData[ "sku" ] ] );

                /**
                 * TODO
                 * Verificar problema com duplicação de SKU no WooCommerce
                 * Acompanhar issue #27295
                 * https://github.com/woocommerce/woocommerce/issues/27295
                 *
                 * ?
                 * Solução temporária
                 * Schedule sku:delete
                 * Excluí SKUs duplicados, diariamente ás 03:03.
                 */
                if ( !$searchSku->first() )
                    $product = Product::create( $wcData );
                else
                    $product = Product::update( $searchSku->first()->id, $wcData );

                if (
                    ( empty( $product[ "images" ] ) && count( $product[ "images" ] ) == 0 )
                    ||
                    ( empty( $product[ "sku"    ] ) ) )
                {
                    Product::update( $product[ "id" ], [
                        "status" => "private",
                    ] );
                }
            }

            return response()->json( [
                "errorCode" => null,
                "message"   => "Ok",
            ], 200 );
        }
        catch ( Exception $e )
        {
            $error = true;
            $ex    = Helper::treatExceptionMsgFS( $e->getMessage() );

            if ( $ex )
            {
                switch ( $ex )
                {
                    case "[product_invalid_sku]":
                        $error = false;
                    break;
                }
            }

            if ( $error )
            {
                Log::exception( "FS - skuSuggestion", $e, $sellerSkuId );

                return response()->json( [
                    "errorCode" => "500",
                    "message"   => $e->getMessage(),
                ], 500 );
            }
            else
            {
                return response()->json( [
                    "errorCode" => null,
                    "message"   => "Ok",
                ], 200 );
            }
        }
    }

    /**
     * Atualização de catálogo
     *
     * @param Request $request
     * @param string $sellerId
     * @param string $sellerSkuId
     * @return Response
     */
    public function updateSku( Request $request, $sellerId, $sellerSkuId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request ) ) !== TRUE ) return $verify;

        try
        {
            // Recupera dados do produto
            $fsData    = $request->all();

            $fsData[ "CategoryFullPath" ] = ( is_null( $fsData[ "CategoryFullPath" ] ) ) ? [] : self::getCategoriesId( $fsData[ "CategoryFullPath" ] );

            // Converte produto da FS para WC
            $wcData    = DataTranslator::translate( "newProduct", $fsData );

            // Atualiza produto no Marketplace
            $searchSku = Product::all( [ "sku" => $wcData[ "sku" ] ] );

            if ( !$searchSku->first() )
            {
                return response()->json( [
                    "errorCode" => "404",
                    "message"   => "something wrong on update product"
                ], 404 );
            }

            Product::update( $searchSku->first()->id, $wcData );

            return response()->json( [
                "errorCode" => null,
                "message"   => "Ok",
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "FS - updateSku", $e );

            return response()->json( [
                "errorCode" => "500",
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Pedido Faturado ou Despachado
     * Quando o pedido for despachado pela FS, ou seja, entregue a transportadora,
     * os dados de rastreamento serão atualizados utilizando este endpoint,
     * com todas as informações de faturamento e rastreio do pedido, identificando o cenário de "Expedição".
     *
     * @param Request $request
     * @param string $orderId
     * @return Response
     */
    public function invoiceNotification( Request $request, $orderId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request ) ) !== TRUE ) return $verify;

        try
        {
            $fsData    = $request->all();

            if ( !empty( $fsData[ "trackingNumber" ] ) )
            {
                $tracking = DataTranslator::translate( "tracking", $fsData );
                Note::create( $orderId, [ "note" => $tracking ] );
            }
            else
            {
                $invoice  = DataTranslator::translate( "invoice",  $fsData );
                Note::create( $orderId, [ "note" => $invoice ] );
            }

            // busca o orderId da FS no meta_data
            $order     = Order::find( $orderId );
            $meta      = json_decode( json_encode( $order[ "meta_data" ] ), true );
            $meta_key  = array_search( "_fastshop_order_id", array_column( $meta, "key" ) );
            $fsOrderId = $meta[ $meta_key ][ "value" ];

            return response()->json( [
                "date"    => date_format( now(), "Y-m-d\TH:i:s.uP" ),
                "orderId" => $fsOrderId,
                "receipt" => null
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "FS - invoiceNotification", $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Pedido Entregue
     * Este endpoint será utilizado pela FS para informar que o pedido foi entregue.
     *
     * @param Request $request
     * @param string $orderId
     * @return Response
     */
    public function deliveredNotification( Request $request, $orderId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request ) ) !== TRUE ) return $verify;

        try
        {
            $deliveredDate = $request->all()[ "deliveredDate" ];
            $deliveredDate = Helper::convertDeliveryDate( $deliveredDate );

            Order::update( $orderId, [ "status" => "completed" ] );

            Note::create( $orderId, [
                "note"          => "ENTREGA REALIZADA\n $deliveredDate",
                "customer_note" => false,
            ] );

            // busca o orderId da FS no meta_data
            $order         = Order::find( $orderId );
            $meta          = json_decode( json_encode( $order[ "meta_data" ] ), true );
            $meta_key      = array_search( "_fastshop_order_id", array_column( $meta, "key" ) );
            $fsOrderId     = $meta[ $meta_key ][ "value" ];

            return response()->json( [
                "date"    => date_format( now(), "Y-m-d\TH:i:s.uP" ),
                "orderId" => $fsOrderId,
                "receipt" => null
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "FS - deliveredNotification", $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Pedido Cancelado pela FS
     * Caso a FS tenha problemas com o pedido e não possa, por algum, realizar a entrega,
     * irá informar o cancelamento ao Marketplace. Tal ação só poderá ser realizada
     * caso o pedido se encontre em um status que permita o cancelamento.
     *
     * @param Request $request
     * @param string $orderId
     * @return Response
     */
    public function cancellationNotification( Request $request, $orderId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request ) ) !== TRUE ) return $verify;

        try
        {
            $data            = $this->getFsOrder( $orderId );
            $data[ "order" ] = $data[ "order" ][ key( $data[ "order" ] ) ];

            Notification::notify( "cancelationOrderFS", $data );
            Note::create( $orderId, [ "note" => "PEDIDO CANCELADO PELA FASTSHOP\n" . date_format( now(), "d/m/Y à\s H:i:s" ) ] );

            // busca o orderId da FS no meta_data
            $order           = Order::find( $orderId );
            $meta            = json_decode( json_encode( $order[ "meta_data" ] ), true );
            $meta_key        = array_search( "_fastshop_order_id", array_column( $meta, "key" ) );
            $fsOrderId       = $meta[ $meta_key ][ "value" ];

            return response()->json( [
                "date"               => date_format( now(), "Y-m-d\TH:i:s.uP" ),
                "marketPlaceOrderId" => $orderId,
                "orderId"            => $fsOrderId,
                "receipt"            => null
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "FS - cancellationNotification", $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }



    // --------- METODOS ABAIXO SÃO CONSUMIDOS PELO WOOCOMMERCE, CONSULTAM AS APIs DA FASTSHOP
    /**
     * Atualiza preço e estoque
     *
     * API chamada a partir do ../plugins/triibo-fastshop-shipping/
     *
     * @param Request $request
     * @param string $sku
     * @return Response
     */
    public function updateSkuWc( Request $request, $sku )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request, true ) ) !== TRUE ) return $verify;

        try
        {
            // Procura produto no Marketplace pelo SKU
            $product = Product::all( [ "sku" => strtoupper( $sku ) ] )->first();

            if ( !$product )
            {
                return response()->json( [
                    "errorCode" => "4001",
                    "message"   => "Unable to retrieve product data",
                ], 404 );
            }

            $sku     = explode( "FS-", $sku )[ 1 ];

            // Atualiza produto no Marketplace
            $this->updateStockPrice( $product, $sku );

            return response()->json( [
                "errorCode" => null,
                "message"   => "Ok",
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "MP - updateSkuWc", $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Cálculo de frete
     * Consulta a Fastshop para obter informações comerciais sobre SKUs, tais como, preço, estoque, frete e prazos de entrega.
     * O "postalCode" deve ser preenchido no request e será obrigatório para o retorno das informações de logística.
     *
     * API chamada a partir do ../plugins/triibo-fastshop-shipping/
     *
     * @param Request $request
     * @return Response
     */
    public function freightCalculation( Request $request )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request, true ) ) !== TRUE ) return $verify;

        $wcData = $request->all();

        try
        {
            // Converte dados do WC para FS
            $fsData = DataTranslator::translate( "freightCalcFs",     $wcData );
            $fsData = DataTranslator::translate( "breakingUpFreight", $fsData );

            $resps  = [];
            $data   = [];

            foreach ( $fsData as $body )
            {
                // Consulta valor do frete na FS
                $url     = "$this->fsUrl/pvt/orderForms/simulation";
                $resps[] = Http::withHeaders( $this->fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );
            }

            foreach ( $resps as $key => $response )
            {
                // falha na consulta
                // não altera nada no MKT
                // não exibe frete
                if ( !$response->successful() )
                {
                    Log::freightCalculation( $fsData[ $key ] );

                    $item         = $wcData[ "items" ][ $key ];
                    $sku          = $item[ "sku" ];
                    $data[ $sku ] = [
                        "errorCode" => "5001",
                        "message"   => "Something wrong when consulting the delivery price",
                        "data"      => $item
                    ];

                    continue;
                }

                $item                  = $wcData[ "items" ][ $key ];
                $sku                   = $item[ "sku" ];

                $fsData                = $response->json();

                $aWcData               = DataTranslator::translate( "freightCalcWc", $fsData );

                $aWcData[ "sku"      ] = isset( $aWcData[ "sku"      ] ) ? $aWcData[ "sku"      ] : $sku;
                $aWcData[ "quantity" ] = isset( $aWcData[ "quantity" ] ) ? $aWcData[ "quantity" ] : $item[ "quantity" ];

                $code                  = $aWcData[ "code"    ];
                $message               = $aWcData[ "message" ];

                unset( $aWcData[ "code" ], $aWcData[ "message" ] );

                $data[ $sku ] = [
                    "errorCode" => $code,
                    "message"   => $message,
                    "data"      => $aWcData,
                ];
            }

            return response()->json( [
                "errorCode" => null,
                "message"   => "ok",
                "data"      => $data,
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "MP - freightCalculation", $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
                "data"      => $wcData[ "items" ],
            ], 500 );
        }
    }

    /**
     * Criação de pedido
     * Notifica a Fastshop para validar e processar o pedido.
     * Deve-se enviará informações como os itens contidos no carrinho, dados do perfil do cliente, dados de envio e os dados de pagamento.
     *
     * API chamada a partir do ../themes/martfury-child/functions.php
     *
     * orderId para testes: 39731, 59798, 59858
     *
     * @param Request $request
     * @param string $orderId
     * @return Response
     */
    public function orderCreation( Request $request, $orderId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request, true ) ) !== TRUE ) return $verify;

        try
        {
            $data = $this->getFsOrder( $orderId );

            // quando vazio, não é pedido da FS
            if ( empty( $data[ "order" ] ) ) return response()->json( "", 200 );

            $data[ "order" ] = $this->getOrderFreight( $data[ "order" ][ key( $data[ "order" ] ) ] );

            if ( $data[ "order" ][ "fs_error" ][ "error" ] )
            {
                $error = $data[ "order" ][ "fs_error" ];

                Log::order( "error", "New order error", [ $error, $data ] );
                Notification::notify( "newOrderError", $data );

                Note::create( $data[ "order" ][ "id" ], [
                    "note" => DataTranslator::translate( "newOrderFsError", $error )
                ] );

                return response()->json( [
                    "errorCode" => "5001", // 5001 - erro na consulta dos dados de frete na FS
                    "message"   => "something wrong",
                    "data"      => $error
                ], 500 );
            }

            // converte pedido WC para FS
            $body = DataTranslator::translate( "newOrderFs", $data );
            $url  = "$this->fsUrl/pvt/orders";

            // Cria pedido na FS
            $response = Http::withHeaders( $this->fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

            if ( !$response->successful() )
            {
                $error = $response->json();

                Log::order( "error", "New order error", [ $error, $body ] );
                Notification::notify( "newOrderError", $data );

                Note::create( $data[ "order" ][ "id" ], [
                    "note" => "ERROR FS\n" . $error[ "Message" ] . "\nCode: " . $error[ "ErrorCode" ]
                ] );

                return response()->json( [
                    "errorCode" => "5002", // 5002 - erro na criação do pedido na FS
                    "message"   => "something wrong",
                    "data"      => [ $error, $body ]
                ], 500 );
            }

            $fsData = $response->json();

            Note::create( $data[ "order" ][ "id" ], [
                "note"      => "PEDIDO CRIADO NA FAST SHOP:\n#" . $fsData[ "orderId" ]
            ] );

            Order::update( $data[ "order" ][ "id" ], [
                "meta_data" => [ [
                    "key"   => "_fastshop_order_id",
                    "value" => $fsData[ "orderId" ]
                ] ]
            ] );

            return response()->json( [
                "errorCode" => null,
                "message"   => "ok",
                "data"      => $response->json(),
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "MP - orderCreation", $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - falha generica
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Pedido Aprovado e Pago
     * Após o pagamento ser concluído e aprovado, notifica a Fastshop avisando que este pode iniciar o processo de faturamento e entrega do pedido.
     *
     * @param Request $request
     * @return Response
     */
    public function orderApproved( Request $request, $orderId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request, true ) ) !== TRUE ) return $verify;

        try
        {
            $data            = $this->getFsOrder( $orderId );

            // quando vazio, não é pedido da FS
            if ( empty( $data[ "order" ] ) ) return response()->json( "", 200 );

            $data[ "order" ] = $data[ "order" ][ key( $data[ "order" ] ) ];

            // busca o orderId da FS no meta_data
            $meta            = $data[ "order" ][ "meta_data" ];
            $meta_key        = array_search( "_fastshop_order_id", array_column( $meta, "key" ) );

            if ( $meta_key === false )
            {
                $log = [ $data[ "order" ][ "id" ] . " : meta_data '_fastshop_order_id' not found" ];
                Log::order( "info", "Approved order error", $log );

                return response()->json( [
                    "errorCode" => "4002",
                    "message"   => "Order does not have meta_data '_fastshop_order_id'"
                ],400 );
            }

            $fsOrderId     = $meta[ $meta_key ][ "value" ];

            $url           = "$this->fsUrl/pvt/orders/$fsOrderId/fulfill";
            $body          = [ "marketplaceOrderId" => $data[ "order" ][ "id" ] ];

            // envia solicitação a FS
            $response      = Http::withHeaders( $this->fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

            if( !$response->successful() )
            {
                Log::order( "info", "Approved order error", $body );
                Notification::notify( "approvedOrderError", $data );

                return response()->json( [
                    "errorCode" => "4001",
                    "message"   => "Something went wrong when reporting order approval"
                ],400 );
            }

            $note  = "PEDIDO APROVADO NA FAST SHOP:\n";
            $note .= DataTranslator::translate( "orderStatusChange", $response->json() );

            Note::create( $data[ "order" ][ "id" ], [
                "note" => $note
            ] );

            return response()->json( [
                "errorCode" => null,
                "message"   => "ok",
                "data"      => $response->json(),
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "MP - orderCreation", $e );

            return response()->json( [
                "errorCode" => "5000", // erro generico
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Pedido Cancelado pelo Marketplace
     * Notifica a Fastshop de que o pedido foi cancelado.
     *
     * @param Request $requests
     * @param string $orderId
     * @return Response
     */
    public function orderCancelation( Request $request, $orderId )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request, true ) ) !== TRUE ) return $verify;

        try
        {
            $data            = $this->getFsOrder( $orderId );

            // quando vazio, não é pedido da FS
            if ( empty( $data[ "order" ] ) ) return response()->json( "", 200 );

            $data[ "order" ] = $data[ "order" ][ key( $data[ "order" ] ) ];

            // busca o orderId da FS no meta_data
            $meta            = $data[ "order" ][ "meta_data" ];
            $meta_key        = array_search( "_fastshop_order_id", array_column( $meta, "key" ) );

            if ( $meta_key === false )
            {
                $log = [ $data[ "order" ][ "id" ] . " : meta_data '_fastshop_order_id' not found" ];
                Log::order( "info", "Cancelation order error", $log );

                return response()->json( [
                    "errorCode" => "4002",
                    "message"   => "Order does not have meta_data '_fastshop_order_id'"
                ],400 );
            }

            $fsOrderId       = $meta[ $meta_key ][ "value" ];

            $url             = "$this->fsUrl/pvt/orders/$fsOrderId/cancel";
            $body            = [ "marketplaceOrderId" => $data[ "order" ][ "id" ] ];

            // envia solicitação a FS
            $response        = Http::withHeaders( $this->fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

            if( !$response->successful() )
            {
                Log::order( "info", "Cancelation order error", $body );
                Notification::notify( "cancelationOrderError", $data );

                return response()->json( [
                    "errorCode" => "4001",
                    "message"   => "Something wrong when canceling the order"
                ],400 );
            }

            $note  = "PEDIDO CANCELADO NA FAST SHOP:\n";
            $note .= DataTranslator::translate( "orderStatusChange", $response->json() );
            Note::create( $data[ "order" ][ "id" ], [
                "note" => $note
            ] );

            return response()->json( [
                "errorCode" => null,
                "message"   => "ok",
                "data"      => $response->json(),
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "MP - orderCancelation", $e );

            return response()->json( [
                "errorCode" => "5000",
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }



    // --------- METODOS DE USO INTERNO
    /**
     * Consulta e cria categorias no Marketplace
     *
     * @param string $data
     * @return array
     */
    private static function getCategoriesId( string $data )
    {
        $auxData    = explode( "/", $data );
        $categories = [];

        foreach ( $auxData as $key => $ctg )
        {
            $slug           = Str::slug( $ctg, "-" );

            $searchCategory = Category::all( [ "slug" => $slug ] );

            if ( !$searchCategory->first() )
            {
                $new          = Category::create( [
                    "name"   => $ctg,
                    "parent" => ( $key == 0 ) ? 0 : $categories[ $key - 1 ]
                ] );

                $categories[] = $new[ "id" ];
            }
            else
            {
                $categories[] = $searchCategory->first()->id;
            }
        }

        return $categories;
    }

    /**
     * Valida as chaves das requisições recebidas
     *
     * @param Request $request
     * @param boolean $wc       Valida chaves do WooCommerce
     * @return true|Response
     */
    private function keysVerification( Request $request, bool $wc = false )
    {
        $env = $this->env;

        if ( !$wc )
        {
            $appKey   = $request->header( "x-vtex-api-appkey"   );
            $appToken = $request->header( "x-vtex-api-apptoken" );

            // Verifição de chaves
            if ( $appKey != config( "fastshop.$env.fs.key" ) || $appToken != config( "fastshop.$env.fs.token" ) )
            {
                Log::invalidCredentials( $appKey, $appToken );

                return response()->json( [
                    "errorCode" => "400",
                    "message"   => "invalid credentials"
                ], 400 );
            }
            else
            {
                return TRUE;
            }
        }
        else
        {
            $appKey   = $request->header( "api-key"   );
            $appToken = $request->header( "api-token" );

            // Verifição de chaves
            if ( $appKey != config( "fastshop.$env.wc.key" ) || $appToken != config( "fastshop.$env.wc.token" ) )
            {
                Log::invalidCredentials( $appKey, $appToken );

                return response()->json( [
                    "errorCode" => "400",
                    "message"   => "invalid credentials"
                ], 400 );
            }
            else
            {
                return TRUE;
            }
        }
    }

    /**
     * Atualização de preço/estoque
     * Consulta a Fastshop para atualizar os dados do produto.
     * Toda vez que changeNotification() retornar o status 200.
     *
     * @param Product $Product
     * @param string $sellerSkuId
     * @return void
     */
    private function updateStockPrice( $product, $sellerSkuId )
    {
        $url  = "$this->fsUrl/pvt/orderForms/simulation";
        $body = DataTranslator::translate( "queryStockPrice", [ $sellerSkuId ] );

        try
        {
            // Consulta produto na FS
            $response = Http::withHeaders( $this->fsHeaders )->withBody( json_encode( $body ), "application/json" )->get( $url );

            if ( $response->successful() )
            {
                $fsData = $response->json();

                // Converte produto da FS para WC
                $wcData = DataTranslator::translate( "updateStockPrice", $fsData );

                // Atualiza produto no Marketplace
                $new    = Product::update( $product->id, $wcData );
            }
            else
            {
                // Registrar log de falha
                Log::updateStockPrice( $product, null, true );
            }
        }
        catch ( Exception $e )
        {
            Log::exception( "INT - updateStockPrice", $e, $sellerSkuId );
        }
    }

    /**
     * Recupera o pedido no WooCommerce
     *
     * @param string $orderId
     * @return array
     */
    private function getOrder( string $orderId )
    {
        $parent = null;
        $data   = Order::all( [ "parent" => $orderId ] );

        if ( $data->count() != 0 )
        {
            $parent = Order::find( $orderId );
        }
        else
        {
            $data = [ Order::find( $orderId ) ];

            if ( ( $id = $data[ 0 ][ "parent_id" ] ) != 0 )
            {
                $parent = Order::find( $id );

                do
                {
                    $has_parent = $parent[ "parent_id" ] != 0;

                    if ( $has_parent )
                    {
                        $parent = Order::find( $parent[ "parent_id" ] );
                    }
                }
                while ( $has_parent );
            }
        }

        return [
            "order"  => json_decode( json_encode( $data   ), true ),
            "parent" => json_decode( json_encode( $parent ), true )
        ];
    }

    /**
     * Recupera o frete do pedido
     *
     * @param array $data
     * @return array
     */
    private function getOrderFreight( array $data )
    {
        $fs_error  = [
            "error"     => false,
            "message"   => "",
            "errorCode" => 0,
            "orderId"   => null,
            "sku"       => [],
        ];

        $body      = DataTranslator::translate( "freightCalcNewOrder", $data );
        $bodies    = DataTranslator::translate( "breakingUpFreight", $body );

        foreach ( $bodies as $body )
        {
            $url       = "{$this->fsUrl}/pvt/orderForms/simulation";
            $response  = Http::withHeaders( $this->fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

            if ( !$response->successful() )
            {
                $msg = "Something wrong when consulting the delivery price";
                $sku = "FS-" . $body[ "items" ][ 0 ][ "id" ];
                $fs_error[ "error"     ]   = true;
                $fs_error[ "message"   ]   = $msg;
                $fs_error[ "errorCode" ]   = "5001";
                $fs_error[ "orderId"   ]   = $data[ "id" ];
                $fs_error[ "sku"       ][] = [ $sku => $msg ];
            }
            else
            {
                $fsData           = $response->json();
                $auxData[]        = $fsData;
                $itemWithoutStock = array_search( 0, array_column( $fsData[ "logisticsInfo" ], "stockBalance" ) );

                if ( empty( $fsData[ "logisticsInfo" ] ) || $itemWithoutStock !== false )
                {
                    $msg = "SKU currently out-of-stock";
                    $sku = "FS-" . $body[ "items" ][ 0 ][ "id" ];
                    $fs_error[ "error"     ]   = true;
                    $fs_error[ "message"   ]   = $msg;
                    $fs_error[ "errorCode" ]   = "4001";
                    $fs_error[ "orderId"   ]   = $data[ "id" ];
                    $fs_error[ "sku"       ][] = [ $sku => $msg ];
                }
                else
                {
                    foreach ( $fsData[ "logisticsInfo" ] as $items )
                    {
                        if ( empty( $items[ "slas" ] ) )
                        {
                            $msg = "Delivery unavailable for this zip code";
                            $sku = "FS-" . $body[ "items" ][ 0 ][ "id" ];
                            $fs_error[ "error"     ]   = true;
                            $fs_error[ "message"   ]   = $msg;
                            $fs_error[ "errorCode" ]   = "4002";
                            $fs_error[ "orderId"   ]   = $data[ "id" ];
                            $fs_error[ "sku"       ][] = [ $sku => $msg ];
                        }
                    }
                }
            }
        }

        if ( $fs_error[ "error" ] )
        {
            $data[ "fs_error" ] = $fs_error;
            $data[ "fs_info"  ] = null;

            return $data;
        }

        if ( count( $auxData ) > 1 )
        {
            $fsData = $auxData[ 0 ];

            foreach ( $auxData as $index => $response )
            {
                if ( $index > 0 )
                {
                    $fsData[ "items"         ][] = $response[ "items"         ][ 0 ];
                    $fsData[ "logisticsInfo" ][] = $response[ "logisticsInfo" ][ 0 ];
                    $fsData[ "logisticsInfo" ][ $index ][ "itemIndex" ] = $index;
                }
            }
        }
        else
        {
            $fsData = $auxData[ 0 ];
        }

        $data[ "fs_error" ] = $fs_error;
        $data[ "fs_info"  ] = $fsData;

        return $data;
    }

    /**
     * Recupera o pedido da Fast Shop
     *
     * @param string $orderId
     * @return array
     */
    private function getFsOrder( string $orderId )
    {
        $data = $this->getOrder( $orderId );

        // retira os pedidos que não são da FastShop
        foreach ( $data[ "order" ] as $key => $order )
        {
            // id da FS no dev-marketplace
            $id = config( "fastshop.$this->env.wc.storeId" );

            if ( $order[ "store" ][ "id" ] != $id ) unset( $data[ "order" ][ $key ] );
        }

        return $data;
    }
}
