<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
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
            $message = "Products: Starting process WC data...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            $this->info( "Truncate table: marketplace_product_skus" );
            DB::table( "marketplace_product_skus" )->truncate();

            $this->processProducts();

            $message = "Products: Finished WC data process...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            $diff    = $start->diffInMinutes( now() );
            $message = "Products: Runtime {$diff} minutes...";
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
        $error    = false;

        do
        {
            $count = 0;

            try
            {
                $wc_products = Product::all( [ "per_page" => 100, "page" => $page, ] )->toArray();

                $count       = count( $wc_products );

                if ( ( $c = count( $wc_products ) ) != 0 )
                {
                    $this->info( "Products: {$c} loaded" );
                    $products = array_merge( $products, $wc_products );
                }

                $page++;

                if ( count( $products ) > 500 )
                {
                    $this->insertProducts( $products );
                    $products = [];
                }
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                {
                    $error = true;

                    log_message( channel: self::LOG_CHANNEL, message: print_r( $e, true ), level: "error" );
                    $this->error( $e->getMessage() );
                    break;
                }
            }
        }
        while ( $count == 100 );

        if ( $error )
        {
            $this->error( "Error while loading products" );
            return;
        }

        if ( count( $products ) > 0 )
        {
            $this->insertProducts( $products );
            $products = [];
        }
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
            $data[] = [
                "metadata_id"   => $this->searchMetadata( $product->meta_data ),
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

        $count   = count( $products );
        $message = "Inserting {$count} products in table: marketplace_product_skus";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        DB::table( "marketplace_product_skus" )->insert( $data );
    }
}
