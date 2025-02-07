<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Vtex\VtexController;

/**
 * OrderController handles various order-related operations for VTEX integration.
 *
 * This controller manages invoice notifications, order cancellations, creations,
 * approvals, and marketplace cancellations. It serves as a bridge between
 * the local e-commerce system (likely WooCommerce) and the VTEX platform.
 */
class OrderController extends VtexController
{
    /**
     * Handles the invoice notification from VTEX.
     *
     * @see     https://developers.vtex.com/docs/guides/external-marketplace-integration-invoice-tracking
     *
     * @param   Request     $request    The incoming HTTP request
     * @param   string      $order_id   The ID of the order
     * @return  JsonResponse            A JSON response indicating success
     */
    public function invoiceNotification( Request $request, string $order_id ) : JsonResponse
    {
        $data   = $request->all();
        $order  = explode( "-", $data[ "orderId" ] );

        $seller = get_partner( $order[ 0 ] );

        if ( $seller === null )
        {
            log_message(
                channel : parent::LOG_CHANNEL,
                message : "VTEX - invoiceNotification 1",
                level   : "error",
                context : [ "order_id" => $order[ 1 ], "data" => $data ],
            );

            return response()->json( [
                "error_code" => "5001",
                "message"    => "Partner not found",
            ], 404 );
        }

        try
        {
            // Retrieve the WooCommerce order
            [ $parent, $order ] = get_wc_order( parent::LOG_CHANNEL, $order_id, $seller );

            if ( $order === false )
                return response()->json( [], 404 );
            elseif ( $order === null )
                return response()->json( [], 200 );

            $invoice  = array_search_by_key( "_has_fs_invoice",  "key", $order[ "meta_data" ] );
            $tracking = array_search_by_key( "_has_fs_tracking", "key", $order[ "meta_data" ] );
            $finished = array_search_by_key( "_has_fs_finished", "key", $order[ "meta_data" ] );

            if ( $invoice === false && !is_null( $data[ "embeddedInvoice" ] ) )
            {
                $message  = "NOTA FISCAL\n";
                $message .= "Nº: {$data[ "invoiceNumber" ]}\n";
                $message .= "Chave: {$data[ "invoiceKey" ]}\n";
                $message .= "URL: {$data[ "invoiceUrl" ]}";

                create_order_note( parent::LOG_CHANNEL, $order_id, $message );

                update_order_meta( $order_id, [
                    "meta_data" => [ [
                        "key"   => "_has_fs_invoice",
                        "value" => "yes"
                    ] ]
                ] );
            }
            elseif ( $tracking === false && !is_null( $data[ "trackingUrl" ] ) )
            {
                $message  = "PEDIDO ENVIADO PARA ENTREGA\n";
                $message .= "Empresa: {$data[ "courier" ]}\n";
                $message .= "Código: {$data[ "trackingNumber" ]}\n";
                $message .= "URL: {$data[ "trackingUrl" ]}";

                create_order_note( parent::LOG_CHANNEL, $order_id, $message );

                update_order_meta( $order_id, [
                    "meta_data" => [ [
                        "key"   => "_has_fs_tracking",
                        "value" => "yes"
                    ] ]
                ] );
            }
            elseif ( $finished === false && !is_null( $data[ "courierStatus" ] ) )
            {
                $courier = $data[ "courierStatus" ];

                if ( isset( $courier[ "finished" ] ) && $courier[ "finished" ] == true )
                {
                    $message  = "ENTREGA REALIZADA\n";
                    $message .= convert_delivery_date( $data[ "courierStatus" ][ "deliveredDate" ] );

                    create_order_note( parent::LOG_CHANNEL, $order_id, $message );

                    update_order_meta( $order_id, [
                        "status"    => "completed",
                        "meta_data" => [ [
                            "key"   => "_has_fs_finished",
                            "value" => "yes"
                        ] ]
                    ] );
                }
            }
            else
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - invoiceNotification 2",
                    level   : "error",
                    context : [ "order_id" => $order_id, "invoice" => $data ],
                );

                // TODO confirmar se precisa reativar essas notificações
                // send_notification( $parent, $order, "invoice_error" );
            }

            return response()->json( [ "success" => true ], 200 );
        }
        catch ( Throwable $e )
        {
            if ( $e->getMessage() != "JSON ERROR: Syntax error" )
            {
                // Log the exception
                log_exception(
                    channel : parent::LOG_CHANNEL,
                    message : $e->getMessage(),
                    method  : "VTEX - invoiceNotification 3",
                    type    : "Throwable",
                    e       : $e
                );

                return response()->json( [
                    "error_code" => "5000", // 5000 - falha generica
                    "message"    => "Something wrong when receiving invoice notification",
                ], 400 );
            }
        }
    }

    /**
     * Handles the cancellation notification from VTEX.
     *
     * @see     https://developers.vtex.com/docs/api-reference/orders-api#post-/api/oms/pvt/orders/-orderId-/cancel
     *
     * @param   Request     $request    The incoming HTTP request
     * @param   string      $order_id   The ID of the order
     * @return  JsonResponse            A JSON response indicating success or an error message
     */
    public function cancellationNotification( Request $request, string $order_id ) : JsonResponse
    {
        $seller = "fast_shop";

        try
        {
            // Retrieve the WooCommerce order
            [ $parent, $order ] = get_wc_order( parent::LOG_CHANNEL, $order_id, $seller );

            if ( $order === false )
            {
                return response()->json( [], 404 );
            }
            elseif ( $order === null )
            {
                return response()->json( [], 200 );
            }

            // Send notification
            send_notification( $parent, $order, "order_cancelled" );

            $message  = "PEDIDO CANCELADO PELA FAST SHOP\n";
            $message .= "{$request->reason}\n";
            $message .= date( "d/m/Y à\s H:i:s" ) . "\n";

            // Create cancellation note
            create_order_note( parent::LOG_CHANNEL, $order_id, $message );

            return response()->json( [ "success" => true ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "VTEX - cancellationNotification 2",
                type    : "Throwable",
                e       : $e
            );

            return response()->json( [
                "error_code" => "5000", // 5000 - falha generica
                "message"    => "Something wrong when cancelling the order",
            ], 400 );
        }
    }

    /**
     * Creates a new order in the VTEX system.
     *
     * This method is called from ../plugins/triibo-api-services | triibo_fastshop
     *
     * @param   Request     $request    The incoming HTTP request
     * @param   string      $order_id   The ID of the order
     * @return  JsonResponse            A JSON response with the created order data or an error message
     */
    public function orderCreation( Request $request, $order_id )
    {
        // Validate request keys.
        if ( ( $verified = $this->validateKeys( $request ) ) !== true )
            return $verified;

        $seller = $request->header( "seller" );

        try
        {
            // Retrieve the WooCommerce order
            [ $parent, $order ] = get_wc_order( parent::LOG_CHANNEL, $order_id, $seller );

            if ( $order === false )
                return response()->json( [], 404 );
            elseif ( $order === null )
                return response()->json( [], 200 );

            // Format product data for freight calculation
            $wc_data  = format_product_to_freight( $order );

            // Simulate freight
            $simulate = vtex_freight_exec( parent::LOG_CHANNEL, $seller, $wc_data, true );

            // Handle simulation errors
            if ( $simulate[ "error_code" ] != null )
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCreation 1",
                    level   : "error",
                    context : [ "order_id" => $order_id, "wc_data" => $wc_data, "data" => $simulate, ],
                );

                send_notification( $parent, $order, "order_creation_error" );

                // Create error note
                create_error_note( parent::LOG_CHANNEL, $order_id, "order_creation_error_1" );

                return response()->json( [
                    "error_code" => "5001", // 5001 - error in freight data query from FS
                    "message "   => "something wrong",
                    "data"       => null
                ], 500 );
            }

            // Handle empty simulation
            if ( empty( $simulate[ "data" ][ "items" ] ) || empty( $simulate[ "data" ][ "logisticsInfo" ] ) )
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCreation 2",
                    level   : "error",
                    context : [ "order_id" => $order_id, "wc_data" => $wc_data, "data" => $simulate, ],
                );

                send_notification( $parent, $order, "order_creation_error" );

                // Create error note
                create_error_note( parent::LOG_CHANNEL, $order_id, "order_creation_error_2" );

                return response()->json( [
                    "error_code" => "5002", // 5002 - error in freight data query from FS
                    "message "   => "something wrong",
                    "data"       => null
                ], 500 );
            }

            // Create new order in VTEX
            $response = vtex_new_order_exec( parent::LOG_CHANNEL, $seller, $simulate[ "data" ], $order, $parent );

            if ( $response[ "error_code" ] != null )
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCreation 3",
                    level   : "error",
                    context : [ "order_id" => $order_id, "wc_data" => $wc_data, "response" => $response[ "data" ], ],
                );

                send_notification( $parent, $order, "order_creation_error" );

                $message = $response[ "message"    ];
                $code    = $response[ "error_code" ];

                // Create error note
                create_error_note( parent::LOG_CHANNEL, $order_id, "custom", "{$message}\nCode: {$code}" );

                return response()->json( [
                    "error_code" => "5003", // 5003 - error in order creation in FS
                    "message"   => "something wrong",
                    "data"      => [ $code, $message ]
                ], 500 );
            }

            // Update order metadata with VTEX order ID
            update_order_meta( $order_id, [
                "meta_data" => [ [
                    "key"   => "_fastshop_order_id",
                    "value" => $response[ "data" ][ "operationId" ]
                ] ]
            ] );

            // Create creation note
            create_order_note( parent::LOG_CHANNEL, $order_id, "PEDIDO CRIADO NA FAST SHOP:\n#" . $response[ "data" ][ "operationId" ] );

            return response()->json( [
                "error_code" => null,
                "message"   => "ok",
                "data"      => $response[ "data" ],
            ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "VTEX - orderCreation 4",
                type    : "Throwable",
                e       : $e
            );

            return response()->json( [
                "error_code" => "5000", // 5000 - generic failure
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Notifies VTEX that an order has been approved and paid.
     *
     * This method is called from ../plugins/triibo-api-services | triibo_fastshop
     *
     * @param   Request     $request    The incoming HTTP request
     * @param   string      $order_id   The ID of the order
     * @return JsonResponse             A JSON response with the updated order data or an error message
     */
    public function orderApproval( Request $request, $order_id )
    {
        // Validate request keys
        if ( ( $verified = $this->validateKeys( $request ) ) !== true )
            return $verified;

        $seller = $request->header( "seller" );

        try
        {
            // Retrieve the WooCommerce order
            [ $parent, $order ] = get_wc_order( parent::LOG_CHANNEL, $order_id, $seller );

            if ( $order === false )
                return response()->json( [], 404 );
            elseif ( $order === null )
                return response()->json( [], 200 );

            // Check for missing VTEX order ID or approval status
            if ( ( $missing_id = handle_order_meta_fs_id_missing( parent::LOG_CHANNEL, $parent, $order, "orderApproval" ) ) !== null )
                return $missing_id;

            if ( ( $missing_approval = handle_order_meta_fs_approval_missing( parent::LOG_CHANNEL, $parent, $order, "orderApproval" ) ) !== null )
                return $missing_approval;

            // Update order status in VTEX
            $response = vtex_update_order_exec( parent::LOG_CHANNEL, $seller, $order_id );

            if ( $response[ "error_code" ] != null )
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderApproval 1",
                    level   : "error",
                    context : [ "order_id" => $order_id, "response" => $response ],
                );

                send_notification( $parent, $order, "order_approval_error_1" );

                $message = $response[ "message"    ];
                $code    = $response[ "error_code" ];

                // Create error note
                create_error_note( parent::LOG_CHANNEL, $order_id, "custom", "{$message}\nCode: {$code}" );

                return response()->json( [
                    "error_code" => "5001", // 5001 - error in order creation in FS
                    "message"    => "something wrong",
                    "data"       => [ $code, $message ]
                ], 500 );
            }

            // Update order metadata to mark as approved
            update_order_meta( $order_id, [
                "meta_data" => [ [
                    "key"   => "_fastshop_order_approved",
                    "value" => "yes"
                ] ]
            ] );

            // Create approval note
            create_order_note( parent::LOG_CHANNEL, $order_id, "PEDIDO APROVADO NA FAST SHOP" );

            return response()->json( [
                "error_code" => null,
                "message"    => "ok",
                "data"       => $response[ "data" ],
            ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "VTEX - orderApproval 2",
                type    : "Throwable",
                e       : $e
            );

            return response()->json( [
                "error_code" => "5000", // 5000 - generic failure
                "message"    => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Notifies VTEX that an order has been cancelled by the marketplace.
     *
     * This method is called from ../plugins/triibo-api-services | triibo_fastshop
     *
     * @param   Request     $request    The incoming HTTP request
     * @param   string      $order_id   The ID of the order
     * @return  JsonResponse            A JSON response indicating success or an error message
     */
    public function orderCancellation( Request $request, $order_id )
    {
        // Validate request keys
        if ( ( $verified = $this->validateKeys( $request ) ) !== true )
            return $verified;

        $seller = $request->header( "seller" );

        try
        {
            // Retrieve the WooCommerce order
            [ $parent, $order ] = get_wc_order( parent::LOG_CHANNEL, $order_id, $seller );

            if ( $order === false )
                return response()->json( [], 404 );
            elseif ( $order === null )
                return response()->json( [], 200 );

            // Check for missing VTEX order ID
            if ( ( $missing_id = handle_order_meta_fs_id_missing( parent::LOG_CHANNEL, $parent, $order, "orderCancellation" ) ) !== null )
                return $missing_id;

            // Get order details from VTEX
            $response = vtex_get_order( parent::LOG_CHANNEL, $seller, $order_id );

            if ( $response[ "error_code" ] != null )
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCancellation 1",
                    level   : "error",
                    context : [ "order_id" => $order_id, "response" => $response ],
                );

                send_notification( $parent, $order, "order_cancellation_e1" );

                $code     = $response[ "error_code" ];
                $message  = "Pedido não cancelado.\n";
                $message .= "Falha na recuperação do pedido na Fast Shop.\n";
                $message .= $response[ "message" ] . "\n";
                $message .= "Code: {$code}";

                // Create error note
                create_error_note( parent::LOG_CHANNEL, $order_id, "custom", $message );

                return response()->json( [
                    "error_code" => "5001", // 5001 - error in order creation in FS
                    "message"    => "something wrong",
                    "data"       => [ $code, $message ]
                ], 500 );
            }

            $fs_order = $response[ "data" ];

            // Check if order can be cancelled (not already invoiced)
            if ( strtolower( $fs_order[ "status" ] ) == "invoiced" )
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCancellation 2",
                    level   : "error",
                    context : [ "order_id" => $order_id, "response" => $response ],
                );

                send_notification( $parent, $order, "order_cancellation_e2" );

                $status       = $fs_order[ "status"            ];
                $description  = $fs_order[ "statusDescription" ];

                $message      = "Pedido não pode ser cancelado na Fast Shop.\n";
                $message     .= "status: {$status}\n";
                $message     .= "Descrição: {$description}";

                // Create error note
                create_error_note( parent::LOG_CHANNEL, $order_id, "custom", $message );

                return response()->json( [
                    "error_code" => "5002", // 5002 - error in order creation in FS
                    "message"    => "something wrong",
                    "data"       => [ $status, $message ]
                ], 500 );
            }

            // Cancel order in VTEX
            $response = vtex_cancel_order( parent::LOG_CHANNEL, $seller, $order_id );

            if ( $response[ "error_code" ] != null )
            {
                // Log error and send notification
                log_message(
                    channel : parent::LOG_CHANNEL,
                    message : "VTEX - orderCancellation 3",
                    level   : "error",
                    context : [ "order_id" => $order_id, "response" => $response ],
                );

                send_notification( $parent, $order, "order_cancellation_e3" );

                $code     = $response[ "error_code" ];
                $message  = "Pedido não cancelado.\n";
                $message .= "Falha ao informar a Fast Shop sobre o cancelamento do pedido.\n";
                $message .= $response[ "message" ] . "\n";
                $message .= "Code: {$code}";

                // Create error note
                create_error_note( parent::LOG_CHANNEL, $order_id, "custom", $message );

                return response()->json( [
                    "error_code" => "5003", // 5003 - error in order creation in FS
                    "message"    => "something wrong",
                    "data"       => [ $code, $message ]
                ], 500 );
            }

            // Create cancellation note
            create_order_note( parent::LOG_CHANNEL, $order_id, "PEDIDO CANCELADO NA FAST SHOP" );

            return response()->json( [
                "error_code" => null,
                "message"    => "ok",
                "data"       => $response[ "data" ],
            ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "VTEX - orderCancellation 4",
                type    : "Throwable",
                e       : $e
            );

            return response()->json( [
                "error_code" => "5000", // 5000 - generic failure
                "message"    => $e->getMessage(),
            ], 500 );
        }
    }
}
