<?php

namespace App\Http\Controllers;

use App\Models\Marketplace\Order;
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

        $orders   = $query->get()->map( function ( $order )
        {
            $order->billing  = reorganize_fields( $order->billing,  get_billing_fields_order()  );
            $order->shipping = reorganize_fields( $order->shipping, get_shipping_fields_order() );
            $order->status   = get_order_status( $order->ord_status );

            return $order;
        } );

        return view( "marketplace.index", compact( "file", "files", "orders", "status", "statuses" ) );
    }

    /**
     * Show the marketplace orders.
     *
     * @return  RedirectResponse
     */
    public function importOrders( Request $request ) : RedirectResponse
    {
        $request->validate( [ "csv_file" => "required|file|mimes:csv,txt|max:2048" ] );

        $type    = self::ERROR;
        $message = "Arquivo inválido.";

        if ( $request->file( "csv_file" )->isValid() )
        {
            $processed = process_csv_file( $request->file( "csv_file" ) );

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

        try
        {
            $product = WC_Product::find( $order->product_id );
        }
        catch ( Throwable $e )
        {
            log_message( channel: self::LOG_CHANNEL, message: print_r( $e, true ) );

            if ( $e->getMessage() == "Error: ID inválido. [woocommerce_rest_product_invalid_id]" )
            {
                $order->ord_status = "product_not_found";
                $order->save();

                return log_and_redirect(
                    channel : self::LOG_CHANNEL,
                    route   : self::ROUTE,
                    type    : self::ERROR,
                    message : "{$order->ord_id}: Produto não encontrado."
                );
            }
            elseif ( $e->getMessage() == "JSON ERROR: Syntax error" )
            {
                // return log_and_redirect(
                //     channel : self::LOG_CHANNEL,
                //     route   : self::ROUTE,
                //     type    : "warning",
                //     message : "{$order->ord_id}: Não foi possível consultar o produto, tente novamente."
                // );
            }
            elseif ( $e->getMessage() == "Error: <p>Há um erro crítico no seu site.</p><p><a href=\"https:\/\/wordpress.org/documentation/article/faq-troubleshooting/\">Saiba mais sobre a como resolver problemas do WordPress.</a></p> [internal_server_error]" )
            {
                return log_and_redirect(
                    channel : self::LOG_CHANNEL,
                    route   : self::ROUTE,
                    type    : self::ERROR,
                    message : "{$order->ord_id}: Erro interno, tente novamente."
                );
            }
            else
            {
                return log_and_redirect(
                    channel : self::LOG_CHANNEL,
                    route   : self::ROUTE,
                    type    : self::ERROR,
                    message : "{$order->ord_id}: Erro ao consultar o produto, tente novamente."
                );
            }
        }

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

        $fs_product_id  = search_product_meta( $product[ "meta_data" ] );

        if ( $fs_product_id == null )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Produto sem ID Fast Shop."
            );
        }

        $freight        = get_freight_vtex( $fs_product_id, $order[ "shipping" ][ "postcode" ] );

        if ( $freight === false )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Falha ao consulta frete da Fast Shop. FCF1"
            );
        }

        $delivery_price = get_delivery_price( $fs_product_id, $freight[ "data" ] );

        if ( $delivery_price === false )
        {
            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Falha ao consulta frete da Fast Shop. FCF2"
            );
        }

        $wc_data        = marketplace_new_order_prepare( $order, $order->product_id, $delivery_price );

        try
        {
            $wc_order = WC_Order::create( $wc_data );
            $wc_order = json_decode( json_encode( $wc_order ), true );
        }
        catch ( Throwable $e )
        {
            log_message( channel: self::LOG_CHANNEL, message: print_r( $e, true ) );

            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id}: Erro ao criar pedido."
            );
        }

        $order->mkt_id     = $wc_order[ "id" ];
        $order->ord_status = "processed";

        $response          = vtex_new_order_exec( "triibo", self::SELLER, $freight[ "data" ], $wc_order );

        if ( $response[ "error_code" ] !== null )
        {
            $order->ord_status = "fs_create_error";
            $order->save();

            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id} #{$order->mkt_id}: Erro ao criar pedido na Fast Shop."
            );
        }

        $order->save();

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

        $request->headers->set( "seller",    self::SELLER                          );
        $request->headers->set( "api-key",   config( "woocommerce.plugins.key"   ) );
        $request->headers->set( "api-token", config( "woocommerce.plugins.token" ) );

        $response = $vtex->orderApproval( $request, $order->mkt_id );

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

        $vtex     = new VtexOrderController();
        $request  = new Request();

        $request->headers->set( "seller",    self::SELLER                          );
        $request->headers->set( "api-key",   config( "woocommerce.plugins.key"   ) );
        $request->headers->set( "api-token", config( "woocommerce.plugins.token" ) );

        $response = $vtex->orderCancellation( $request, $order->mkt_id );

        if ( $response->status() != 200 )
        {
            $data = $response->getData();

            $order->ord_status = "fs_cancel_error";
            $order->save();

            return log_and_redirect(
                channel : self::LOG_CHANNEL,
                route   : self::ROUTE,
                type    : self::ERROR,
                message : "{$order->ord_id} #{$order->mkt_id}: {$data->message}"
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
}
