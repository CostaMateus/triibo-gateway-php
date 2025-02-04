<?php

namespace App\Console\Commands\Vtex;

use Throwable;
use App\Models\Vtex\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product as WC_Product;
use App\Services\Vtex\{ ProductService, ConnectorService, FulfillmentService };

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
     * @var     Collection
     */
    protected $wc_products        = null;

    /**
     *
     * @var     array
     */
    protected $allowed_sellers    = [ "fast_shop" => "fastshopbr", ];

    /**
     *
     * @var     array
     */
    protected $update_local       = [];

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
     * @return  int
     */
    public function handle() : int
    {
        $start                    = now();
        $message                  = "Products: Starting process Vtex data...";
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

            return 1;
        }

        $origin                   = $this->allowed_sellers[ $seller ];
        $products                 = Product::where( "status", "unprocessed" )->where( "origin", $origin )->get();

        if ( $products->count() == 0 )
        {
            $message = "Products: No products to process...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            $diff    = $start->diffInMinutes( now() );
            $message = "Products: Runtime {$diff} minutes...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            return 1;
        }

        $env                      = config( "vtex.partners.{$seller}.env"                  );
        $this->sale_channel       = config( "vtex.partners.{$seller}.{$env}.sales_channel" );
        $this->store_id           = config( "woocommerce.partners.{$seller}.store_id"      );

        $connector                = new ConnectorService( $seller );
        $connector->enableLogNotify( false );

        $this->product_service    = new ProductService( $connector );
        $this->simulation_service = new FulfillmentService( $connector );

        $this->loadWcProducts();

        $message                  = "Products: Processing Vtex notifications...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $products_chunk           = $products->chunk( 100 );

        foreach ( $products_chunk as $products )
        {
            foreach ( $products as $product )
            {
                $message              = "Products: SKU {$product->sku}...";
                log_message( channel: self::LOG_CHANNEL, message: $message );
                $this->info( $message );

                $this->processProduct( $product );

                $this->update_local[] = $product->id;
            }

            $this->updateMarketplaceProduct();
            $this->deleteProcessed();

            $this->updateLocalWcProducts();
            $this->updateLocalProducts();
        }

        $message                  = "Products: Finished data process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff                     = $start->diffInMinutes( now() );
        $message                  = "Products: Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        return 1;
    }

    /**
     *
     * @return  void
     */
    private function loadWcProducts() : void
    {
        $message           = "Products: Loading marketplace products...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $this->wc_products = DB::table( "marketplace_product_skus" )->where( "store_id", $this->store_id )->get();

        $count             = count( $this->wc_products );
        $message           = "Products: {$count} products loaded...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );
    }

    /**
     *
     * @return  void
     */
    private function updateMarketplaceProduct() : void
    {
        $message       = "Products: Updating marketplace products...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $chunks        = [];
        $create_chunks = array_chunk( $this->update_wc[ "create" ], 50 );
        $update_chunks = array_chunk( $this->update_wc[ "update" ], 50 );

        $max_chunks    = max( count( $create_chunks ), count( $update_chunks ) );

        foreach ( range( 0, $max_chunks ) as $i )
        {
            $chunks[] = [
                "create" => $create_chunks[ $i ] ?? [],
                "update" => $update_chunks[ $i ] ?? [],
            ];
        }

        foreach ( $chunks as $chunk )
        {
            if ( empty( $chunk[ "create" ] ) && empty( $chunk[ "update" ] ) )
                continue;

            try
            {
                WC_Product::batch( $chunk );
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                {
                    log_message( channel: self::LOG_CHANNEL, message: $e->getMessage() );
                    $this->error( $e->getMessage() );
                }
            }
        }
    }

    /**
     *
     * @return  void
     */
    private function deleteProcessed() : void
    {
        $message = "Products: Deleting completed processes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        Product::where( "status", "processed" )->delete();
    }

    /**
     *
     * @return  void
     */
    private function updateLocalWcProducts() : void
    {
        $message = "Products: Updating local products...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $created = $this->update_wc[ "create" ];

        foreach ( $created as $product )
        {
            $wc_product    = WC_Product::where( "sku", $product[ "sku" ] )->first();

            if ( empty( $wc_product ) )
                continue;

            $local_product = DB::table( "marketplace_product_skus" )
                               ->where( "product_sku", $product[ "sku" ] )
                               ->whereNull( "product_id" )
                               ->first();

            if ( empty( $local_product ) )
                continue;

            $this->updateWcProduct( $local_product[ "id" ], [ "product_id" => $wc_product->id ] );
        }
    }

    /**
     *
     * @return  void
     */
    private function updateLocalProducts() : void
    {
        Product::whereIn( "id", $this->update_local )
               ->update( [ "status" => "processed" ] );
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
            $wc_product = $this->searchProduct( $product->sku );

            if ( $product->action === "disable" )
            {
                if ( empty( $wc_product ) )
                    return;

                if ( $wc_product->stock_status == "outofstock" )
                    return;

                $this->update_wc[ "update" ][] = [ "id" => $wc_product->id, "stock_status" => "outofstock" ];

                $this->updateWcProduct( $wc_product->id, [ "stock_status" => "outofstock" ] );

                return;
            }

            if ( $product->action === "update" )
            {
                $data = json_decode( $product->meta, true );

                if ( empty( $wc_product ) || ( !$data[ "PriceModified" ] && !$data[ "StockModified" ] ) )
                    return;

                $data = $this->getProductData( $wc_product->id, $product->sku );

                $this->update_wc[ "update" ][] = $data;

                $this->updateWcProduct( $wc_product->id, $data );

                return;
            }

            if ( $product->action === "create" )
            {
                if ( !empty( $wc_product ) )
                    return;

                $data = $this->getProductData( 0, $product->sku, true );

                if ( empty( $data ) )
                    return;

                $data = vtex_product_to_mkt( $data[ "context" ], $data[ "simulate" ] );

                $this->update_wc[ "create" ][] = $data;

                $this->updateWcProduct( data: $data );

                return;
            }
        }
        catch ( Exception $e )
        {
            $this->error( $e->getMessage() );
        }
    }

    /**
     *
     * @param   string  $id
     * @return  object|null
     */
    private function searchProduct( string $id ) : mixed
    {
        $product = $this->wc_products->filter( function ( $product ) use ( $id )
        {
            return $product->metadata_id == $id;
        } );

        return $product->isEmpty() ? null : $product->first();
    }

    /**
     *
     * @param   integer     $id
     * @param   array       $data
     * @return  void
     */
    private function updateWcProduct( int $id = 0, array $data = [] ) : void
    {
        if ( $id == 0 )
        {
            DB::table( "marketplace_product_skus" )->insert( [
                "store_id"      => $this->store_id,
                "metadata_id"   => $data[ "meta_data"     ][ 0 ][ "value" ],
                "product_sku"   => $data[ "sku"           ],
                "regular_price" => $data[ "regular_price" ],
                "sale_price"    => $data[ "sale_price"    ],
                "stock_status"  => $data[ "stock_status"  ],
            ] );
        }
        else
        {
            DB::table( "marketplace_product_skus" )->where( "id", $id )->update( $data );
        }
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
