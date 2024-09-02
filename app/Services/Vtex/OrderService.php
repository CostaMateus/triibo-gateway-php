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
     * New Order.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/order-integration/orders
     *
     * @uses    $data must be result of app\Helpers\Vtex::vtex_new_order
     *
     * @param   array   $data   Order data in VTEX format.
     * @return  array
     */
    public function newOrder( array $data ) : array
    {
        $affiliated_id = config( "vtex.{$this->connector->seller}.{$this->env}.affiliated_id" );

        $this->connector
            ->setCallOrigin(
                origin : "newOrder"
            )
            ->buildUrl(
                api    : "order-integration",
                param  : "?affiliateId={$affiliated_id}"
            );

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
        $account = config( "vtex.{$this->connector->seller}.{$this->env}.account_name" );

        $this->connector
            ->setCallOrigin(
                origin : "updateOrderStatus"
            )
            ->buildUrl(
                api    : "order-integration-status",
                param  : "?an={$account}"
            );

        return $this->connector->sendRequest( method: "put", body: $data );
    }
}
