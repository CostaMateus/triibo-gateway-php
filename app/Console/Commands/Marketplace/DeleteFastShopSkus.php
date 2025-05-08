<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;

class DeleteFastShopSkus extends Command
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
    protected $signature = "mkt:delete-fs-skus";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete Fast Shop skus in marketplace";
    protected $prefix = "MKT DEL Products:";

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
        $start = now();
        $message = "{$this->prefix} Starting process WC data...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $this->processProducts();

        $this->info( string: "Truncate table: marketplace_product_skus" );
        DB::table( "marketplace_product_skus" )->truncate();

        $message = "{$this->prefix} Finished WC data process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $diff = $start->diffInMinutes( date: now() );
        $message = "{$this->prefix} Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );
    }

    /**
     * Search and delete products in mkt.
     *
     * @return void
     */
    private function processProducts() : void
    {
        $page = 1;
        $count = 100;
        $products = [];

        do
        {
            try
            {
                $response = Product::all( ["per_page" => 100, "page" => $page] );
                $count = $response->count();

                if ( $count !== 0 )
                {
                    $this->info( string: "{$this->prefix} {$count} loaded" );
                    $products = $response->toArray();

                    $this->deleteProducts( products: $products );
                }

                $page++;
            }
            catch ( Throwable $e )
            {
                $errors = [
                    "JSON ERROR: Syntax error",
                    "Undefined property: stdClass::\$message",
                ];

                if ( ! in_array( $e->getMessage(), $errors ) )
                {
                    report( exception: $e );

                    log_message( channel: self::LOG_CHANNEL, message: print_r( $e, true ), level: "error" );
                    $this->error( string: $e->getMessage() );

                    continue;
                }
            }
        }
        while ( $count === 100 );

        if ( count( $products ) > 0 )
            $this->deleteProducts( products: $products );
    }

    /**
     * Delete the product in mkt.
     *
     * @param array $products
     *
     * @return void
     */
    private function deleteProducts( array $products ) : void
    {
        $ids = [];

        foreach ( $products as $product )
        {
            $has_fs_id = $this->searchMetadata( metas: $product->meta_data );

            if ( $has_fs_id === true || $product->store?->id === 4265 )
            {
                $ids[] = $product->id;

                $message = "{$this->prefix} {$product->id} deleted";
                log_message( channel: self::LOG_CHANNEL, message: $message );
                $this->info( string: $message );
            }
        }

        if ( count( $ids ) > 0 )
            Product::batch( ["delete" => $ids] );
    }

    /**
     * Search metadata key.
     *
     * @param array  $metas
     * @param string $key
     *
     * @return bool
     */
    private function searchMetadata( array $metas, string $key = "_fs_product_id" ) : bool
    {
        foreach ( $metas as $meta )
            if ( $meta->key === $key )
                return true;

        return false;
    }
}
