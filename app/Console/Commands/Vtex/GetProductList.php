<?php

namespace App\Console\Commands\Vtex;

use Throwable;
use Illuminate\Console\Command;
use App\Services\Vtex\ProductService;
use App\Services\Vtex\ConnectorService;
use Illuminate\Support\Facades\Storage;
use App\Services\Vtex\FulfillmentService;
use Codexshaper\WooCommerce\Facades\Product;

class GetProductList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var     string
     */
    protected $signature   = "vtex:get-product-list";

    /**
     * The console command description.
     *
     * @var     string
     */
    protected $description = "Get product list";

    /**
     * @var     string
     */
    protected $prefix      = "FS:";

    /**
     * @var     string
     */
    protected $sale_channel;

    /**
     * @var     ProductService
     */
    protected $product_service;

    /**
     * @var     FulfillmentService
     */
    protected $simulation_service;

    /**
     * @var     string
     */
    protected const LOG_CHANNEL = "fastshop";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $seller                   = "fast_shop";
        $partners                 = config( "vtex.partners" );
        $env                      = $partners[ $seller ][ "env" ];

        $this->sale_channel       = $partners[ $seller ][ $env ][ "sales_channel" ];

        $connector                = new ConnectorService( $seller );
        $connector->enableLogNotify( false );

        $this->product_service    = new ProductService( $connector );
        $this->simulation_service = new FulfillmentService( $connector );
    }

    /**
     * Log message
     *
     * @param   string  $message
     * @param   string  $type
     * @return  void
     */
    private function log_message( string $message, string $type = "info" ) : void
    {
        $message = "{$this->prefix} {$message}";

        log_message( channel: self::LOG_CHANNEL, message: $message, level: $type );

        $this->$type( string: $message );
    }

    /**
     * Execute the console command.
     *
     * @return  int
     */
    public function handle() : int
    {
        $start = now();
        $this->log_message( message: "Starting process" );

        $skus  = $this->getAllSkus();

        if ( count( $skus ) == 0 )
        {
            $this->log_message( message: "Error on load skus from connector", type: "error" );
            return Command::FAILURE;
        }

        // $skus = Storage::get( "vtex/skus.json" );
        // $skus = json_decode( $skus, true ); // 17905 | 15-03-2025

        $this->log_message( message: "Load: " . count( $skus ) );

        $this->getAllProducts( skus: $skus );

        $this->log_message( message: "Finished process..." );

        $diff    = $start->diffInMinutes( date: now() );

        $this->log_message( message: "Runtime {$diff} minutes..." );

        return Command::SUCCESS;
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
            $response = $this->product_service->initialSkuLoad( page: $page );

            if ( $response[ 0 ] != 200 )
            {
                $this->log_message( message: "Error on load skus from connector", type: "error" );

                $count = 0;

                return [];
            }

            $count     = count( $response[ 1 ][ "data" ] );
            $skus_list = array_merge( $skus_list, $response[ 1 ][ "data" ] );

            $this->log_message( message: "Load {$count} | page {$page}" );

            $page++;
        }
        while ( $count == 500 );

        $this->log_message( message: "List of skus, loaded successfully" );

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
                // if ( $sku != 24 )
                //     continue;

                $data     = $this->getSkuData( sku: $sku );
                $context  = $this->getSkuContext( sku: $sku );

                // No product data
                if ( is_null( $data ) || is_null( $context ) )
                {
                    $s       = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                    $this->log_message( message: "SKU: {$s} | Empty or disabled", type: "error" );

                    $fails[] = $sku;
                    continue;
                }

                $simulate = $this->getSimulate( id: $data[ "Id" ] );

                // No freight simulation data
                if ( is_null( $simulate ) )
                {
                    $s       = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                    $this->log_message( message: "SKU: {$s} | Without simulate", type: "error" );

                    $fails[] = $sku;
                    continue;
                }

                // Product without stock
                if ( isset( $simulate[ "items" ] ) && $simulate[ "items" ][ 0 ][ "availability" ] !== "available" )
                {
                    $s       = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                    $this->log_message( message: "SKU: {$s} | Without stock", type: "error" );

                    $fails[] = $sku;
                    continue;
                }

                $product  = [
                    "data"     => $data,
                    "context"  => $context,
                    "simulate" => $simulate,
                ];

                $s        = str_pad( $sku, 5, " ", STR_PAD_LEFT );

                $this->log_message( message: "SKU loaded: {$s}" );

                $products[ $sku ] = $product;

                // if ( count( $products ) == 10 || $sku == 24 )
                if ( count( $products ) == 10 )
                {
                    $wc_products = $this->translateProducts( $products );

                    if ( is_array( $wc_products ) )
                        $this->sendToMarketplace( $wc_products, array_keys( $products ) );

                    $this->log_message( message: "=================== Reset ===================" );

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

            $this->log_message( message: $e->getMessage(), type: "error" );
        }

        if ( count( $products ) != 0 )
        {
            $wc_products = $this->translateProducts( products: $products );

            if ( is_array( $wc_products ) )
                $this->sendToMarketplace( products: $wc_products, ids: array_keys( $products ) );
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
            $response = $this->product_service->getSkuData( sku: $sku );

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
                context : [ "id" => $sku ],
                method  : "getSkuData",
                type    : "Throwable",
                e       : $e
            );

            $this->log_message( message: $e->getMessage(), type: "error" );

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
            $response = $this->product_service->getSkuContext( sku: $sku );

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
                context : [ "id" => $sku ],
                method  : "getSkuContext",
                type    : "Throwable",
                e       : $e
            );

            $this->log_message( message: $e->getMessage(), type: "error" );

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

            $response = $this->simulation_service->simulate( data: $data );

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
                context : [ "id" => $id ],
                method  : "getSimulate",
                type    : "Throwable",
                e       : $e
            );

            $this->log_message( message: $e->getMessage(), type: "error" );

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
            $this->log_message( message: "Translate to marketplace: " . count( $products ) );

            $wc_data = [];

            foreach( $products as $sku => $product )
            {
                $s         = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                $this->log_message( message: "SKU: {$s} | " . $product[ "data" ][ "Name" ] );

                $wc_data[] = vtex_product_to_mkt(
                    data     : $product[ "data"     ],
                    context  : $product[ "context"  ],
                    simulate : $product[ "simulate" ]
                );
            }

            return $wc_data;
        }
        catch( Throwable $e )
        {
            $ids = array_keys( $products );

            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                context : [ "ids" => $ids ],
                method  : "translateProducts",
                type    : "Throwable",
                e       : $e
            );

            $this->log_message( message: $e->getMessage(), type: "error" );

            return null;
        }
    }

    /**
     * Undocumented function
     *
     * @param   array   $products
     * @param   array   $ids
     * @return  void
     */
    private function sendToMarketplace( array $products, array $ids ) : void
    {
        try
        {
            $this->log_message( message: "Send to marketplace" );

            $ids = Product::batch( [ "create" => $products ] );

            // dump( $ids );

            $this->log_message( message: "Sent to marketplace successfully" );
        }
        catch ( Throwable $e )
        {
            log_exception(
                channel : self::LOG_CHANNEL,
                message : $e->getMessage(),
                context : [ "ids" => $ids ],
                method  : "sendToMarketplace",
                type    : "Throwable",
                e       : $e
            );

            $this->log_message( message: $e->getMessage(), type: "error" );
        }
    }
}
