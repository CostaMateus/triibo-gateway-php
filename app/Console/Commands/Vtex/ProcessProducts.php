<?php

namespace App\Console\Commands\Vtex;

use Throwable;
use App\Models\Vtex\Product;
use Illuminate\Console\Command;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\{Artisan, DB};
use Codexshaper\WooCommerce\Facades\Product as WC_Product;
use App\Services\Vtex\{ConnectorService, FulfillmentService, ProductService};

class ProcessProducts extends Command
{
    /**
     * @var string
     */
    protected const LOG_CHANNEL = "marketplace";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "vtex:process_products {--seller= : Seller of products that will be processed. Options: fast_shop }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Process update or create new products";

    /**
     * @var string
     */
    protected $store_id = 0;

    /**
     * @var string
     */
    protected $sale_channel = "";

    /**
     * @var ProductService
     */
    protected $product_service;

    /**
     * @var FulfillmentService
     */
    protected $simulation_service;

    /**
     * @var Collection
     */
    protected $wc_products;

    /**
     * @var array
     */
    protected $allowed_sellers = ["fast_shop" => "fastshopbr"];

    /**
     * @var array
     */
    protected $update_local = [];

    /**
     * @var array
     */
    protected $batch_wc = [
        "create" => [],
        "update" => [],
        "delete" => []
    ];

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
    public function handle() : int
    {
        $start = now();
        $this->logMessage( message: "============================================" );
        $this->logMessage( message: "VTEX Products: Starting process Vtex data..." );

        $seller = $this->option( key: "seller" );

        if ( ! $this->isSellerAllowed( seller: $seller ) )
        {
            $this->logRuntimeAndFinish( start: $start );

            return Command::SUCCESS;
        }

        $origin = $this->allowed_sellers[$seller];
        $products = Product::where( "status", "unprocessed" )->where( "origin", $origin )->get();

        if ( $products->isEmpty() )
        {
            $this->logMessage( message: "VTEX Products: No products to process..." );
            $this->logRuntimeAndFinish( start: $start );

            return Command::SUCCESS;
        }

        $this->initializeServices( seller: $seller );
        $this->loadWcProducts();

        $this->logMessage( message: "VTEX Products: Processing Vtex notifications..." );

        $this->processProductChunks( products: $products );

        $this->logMessage( message: "VTEX Products: Finished data process..." );

        $this->logRuntimeAndFinish( start: $start );

        Artisan::call( command: "mkt:delete_fs_data --meta" );

        return Command::SUCCESS;
    }

    /**
     * Check if seller is allowed
     *
     * @param string $seller
     *
     * @return bool
     */
    private function isSellerAllowed( string $seller ) : bool
    {
        if ( ! isset( $this->allowed_sellers[$seller] ) )
        {
            $this->logMessage( "VTEX Products: Seller not allowed..." );

            return false;
        }

        return true;
    }

    /**
     * Initialize services for the seller
     *
     * @param string $seller
     *
     * @return void
     */
    private function initializeServices( string $seller ) : void
    {
        $partner = config( key: "vtex.partners" )[$seller];
        $env = $partner["env"];
        $this->sale_channel = $partner[$env]["sales_channel"];
        $this->store_id = config( key: "woocommerce.partners" )[$seller]["store_id"];

        $connector = new ConnectorService( seller: $seller );
        $connector->enableLogNotify( notify: false );

        $this->product_service = new ProductService( connector: $connector );
        $this->simulation_service = new FulfillmentService( connector: $connector );
    }

    /**
     * Process products in chunks
     *
     * @param Collection $products
     *
     * @return void
     */
    private function processProductChunks( Collection $products ) : void
    {
        $products_chunk = $products->chunk( 100 );
        $count = count( $products_chunk );

        foreach ( $products_chunk as $key => $chunk )
        {
            $i = $key + 1;

            try
            {
                DB::beginTransaction();

                foreach ( $chunk as $product )
                {
                    $this->processProduct( product: $product );

                    $this->update_local[] = $product->id;
                }

                $success = $this->batchMarketplaceProduct();

                if ( $success )
                    $success = $this->createLocalWcProducts();

                if ( $success )
                    $this->deleteProcessed();

                if ( $success )
                {
                    DB::commit();
                    $this->logMessage( message: "VTEX Products: Processed {$i} of {$count} notifications" );
                }
                else
                {
                    DB::rollBack();
                    $this->logMessage( message: "VTEX Products: Fail processing {$i} of {$count} notifications" );
                }
            }
            catch ( Throwable $e )
            {
                DB::rollBack();

                $this->logMessage( message: $e->getMessage(), type: "error" );
            }
        }
    }

    /**
     * Log runtime and finish message
     *
     * @param Carbon $start
     *
     * @return void
     */
    private function logRuntimeAndFinish( Carbon $start ) : void
    {
        $diff = $start->diffInMinutes( date: now() );

        $this->logMessage( message: "VTEX Products: Runtime {$diff} minutes..." );
    }

    /**
     * Load WooCommerce products
     *
     * @return void
     */
    private function loadWcProducts() : void
    {
        $this->logMessage( message: "VTEX Products: Loading marketplace products..." );

        $this->wc_products = DB::table( "marketplace_product_skus" )->where( "store_id", $this->store_id )->get();

        $count = $this->wc_products->count();

        $this->logMessage( message: "VTEX Products: {$count} products loaded..." );
    }

    /**
     * Batch update marketplace products
     *
     * @return bool
     */
    private function batchMarketplaceProduct() : bool
    {
        $this->logMessage( message: "VTEX Products: Updating marketplace products..." );

        $chunks = $this->prepareBatchChunks();

        $total_count = count( $chunks );

        foreach ( $chunks as $key => $chunk )
        {
            $group = ( $key + 1 ) . " of {$total_count}";

            $this->logMessage( message: "VTEX Products: Updating marketplace products, {$group}" );

            if ( empty( $chunk["create"] ) && empty( $chunk["update"] ) && empty( $chunk["delete"] ) )
                continue;

            $chunk = $this->cleanEmptyActions( chunk: $chunk );

            try
            {
                WC_Product::batch( $chunk );
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() !== "JSON ERROR: Syntax error" )
                {
                    $this->logMessage( message: $e->getMessage(), type: "error" );

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Prepare batch chunks from operations
     *
     * @return array
     */
    private function prepareBatchChunks() : array
    {
        $create_chunks = $this->chunkBatchOperation( operation: "create" );
        $update_chunks = $this->chunkBatchOperation( operation: "update" );
        $delete_chunks = $this->chunkBatchOperation( operation: "delete" );

        $max_chunks = max(
            count( $create_chunks ),
            count( $update_chunks ),
            count( $delete_chunks )
        );

        $chunks = [];

        foreach ( range( 0, $max_chunks - 1 ) as $i )
        {
            $chunks[] = [
                "create" => $create_chunks[$i] ?? [],
                "update" => $update_chunks[$i] ?? [],
                "delete" => $delete_chunks[$i] ?? [],
            ];
        }

        return $chunks;
    }

    /**
     * Chunk a specific batch operation
     *
     * @param string $operation
     *
     * @return array
     */
    private function chunkBatchOperation( string $operation ) : array
    {
        if ( empty( $this->batch_wc[$operation] ) )
            return [];

        return array_chunk( $this->batch_wc[$operation], 30 );
    }

    /**
     * Remove empty action arrays from chunk
     *
     * @param array $chunk
     *
     * @return array
     */
    private function cleanEmptyActions( array $chunk ) : array
    {
        foreach ( ["create", "update", "delete"] as $action )
            if ( empty( $chunk[$action] ) )
                unset( $chunk[$action] );

        return $chunk;
    }

    /**
     * Delete processed products
     *
     * @return void
     */
    private function deleteProcessed() : void
    {
        $this->logMessage( message: "VTEX Products: Deleting completed processes..." );

        Product::whereIn( "id", $this->update_local )->delete();
    }

    /**
     * Create local WooCommerce products
     *
     * @return bool
     */
    private function createLocalWcProducts() : bool
    {
        $this->logMessage( message: "VTEX Products: Creating local WC products..." );

        foreach ( $this->batch_wc["create"] as $product )
        {
            try
            {
                $wc_product = WC_Product::where( "sku", $product["sku"] )->first();

                if ( ! $wc_product )
                    continue;

                $local_product = DB::table( "marketplace_product_skus" )
                    ->where( "product_sku", $product["sku"] )
                    ->whereNull( "product_id" )
                    ->first();

                if ( ! $local_product )
                    continue;

                $this->createOrUpdateLocalWcProduct(
                    id: $local_product->id,
                    data: ["product_id" => $wc_product["id"]]
                );
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() !== "JSON ERROR: Syntax error" )
                {
                    $this->logMessage( message: $e->getMessage(), type: "error" );
                    $this->logMessage( message: print_r( $e, true ), type: "error" );

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Process a single product
     *
     * @param Product $product
     *
     * @return void
     */
    private function processProduct( Product $product ) : void
    {
        $this->logMessage( message: "VTEX Products: Processing product SKU {$product->sku}, with action {$product->action}...", context: $product->toArray() );

        $wc_product = $this->searchProduct( id: $product->sku );

        match ( $product->action )
        {
            "disable" => $this->processDisableAction( $wc_product ),
            "update" => $this->processUpdateAction( $product, $wc_product ),
            "create" => $this->processCreateAction( $product, $wc_product ),
        };
    }

    /**
     * Process 'disable' action
     *
     * @param null|object $wc_product
     *
     * @return void
     */
    private function processDisableAction( ?object $wc_product ) : void
    {
        if ( empty( $wc_product ) )
            return;

        $this->toUpdate( id: $wc_product->id, wc_id: $wc_product->product_id );
    }

    /**
     * Process 'update' action
     *
     * @param Product     $product
     * @param null|object $wc_product
     *
     * @return void
     */
    private function processUpdateAction( Product $product, ?object $wc_product ) : void
    {
        if ( empty( $wc_product ) )
            return;

        $data = json_decode( $product->meta, true );

        if ( $data["HasStockKeepingUnitRemovedFromAffiliate"] )
        {
            $this->toDelete( wc_product: $wc_product );

            return;
        }

        $product_data = $this->getProductData( sku: $product->sku, full: $data["HasStockKeepingUnitModified"] );

        if ( $product_data === false )
        {
            $this->toUpdate( id: $wc_product->id, wc_id: $wc_product->product_id );

            return;
        }

        if ( isset( $product_data["data"] ) )
        {
            $product_data = vtex_product_to_mkt(
                data    : $product_data["data"],
                context : $product_data["context"],
                simulate: $product_data["simulate"]
            );
        }

        $product_data["id"] = $wc_product->product_id;

        $this->batch_wc["update"][] = $product_data;

        $this->createOrUpdateLocalWcProduct( id: $wc_product->id, data: [
            "regular_price" => $product_data["regular_price"],
            "sale_price" => $product_data["sale_price"],
            "stock_status" => $product_data["stock_status"],
        ] );
    }

    /**
     * Process 'create' action
     *
     * @param Product     $product
     * @param null|object $wc_product
     *
     * @return void
     */
    private function processCreateAction( Product $product, $wc_product ) : void
    {
        if ( ! empty( $wc_product ) )
            return;

        $data = $this->getProductData( sku: $product->sku, create: true );

        if ( $data === null )
            return;

        $data = vtex_product_to_mkt(
            data    : $data["data"],
            context : $data["context"],
            simulate: $data["simulate"]
        );

        $this->batch_wc["create"][] = $data;

        $this->createOrUpdateLocalWcProduct( data: $data );
    }

    /**
     * Search for a product by ID
     *
     * @param string $id
     *
     * @return null|object
     */
    private function searchProduct( string $id ) : ?object
    {
        $product = $this->wc_products->filter( callback: static function ( $product ) use ( $id )
        {
            return $product->metadata_id === $id;
        } );

        return $product->isEmpty() ? null : $product->first();
    }

    /**
     * Mark product for deletion
     *
     * @param object $wc_product
     *
     * @return void
     */
    private function toDelete( object $wc_product ) : void
    {
        $this->batch_wc["delete"][] = $wc_product->product_id;

        DB::table( "marketplace_product_skus" )
            ->where( "id", $wc_product->id )
            ->delete();
    }

    /**
     * Mark product for update
     *
     * @param string $id
     * @param string $wc_id
     *
     * @return void
     */
    private function toUpdate( string $id, string $wc_id ) : void
    {
        $this->batch_wc["update"][] = [
            "id" => $wc_id,
            "stock_status" => "outofstock",
        ];

        $this->createOrUpdateLocalWcProduct( id: $id, data: [
            "stock_status" => "outofstock",
        ] );
    }

    /**
     * Create or update local WooCommerce product
     *
     * @param int   $id
     * @param array $data
     *
     * @return void
     */
    private function createOrUpdateLocalWcProduct( int $id = 0, array $data = [] ) : void
    {
        if ( $id === 0 )
        {
            DB::table( "marketplace_product_skus" )
                ->insert( [
                    "store_id" => $this->store_id,
                    "metadata_id" => $data["meta_data"][0]["value"],
                    "product_sku" => $data["sku"],
                    "regular_price" => $data["regular_price"],
                    "sale_price" => $data["sale_price"],
                    "stock_status" => $data["stock_status"],
                ] );

            return;
        }

        DB::table( "marketplace_product_skus" )->where( "id", $id )->update( $data );
    }

    /**
     * Get product data from VTEX API
     *
     * @param string $sku
     * @param bool   $create
     * @param bool   $full
     *
     * @return null|array|false
     */
    private function getProductData( string $sku, bool $create = false, bool $full = false ) : null|array|false
    {
        $data = $this->getSkuData( sku: $sku );
        $context = $this->getSkuContext( sku: $sku );

        if ( ! $context || ! $data )
            return ! $create ? false : null;

        $simulate = $this->getSimulate( id: $data["Id"] );

        if ( ! $simulate || ! isset( $simulate["items"] ) )
            return ! $create ? false : null;

        if ( $simulate["items"][0]["availability"] !== "available" )
            return ! $create ? false : null;

        if ( $create || $full )
        {
            return [
                "data" => $data,
                "context" => $context,
                "simulate" => $simulate
            ];
        }

        return [
            "regular_price" => convert_price( price: $simulate["items"][0]["price"] ?? 0 ),
            "sale_price" => convert_price( price: $simulate["items"][0]["sellingPrice"] ?? 0 ),
            "stock_status" => "instock",
        ];
    }

    /**
     * Get SKU context from VTEX API
     *
     * @param int $sku
     *
     * @return null|array|false
     */
    private function getSkuContext( int $sku ) : null|array|false
    {
        return $this->safeApiCall(
            method_name: "getSkuContext",
            callback: function () use ( $sku )
            {
                $response = $this->product_service->getSkuContext( sku: $sku );

                if ( $response[0] !== 200 )
                    return;

                $product = $response[1]["data"];

                if ( $product["IsActive"] === false && $product["IsProductActive"] === false )
                    return false;

                if ( ! in_array( $this->sale_channel, $product["SalesChannels"] ) )
                    return false;

                return $product;
            }
        );
    }

    /**
     * Get SKU data from VTEX API
     *
     * @param int $sku
     *
     * @return null|array|false
     */
    private function getSkuData( int $sku ) : null|array|false
    {
        return $this->safeApiCall(
            method_name: "getSkuData",
            callback: function () use ( $sku )
            {
                $response = $this->product_service->getSkuData( sku: $sku );

                if ( $response[0] !== 200 )
                    return;

                $product = $response[1]["data"];

                if ( $product["IsActive"] === false )
                    return false;

                return $product;
            }
        );
    }

    /**
     * Get simulation data from VTEX API
     *
     * @param int $id
     *
     * @return null|array
     */
    private function getSimulate( int $id ) : ?array
    {
        return $this->safeApiCall(
            method_name: "getSimulate",
            callback: function () use ( $id )
            {
                $data = [
                    "items" => [
                        [
                            "id" => $id,
                            "quantity" => 1,
                            "seller" => "1"
                        ]
                    ],
                    "isCheckedIn" => false,
                    "country" => "BRA",
                    "selectedSla" => "Normal",
                ];

                $response = $this->simulation_service->simulate( data: $data );

                if ( $response[0] !== 200 )
                    return;

                return array_filter(
                    $response[1]["data"],
                    static function ( $value, $key )
                    {
                        return $value !== null
                            && $value !== []
                            && $key !== "paymentData"
                            && $key !== "purchaseConditions"
                            && $key !== "ratesAndBenefitsData";
                    },
                    ARRAY_FILTER_USE_BOTH
                );
            }
        );
    }

    /**
     * Safely call API with exception handling
     *
     * @param string   $method_name
     * @param callable $callback
     *
     * @return mixed
     */
    private function safeApiCall( string $method_name, callable $callback ) : mixed
    {
        try
        {
            return $callback();
        }
        catch ( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message: $e->getMessage(),
                method : $method_name,
                type   : "Throwable",
                e      : $e
            );

            $this->logMessage( message: $e->getMessage(), type: "error" );

            return null;
        }
    }

    /**
     * Log message with consistent format
     *
     * @param string $message
     * @param string $type
     * @param array  $context
     *
     * @return void
     */
    private function logMessage( string $message, string $type = "info", array $context = [] ) : void
    {
        log_message(
            channel: self::LOG_CHANNEL,
            message: $message,
            level  : $type,
            context: $context
        );

        $this->{$type}( string: $message );
    }
}
