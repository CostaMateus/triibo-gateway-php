<?php

namespace App\Console\Commands\Vtex;

use Throwable;
use Illuminate\Console\Command;
use App\Models\Vtex\ProductNotification;
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
     * @var string
     */
    protected $prefix = "VTEX Notifications:";

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
        $this->logMessage( message: "Starting process Vtex data..." );

        $seller = $this->option( key: "seller" );

        if ( ! $this->isSellerAllowed( seller: $seller ) )
        {
            $this->logRuntimeAndFinish( start: $start );

            return Command::SUCCESS;
        }

        $origin = $this->allowed_sellers[$seller];
        $notifications = $this->fetchUnprocessedProducts( origin: $origin );

        if ( $notifications->isEmpty() )
        {
            $this->logMessage( message: "No notification to process..." );
            $this->logRuntimeAndFinish( start: $start );

            return Command::SUCCESS;
        }

        $this->initializeServices( seller: $seller );
        $this->loadWcProducts();

        $this->logMessage( message: "Processing Vtex notifications..." );

        $success = $this->processProductChunks( notifications: $notifications );

        $status_message = $success
            ? "Finished data process successfully..."
            : "Finished data process with some errors...";

        $this->logMessage( message: $status_message );

        $this->logRuntimeAndFinish( start: $start );

        return Command::SUCCESS;
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
        $message = "{$this->prefix} {$message}";

        log_message(
            channel: self::LOG_CHANNEL,
            message: $message,
            level  : $type,
            context: $context
        );

        $this->{$type}( string: $message );
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
            $this->logMessage( message: "Seller not allowed..." );

            return false;
        }

        return true;
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

        $this->logMessage( message: "Runtime {$diff} minutes..." );
    }

    /**
     * Fetch unprocessed products for the given origin
     *
     * @param string $origin
     *
     * @return Collection
     */
    private function fetchUnprocessedProducts( string $origin ) : Collection
    {
        try
        {
            return ProductNotification::query()
                ->where( column: "status", operator: "=", value: "unprocessed" )
                ->where( column: "origin", operator: "=", value: $origin )
                ->get();
        }
        catch ( Throwable $e )
        {
            $this->logMessage(
                message: "Error fetching unprocessed products: {$e->getMessage()}",
                type: "error"
            );

            return collect( value: [] );
        }
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
     * Load WooCommerce products
     *
     * @return void
     */
    private function loadWcProducts() : void
    {
        $this->logMessage( message: "Loading marketplace products..." );

        $this->wc_products = DB::table( table: "marketplace_product_skus" )
            ->where( column: "store_id", operator: "=", value: $this->store_id )
            ->get();

        $count = $this->wc_products->count();

        $this->logMessage( message: "{$count} products loaded..." );
    }

    /**
     * Process notifications in chunks
     *
     * @param Collection $notifications
     *
     * @return bool Overall success status
     */
    private function processProductChunks( Collection $notifications ) : bool
    {
        $notifications_chunk = $notifications->chunk( size: 100 );
        $count = count( $notifications_chunk );
        $overall_success = true;

        foreach ( $notifications_chunk as $key => $chunk )
        {
            $chunk_success = $this->processChunkWithTransaction( chunk: $chunk, chunk_index: $key, total_chunks: $count );

            // Even if one chunk fails, continue processing other chunks
            // but track that there was at least one failure
            if ( ! $chunk_success )
                $overall_success = false;

            // Give the system a small break between chunks
            if ( $key < $count - 1 )
                usleep( 500000 ); // 500ms
        }

        return $overall_success;
    }

    /**
     * Process a single chunk of products with transaction management
     *
     * @param Collection $chunk        Collection of products to process
     * @param int        $chunk_index  Index of current chunk for logging
     * @param int        $total_chunks Total number of chunks
     *
     * @return bool Success status
     */
    private function processChunkWithTransaction( Collection $chunk, int $chunk_index, int $total_chunks ) : bool
    {
        $i = $chunk_index + 1;
        $chunk_ids = $chunk->pluck( value: "id" )->toArray();
        $batch_local_updates = [];

        $batch_wc_operations = [
            "create" => [],
            "update" => [],
            "delete" => []
        ];
        $batch_local_operations = [
            "create" => [],
            "update" => [],
            "delete" => []
        ];

        // First process all notifications and collect changes without committing
        try
        {
            foreach ( $chunk as $notification )
            {
                $result = $this->processNotification( notification: $notification );

                if ( $result )
                {
                    $batch_local_updates[] = $notification->id;

                    // Merge operations from product processing
                    foreach ( $result as $key => $data )
                        foreach ( $data as $operation => $value )
                            if ( ! empty( $value ) )
                                ${"batch_{$key}_operations"}[$operation] = array_merge(
                                    ${"batch_{$key}_operations"}[$operation],
                                    $value
                                );
                }
            }

            // If we have no operations to perform, mark as successful
            if ( empty( $batch_local_updates ) )
            {
                $this->logMessage( message: "No valid notifications to process in chunk {$i} of {$total_chunks}" );

                return true;
            }

            // Now perform database operations within a transaction
            DB::beginTransaction();

            // Execute marketplace batch updates
            $success = $this->executeBatchWcOperations( operations: $batch_wc_operations );

            if ( ! $success )
            {
                DB::rollBack();

                $this->logMessage(
                    message: "Failed executing WooCommerce batch operations for chunk {$i} of {$total_chunks}",
                    type: "error"
                );

                return false;
            }

            // Create local WC products
            $success = $this->executeBatchLocalOperations( operations: $batch_local_operations );

            if ( ! $success )
            {
                DB::rollBack();

                $this->logMessage(
                    message: "Failed creating local WC products for chunk {$i} of {$total_chunks}",
                    type: "error"
                );

                return false;
            }

            // Delete processed notifications
            ProductNotification::query()
                ->whereIn( column: "id", values: $batch_local_updates )
                ->update( values: ["status" => "processed"] );

            DB::commit();

            $this->logMessage( message: "Successfully processed chunk {$i} of {$total_chunks}" );

            return true;

        }
        catch ( Throwable $e )
        {
            if ( DB::transactionLevel() > 0 )
                DB::rollBack();

            $this->logMessage(
                message: "Error processing chunk {$i} of {$total_chunks}: {$e->getMessage()}",
                context: ["chunk_ids" => $chunk_ids],
                type: "error"
            );

            return false;
        }
    }

    /**
     * Process a single notification and return operations to be performed
     *
     * @param ProductNotification $notification
     *
     * @return array|false Operations to be performed or false on failure
     */
    private function processNotification( ProductNotification $notification ) : array|false
    {
        $this->logMessage(
            message: "Processing notification SKU {$notification->sku}, with action {$notification->action}...",
            context: $notification->toArray()
        );

        try
        {
            $wc_product = $this->searchProduct( id: $notification->sku );

            $operations = [
                "wc" => [
                    "create" => [],
                    "update" => [],
                    "delete" => [],
                ],
                "local" => [
                    "create" => [],
                    "update" => [],
                    "delete" => [],
                ],
            ];

            switch ( $notification->action )
            {
                case "disable":
                    $this->processDisableAction( wc_product: $wc_product, operations: $operations );

                    break;

                case "update":
                    $this->processUpdateAction( notification: $notification, wc_product: $wc_product, operations: $operations );

                    break;

                case "create":
                    $this->processCreateAction( notification: $notification, wc_product: $wc_product, operations: $operations );

                    break;

                default:
                    $this->logMessage(
                        message: "Unknown action \"{$notification->action}\" for SKU {$notification->sku}",
                        type: "error"
                    );

                    return false;

                    break;
            }

            return $operations;
        }
        catch ( Throwable $e )
        {
            $this->logMessage(
                message: "Error processing product SKU {$notification->sku}: {$e->getMessage()}",
                type: "error"
            );

            return false;
        }
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
        $wc_product = $this->wc_products->filter( callback: static function ( $wc_product ) use ( $id )
        {
            return $wc_product->metadata_id === $id;
        } );

        return $wc_product->isEmpty() ? null : $wc_product->first();
    }

    /**
     * Process "disable" action
     *
     * @param null|object $wc_product
     * @param array       $operations Reference to operations array
     *
     * @return void
     */
    private function processDisableAction( ?object $wc_product, array &$operations ) : void
    {
        if ( empty( $wc_product ) )
            return;

        $operations[ "wc" ][ "update" ][] = [
            "id" => $wc_product->product_id,
            "stock_status" => "outofstock",
        ];

        $operations[ "local" ][ "update" ][] = [
            "id" => $wc_product->id,
            "data" => ["stock_status" => "outofstock"]
        ];
    }

    /**
     * Process "update" action
     *
     * @param ProductNotification $notification
     * @param null|object         $wc_product
     * @param array               $operations             Reference to operations array
     * @param ?object             $wc_productnotification
     *
     * @return void
     */
    private function processUpdateAction( ProductNotification $notification, ?object $wc_product, array &$operations ) : void
    {
        if ( empty( $wc_product ) )
            return;

        $data = json_decode( $notification->meta, true );

        if ( $data["HasStockKeepingUnitRemovedFromAffiliate"] )
        {
            $operations["wc"]["delete"][] = $wc_product->product_id;
            $operations["local"]["delete"][] = $wc_product->id;

            return;
        }

        $product = $this->getProductData( sku: $notification->sku, full: $data["HasStockKeepingUnitModified"] );

        if ( $product === false )
        {
            $operations["wc"]["update"][] = [
                "id" => $wc_product->product_id,
                "stock_status" => "outofstock",
            ];

            $operations["local"]["update"][] = [
                "id" => $wc_product->id,
                "data" => ["stock_status" => "outofstock"]
            ];

            return;
        }

        if ( isset( $product["data"] ) )
        {
            $product = vtex_product_to_mkt(
                data    : $product["data"],
                context : $product["context"],
                simulate: $product["simulate"]
            );
        }

        $product["id"] = $wc_product->product_id;

        $operations["wc"]["update"][] = $product;

        $operations["local"]["updates"][] = [
            "id" => $wc_product->id,
            "data" => [
                "regular_price" => $product["regular_price"],
                "sale_price" => $product["sale_price"],
                "stock_status" => $product["stock_status"],
            ]
        ];
    }

    /**
     * Process "create" action
     *
     * @param ProductNotification $notification
     * @param null|object         $wc_product
     * @param array               $operations   Reference to operations array
     *
     * @return void
     */
    private function processCreateAction( ProductNotification $notification, ?object $wc_product, array &$operations ) : void
    {
        if ( ! empty( $wc_product ) )
            return;

        $data = $this->getProductData( sku: $notification->sku, create: true );

        if ( $data === null )
            return;

        $product = vtex_product_to_mkt(
            data    : $data["data"],
            context : $data["context"],
            simulate: $data["simulate"]
        );

        $operations["wc"]["create"][] = $product;

        $operations["local"]["create"][] = [
            "data" => [
                "store_id" => $this->store_id,
                "metadata_id" => $product["meta_data"][0]["value"],
                "product_sku" => $product["sku"],
                "regular_price" => $product["regular_price"],
                "sale_price" => $product["sale_price"],
                "stock_status" => $product["stock_status"],
            ]
        ];
    }

    /**
     * Get product data from VTEX API with improved error handling and retries
     *
     * @param int  $sku
     * @param bool $create
     * @param bool $full
     *
     * @return null|array|false
     */
    private function getProductData( int $sku, bool $create = false, bool $full = false ) : null|array|false
    {
        // Get SKU data with retry
        $data = $this->apiCallWithRetry(
            method_name: "getSkuData",
            callback: fn () => $this->getSkuData( sku: $sku )
        );

        if ( ! $data )
        {
            $this->logMessage(
                message: "Failed to get SKU data for {$sku}",
                type: "error"
            );

            return ! $create ? false : null;
        }

        // Get SKU context with retry
        $context = $this->apiCallWithRetry(
            method_name: "getSkuContext",
            callback: fn () => $this->getSkuContext( sku: $sku )
        );

        if ( ! $context )
        {
            $this->logMessage(
                message: "Failed to get SKU context for {$sku}",
                type: "error"
            );

            return ! $create ? false : null;
        }

        // Get simulation data with retry
        $simulate = $this->apiCallWithRetry(
            method_name: "getSimulate",
            callback: fn () => $this->getSimulate( id: $data["Id"] )
        );

        if ( ! $simulate || ! isset( $simulate["items"] ) )
        {
            $this->logMessage(
                message: "Failed to get simulation data for {$sku}",
                type: "error"
            );

            return ! $create ? false : null;
        }

        if ( $simulate["items"][0]["availability"] !== "available" )
        {
            $this->logMessage(
                message: "Product {$sku} is not available",
                type: "info"
            );

            return ! $create ? false : null;
        }

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
     * Make API call with retry mechanism
     *
     * @param string   $method_name  Name of the method for logging
     * @param callable $callback     The API call to execute
     * @param int      $max_attempts Maximum number of retry attempts
     *
     * @return mixed Result of the callback or null on permanent failure
     */
    private function apiCallWithRetry( string $method_name, callable $callback, int $max_attempts = 3 ) : mixed
    {
        $should_retry = static function ( Throwable $e )
        {
            // Define which exceptions are retryable
            // For example, network errors, rate limits, and timeouts are typically retryable
            $retry_error_messages = [
                "cURL error",
                "Connection refused",
                "Connection timed out",
                "rate limit",
                "timeout",
                "429", // Too Many Requests
                "503", // Service Unavailable
                "504", // Gateway Timeout
            ];

            foreach ( $retry_error_messages as $message )
                if ( mb_stripos( $e->getMessage(), $message ) !== false )
                    return true;

            // Retry on specific HTTP status codes
            $retry_status_codes = [408, 429, 500, 502, 503, 504];

            return (bool) ( method_exists( $e, "getCode" ) && in_array( $e->getCode(), $retry_status_codes ) );
        };

        try
        {
            return $this->withRetry(
                callback: $callback,
                max_attempts: $max_attempts,
                delay: 1000, // 1 second initial delay
                should_retry: $should_retry
            );
        }
        catch ( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message: $e->getMessage(),
                method: $method_name,
                type: "Throwable",
                e: $e
            );

            $this->logMessage( message: "API Call Failed: {$method_name} - {$e->getMessage()}", type: "error" );

            return null;
        }
    }

    /**
     * Execute a function with retry mechanism
     *
     * @param callable      $callback     Function to execute
     * @param int           $max_attempts Maximum number of attempts
     * @param int           $delay        Delay between attempts in milliseconds
     * @param null|callable $should_retry Function that determines if retry should be attempted
     *
     * @throws Exception Throws if all attempts fail
     *
     * @return mixed Result of the callback
     */
    private function withRetry( callable $callback, int $max_attempts = 3, int $delay = 1000, ?callable $should_retry = null ) : mixed
    {
        $attempts = 0;
        $last_exception = null;

        while ( $attempts < $max_attempts )
        {
            try
            {
                $attempts++;

                return $callback();
            }
            catch ( Throwable $e )
            {
                $last_exception = $e;

                // Skip retry if the error is not retry-able
                if ( $should_retry && ! $should_retry( $e ) )
                {
                    $this->logMessage(
                        message: "Non-retryable error: {$e->getMessage()}",
                        type: "error"
                    );

                    break;
                }

                if ( $attempts >= $max_attempts )
                {
                    $this->logMessage(
                        message: "Max retry attempts reached: {$e->getMessage()}",
                        type: "error"
                    );

                    break;
                }

                $this->logMessage(
                    message: "Retry attempt {$attempts}/{$max_attempts}: {$e->getMessage()}",
                    type: "error"
                );

                // Wait before next attempt (converting milliseconds to microseconds)
                usleep( $delay * 1000 );

                // Use exponential backoff
                $delay *= 2;
            }
        }

        throw $last_exception;
    }

    /**
     * Get SKU data from VTEX API with improved validation
     *
     * @param int $sku
     *
     * @return null|array|false
     */
    private function getSkuData( int $sku ) : array|false|null
    {
        $response = $this->product_service->getSkuData( sku: $sku );

        if ( $response[0] !== 200 )
        {
            $this->logMessage(
                type: "error",
                message: "VTEX API returned status {$response[0]} for SKU {$sku}",
                context: $response[1]
            );

            return null;
        }

        $product = $response[1]["data"];

        if ( $product["IsActive"] === false )
        {
            $this->logMessage(
                type: "info",
                message: "SKU {$sku} is not active in VTEX",
                context: $response[1]
            );

            return false;
        }

        return $product;
    }

    /**
     * Get SKU context from VTEX API with improved validation
     *
     * @param int $sku
     *
     * @return null|array|false
     */
    private function getSkuContext( int $sku ) : array|false|null
    {
        $response = $this->product_service->getSkuContext( sku: $sku );

        if ( $response[0] !== 200 )
        {
            $this->logMessage(
                type: "error",
                message: "VTEX API returned status {$response[0]} for SKU context {$sku}",
                context: $response[1]
            );

            return null;
        }

        $product = $response[1]["data"];

        if ( $product["IsActive"] === false && $product["IsProductActive"] === false )
        {
            $this->logMessage(
                type: "info",
                message: "SKU {$sku} is not active in VTEX",
                context: $response[1]
            );

            return false;
        }

        if ( ! in_array( $this->sale_channel, $product["SalesChannels"] ) )
        {
            $this->logMessage(
                type: "info",
                message: "SKU {$sku} is not available in sales channel {$this->sale_channel}",
                context: $response[1]
            );

            return false;
        }

        return $product;
    }

    /**
     * Get simulation data from VTEX API with improved error handling
     *
     * @param int $id
     *
     * @return null|array
     */
    private function getSimulate( int $id ) : ?array
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
        {
            $this->logMessage(
                type: "error",
                message: "VTEX API returned status {$response[0]} for simulation ID {$id}",
                context: $response[1]
            );

            return null;
        }

        // Filter and validate simulation data
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

    /**
     * Execute WooCommerce batch operations
     *
     * @param array $operations WooCommerce batch operations
     *
     * @return bool Success status
     */
    private function executeBatchWcOperations( array $operations ) : bool
    {
        try
        {
            $chunks = $this->prepareBatchChunks( data: $operations );
            $total_count = count( $chunks );

            foreach ( $chunks as $key => $chunk )
            {
                $group = ( $key + 1 ) . " of {$total_count}";

                $this->logMessage( message: "Updating marketplace products, {$group}" );

                if ( empty( $chunk["create"] ) && empty( $chunk["update"] ) && empty( $chunk["delete"] ) )
                    continue;

                $chunk = $this->cleanEmptyActions( chunk: $chunk );

                // Try with retry for batch operations
                $this->apiCallWithRetry(
                    method_name: "WC_Product::batch",
                    callback: static function () use ( $chunk )
                    {
                        WC_Product::batch( $chunk );

                        return true;
                    }
                );
            }

            return true;
        }
        catch ( Throwable $e )
        {
            $this->logMessage(
                message: "Failed to execute WooCommerce batch operations: {$e->getMessage()}",
                type: "error"
            );

            return false;
        }
    }

    /**
     * Prepare batch chunks from operations
     *
     * @param array $data
     *
     * @return array
     */
    private function prepareBatchChunks( array $data ) : array
    {
        $create_chunks = $this->chunkBatchOperation( data: $data, operation: "create" );
        $update_chunks = $this->chunkBatchOperation( data: $data, operation: "update" );
        $delete_chunks = $this->chunkBatchOperation( data: $data, operation: "delete" );

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
     * @param array  $data
     *
     * @return array
     */
    private function chunkBatchOperation( array $data, string $operation ) : array
    {
        if ( empty( $data[$operation] ) )
            return [];

        return array_chunk( $data[$operation], 30 );
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
     * Execute local database operations in batch
     *
     * @param array $operations Operations to execute
     *
     * @return bool Success status
     */
    private function executeBatchLocalOperations( array $operations ) : bool
    {
        try
        {
            $create_data = $operations["create"];
            $update_data = $operations["update"];
            $delete_data = $operations["delete"];

            // Process local creates
            if ( ! empty( $create_data ) )
            {
                $create_data = array_map(
                    static function ( $item )
                    {
                        return $item["data"];
                    },
                    $create_data );

                DB::table( table: "marketplace_product_skus" )->insert( values: $create_data );
            }

            // Process local updates
            foreach ( $update_data ?? [] as $update )
            {
                DB::table( table: "marketplace_product_skus" )
                    ->where( column: "id", operator: "=", value: $update["id"] )
                    ->update( values: $update["data"] );
            }

            // Process local deletes
            if ( ! empty( $delete_data ) )
            {
                DB::table( "marketplace_product_skus" )
                    ->whereIn( column: "id", values: $delete_data )
                    ->delete();
            }

            return true;
        }
        catch ( Throwable $e )
        {
            $this->logMessage(
                message: "Failed to execute local database operations: {$e->getMessage()}",
                type: "error"
            );

            return false;
        }
    }
}
