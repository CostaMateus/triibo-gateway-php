<?php

namespace App\Http\Controllers;

use App\Models\Marketplace\Order;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\{ Request, RedirectResponse };
use Codexshaper\WooCommerce\Facades\Order as WC_Order;
use Codexshaper\WooCommerce\Facades\Product as WC_Product;
use App\Http\Controllers\Vtex\OrderController as VtexOrderController;

class MarketplaceController extends Controller
{
    const LOG_CHANNEL = "triibo";

    const ROUTE       = "mkt.index";

    const SELLER      = "fast_shop";

    const ERROR       = "error";
    const SUCCESS     = "success";
    const WARNING     = "warning";

    /**
     * Show the marketplace orders.
     *
     * @return  Renderable
     */
    public function orders( Request $request ) : Renderable
    {
        $statuses = get_all_order_status();

        $files    = Order::select( "file_name" )->distinct()->orderBy( "file_name", "DESC" )->pluck( "file_name" );
        $file     = $request->query( "file" ) ?? $files->first();

        $status   = $request->query( "status" ) ?? "all";

        $query    = Order::where( "file_name", $file );

        if ( $status != "all" )
            $query->where( "ord_status", $status );

        $orders   = $query->paginate( 100 )->through( function ( $order )
        {
            $order->billing  = reorganize_fields( data : $order->billing,  order : get_billing_fields_order()  );
            $order->shipping = reorganize_fields( data : $order->shipping, order : get_shipping_fields_order() );
            $order->status   = get_order_status( status : $order->ord_status );

            return $order;
        } );

        return view(
            view : "marketplace.index",
            data : compact( "file", "files", "orders", "status", "statuses" )
        );
    }

    /**
     * Show the marketplace orders.
     *
     * @return  RedirectResponse
     */
    public function importOrders( Request $request ) : RedirectResponse
    {
        $request->validate( rules : [ "csv_file" => "required|file|mimes:csv,txt|max:2048" ] );

        $type    = self::ERROR;
        $message = "Arquivo inválido.";

        if ( $request->file( key : "csv_file" )->isValid() )
        {
            $processed = process_csv_file( file : $request->file( "csv_file" ) );

            if ( $processed )
            {
                $type    = self::SUCCESS;
                $message = "Arquivo importado com sucesso.";
            }
            else
            {
                $message = "Falha ao processar arquivo.";
            }
        }

        return log_and_redirect(
            channel : self::LOG_CHANNEL,
            route   : self::ROUTE,
            type    : $type,
            message : $message
        );
    }

    /**
     * Undocumented function
     *
     * @param   Request     $request
     * @return  RedirectResponse
     */
    public function orderCreate( Request $request ) : RedirectResponse
    {
        if ( !$request->ord_id || !$request->file_name )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "Dados inválidos."
            );
        }

        $store_id = config( key: "woocommerce.partners." . self::SELLER . ".store_id" );

        $order    = Order::where( "file_name",  $request->file_name )
                         ->where( "ord_id",     $request->ord_id    )
                         ->where( "ord_status", "not_processed"     )
                         ->first();

        if ( !$order )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$request->ord_id}: Pedido com status diferente de 'Não processado'."
            );
        }

        do
        {
            $response = get_marketplace_product(
                channel : self::LOG_CHANNEL,
                order   : $order
            );
        }
        while( !$response[ "success" ] && $response[ "code" ] == 502 );

        if ( !$response[ "success" ] )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : $response[ "message" ]
            );
        }

        $product = $response[ "data" ];

        if ( $product[ "store" ]->id != $store_id )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Esse produto não é da Fast Shop."
            );
        }
        else if ( ( float ) $product[ "price" ] > ( float ) $order->max_price )
        {
            $order->ord_status = "over_max";
            $order->save();

            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Valor do produto acima do máximo."
            );
        }

        $fs_product_id = search_product_meta( metas : $product[ "meta_data" ] );

        if ( $fs_product_id == null )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Produto sem ID Fast Shop."
            );
        }

        $freight = get_freight_vtex(
            product_id : $fs_product_id,
            postcode   : $order[ "shipping" ][ "postcode" ]
        );

        if ( $freight === false )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Falha ao consulta frete da Fast Shop. FCF1"
            );
        }

        $delivery_price = get_delivery_price(
            product_id : $fs_product_id,
            freight    : $freight[ "data" ]
        );

        if ( $delivery_price === false )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Falha ao consulta frete da Fast Shop. FCF2"
            );
        }

        $wc_data = marketplace_new_order_prepare(
            order      : $order,
            product_id : $order->product_id,
            freight    : $delivery_price
        );

        $response = create_marketplace_order(
            channel  : self::LOG_CHANNEL,
            data     : $wc_data,
            order_id : $order->ord_id
        );

        if ( !$response[ "success" ] )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : $response[ "message" ]
            );
        }

        $wc_order          = $response[ "data" ];
        $wc_order          = json_decode( json_encode( $wc_order ), true );

        $order->mkt_id     = $wc_order[ "id" ];
        $order->ord_status = "processed";
        $order->save();

        // $response          = vtex_new_order_exec(
        //     channel : self::LOG_CHANNEL,
        //     seller  : self::SELLER,
        //     freight : $freight[ "data" ],
        //     order   : $wc_order
        // );

        // if ( $response[ "error_code" ] !== null )
        // {
        //     $order->ord_status = "fs_create_error";
        //     $order->save();

        //     return log_and_redirect(
        //         channel : self::LOG_CHANNEL,
        //         route   : self::ROUTE,
        //         type    : self::ERROR,
        //         message : "{$order->ord_id} #{$order->mkt_id}: Erro ao criar pedido na Fast Shop."
        //     );
        // }

        // // Update order metadata with VTEX order ID
        // update_order_meta(
        //     channel  : self::LOG_CHANNEL,
        //     order_id : $order->mkt_id,
        //     data     : [
        //         "meta_data" => [ [
        //             "key"   => "_fastshop_order_id",
        //             "value" => $response[ "data" ][ "operationId" ]
        //         ] ]
        //     ]
        // );

        // // Create creation note
        // create_order_note(
        //     channel  : self::LOG_CHANNEL,
        //     order_id : $order->mkt_id,
        //     message  : "PEDIDO CRIADO NA FAST SHOP:\n#" . $response[ "data" ][ "operationId" ]
        // );

        return log_and_redirect(
            channel : self::LOG_CHANNEL,
            route   : self::ROUTE,
            type    : self::SUCCESS,
            message : "{$order->ord_id} #{$order->mkt_id}: Pedido criado com sucesso."
        );
    }

    /**
     * Undocumented function
     *
     * @param   Request     $request
     * @return  RedirectResponse
     */
    public function orderApprove( Request $request ) : RedirectResponse
    {
        if ( !$request->ord_id || !$request->file_name )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "Dados inválidos."
            );
        }

        $order = Order::where( "file_name",  $request->file_name )
                      ->where( "ord_id",     $request->ord_id    )
                      ->where( "ord_status", "processed"         )
                      ->first();

        if ( !$order )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$request->ord_id}: Pedido com status diferente de 'Processado'."
            );
        }

        if ( !$order->mkt_id )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$request->ord_id}: Pedido sem ID do marketplace, 'Mkt ID'."
            );
        }

        $vtex     = new VtexOrderController();
        $request  = new Request();

        $request->headers->set( key : "seller",    values : self::SELLER                          );
        $request->headers->set( key : "api-key",   values : config( "woocommerce.plugins.key"   ) );
        $request->headers->set( key : "api-token", values : config( "woocommerce.plugins.token" ) );

        $response = $vtex->orderApproval( request : $request, order_id : $order->mkt_id );

        if ( $response->status() != 200 )
        {
            $data = $response->getData();

            $order->ord_status = "fs_approve_error";
            $order->save();

            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id} #{$order->mkt_id}: {$data->message}"
            );
        }

        // Create manual approval note
        create_order_note(
            channel  : self::LOG_CHANNEL,
            order_id : $order->mkt_id,
            message  : "Pedido aprovado manualmente na FS por: " . auth()->user()->name . " (integração)"
        );

        $order->ord_status = "approved";
        $order->save();

        return log_and_redirect(
            channel : self::LOG_CHANNEL,
            route   : self::ROUTE,
            type    : self::SUCCESS,
            message : "{$order->ord_id} #{$order->mkt_id}: Pedido aprovado com sucesso."
        );
    }

    /**
     * Undocumented function
     *
     * @param   Request     $request
     * @return  RedirectResponse
     */
    public function orderCancel( Request $request ) : RedirectResponse
    {
        if ( !$request->ord_id || !$request->file_name )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "Dados inválidos."
            );
        }

        $order = Order::where( "file_name",  $request->file_name )
                      ->where( "ord_id",     $request->ord_id    )
                      ->whereIn( "ord_status", [ "processed", "approved" ] )
                      ->first();

        if ( !$order )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$request->ord_id}: Pedido com status diferente de 'Processado' e/ou 'Aprovado'."
            );
        }

        if ( !$order->mkt_id )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$request->ord_id}: Pedido sem ID do marketplace, 'Mkt ID'."
            );
        }

        // $vtex     = new VtexOrderController();
        // $request  = new Request();

        // $request->headers->set( key : "seller",    values: self::SELLER                          );
        // $request->headers->set( key : "api-key",   values: config( "woocommerce.plugins.key"   ) );
        // $request->headers->set( key : "api-token", values: config( "woocommerce.plugins.token" ) );

        // $response = $vtex->orderCancellation( request : $request, order_id : $order->mkt_id );

        // if ( $response->status() != 200 )
        // {
        //     $data = $response->getData();

        //     $order->ord_status = "fs_cancel_error";
        //     $order->save();

        //     return log_and_redirect(
        //         channel : self::LOG_CHANNEL,
        //         route   : self::ROUTE,
        //         type    : self::ERROR,
        //         message : "{$order->ord_id} #{$order->mkt_id}: {$data->message}"
        //     );
        // }

        $response = update_marketplace_order(
            channel  : self::LOG_CHANNEL,
            order_id : $order->mkt_id,
            status   : "cancelled",
        );

        if ( !$response[ "success" ] )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : $response[ "message" ]
            );
        }

        $order->ord_status = "canceled";
        $order->save();

        return log_and_redirect(
            channel : self::LOG_CHANNEL,
            route   : self::ROUTE,
            type    : self::SUCCESS,
            message : "{$order->ord_id} #{$order->mkt_id}: Pedido cancelado com sucesso."
        );
    }

    /**
     * Undocumented function
     *
     * @param   Request     $request
     * @param   string      $order_id
     * @return  void
     */
    public function getFsOrder( Request $request, string $order_id ) : void
    {
        $response = vtex_get_order( "marketplace", "fast_shop", $order_id );

        dd( $response );
    }
}
