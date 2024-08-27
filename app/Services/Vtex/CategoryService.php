<?php

namespace App\Services\Vtex;

use Throwable;
use App\Services\Vtex\ConnectorService;

class CategoryService
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
     * Registers the marketplace connector with VTEX.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/connector/register
     *
     * @param   string  $account    The name of the VTEX account.
     * @return  array
     */
    public function registerMarketplaceConnector( string $account ) : array
    {
        // Prepare the request body.
        $body     = [
            "displayName"          => "Marketplace Triibo",
            "categoryTreeEndPoint" => route( "api.marketplace.vtex.mapper.categories" ),
            "mappingEndPoint"      => route( "api.marketplace.vtex.mapper.mapping"    ),
            "properties"           => [ "allowsRemap" => true ],
        ];

        $this->connector
            ->setCallOrigin(
                origin      : "registerMarketplaceConnector"
            )
            ->buildUrl(
                api         : "category-register",
                account     : "portal",
                environment : null,
                param       : "?an={$account}"
            );

        $response = $this->connector->sendRequest( method: "post", body: $body );

        save_file( name: "Vtex-CategoryService-registerMarketplaceConnector", data: $response );

        return $response;
    }

    /**
     * Sends marketplace category mapping to VTEX.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/categories/marketplace/-id-
     *
     * @uses    $data must be result of app\Helpers\Vtex::vtex_format_categories
     *
     * @param   integer     $id
     * @param   array       $categories   Categories in VTEX format.
     * @return  array
     */
    public function sendCategoryMapping( int $id, array $categories ) : array
    {
        // TODO remover
        // // Fetch all marketplace categories.
        // $categories = get_marketplace_categories();

        // // Format categories for VTEX integration.
        // $body       = [ "categories" => vtex_format_categories( $categories ) ];

        $this->connector
            ->setCallOrigin(
                origin      : "sendCategoryMapping"
            )
            ->buildUrl(
                api         : "category-marketplace",
                account     : "portal",
                environment : null,
                param       : null
            )
            ->replaceUrl(
                data        : [ "{id}" => $id ]
            );

        return $this->connector->sendRequest( method: "post", body: [ "categories" => $categories ] );
    }
}
