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
        $start    = now();
        $this->log_message( message: "=======================================" );
        $this->log_message( message: "Products: Starting process Vtex data..." );

        $seller   = $this->option( key: "seller" );

        if ( !isset( $this->allowed_sellers[ $seller ] ) )
        {
            $this->log_message( message: "Products: Seller not allowed..." );

            $diff = $start->diffInMinutes( date: now() );

            $this->log_message( message: "Products: Runtime {$diff} minutes..." );

            return 0;
        }

        $origin   = $this->allowed_sellers[ $seller ];
        $products = Product::where( "status", "unprocessed" )->where( "origin", $origin )->get();

        if ( $products->count() == 0 )
        {
            $this->log_message( message: "Products: No products to process..." );

            $diff    = $start->diffInMinutes( date: now() );

            $this->log_message( message: "Products: Runtime {$diff} minutes..." );

            return 0;
        }

        $env                      = config( key: "vtex.partners.{$seller}.env"                  );
        $this->sale_channel       = config( key: "vtex.partners.{$seller}.{$env}.sales_channel" );
        $this->store_id           = config( key: "woocommerce.partners.{$seller}.store_id"      );

        $connector                = new ConnectorService( seller: $seller );
        $connector->enableLogNotify( notify: false );

        $this->product_service    = new ProductService( connector: $connector );
        $this->simulation_service = new FulfillmentService( connector: $connector );

        $this->loadWcProducts();

        $this->log_message( message: "Products: Processing Vtex notifications..." );

        $products_chunk           = $products->chunk( 100 );

        foreach ( $products_chunk as $products )
        {
            foreach ( $products as $product )
            {
                // $this->log_message( message: "Products: SKU {$product->sku}..." );

                $this->processProduct( product: $product );

                $this->update_local[] = $product->id;
            }

            $this->updateMarketplaceProduct();
            $this->deleteProcessed();

            $this->updateLocalWcProducts();
            $this->updateLocalProducts();
        }

        $this->log_message( message: "Products: Finished data process..." );

        $diff = $start->diffInMinutes( date: now() );

        $this->log_message( message: "Products: Runtime {$diff} minutes..." );

        return 0;
    }

    /**
     *
     * @return  void
     */
    private function loadWcProducts() : void
    {
        $this->log_message( message: "Products: Loading marketplace products..." );

        $this->wc_products = DB::table( table: "marketplace_product_skus" )->where( column: "store_id", value: $this->store_id )->get();

        $count             = count( $this->wc_products );

        $this->log_message( message: "Products: {$count} products loaded..." );
    }

    /**
     *
     * @return  void
     */
    private function updateMarketplaceProduct() : void
    {
        $this->log_message( message: "Products: Updating marketplace products..." );

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
                WC_Product::batch( data: $chunk );
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                    $this->log_message( message: $e->getMessage(), type: "error" );
            }
        }
    }

    /**
     *
     * @return  void
     */
    private function deleteProcessed() : void
    {
        $this->log_message( message: "Products: Deleting completed processes..." );

        Product::where( column: "status", value: "processed" )->delete();
    }

    /**
     *
     * @return  void
     */
    private function updateLocalWcProducts() : void
    {
        $this->log_message( message: "Products: Updating local products..." );

        $created = $this->update_wc[ "create" ];

        foreach ( $created as $product )
        {
            try
            {
                $wc_product = WC_Product::where( column: "sku", value: $product[ "sku" ] )->first();

                if ( !$wc_product )
                    continue;

                $local_product = DB::table( table: "marketplace_product_skus" )
                                   ->where( column: "product_sku", value: $product[ "sku" ] )
                                   ->whereNull( columns: "product_id" )
                                   ->first();

                if ( !$local_product )
                    continue;

                $this->updateWcProduct( id: $local_product->id, data: [ "product_id" => $wc_product[ "id" ] ] );
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                    $this->log_message( message: $e->getMessage(), type: "error" );
            }
        }
    }

    /**
     *
     * @return  void
     */
    private function updateLocalProducts() : void
    {
        Product::whereIn( "id", $this->update_local )->update( [ "status" => "processed" ] );
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
            $wc_product = $this->searchProduct( id: $product->sku );

            if ( $product->action === "disable" )
            {
                if ( empty( $wc_product ) )
                    return;

                if ( $wc_product->stock_status == "outofstock" )
                    return;

                $this->update_wc[ "update" ][] = [ "id" => $wc_product->id, "stock_status" => "outofstock" ];

                $this->updateWcProduct( id: $wc_product->id, data: [ "stock_status" => "outofstock" ] );

                return;
            }

            if ( $product->action === "update" )
            {
                $data = json_decode( $product->meta, true );

                if ( empty( $wc_product ) || ( !$data[ "PriceModified" ] && !$data[ "StockModified" ] ) )
                    return;

                $data = $this->getProductData( wc_id: $wc_product->id, sku: $product->sku );

                $this->update_wc[ "update" ][] = $data;

                $this->updateWcProduct( id: $wc_product->id, data: $data );

                return;
            }

            if ( $product->action === "create" )
            {
                if ( !empty( $wc_product ) )
                    return;

                $data = $this->getProductData( wc_id: 0, sku: $product->sku, create: true );

                if ( empty( $data ) )
                    return;

                $data = vtex_product_to_mkt( data: $data[ "context" ], simulate: $data[ "simulate" ] );

                $this->update_wc[ "create" ][] = $data;

                $this->updateWcProduct( data: $data );

                return;
            }
        }
        catch ( Exception $e )
        {
            $this->log_message( message: $e->getMessage(), type: "error" );
        }
    }

    /**
     *
     * @param   string  $id
     * @return  object|null
     */
    private function searchProduct( string $id ) : mixed
    {
        $product = $this->wc_products->filter( callback: function ( $product ) use ( $id )
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
            DB::table( table: "marketplace_product_skus" )->insert( values: [
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
            DB::table( table: "marketplace_product_skus" )->where( column: "id", value: $id )->update( values: $data );
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
        $context = $this->getSkuContext( sku: $sku );
        $data    = $this->getSkuData( sku: $sku );

        if ( ( !$context || !$data ) && !$create )
            return [ "id" => $wc_id, "stock_status" => "outofstock" ];

        if ( ( !$context || !$data ) && $create )
            return null;

        $simulate = $this->getSimulate( id: $data[ "Id" ] );

        if ( ( !$simulate || !isset( $simulate[ "items" ] ) ) && !$create )
            return [ "id" => $wc_id, "stock_status" => "outofstock" ];

        if ( ( !$simulate || !isset( $simulate[ "items" ] ) ) && $create )
            return null;

        if ( $create )
            return [ "context" => $context, "simulate" => $simulate ];

        return [
            "regular_price" => convert_price( price: $simulate[ "items" ][ 0 ][ "price"        ] ?? 0 ),
            "sale_price"    => convert_price( price: $simulate[ "items" ][ 0 ][ "sellingPrice" ] ?? 0 ),
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
            $response = $this->product_service->getSkuContext( sku: $sku );

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
            $response = $this->product_service->getSkuData( sku: $sku );

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
                method  : "getSimulate",
                type    : "Throwable",
                e       : $e
            );

            $this->log_message( message: $e->getMessage(), type: "error" );

            return null;
        }
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
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->$type( string: $message );
    }
}
