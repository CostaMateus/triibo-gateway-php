<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Product;

class ProductPrivateWithoutImg extends Command
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
    protected $signature = "mkt:product_private_without_img";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Change the status of products to private when without images";

    /**
     * @var string
     */
    protected $prefix = "MKT Without Img Products:";

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

        $ended = false;
        $page = 1;
        $per = 100;
        $update = [];

        do
        {
            try
            {
                $products = Product::all( [
                    "per_page" => $per,
                    "page" => $page,
                    "status" => "publish",
                ] );

                if ( $products->count() < 100 ) $ended = true;

                $page++;

                foreach ( $products as $product )
                {
                    if ( empty( $product->images ) && count( $product->images ) === 0 )
                    {
                        $update[] = [
                            "id" => $product->id,
                            "status" => "private",
                        ];
                    }

                    if ( count( $update ) === 100 )
                    {
                        Product::batch( ["update" => $update] );

                        $update = [];
                    }
                }
            }
            catch ( Throwable $e )
            {
                $this->error( $e->getMessage() );

                $ended = false;
            }
        }
        while ( ! $ended );

        try
        {
            Product::batch( ["update" => $update] );
        }
        catch ( Throwable $e )
        {
            $this->error( $e->getMessage() );
        }

        $message = "{$this->prefix} Finished process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff = $start->diffInMinutes( now() );
        $message = "{$this->prefix} Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        return Command::SUCCESS;
    }
}
