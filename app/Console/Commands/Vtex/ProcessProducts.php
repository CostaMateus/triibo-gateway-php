<?php

namespace App\Console\Commands\Vtex;

use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Str;
use App\Models\Vtex\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Vtex\ProductService;
use App\Services\Vtex\ConnectorService;
use Illuminate\Support\Facades\Storage;
use App\Services\Vtex\FulfillmentService;
use Codexshaper\WooCommerce\Facades\Category;
use Codexshaper\WooCommerce\Facades\Product as WC_Product;
use Google\Cloud\Storage\Connection\Rest;

class ProcessProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var     string
     */
    protected $signature          = "vtex:process_products {--seller= : Seller of products that will be processed. Options: fast_shop }";

    /**
     * The console command description.
     *
     * @var     string
     */
    protected $description        = "Process update or create new products";

    /**
     *
     * @var     string
     */
    protected $store_id           = 0;

    /**
     *
     * @var     string
     */
    protected $sale_channel       = "";

    /**
     *
     * @var     ProductService
     */
    protected $product_service    = "";

    /**
     *
     * @var     FulfillmentService
     */
    protected $simulation_service = "";

    /**
     *
     * @var     array
     */
    protected $wc_products        = [];

    /**
     *
     * @var     array
     */
    protected $allowed_sellers    = [ "fast_shop" => "fastshopbrqa2", ];

    /**
     *
     * @var     array
     */
    protected $delete_local       = [];

    /**
     *
     * @var     array
     */
    protected $update_wc          = [ "create" => [], "update" => [] ];

    /**
     *
     * @var     string
     */
    protected const LOG_CHANNEL   = "marketplace";


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $start                    = now();
        $message                  = "Products: Starting process FS data...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $seller                   = $this->option( key: "seller" );

        if ( !isset( $this->allowed_sellers[ $seller ] ) )
        {
            $message = "Products: Seller not allowed...";

            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            $diff    = $start->diffInMinutes( now() );
            $message = "Products: Runtime {$diff} minutes...";

            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );
            return;
        }

        $origin                   = $this->allowed_sellers[ $seller ];
        $products                 = Product::where( "status", "unprocessed" )
                                        //    ->where( "action", "create" )
                                           ->where( "origin", $origin )
                                           ->get();

        $env                      = config( "vtex.partners.{$seller}.env"                  );
        $this->sale_channel       = config( "vtex.partners.{$seller}.{$env}.sales_channel" );
        $this->store_id           = config( "woocommerce.partners.{$seller}.store_id"      );

        $connector                = new ConnectorService( $seller );
        $connector->enableLogNotify( false );

        $this->product_service    = new ProductService( $connector );
        $this->simulation_service = new FulfillmentService( $connector );

        $this->loadWcProducts();

        foreach ( $products as $product )
            $this->processProduct( $product );

        dd( $this->delete_local, $this->update_wc );

        $message                  = "Products: Finished FS data process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff                     = $start->diffInMinutes( now() );
        $message                  = "Products: Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );
    }

    /**
     *
     * @return  void
     */
    private function loadWcProducts() : void
    {
        $page = 1;

        try
        {
            do
            {
                $products = WC_Product::all( [
                    // "exclude_meta_key" => "_fs_product_id", // produtos FS da integração antiga
                    "status"       => "publish",
                    "manage_stock" => false,
                    "per_page"     => 100,
                    "page"         => $page,
                ] )->toArray();

                $count    = count( $products );

                foreach ( $products as $key => $product )
                {
                    if ( $product->store->id != $this->store_id )
                    {
                        unset( $products[ $key ] );
                        continue;
                    }
                }

                if ( ( $c = count( $products ) ) != 0 )
                {
                    $this->info( "Products: {$c} loaded" );
                    $this->wc_products = array_merge( $this->wc_products, $products );
                }

                // if ( count( $this->wc_products ) >= 200 )
                //     break;

                $page++;
            }
            while ( $count == 100 );
        }
        catch ( Throwable $e )
        {
            dd( $e->getMessage() );
        }
    }

    /**
     *
     * @param   Product     $product
     * @return  void
     */
    private function processProduct( Product $product )
    {
        try
        {
            $this->delete_local[] = $product->id;

            if ( $product->action === "disable" )
            {
                $wc_product = $this->searchProduct( $product->sku );

                if ( empty( $wc_product ) )
                    return;

                $wc_product = reset( $wc_product );

                if ( $wc_product->stock_status == "outofstock" )
                    return;

                $this->update_wc[ "update" ][] = [ "id" => $wc_product->id, "stock_status" => "outofstock" ];
                return;
            }

            if ( $product->action === "update" )
            {
                $wc_product = $this->searchProduct( $product->sku );
                $data       = json_decode( $product->meta, true );

                if ( empty( $wc_product ) || ( !$data[ "PriceModified" ] && !$data[ "StockModified" ] ) )
                    return;

                $wc_product = reset( $wc_product );

                $this->update_wc[ "update" ][] = $this->getProductData( $wc_product->id, $product->sku );
                return;
            }

            if ( $product->action === "create" )
            {
                $wc_product = $this->searchProduct( $product->sku );

                if ( !empty( $wc_product ) )
                    return;

                $data       = $this->getProductData( 0, $product->sku, true );

                if ( empty( $data ) )
                    return;

                $this->update_wc[ "create" ][] = vtex_product_to_mkt( $data[ "context" ], $data[ "simulate" ] );
                return;
            }
        }
        catch ( Exception $e )
        {
            dd( $e->getMessage() );
        }
    }

    /**
     *
     * @param   string  $id
     * @return  object|false
     */
    private function searchProduct( string $id )
    {
        return array_filter( $this->wc_products, function ( $wc_product ) use ( $id )
        {
            foreach ( $wc_product->meta_data as $meta )
            {
                if ( $meta->key === '_fs_product_id' && $meta->value == $id )
                    return true;
            }

            return false;
        } );
    }

    /**
     *
     * @param   int     $sku
     * @param   string  $sku
     * @return  array
     */
    private function getProductData( int $wc_id, string $sku, bool $create = false )
    {
        $context = $this->getSkuContext( $sku );
        $data    = $this->getSkuData( $sku );

        if ( ( !$context || !$data ) && !$create )
            return [ "id" => $wc_id, "stock_status" => "outofstock" ];

        if ( ( !$context || !$data ) && $create )
            return null;

        $simulate = $this->getSimulate( $data[ "Id" ] );

        if ( ( !$simulate || !isset( $simulate[ "items" ] ) ) && !$create )
            return [ "id" => $wc_id, "stock_status" => "outofstock" ];

        if ( ( !$simulate || !isset( $simulate[ "items" ] ) ) && $create )
            return null;

        if ( $create )
            return [ "context" => $context, "simulate" => $simulate ];

        return [
            "regular_price" => convert_price( $simulate[ "items" ][ 0 ][ "price"        ] ?? 0 ),
            "sale_price"    => convert_price( $simulate[ "items" ][ 0 ][ "sellingPrice" ] ?? 0 ),
            "stock_status"  => ( $simulate[ "items" ][ 0 ][ "availability" ] == "available" ) ? "instock" : "outofstock",
        ];
    }

    /**
     *
     * @param   integer     $sku
     * @return  array|false|null
     */
    private function getSkuContext( int $sku ) : array|false|null
    {
        try
        {
            $response = $this->product_service->getSkuContext( $sku );

            if ( $response[ 0 ] != 200 )
                return null;

            $product  = $response[ 1 ][ "data" ];

            if ( $product[ "IsActive" ] == false && $product[ "IsProductActive" ] == false )
                return false;

            if ( !in_array( $this->sale_channel, $product[ "SalesChannels" ] ) )
                return false;

            return $product;
        }
        catch( Throwable $e )
        {
            return null;
        }
    }

    /**
     *
     * @param   integer     $sku
     * @return  array|false|null
     */
    private function getSkuData( int $sku ) : array|false|null
    {
        try
        {
            $response = $this->product_service->getSkuData( $sku );

            if ( $response[ 0 ] != 200 )
                return null;

            $product  = $response[ 1 ][ "data" ];

            if ( $product[ "IsActive" ] == false )
                return false;

            return $product;
        }
        catch( Throwable $e )
        {
            return null;
        }
    }

    /**
     *
     * @param   integer     $id
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
}
