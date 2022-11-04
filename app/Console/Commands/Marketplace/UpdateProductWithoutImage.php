<?php

namespace App\Console\Commands\Marketplace;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Codexshaper\WooCommerce\Facades\Product;

class UpdateProductWithoutImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "sku:private";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Change the status, of FS products, to private when without images";

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
        $ended = false;
        $page  = 1;
        $per   = 100;

        $aux   = [];

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
                    if ( strpos( $product->sku, "FS-" ) === false ) continue;

                    if ( empty( $product->images ) && count( $product->images ) == 0 )
                    {
                        $update[] = [
                            "id"     => $product->id,
                            "status" => "private",
                        ];
                    }

                    if ( count( $update ) == 100 )
                    {
                        Product::batch( [
                            "update" => $update
                        ] );

                        echo "Batch updated\n" . json_encode( $update );

                        $update = [];
                    }
                }
            }
            catch (Exception $e)
            {
                $ended = false;
            }
        }
        while ( !$ended );

        try
        {
            Product::batch( [
                "update" => $update
            ] );
        }
        catch ( Exception $e )
        {
            Log::error( $e );
        }

        echo "Last batch updated\n" . json_encode( $update );
    }
}
