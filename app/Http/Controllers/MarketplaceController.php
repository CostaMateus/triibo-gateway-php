<?php

namespace App\Http\Controllers;

use App\Traits\OrderFieldsTrait;
use App\Models\Marketplace\Order;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\{ Request, RedirectResponse };

class MarketplaceController extends Controller
{
    use OrderFieldsTrait;

    /**
     * Show the marketplace orders.
     *
     * @return  Renderable
     */
    public function orders( Request $request ) : Renderable
    {
        $files  = Order::select( "file_name" )->distinct()->orderBy( "file_name", "DESC" )->pluck( "file_name" );
        $file   = $request->query( "file" ) ?? $files->first();

        $status = $request->query( "status" ) ?? "all";

        $query  = Order::where( "file_name", $file );

        if ( $status != "all" )
            $query->where( "ord_status", $status );

        $orders  = $query->get()->map( function ( $order )
        {
            $order->billing  = $this->reorganizeFields( $order->billing,  $this->getBillingFieldsOrder()  );
            $order->shipping = $this->reorganizeFields( $order->shipping, $this->getShippingFieldsOrder() );
            $order->status   = $this->getOrderStatus( $order->ord_status );

            return $order;
        } );

        return view( "marketplace.orders", compact( "file", "files", "orders", "status" ) );
    }

    /**
     * Show the marketplace orders.
     *
     * @return  RedirectResponse
     */
    public function importOrders( Request $request ) : RedirectResponse
    {
        $request->validate( [ "csv_file" => "required|file|mimes:csv,txt|max:2048" ] );

        $message = [ "error" => "Arquivo inválido." ];

        if ( $request->file( "csv_file" )->isValid() )
        {
            $processed = process_csv_file( $request->file( "csv_file" ) );

            if ( $processed )
                $message = [ "success" => "Arquivo importado com sucesso." ];

            else
                $message = [ "error"   => "Falha ao processar arquivo."    ];
        }

        return redirect()->route( "mkt.orders" )->with( $message );
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
            return redirect()->route( "mkt.orders" )->with( [ "error" => "Dados inválidos." ] );

        $message = [ "success" => "Pedido criado com sucesso." ];

        return redirect()->route( "mkt.orders" )->with( $message );
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
            return redirect()->route( "mkt.orders" )->with( [ "error" => "Dados inválidos." ] );

        $message = [ "success" => "Pedido aprovado com sucesso." ];

        return redirect()->route( "mkt.orders" )->with( $message );
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
            return redirect()->route( "mkt.orders" )->with( [ "error" => "Dados inválidos." ] );

        $message = [ "success" => "Pedido cancelado com sucesso." ];

        return redirect()->route( "mkt.orders" )->with( $message );
    }
}
