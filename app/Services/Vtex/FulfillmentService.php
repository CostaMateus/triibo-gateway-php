<?php

namespace App\Services\Vtex;

use Throwable;
use App\Services\Vtex\ConnectorService;

class FulfillmentService
{
    /**
     * Vtex Connector Service.
     *
     * @var     ConnectorService
     */
    private $connector;

    /**
     * Env (e.g., local, production) as a string.
     *
     * @var     string
     */
    private $env;

    /**
     * Constructor.
     *
     * @param   ConnectorService    $connector
     */
    public function __construct( ConnectorService $connector )
    {
        $this->connector = $connector;
        $this->env       = config( "vtex.{$this->connector->seller}.env" );
    }

    /**
     * Simulates the process of creating a new order by sending a request to the order integration API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/checkout/pub/orderForms/simulation
     *
     * @param   array   $data  The data to be sent in the request body.
     * @return  array
     */
    public function simulate( array $data )
    {
        $affiliated_id = config( "vtex.{$this->connector->seller}.{$this->env}.affiliated_id" );
        $sales_channel = config( "vtex.{$this->connector->seller}.{$this->env}.sales_channel" );

        $this->connector
            ->setCallOrigin(
                origin      : "simulate"
            )
            ->buildUrl(
                api         : "fulfillment-simulation",
                account     : null,
                environment : null,
                param       : "?affiliateId={$affiliated_id}&sc={$sales_channel}"
            );

        return $this->connector->sendRequest( method: "post", body: $data );
    }

    /**
     * Handles the fulfillment of an order by sending the order data to the fulfillment API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/fulfillment/pvt/orders
     *
     * @param   array   $data   Order data in VTEX format.
     * @return  array
     */
    public function fulfillmentOrder( array $data )
    {
        $affiliated_id = config( "vtex.{$this->connector->seller}.{$this->env}.affiliated_id" );
        $sales_channel = config( "vtex.{$this->connector->seller}.{$this->env}.sales_channel" );

        $this->connector
            ->setCallOrigin(
                origin      : "fulfillmentOrder"
            )
            ->buildUrl(
                api         : "fulfillment-order",
                account     : null,
                environment : null,
                param       : "?affiliateId={$affiliated_id}&sc={$sales_channel}"
            );

        return $this->connector->sendRequest( method: "post", body: $data );
    }

    /**
     * Dispatches an order by sending a request to the fulfillment dispatch API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/fulfillment/pvt/orders/-orderId-/fulfill
     *
     * @param   string  $order_id  The marketplace order id to be dispatched.
     * @return  array              The response from the API call.
     */
    public function dispatchOrder( string $order_id )
    {
        $affiliated_id = config( "vtex.{$this->connector->seller}.{$this->env}.affiliated_id" );
        $sales_channel = config( "vtex.{$this->connector->seller}.{$this->env}.sales_channel" );

        $this->connector
            ->setCallOrigin(
                origin      : "dispatchOrder"
            )
            ->buildUrl(
                api         : "fulfillment-dispatch",
                account     : null,
                environment : null,
                param       : "?affiliateId={$affiliated_id}&sc={$sales_channel}",
            );

        $this->connector->replaceUrl( data: [ "{orderId}" => "{$affiliated_id}-{$order_id}" ] );

        return $this->connector->sendRequest( method: "post", body: [ "marketplaceOrderId" => $order_id ] );
    }
}
