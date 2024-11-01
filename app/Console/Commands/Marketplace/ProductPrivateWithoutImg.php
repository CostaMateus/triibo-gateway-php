<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Product;

class ProductPrivateWithoutImg extends Command
{
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
        $ended  = false;
        $page   = 1;
        $per    = 100;
        $update = [];

        do
        {
            try
            {
                $products = Product::all( [
                    "per_page" => $per,
                    "page"     => $page,
                    "status"   => "publish",
                ] );

                if ( $products->count() < 100 ) $ended = true;

                $page++;

                foreach ( $products as $product )
                {
                    if ( empty( $product->images ) && count( $product->images ) == 0 )
                    {
                        $update[] = [
                            "id"     => $product->id,
                            "status" => "private",
                        ];
                    }

                    if ( count( $update ) == 100 )
                    {
                        Product::batch( [ "update" => $update ] );

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
        while ( !$ended );

        try
        {
            Product::batch( [ "update" => $update ] );
        }
        catch ( Throwable $e )
        {
            $this->error( $e->getMessage() );
        }

        $this->info( "FInished" );
    }
}
