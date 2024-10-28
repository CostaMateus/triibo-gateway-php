<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Vtex\VtexController;

class OrderController extends VtexController
{
    /**
     * Handles the invoice notification.
     *
     * @see     https://developers.vtex.com/docs/guides/external-marketplace-integration-invoice-tracking
     *
     * @param   Request     $request
     * @param   string      $order_id
     * @return  JsonResponse
     */
    public function invoiceNotification( Request $request, string $order_id ) : JsonResponse
    {
        save_file( name: "Vtex-OrderController-{$order_id}-invoiceNotification", data: $request->all() );

        return response()->json( [ "success" => true ], 200 );
    }

    /**
     * Handles the cancel notification.
     *
     * @see     https://developers.vtex.com/docs/api-reference/orders-api#post-/api/oms/pvt/orders/-orderId-/cancel
     *
     * @param   Request     $request
     * @param   string      $order_id
     * @return  JsonResponse
     */
    public function cancellationNotification( Request $request, string $order_id ) : JsonResponse
    {
        save_file( name: "Vtex-OrderController-{$order_id}-cancellationNotification", data: $request->all() );

        return response()->json( [ "success" => true ], 200 );
    }

    /**
     * Criação de pedido
     * Notifica a Fastshop para validar e processar o pedido.
     * Deve-se enviará informações como os itens contidos no carrinho, dados do perfil do cliente, dados de envio e os dados de pagamento.
     *
     * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
     *
     * @param   Request     $request
     * @param   string      $order_id
     * @return  Response
     */
    public function orderCreation( Request $request, $order_id )
    {
        if ( ( $verified = $this->validateKeys( $request ) ) !== true )
            return $verified;

        $seller = $request->header( "seller" );

        try
        {
            [ $parent, $order ] = get_wc_order( parent::LOG_CHANNEL, $order_id, $seller );

            if ( $order == false )
            {
                // notificar
                return response()->json( [], 404 );
            }
            elseif ( $order == null )
            {
                return response()->json( [], 200 );
            }

            $wc_data  = format_product_to_freight( $order );

            $simulate = vtex_freight_exec( parent::LOG_CHANNEL, $seller, $wc_data, true );

            if ( $simulate[ "error_code" ] != null )
            {
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCreation 1",
                    level   : "error",
                    context : [ "order_id" => $order_id, "wc_data" => $wc_data, "data" => $simulate, ],
                );

                send_notification( $parent, $order, "new_order_error" );

                create_error_note( parent::LOG_CHANNEL, $order_id, "new_order_error" );

                return response()->json( [
                    "error_code" => "5001", // 5001 - erro na consulta dos dados de frete na FS
                    "message "   => "something wrong",
                    "data"       => null
                ], 500 );
            }

            if ( empty( $simulate[ "data" ][ "items" ] ) || empty( $simulate[ "data" ][ "logisticsInfo" ] ) )
            {
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCreation 2",
                    level   : "error",
                    context : [ "order_id" => $order_id, "wc_data" => $wc_data, "data" => $simulate, ],
                );

                send_notification( $parent, $order, "new_order_error" );

                create_error_note( parent::LOG_CHANNEL, $order_id, "new_order_error_2" );

                return response()->json( [
                    "error_code" => "5002", // 5001 - erro na consulta dos dados de frete na FS
                    "message "   => "something wrong",
                    "data"       => null
                ], 500 );
            }

            $response = vtex_order_exec( parent::LOG_CHANNEL, $seller, $simulate[ "data" ], $order, $parent );

            if ( $response[ "error_code" ] != null )
            {
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCreation 3",
                    level   : "error",
                    context : [ "order_id" => $order_id, "wc_data" => $wc_data, "response" => $response[ "data" ], ],
                );

                send_notification( $parent, $order, "new_order_error" );

                $message = $response[ "message"    ];
                $code    = $response[ "error_code" ];

                create_error_note( parent::LOG_CHANNEL, $order_id, "custom", "{$message}\nCode: {$code}" );

                return response()->json( [
                    "error_code" => "5002", // 5002 - erro na criação do pedido na FS
                    "message"   => "something wrong",
                    "data"      => [ $code, $message ]
                ], 500 );
            }

            update_order_meta( $order_id, [
                "meta_data" => [ [
                    "key"   => "_fastshop_order_id",
                    "value" => $response[ "data" ][ "operationId" ]
                ] ]
            ] );

            create_order_note( parent::LOG_CHANNEL, $order_id, "PEDIDO CRIADO NA FAST SHOP:\n#" . $response[ "data" ][ "operationId" ] );

            return response()->json( [
                "error_code" => null,
                "message"   => "ok",
                "data"      => $response[ "data" ],
            ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "VTEX - orderCreation 4",
                type    : "Throwable",
                e       : $e
            );

            return response()->json( [
                "error_code" => "5000", // 5000 - falha generica
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Pedido Aprovado e Pago
     * Após o pagamento ser concluído e aprovado, notifica a Fastshop avisando que este pode iniciar o processo de faturamento e entrega do pedido.
     *
     * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop-orders
     *
     * @param Request $request
     * @return Response
     */
    public function orderApproved( Request $request, $order_id )
    {
        if ( ( $verified = $this->validateKeys( $request ) ) !== true )
            return $verified;

        $seller = $request->header( "seller" );

        try
        {
            [ $parent, $order ] = get_wc_order( parent::LOG_CHANNEL, $order_id, $seller );

            if ( $order == false )
            {
                // notificar
                return response()->json( [], 404 );
            }
            elseif ( $order == null )
            {
                return response()->json( [], 200 );
            }

            $meta            = $order[ "meta_data" ];
            $meta_key        = array_search( "_fastshop_order_id", array_column( $meta, "key" ) );

            if ( $meta_key === false )
            {
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderApproved 1",
                    level   : "error",
                    context : [ "order_id" => $order_id ],
                );

                send_notification( $parent, $order, "approved_order_error_2" );

                create_error_note( parent::LOG_CHANNEL, $order_id, "approved_order_error_2" );

                return response()->json( [
                    "error_code" => "4002",
                    "message"   => "Order does not have meta_data '_fastshop_order_id'"
                ], 400 );
            }

            $response = vtex_update_order_exec( parent::LOG_CHANNEL, $seller, $order_id );

            if ( $response[ "error_code" ] != null )
            {
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderApproved 2",
                    level   : "error",
                    context : [ "order_id" => $order_id, "response" => $response ],
                );

                send_notification( $parent, $order, "approved_order_error_1" );

                $message = $response[ "message"    ];
                $code    = $response[ "error_code" ];

                create_error_note( parent::LOG_CHANNEL, $order_id, "custom", "{$message}\nCode: {$code}" );

                return response()->json( [
                    "error_code" => "5002", // 5002 - erro na criação do pedido na FS
                    "message"    => "something wrong",
                    "data"       => [ $code, $message ]
                ], 500 );
            }

            create_order_note( parent::LOG_CHANNEL, $order_id, "PEDIDO APROVADO NA FAST SHOP" );

            return response()->json( [
                "error_code" => null,
                "message"    => "ok",
                "data"       => $response[ "data" ],
            ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "VTEX - orderCreation 4",
                type    : "Throwable",
                e       : $e
            );

            return response()->json( [
                "error_code" => "5000", // 5000 - falha generica
                "message"    => $e->getMessage(),
            ], 500 );
        }
    }
}
