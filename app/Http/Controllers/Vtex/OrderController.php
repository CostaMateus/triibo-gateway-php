<?php

namespace App\Http\Controllers\Vtex;

use Illuminate\Http\Request;

class OrderController extends ConnectorController
{
    /**
     * https://{accountName}.{environment}.com.br/api/order-integration/orders
     * https://{accountName}.{environment}.com.br/api/order-integration/orders/status
     * https://{accountName}.{environment}.com.br/api/checkout/pub/orderForms/simulation
     * https://{accountName}.{environment}.com.br/api/fulfillment/pvt/orders
     * https://{accountName}.{environment}.com.br/api/fulfillment/pvt/orders/{orderId}/fulfill
     */

    /**
     * Seller.
     *
     * @var     string
     */
    private $seller;

    /**
     * Env (e.g., local, production) as a string.
     *
     * @var     string
     */
    private $env;

    /**
     * Connector controller.
     *
     * @var     ConnectorController
     */
    private $vtex;

    public function __construct( string $seller )
    {
        $this->seller = $seller;
        $this->env    = config( key: "vtex.{$this->seller}.env" );
        $this->vtex   = new ConnectorController( $seller );
    }

    /**
     * Undocumented function
     *
     * @refer   https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/order-integration/orders
     *
     * @return  void
     */
    public function newOrderIntegration( array $data )
    {
        $affiliated_id   = config( key: "vtex.{$this->seller}.{$this->env}.affiliated_id" );

        $order_data      = new_vtex_order( $data );

        $this->vtex->logMessage();
        $this->vtex->logNotify();
        $this->vtex->origin( origin: "newOrderIntegration" );
        $this->vtex->url( api: "order-integration", param: "?affiliateId={$affiliated_id}" );

        [ $code, $data ] = $this->vtex->call( method: "post", body: $order_data );

        // Return JSON response with appropriate status code.
        return response()->json( data: $data, status: $code );
    }

    /**
     * Undocumented function
     *
     * @refer   https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#put-/api/order-integration/orders/status
     *
     * @return  void
     */
    public function updateOrderStatus()
    {

    }
}
