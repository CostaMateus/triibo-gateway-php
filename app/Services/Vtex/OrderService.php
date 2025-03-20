<?php

namespace App\Services\Vtex;

use App\Services\Vtex\ConnectorService;

class OrderService
{
    /**
     * Vtex Connector Service.
     *
     * @var     ConnectorService
     */
    private $connector;

    /**
     * Constructor.
     *
     * @param   ConnectorService    $connector
     */
    public function __construct( ConnectorService $connector )
    {
        $this->connector = $connector;
    }

    /**
     * Get Order.
     *
     * @see     https://developers.vtex.com/docs/api-reference/orders-api#get-/api/oms/pvt/orders/-orderId-
     *
     * @param   string  $order_id
     * @return  array
     */
    public function getOrder( string $order_id ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "getOrder" )
            ->buildUrl( api: "order-retrieve", )
            ->replaceUrl( data: [ "{orderId}" => "{$this->connector->affiliated_id}-{$order_id}" ] );

        return $this->connector->sendRequest( method: "get" );
    }

    /**
     * New Order.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/order-integration/orders
     *
     * @uses    $data must be result of app\Helpers\Vtex::vtex_new_order_prepare
     *
     * @param   array   $data   Order data in VTEX format.
     * @return  array
     */
    public function newOrder( array $data ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "newOrder" )
            ->uildUrl( api: "order-integration", param: "?affiliateId={$this->connector->affiliated_id}" );

        return $this->connector->sendRequest( method: "post", body: $data );
    }

    /**
     * Update Order Status.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#put-/api/order-integration/orders/status
     *
     * @uses    $data must be result of app\Helpers\Vtex::vtex_update_order
     *
     * @param   array   $data       Order data in VTEX format.
     * @return  array
     */
    public function updateOrderStatus( array $data ) : array
    {
        $this->connector
            ->setCallOrigin( origin : "updateOrderStatus" )
            ->buildUrl( api: "order-integration-status", param: "?an={$this->connector->account_name}" );

        return $this->connector->sendRequest( method: "put", body: $data );
    }

    /**
     * Cancel order.
     *
     * @param   string   $string
     * @return  array
     */
    public function cancelOrder( string $order_id ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "cancelOrder" )
            ->buildUrl( api: "order-integration-cancel", )
            ->replaceUrl( data: [ "{orderId}" => "{$this->connector->affiliated_id}-{$order_id}" ] );

        return $this->connector->sendRequest( method: "post" );
    }
}
