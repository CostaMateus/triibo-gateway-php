<?php

namespace App\Services\Vtex;

use App\Services\Vtex\ConnectorService;

class OrderService
{
    /**
     * Env (e.g., local, production) as a string.
     *
     * @var     string
     */
    private $env;

    /**
     * Vtex Connector Service
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
        $this->env       = config( "vtex.{$this->connector->seller}.env" );
    }

    /**
     * New Order.
     *
     * @refer   https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/order-integration/orders
     *
     * @param   array   $data   Order data in VTEX format.
     * @return  array
     */
    public function newOrder( array $data )
    {
        $affiliated_id = config( "vtex.{$this->connector->seller}.{$this->env}.affiliated_id" );

        $this->connector
            ->setCallOrigin(
                origin      : "newOrder"
            )
            ->buildUrl(
                api         : "order-integration",
                account     : null,
                environment : null,
                param       : "?affiliateId={$affiliated_id}"
            );

        return $this->connector->sendRequest( method: "post", body: $data );
    }

    /**
     * Update Order Status.
     *
     * @refer   https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#put-/api/order-integration/orders/status
     *
     * @param   string  $account    The name of the VTEX account.
     * @param   array   $data       Order data in VTEX format.
     * @return  array
     */
    public function updateOrderStatus( string $account, array $data )
    {
        $this->connector
            ->setCallOrigin(
                origin      : "updateOrderStatus"
            )
            ->buildUrl(
                api         : "order-integration-status",
                account     : null,
                environment : null,
                param       : "?an={$account}"
            );

        return $this->connector->sendRequest( method: "put", body: $data );
    }
}
