<?php

namespace App\Services\Vtex;

class ProductService
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
     * Fetches a list of SKUs from the VTEX API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog_system/pvt/sku/stockkeepingunitidsbysaleschannel
     *
     * @param int $page The page number to fetch. Defaults to 1.
     *
     * @return array
     */
    public function initialSkuLoad( int $page = 1 ) : array
    {
        $page_size = 500;
        $only_assigned = true;

        $this->connector
            ->setCallOrigin( origin: "initialSkuLoad" )
            ->buildUrl( api: "all-skus-list", param: "?onlyAssigned={$only_assigned}&sc={$this->connector->sales_channel}&pageSize={$page_size}&page={$page}" );

        return $this->connector->sendRequest( method: "get" );
    }

    /**
     * Fetches detailed information for a specific SKU from the VTEX API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog_system/pvt/sku/stockkeepingunitbyid/-skuId-
     *
     * @param string $sku the SKU identifier
     *
     * @return array
     */
    public function getSkuContext( string $sku ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "getSkuContext" )
            ->buildUrl( api: "get-sku-context" )
            ->replaceUrl( data: ["{skuId}" => $sku] );

        return $this->connector->sendRequest( method: "get" );
    }

    /**
     * Fetches detailed information for a specific SKU from the VTEX API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog/pvt/stockkeepingunit/-skuId-
     *
     * @param string $sku
     *
     * @return array
     */
    public function getSkuData( string $sku ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "getSkuData" )
            ->buildUrl( api: "get-sku-data" )
            ->replaceUrl( data: ["{skuId}" => $sku] );

        return $this->connector->sendRequest( method: "get" );
    }

    /**
     * Fetches detailed information for a specific product ID from the VTEX API.
     *
     * @see     https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog_system/pvt/products/productget/-productId-
     *
     * @param int $product_id the product ID
     *
     * @return array
     */
    public function getProduct( int $product_id ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "getProduct" )
            ->buildUrl( api: "get-detail-product", param: "?an={$this->connector->account_name}" )
            ->replaceUrl( data: ["{productId}" => $product_id] );

        return $this->connector->sendRequest( method: "get" );
    }
}
