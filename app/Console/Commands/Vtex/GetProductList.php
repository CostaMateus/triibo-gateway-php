<?php

namespace App\Console\Commands\Vtex;

use Illuminate\Console\Command;
use App\Services\Vtex\ProductService;
use App\Services\Vtex\ConnectorService;
use App\Services\Vtex\FulfillmentService;
use Codexshaper\WooCommerce\Facades\Product;

class GetProductList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "vtex:get-product-list";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get product list";

    protected $sale_channel;
    protected $product_service;
    protected $simulation_service;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $seller                   = "fast_shop";
        $env                      = config( "vtex.partners.{$seller}.env" );
        $this->sale_channel       = config( "vtex.partners.{$seller}.{$env}.sales_channel" );

        // 1.
        // 2.
        $connector                = new ConnectorService( $seller );
        $connector->enableLogNotify( false );

        $this->product_service    = new ProductService( $connector );
        $this->simulation_service = new FulfillmentService( $connector );
    }

    /**
     * Execute the console command.
     *
     * @see     https://developers.vtex.com/docs/guides/external-marketplace-integration-product-load
     *
     * @return  void
     */
    public function handle() : void
    {
        // 3.
        $skus = $this->getAllSkus();

        if ( count( $skus ) == 0 )
        {
            $this->error( "Error on load skus from connector" );
            return;
        }

        // 4.
        $this->getAllProducts( $skus );
    }

    /**
     * Get all skus list
     *
     * @return  array
     */
    private function getAllSkus() : array
    {
        $page      = 1;
        $skus_list = [];
        $count     = 500;

        do
        {
            $response = $this->product_service->initialSkuLoad( $page );

            if ( $response[ 0 ] != 200 )
            {
                $this->error( "Error on load skus from connector" );

                $count = 0;

                return [];
            }

            $count     = count( $response[ 1 ][ "data" ] );
            $skus_list = array_merge( $skus_list, $response[ 1 ][ "data" ] );

            $this->info( "Load {$count}" );

            $page++;
        }
        while ( $count == 500 );

        $this->info( "List of skus, loaded successfully" );

        return $skus_list;
    }

    /**
     * Get all products
     *
     * @param   array   $skus
     * @return  void
     */
    private function getAllProducts( array $skus ) : void
    {
        $fails    = [];
        $products = [];

        foreach ( $skus as $sku )
        {
            // 4 a.
            $context  = $this->getSkuContext( $sku );
            $data     = $this->getSkuData( $sku );

            if ( is_null( $data ) || is_null( $context ) )
            {
                $fails[] = $sku;
                continue;
            }

            // 4 g.
            $simulate = $this->getSimulate( $data[ "Id" ] );

            $product  = [
                "data"     => $data,
                "context"  => $context,
                "simulate" => $simulate,
            ];

            $this->info( $sku );

            $products[ $sku ] = $product;

            if ( count( $products ) == 5 )
            {
                $wc_products = $this->translateProducts( $products );

                $this->sendToMarketplace( $wc_products );

                $products = [];
            }
        }

        save_file( name: "vtex-fails-sku", data: $fails );
    }

    /**
     * Undocumented function
     *
     * @param   integer     $sku
     * @return  array|null
     */
    private function getSkuData( int $sku ) : array|null
    {
        $response = $this->product_service->getSkuData( $sku );

        // save_file( name: "vtex-sku-data", data: $response );

        if ( $response[ 0 ] != 200 )
            return null;

        $product  = $response[ 1 ][ "data" ];

        if ( $product[ "IsActive" ] == false )
            return null;

        return $product;
    }

    /**
     * Undocumented function
     *
     * @param   integer     $sku
     * @return  array|null
     */
    private function getSkuContext( int $sku ) : array|null
    {
        $response = $this->product_service->getSkuContext( $sku );

        if ( $response[ 0 ] != 200 )
            return null;

        $product  = $response[ 1 ][ "data" ];

        // 4 b. && 4 d.
        if ( $product[ "IsActive" ] == false && $product[ "IsProductActive" ] == false )
            return null;

        // 4 c.
        if ( !in_array( $this->sale_channel, $product[ "SalesChannels" ] ) )
            return null;

        return $product;
    }

    /**
     * Undocumented function
     *
     * @param   int     $id
     * @return  array|null
     */
    private function getSimulate( int $id ) : array|null
    {
        $data = [
            "items" => [
                [
                    "id"       => $id,
                    "quantity" => 1,
                    "seller"   => "1"
                ]
            ],
            "isCheckedIn" => false,
            "country"     => "BRA",
            "selectedSla" => "Normal",
            // "postalCode"  => "01201030",
        ];

        $response = $this->simulation_service->simulate( $data );

        if ( $response[ 0 ] != 200 )
            return null;

        return array_filter(
            $response[ 1 ][ "data" ],
            function( $value, $key )
            {
                return $value !== null &&
                        $value !== [] &&
                        $key !== "paymentData" &&
                        $key !== "purchaseConditions" &&
                        $key !== "ratesAndBenefitsData";
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Undocumented function
     *
     * @param   array   $products
     * @return  array
     */
    private function translateProducts( array $products ) : array
    {
        $this->info( "Translate to marketplace" );

        $wc_data = [];

        foreach( $products as $product )
        {
            $this->info( "Product: " . $product[ "data" ][ "Name" ] );

            $wc_data[] = vtex_product_to_mkt( $product[ "context" ], $product[ "simulate" ] );
        }

        return $wc_data;
    }

    /**
     * Undocumented function
     *
     * @param   array   $products
     * @return  void
     */
    private function sendToMarketplace( array $products ) : void
    {
        $this->info( "Send to marketplace" );

        try
        {
            Product::batch( [ "create" => $products ] );

            $this->info( "Sent to marketplace successfully" );
        }
        catch ( Exception $e )
        {
            dump( $e->getMessage() );
        }
    }
}
