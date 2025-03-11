<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Codexshaper\WooCommerce\Facades\Product;
use Illuminate\Support\Facades\{ DB, Cache };

class GetProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:get_products";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get marketplace products";

    /**
     *
     * @var     string
     */
    protected $prefix      = "MKT Get Products:";

    /**
     *
     * @var     string
     */
    protected const LOG_CHANNEL = "marketplace";

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
        Cache::put( "mkt:get_products_running", true, now()->addMinutes( 30 ) );

        try
        {
            $start   = now();
            $message = "{$this->prefix} Starting process WC data...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            $this->info( "Truncate table: marketplace_product_skus" );
            DB::table( "marketplace_product_skus" )->truncate();

            $this->processProducts();

            $message = "{$this->prefix} Finished WC data process...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            $diff    = $start->diffInMinutes( now() );
            $message = "{$this->prefix} Runtime {$diff} minutes...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );
        }
        finally
        {
            Cache::forget( "mkt:get_products_running" );
        }
    }

    /**
     *
     * @return  void
     */
    private function processProducts() : void
    {
        $page     = 1;
        $products = [];
        $count    = 100;

        do
        {
            try
            {
                $response = Product::all( [ "per_page" => 100, "page" => $page ] );
                $count    = $response->count();

                if ( $count != 0 )
                {
                    $this->info( "{$this->prefix} {$count} loaded" );
                    $products = $response->toArray();

                    $this->insertProducts( $products );
                }

                $page++;
            }
            catch ( \Throwable $e )
            {
                $errors = [
                    "JSON ERROR: Syntax error",
                    "Undefined property: stdClass::\$message",
                ];

                if ( !in_array( $e->getMessage(), $errors ) )
                {
                    report( $e );

                    log_message( channel: self::LOG_CHANNEL, message: print_r( $e, true ), level: "error" );
                    $this->error( $e->getMessage() );
                    continue;
                }
            }
        }
        while ( $count == 100 );

        if ( count( $products ) > 0 )
            $this->insertProducts( $products );
    }

    /**
     *
     * @param   array   $metas
     * @param   string  $key
     * @return  mixed
     */
    private function searchMetadata( array $metas, string $key = "_fs_product_id" ) : mixed
    {
        foreach ( $metas as $meta )
            if ( $meta->key == $key )
                return $meta->value;

        return null;
    }

    /**
     *
     * @param   array   $products
     * @return  void
     */
    private function insertProducts( array $products ) : void
    {
        $data    = [];

        foreach ( $products as $product )
        {
            $founded     = false;
            $metadata_id = $this->searchMetadata( $product->meta_data );

            foreach ( $data as $value )
            {
                if ( $value[ "metadata_id" ] == $metadata_id )
                {
                    $founded = true;
                    break;
                }
            }

            if ( $founded )
                continue;

            $data[] = [
                "metadata_id"   => $metadata_id,
                "store_id"      => $product->store?->id     ?: null,
                "product_id"    => $product->id             ?: null,
                "product_sku"   => $product->sku            ?: null,
                "regular_price" => $product->regular_price  ?: null,
                "sale_price"    => $product->sale_price     ?: null,
                "stock_status"  => $product->stock_status   ?: null,
                "stock"         => $product->stock_quantity ?: null,
                "status"        => $product->status         ?: null,
            ];
        }

        $count   = count( $data );
        $message = "MKT Inserting {$count} products in table: marketplace_product_skus";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        DB::table( "marketplace_product_skus" )->upsert(
            [ $data ],
            [ "product_id", "product_sku" ],
            [ "metadata_id", "store_id", "product_id", "product_sku", "regular_price", "sale_price", "stock_status", "stock", "status" ]
        );
    }
}
