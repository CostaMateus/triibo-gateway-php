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
     * Registers the marketplace connector with VTEX.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/connector/register
     *
     * @return  array
     */
    public function registerMarketplaceConnector() : array
    {
        $account  = config( "vtex.{$this->connector->seller}.{$this->env}.account_name" );

        // Prepare the request body.
        $body     = [
            "displayName"          => "Marketplace Triibo",
            "categoryTreeEndPoint" => route( "api.marketplace.vtex.mapper.categories" ),
            "mappingEndPoint"      => route( "api.marketplace.vtex.mapper.mapping"    ),
            "properties"           => [ "allowsRemap" => true ],
        ];

        $this->connector
            ->setCallOrigin(
                origin  : "registerMarketplaceConnector"
            )
            ->buildUrl(
                api     : "category-register",
                account : "portal",
                param   : "?an={$account}"
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
     * @uses    $categories recovers marketplace categories in app\Helpers\Vtex::get_marketplace_categories, than treats response with app\Helpers\Vtex::vtex_format_categories
     *
     * @param   integer     $id
     * @param   array       $categories   Categories in VTEX format.
     * @return  array
     */
    public function sendCategoryMapping( int $id, array $categories ) : array
    {
        $this->connector
            ->setCallOrigin(
                origin  : "sendCategoryMapping"
            )
            ->buildUrl(
                api     : "category-marketplace",
                account : "portal",
            )
            ->replaceUrl(
                data    : [ "{id}" => $id ]
            );

        return $this->connector->sendRequest( method: "post", body: [ "categories" => $categories ] );
    }
}
