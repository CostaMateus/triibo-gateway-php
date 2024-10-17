<?php

namespace App\Http\Controllers\Vtex;

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

}
