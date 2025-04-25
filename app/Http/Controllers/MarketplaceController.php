<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Marketplace\Order;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\{RedirectResponse, Request};
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Controllers\Vtex\OrderController as VtexOrderController;

class MarketplaceController extends Controller
{
    public const LOG_CHANNEL = "triibo";
    public const ROUTE = "mkt.index";
    public const SELLER = "fast_shop";
    public const ERROR = "error";
    public const SUCCESS = "success";
    public const WARNING = "warning";

    /**
     * @param Request $request
     *
     * @return Renderable
     */
    public function orders( Request $request ) : Renderable
    {
        $statuses = get_all_order_status();

        $files = Order::select( "file_name" )->distinct()->orderBy( "file_name", "DESC" )->pluck( "file_name" );

        $file = $request->query( "file", session( "mkt_file", $files->first() ) );
        $per_page = $request->query( "per_page", session( "mkt_per_page", 100 ) );
        $status = $request->query( "status", session( "mkt_status", "all" ) );
        $product_id = $request->query( "product_id", session( "mkt_product_id", "all" ) );

        $product_ids = Order::where( "file_name", $file )->distinct()->orderBy( "product_id" )->pluck( "product_name", "product_id" );
        $query = Order::where( "file_name", $file );

        if ( $status !== "all" )
            $query->where( "ord_status", $status );

        if ( $product_id !== "all" )
            $query->where( "product_id", $product_id );

        $orders = $query->paginate( $per_page );

        if ( $orders->currentPage() > 1 && $orders->isEmpty() )
            $orders = $query->paginate( $per_page, ["*"], "page", $orders->lastPage() );

        $orders = $orders->through( static function ( $order )
        {
            $order->billing = reorganize_fields( data: $order->billing, order: get_billing_fields_order() );
            $order->shipping = reorganize_fields( data: $order->shipping, order: get_shipping_fields_order() );
            $order->status = get_order_status( status: $order->ord_status );

            return $order;
        } );

        session( [
            "mkt_file" => $file,
            "mkt_per_page" => $per_page,
            "mkt_status" => $status,
            "mkt_product_id" => $product_id,
            "mkt_last_page" => request()->query( "page", 1 )
        ] );

        return view(
            view: "marketplace.index",
            data: compact(
                "orders",
                "file", "files",
                "status", "statuses",
                "product_id", "product_ids"
            )
        );
    }

    /**
     * @param Request $request
     *
     * @return Renderable
     */
    public function searchFsProducts( Request $request ) : Renderable
    {
        $data = null;
        $sku = $request->product_id;

        if ( $sku !== null )
            $data = vtex_get_product( sku: $sku );

        return view( view: "marketplace.search-fs-product", data: compact( "data" ) );
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function importOrders( Request $request ) : RedirectResponse
    {
        $request->validate( rules: ["csv_file" => "required|file|mimes:csv,txt|max:2048"] );

        $type = self::ERROR;
        $message = "Arquivo inválido.";

        if ( $request->file( key: "csv_file" )->isValid() )
        {
            $processed = process_csv_file( file: $request->file( "csv_file" ) );

            if ( $processed )
            {
                $type = self::SUCCESS;
                $message = "Arquivo importado com sucesso.";
            }
            else
            {
                $message = "Falha ao processar arquivo.";
            }
        }

        return log_and_redirect(
            channel: self::LOG_CHANNEL,
            route  : self::ROUTE,
            type   : $type,
            message: $message
        );
    }

    /**
     * @param Request $request
     *
     * @return BinaryFileResponse
     */
    public function exportOrders( Request $request ) : BinaryFileResponse
    {
        $files = Order::select( "file_name" )->distinct()->orderBy( "file_name", "DESC" )->pluck( "file_name" );

        $file = $request->query( "file", session( "mkt_file", $files->first() ) );
        $status = $request->query( "status", session( "mkt_status", "all" ) );
        $product_id = $request->query( "product_id", session( "mkt_product_id", "all" ) );

        $query = Order::select( [
            "strong_key",
            "mkt_id",
            "ord_id",
            "ord_status",
            "max_price",
            "ord_price",
            "ord_shipping",
            "ord_created_at",
            "product_id",
            "product_qntt",
            "product_name",
        ] )->where( "file_name", $file );

        if ( $status !== "all" )
            $query->where( "ord_status", $status );

        if ( $product_id !== "all" )
            $query->where( "product_id", $product_id );

        return export_csv_file( data: $query->get()->toArray(), file_name: $file );
    }

    /**
     * Cria o pedido no marketplace, marcado como 'pago', troca o status de pendente para processando.
     * Por conta dessa troca, dispara a ação do plugin, de criação do pedido na Fast Shop.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function orderCreate( Request $request ) : RedirectResponse
    {
        if ( ! $request->ord_id || ! $request->file_name )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "Dados inválidos."
            );
        }

        $store_id = config( key: "woocommerce.partners.".self::SELLER.".store_id" );

        $order = Order::where( "file_name", $request->file_name )
            ->where( "ord_id", $request->ord_id )
            ->whereIn( "ord_status", ["not_processed", "over_max"] )
            ->first();

        if ( ! $order )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$request->ord_id}: Pedido com status diferente de 'Não processado' ou 'Preço acima do limite'."
            );
        }

        do
        {
            $response = get_marketplace_product( channel: self::LOG_CHANNEL, order: $order );
        }
        while ( ! $response[ "success" ] && $response[ "code" ] === 502 );

        if ( ! $response[ "success" ] )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: $response[ "message" ]
            );
        }

        $product = $response[ "data" ];

        if ( $product[ "store" ]->id !== $store_id )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id}: Esse produto não é da Fast Shop."
            );
        }

        $fs_product_id = search_product_meta( metas: $product[ "meta_data" ] );

        if ( $fs_product_id === null )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id}: Produto sem ID Fast Shop."
            );
        }

        $order->shipping = format_postcode( $order->shipping );

        $freight = get_freight_vtex( product_id: $fs_product_id, postcode: $order->shipping[ "postcode" ] );

        if ( $freight === false )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id}: Falha ao consulta frete da Fast Shop. FCF1"
            );
        }

        $product_prices = get_product_prices( product_id: $fs_product_id, freight: $freight[ "data" ] );

        if ( $product_prices[ "delivery_price" ] === false )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id}: Falha ao consulta frete da Fast Shop. FCF2"
            );
        }

        $response = update_marketplace_product(
            channel   : self::LOG_CHANNEL,
            product_id: $order->product_id,
            data      : $product_prices
        );

        if ( ! $response[ "success" ] )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id}: ".$response[ "message" ]
            );
        }

        if ( (float) $product_prices[ "price" ] > $order->max_price )
        {
            $order->ord_status = "over_max";
            $order->save();

            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id}: Valor do produto acima do máximo."
            );
        }

        $wc_data = marketplace_new_order_prepare(
            order     : $order,
            product_id: $order->product_id,
            freight   : $delivery_price
        );

        $response = create_marketplace_order(
            channel : self::LOG_CHANNEL,
            data    : $wc_data,
            order_id: $order->ord_id
        );

        if ( ! $response[ "success" ] )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: $response[ "message" ]
            );
        }

        $wc_order = $response[ "data" ];
        $wc_order = json_decode( json_encode( $wc_order ), true );

        $created_at = str_replace( "T", " ", $wc_order[ "date_created" ] );
        $total = (float) $wc_order[ "total" ];
        $shipping = (float) $wc_order[ "shipping_total" ];
        $price = $total - $shipping;

        $order->mkt_id = $wc_order[ "id" ];
        $order->ord_status = "processed";
        $order->ord_price = $price;
        $order->ord_shipping = $shipping;
        $order->ord_created_at = $created_at;
        $order->save();

        return log_and_redirect(
            channel: self::LOG_CHANNEL,
            route  : self::ROUTE,
            type   : self::SUCCESS,
            message: "{$order->ord_id} #{$order->mkt_id}: Pedido criado com sucesso."
        );
    }

    /**
     * Executa a ação de aprovação na Fast Shop.
     * Manual igual a feito pelo admin do marketplace.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function orderApproval( Request $request ) : RedirectResponse
    {
        if ( ! $request->ord_id || ! $request->file_name )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "Dados inválidos."
            );
        }

        $order = Order::where( "file_name", $request->file_name )
            ->where( "ord_id", $request->ord_id )
            ->where( "ord_status", "processed" )
            ->first();

        if ( ! $order )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$request->ord_id}: Pedido com status diferente de 'Processado'."
            );
        }

        if ( ! $order->mkt_id )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$request->ord_id}: Pedido sem ID do marketplace, 'Mkt ID'."
            );
        }

        if ( ! $request->has( "mkt_verified" ) )
        {
            $response = get_marketplace_order( channel: self::LOG_CHANNEL, order_id: $order->mkt_id );

            if ( ! $response[ "success" ] )
            {
                return log_and_redirect(
                    channel: self::LOG_CHANNEL,
                    route  : self::ROUTE,
                    type   : self::ERROR,
                    message: $response[ "message" ]
                );
            }

            $wc_order = $response[ "data" ];

            $is_created = search_order_meta( metas: $wc_order[ "meta_data" ], key: "_fastshop_order_id" );

            if ( $is_created === null )
            {
                return log_and_redirect(
                    channel: self::LOG_CHANNEL,
                    route  : self::ROUTE,
                    type   : self::ERROR,
                    message: "{$order->ord_id}: Pedido ainda não foi criado na Fast Shop. Tente a opção 'atualizar'."
                );
            }
        }

        $config = config( "woocommerce.plugins" );
        $vtex = new VtexOrderController();
        $request = new Request();

        $request->headers->set( key: "seller", values: self::SELLER );
        $request->headers->set( key: "api-key", values: $config[ "key"   ] );
        $request->headers->set( key: "api-token", values: $config[ "token" ] );

        $response = $vtex->orderApproval( request: $request, order_id: $order->mkt_id );

        if ( $response->status() !== 200 )
        {
            $data = $response->getData();

            $order->ord_status = "fs_approval_error";
            $order->save();

            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id} #{$order->mkt_id}: {$data->message}"
            );
        }

        $order->ord_status = "approved";
        $order->save();

        // Create manual approval note
        create_order_note(
            channel : self::LOG_CHANNEL,
            order_id: $order->mkt_id,
            message : "Pedido aprovado manualmente na FS por: ".auth()->user()->name." (integração)"
        );

        return log_and_redirect(
            channel: self::LOG_CHANNEL,
            route  : self::ROUTE,
            type   : self::SUCCESS,
            message: "{$order->ord_id} #{$order->mkt_id}: Pedido aprovado com sucesso."
        );
    }

    /**
     * Cancela o pedido no marketplace, troca o status de processando para cancelado.
     * Por conta dessa troca, dispara a ação do plugin, de cancelamento do pedido na Fast Shop.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function orderCancel( Request $request ) : RedirectResponse
    {
        if ( ! $request->ord_id || ! $request->file_name )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "Dados inválidos."
            );
        }

        $order = Order::where( "file_name", $request->file_name )
            ->where( "ord_id", $request->ord_id )
            ->first();

        if ( ! $order )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$request->ord_id}: Pedido não encontrado."
            );
        }

        if ( $order->mkt_id )
        {
            $response = update_marketplace_order(
                channel : self::LOG_CHANNEL,
                order_id: $order->mkt_id,
                status  : "cancelled",
            );

            if ( ! $response[ "success" ] )
            {
                return log_and_redirect(
                    channel: self::LOG_CHANNEL,
                    route  : self::ROUTE,
                    type   : self::ERROR,
                    message: $response[ "message" ]
                );
            }
        }

        $order->ord_status = "canceled";
        $order->save();

        return log_and_redirect(
            channel: self::LOG_CHANNEL,
            route  : self::ROUTE,
            type   : self::SUCCESS,
            message: "{$order->ord_id} #{$order->mkt_id}: Pedido cancelado com sucesso."
        );
    }

    /**
     * Valida o status do pedido no marketplace.
     * Se não foi criado na Fast Shop, dispara a ação de criação.
     * Se não foi aprovado, dispara ação de aprovação.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function orderUpdate( Request $request ) : RedirectResponse
    {
        if ( ! $request->ord_id || ! $request->file_name )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "Dados inválidos."
            );
        }

        $order = Order::where( "file_name", $request->file_name )
            ->where( "ord_id", $request->ord_id )
            ->first();

        if ( ! $order->mkt_id )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: "{$order->ord_id} O pedido não foi criado no marketplace ainda."
            );
        }

        $response = get_marketplace_order( channel: self::LOG_CHANNEL, order_id: $order->mkt_id );

        if ( ! $response[ "success" ] )
        {
            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::ERROR,
                message: $order->ord_id." ".$response[ "message" ]
            );
        }

        $wc_order = $response[ "data" ];

        $is_created = search_order_meta( metas: $wc_order[ "meta_data" ], key: "_fastshop_order_id" );
        $is_approved = search_order_meta( metas: $wc_order[ "meta_data" ], key: "_fastshop_order_approved" );

        if ( $is_created === null )
        {
            $config = config( "woocommerce.plugins" );
            $vtex = new VtexOrderController();
            $request = new Request();

            $request->headers->set( key: "seller", values: self::SELLER );
            $request->headers->set( key: "api-key", values: $config[ "key"   ] );
            $request->headers->set( key: "api-token", values: $config[ "token" ] );

            $response = $vtex->orderCreation( request: $request, order_id: $order->mkt_id );

            if ( $response->status() !== 200 )
            {
                $data = $response->getData();

                $order->ord_status = "fs_create_error";
                $order->save();

                return log_and_redirect(
                    channel: self::LOG_CHANNEL,
                    route  : self::ROUTE,
                    type   : self::ERROR,
                    message: "{$order->ord_id} #{$order->mkt_id}: {$data->message} | Verifique as notas do pedido."
                );
            }

            $order->ord_status = "processed";
            $order->save();

            return log_and_redirect(
                channel: self::LOG_CHANNEL,
                route  : self::ROUTE,
                type   : self::SUCCESS,
                message: "{$order->ord_id} #{$order->mkt_id}: Pedido criado com sucesso."
            );
        }
        elseif ( $is_approved === null )
        {
            $order->ord_status = "processed";
            $order->save();

            $request->merge( ["mkt_verified" => true] );

            return $this->orderApproval( $request );
        }

        $order->ord_status = "approved";
        $order->save();

        return log_and_redirect(
            channel: self::LOG_CHANNEL,
            route  : self::ROUTE,
            type   : self::SUCCESS,
            message: "{$order->ord_id} #{$order->mkt_id}: Pedido atualizado com sucesso."
        );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param string  $order_id
     *
     * @return void
     */
    public function getFsOrder( Request $request, string $order_id ) : void
    {
        $response = vtex_get_order( "marketplace", "fast_shop", $order_id );

        dd( $response );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param string  $order_id
     *
     * @return void
     */
    public function getMktOrder( Request $request, string $order_id ) : void
    {
        try
        {
            $response = get_marketplace_order( channel: self::LOG_CHANNEL, order_id: $order_id );
        }
        catch ( Exception $e )
        {
            dd( $e->getMessage() );
        }

        dd( $response );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param string  $id
     *
     * @return RedirectResponse
     */
    public function redirectToMkt( Request $request, string $id ) : RedirectResponse
    {
        $prefix = config( key: "marketplace.url" );
        $url = "{$prefix}/wp-admin/post.php?post={$id}&action=edit";

        return redirect()->away( path: $url );
    }
}
