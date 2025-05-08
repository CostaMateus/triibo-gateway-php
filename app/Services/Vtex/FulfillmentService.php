<?php

namespace App\Services\Vtex;

class FulfillmentService
{
    /**
     * Vtex Connector Service.
     *
     * @var ConnectorService
     */
    private $connector;

    /**
     * Constructor.
     *
     * @param ConnectorService $connector
     */
    public function __construct( ConnectorService $connector )
    {
        $this->connector = $connector;
    }

    /**
     * Simulates the process of creating a new order by sending a request to the order integration API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/checkout/pub/orderForms/simulation
     *
     * @param array $data the data to be sent in the request body
     *
     * @return array
     */
    public function simulate( array $data ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "simulate" )
            ->buildUrl( api: "fulfillment-simulation", param: "?affiliateId={$this->connector->affiliated_id}&sc={$this->connector->sales_channel}" );

        return $this->connector->sendRequest( method: "post", body: $data );
    }

    /**
     * Handles the fulfillment of an order by sending the order data to the fulfillment API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/fulfillment/pvt/orders
     *
     * @param array $data order data in VTEX format
     *
     * @return array
     */
    public function fulfillmentOrder( array $data ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "fulfillmentOrder" )
            ->buildUrl( api: "fulfillment-order", param: "?affiliateId={$this->connector->affiliated_id}&sc={$this->connector->sales_channel}" );

        return $this->connector->sendRequest( method: "post", body: $data );
    }

    /**
     * Dispatches an order by sending a request to the fulfillment dispatch API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/fulfillment/pvt/orders/-orderId-/fulfill
     *
     * @param string $order_id the marketplace order id to be dispatched
     *
     * @return array the response from the API call
     */
    public function dispatchOrder( string $order_id ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "dispatchOrder" )
            ->buildUrl( api: "fulfillment-dispatch", param: "?affiliateId={$this->connector->affiliated_id}&sc={$this->connector->sales_channel}" )
            ->replaceUrl( data: ["{orderId}" => "{$this->connector->affiliated_id}-{$order_id}"] );

        return $this->connector->sendRequest( method: "post", body: ["marketplaceOrderId" => $order_id] );
    }
}
