<?php

namespace App\Http\Controllers\Vtex;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class OrderController extends Controller
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
        return response()->json( [ "success" => true ], 200 );
    }

}
