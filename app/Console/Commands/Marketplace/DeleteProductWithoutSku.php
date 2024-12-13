<?php

namespace App\Console\Commands\Marketplace;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Codexshaper\WooCommerce\Facades\Product;
use Throwable;

class DeleteProductWithoutSku extends Command
{
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
        $env                = config( "vtex.env" );
        $has_ended          = false;
        $page               = 1;
        $per_page           = 100;
        $delete             = [];
        $shipping_class_ids = [
            4744,                              // Sem HUB
            3970,                              // Padrão
            ( $env == "local" ) ? 4824 : 4799, // Normal
        ];

        do
        {
            try
            {
                $search = [
                    "per_page"     => $per_page,
                    "page"         => $page,
                    "manage_stock" => false,
                    "stock_status" => "outofstock",
                    "status"       => "private",
                ];

                $aux    = Product::all( $search );

                $this->info( "Page: {$page} | Loaded: {$aux->count()}" );

                if ( $aux->count() < 100 ) $has_ended = true;

                $page++;

                foreach ( $aux as $product )
                {
                    if ( $product->store->id != 4265 )
                        continue;

                    // not empty sku
                    if ( !empty( $product->sku ) )
                        continue;

                    // // not shipping-class of FS
                    // if ( !in_array( $product->shipping_class_id, $shipping_class_ids ) )
                    //     continue;

                    $this->info( $product->id );

                    $delete[] = [
                        "id"    => $product->id,
                        "force" => true
                    ];

                    if ( count( $delete ) == 100 )
                    {
                        try
                        {
                            Product::batch( [ "delete" => $delete ] );
                        }
                        catch ( Throwable $e )
                        {
                            $this->error( $e->getMessage() );
                        }

                        $delete = [];
                    }
                }
            }
            catch ( Throwable $e )
            {
                $this->error( $e->getMessage() );

                $has_ended = false;
            }
        }
        while ( !$has_ended );

        try
        {
            Product::batch( [ "delete" => $delete ] );
        }
        catch ( Throwable $e )
        {
            $this->error( $e->getMessage() );
        }

        $this->info( "Finished" );
    }
}
