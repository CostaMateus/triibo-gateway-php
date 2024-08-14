<?php

namespace App\Services\Vtex;

use Throwable;
use App\Services\Vtex\ConnectorService;

class CategoryService
{
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
    public function __construct( ConnectorService $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Registers the marketplace connector with VTEX.
     *
     * @refer   https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/connector/register
     *
     * @param   string  $account
     * @return  array
     */
    public function registerMarketplaceConnector( string $account )
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
                environment : "vtexcommercestable",
                param       : "?an={$account}"
            );

        $response = $this->connector->sendRequest( method: "post", body: $body );

        save_file( name: "Vtex-CategoryService-registerMarketplaceConnector", data: $response );

        return $response;
    }

    /**
     * Sends marketplace category mapping to VTEX.
     *
     * @refer   https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/categories/marketplace/-id-
     *
     * @param   integer     $id
     * @param   array       $categories   Categories in VTEX format.
     * @return  void
     */
    public function sendCategoryMapping( int $id, array $categories )
    {
        try
        {
            // TODO remover
            // // Fetch all marketplace categories.
            // $categories = get_marketplace_categories();

            // // Format categories for VTEX integration.
            // $body       = [ "categories" => format_categories_to_vtex( $categories ) ];

            $this->connector
                ->setCallOrigin(
                    origin      : "sendCategoryMapping"
                )
                ->buildUrl(
                    api         : "category-marketplace",
                    account     : "portal",
                    environment : "vtexcommercestable",
                    param       : $id
                );

            return $this->connector->sendRequest( method: "post", body: [ "categories" => $categories ] );
        }
        catch ( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "sendMapping",
                type    : "Throwable",
                e       : $e
            );

            return [
                500,
                [
                    "error_code" => $e->getCode(),
                    "message"    => "Unexpected error",
                    "data"       => null
                ]
            ];
        }
    }
}
