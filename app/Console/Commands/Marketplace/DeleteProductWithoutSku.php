<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Product;

class DeleteProductWithoutSku extends Command
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
    protected $signature = "mkt:delete_product_without_sku";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete products of Fast Shop when SKU is empty";

    /**
     * @var string
     */
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
        $message = "{$this->prefix} Starting process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $env = config( key: "vtex.env" );
        $has_ended = false;
        $page = 1;
        $per_page = 100;
        $delete = [];

        do
        {
            try
            {
                $search = [
                    "per_page" => $per_page,
                    "page" => $page,
                    "manage_stock" => false,
                    "stock_status" => "outofstock",
                    "status" => "private",
                ];

                $aux = Product::all( $search );

                $this->info( string: "Page: {$page} | Loaded: {$aux->count()}" );

                if ( $aux->count() < 100 ) $has_ended = true;

                $page++;

                foreach ( $aux as $product )
                {
                    if ( $product->store->id !== 4265 )
                        continue;

                    // not empty sku
                    if ( ! empty( $product->sku ) )
                        continue;

                    $this->info( string: "Product ID: {$product->id}" );

                    $delete[] = $product->id;

                    if ( count( $delete ) === 100 )
                    {
                        try
                        {
                            Product::batch( ["delete" => $delete] );
                        }
                        catch ( Throwable $e )
                        {
                            $this->error( string: $e->getMessage() );
                        }

                        $delete = [];
                    }
                }
            }
            catch ( Throwable $e )
            {
                $this->error( string: $e->getMessage() );

                $has_ended = false;
            }
        }
        while ( ! $has_ended );

        try
        {
            Product::batch( ["delete" => $delete] );
        }
        catch ( Throwable $e )
        {
            $this->error( string: $e->getMessage() );
        }

        $message = "{$this->prefix} Finished process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $diff = $start->diffInMinutes( now() );
        $message = "{$this->prefix} Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        return Command::SUCCESS;
    }
}
