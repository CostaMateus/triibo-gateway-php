<?php

namespace App\Services\Vtex;

use App\Services\Vtex\ConnectorService;

class ProductService
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
        $this->env       = config( "vtex.partners.{$this->connector->seller}.env" );
    }

    /**
     * Fetches a list of SKUs from the VTEX API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog_system/pvt/sku/stockkeepingunitidsbysaleschannel
     *
     * @param   int     $page   The page number to fetch. Defaults to 1.
     * @return  array
     */
    public function initialSkuLoad( int $page = 1 ) : array
    {
        $page_size     = 500;
        $only_assigned = true;
        $sales_channel = config( "vtex.partners.{$this->connector->seller}.{$this->env}.sales_channel" );

        $this->connector
            ->setCallOrigin(
                origin : "initialSkuLoad"
            )
            ->buildUrl(
                api    : "all-skus-list",
                param  : "?onlyAssigned={$only_assigned}&sc={$sales_channel}&pageSize={$page_size}&page={$page}"
            );

        return $this->connector->sendRequest( method: "get" );
    }

    /**
     * Fetches detailed information for a specific SKU from the VTEX API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog/pvt/stockkeepingunit/-skuId-
     *
     * @param   string  $sku    The SKU identifier.
     * @return  array
     */
    public function getSku( string $sku ) : array
    {
        $this->connector
            ->setCallOrigin(
                origin : "getSku"
            )
            ->buildUrl(
                api    : "get-detail-sku",
            )
            ->replaceUrl(
                data   : [ "{skuId}" => $sku ]
            );

        return $this->connector->sendRequest( method: "get" );
    }

    /**
     * Fetches detailed information for a specific product ID from the VTEX API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog_system/pvt/products/productget/-productId-
     *
     * @param   int     $product_id     The product ID.
     * @return  array
     */
    public function getProduct( int $product_id ) : array
    {
        $account = config( "vtex.partners.{$this->connector->seller}.{$this->env}.account" );

        $this->connector
            ->setCallOrigin(
                origin : "getProduct"
            )
            ->buildUrl(
                api    : "get-detail-product",
                param  : "?an={$account}"
            )
            ->replaceUrl(
                data   : [ "{productId}" => $product_id ]
            );

        return $this->connector->sendRequest( method: "get" );
    }
}
