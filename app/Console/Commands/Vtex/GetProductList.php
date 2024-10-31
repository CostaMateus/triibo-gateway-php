<?php

namespace App\Console\Commands\Vtex;

use Throwable;
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
    protected const LOG_CHANNEL = "marketplace";

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

        $connector                = new ConnectorService( $seller );
        $connector->enableLogNotify( false );

        $this->product_service    = new ProductService( $connector );
        $this->simulation_service = new FulfillmentService( $connector );
    }

    /**
     * Execute the console command.
     *
     * @return  void
     */
    public function handle() : void
    {
        $skus = $this->getAllSkus();

        if ( count( $skus ) == 0 )
        {
            $this->error( "Error on load skus from connector" );
            return;
        }

        $this->info( "Load: " . count( $skus ) );

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

        try
        {
            foreach ( $skus as $sku )
            {
                $context  = $this->getSkuContext( $sku );
                $data     = $this->getSkuData( $sku );

                if ( is_null( $data ) || is_null( $context ) )
                {
                    $s = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                    $this->error( "SKU: {$s} | Empty or disabled" );

                    $fails[] = $sku;
                    continue;
                }

                $simulate = $this->getSimulate( $data[ "Id" ] );

                if ( is_null( $simulate ) )
                {
                    $s = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                    $this->error( "SKU: {$s} | Empty or disabled" );

                    $fails[] = $sku;
                    continue;
                }

                $product  = [
                    "data"     => $data,
                    "context"  => $context,
                    "simulate" => $simulate,
                ];

                $s = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                $this->info( "SKU: {$s}" );

                $products[ $sku ] = $product;

                if ( count( $products ) == 50 )
                {
                    $wc_products = $this->translateProducts( $products );

                    if ( is_array( $wc_products ) )
                        $this->sendToMarketplace( $wc_products );

                    $this->info( "=================== Reset ===================" );

                    $products = [];
                }
            }
        }
        catch ( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "getAllProducts",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );
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
        try
        {
            $response = $this->product_service->getSkuData( $sku );

            if ( $response[ 0 ] != 200 )
                return null;

            $product  = $response[ 1 ][ "data" ];

            if ( $product[ "IsActive" ] == false )
                return null;

            return $product;
        }
        catch( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "getSkuData",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @param   integer     $sku
     * @return  array|null
     */
    private function getSkuContext( int $sku ) : array|null
    {
        try
        {
            $response = $this->product_service->getSkuContext( $sku );

            if ( $response[ 0 ] != 200 )
                return null;

            $product  = $response[ 1 ][ "data" ];

            if ( $product[ "IsActive" ] == false && $product[ "IsProductActive" ] == false )
                return null;

            if ( !in_array( $this->sale_channel, $product[ "SalesChannels" ] ) )
                return null;

            return $product;
        }
        catch( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "getSkuContext",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @param   int     $id
     * @return  array|null
     */
    private function getSimulate( int $id ) : array|null
    {
        try
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
        catch ( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "getSimulate",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @param   array   $products
     * @return  array|null
     */
    private function translateProducts( array $products ) : array|null
    {
        try
        {
            $this->info( "Translate to marketplace: " . count( $products ) );

            $wc_data = [];

            foreach( $products as $product )
            {
                $this->info( "Product: " . $product[ "data" ][ "Name" ] );

                $wc_data[] = vtex_product_to_mkt( $product[ "context" ], $product[ "simulate" ] );
            }

            return $wc_data;
        }
        catch( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "translateProducts",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @param   array   $products
     * @return  void
     */
    private function sendToMarketplace( array $products ) : void
    {
        try
        {
            $this->info( "Send to marketplace" );

            Product::batch( [ "create" => $products ] );

            $this->info( "Sent to marketplace successfully" );
        }
        catch ( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "sendToMarketplace",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            save_file( name: "vtex-fails-products", data: $products );
        }
    }
}
