<?php

declare(strict_types = 1);

namespace App\Services\Vtex;

final class CategoryService
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
     * Registers the marketplace connector with VTEX.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/connector/register
     *
     * @return array
     */
    public function registerMarketplaceConnector() : array
    {
        // Prepare the request body.
        $body = [
            "displayName" => "Marketplace Triibo",
            "categoryTreeEndPoint" => route( name: "api.marketplace.vtex.mapper.categories" ),
            "mappingEndPoint" => route( name: "api.marketplace.vtex.mapper.mapping" ),
            "CategoryTreeProcessingNotificationEndpoint" => route( name: "api.marketplace.vtex.mapper.notification" ),
            "properties" => [ "allowsRemap" => false ],
        ];

        $this->connector
            ->setCallOrigin( origin: "registerMarketplaceConnector" )
            ->buildUrl(
                api    : "category-register",
                account: "portal",
                param  : "?an={$this->connector->account_name}"
            );

        return $this->connector->sendRequest( method: "post", body: $body );
    }

    /**
     * Sends marketplace category mapping to VTEX.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/categories/marketplace/-id-
     *
     * @uses    $categories recovers marketplace categories in app\Helpers\Vtex::get_marketplace_categories, than treats response with app\Helpers\Vtex::vtex_format_categories
     *
     * @param string $id
     * @param array  $categories categories in VTEX format
     *
     * @return array
     */
    public function sendCategoryMapping( string $id, array $categories ) : array
    {
        $this->connector
            ->setCallOrigin( origin: "sendCategoryMapping" )
            ->buildUrl( api: "category-marketplace", account: "portal" )
            ->replaceUrl( data: [ "{id}" => $id ] );

        return $this->connector->sendRequest( method: "post", body: ["categories" => $categories] );
    }
}
