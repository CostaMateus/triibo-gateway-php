<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Product;

class RemoveSkuFastShop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "sku:remove";

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
        $env   = config( "fastshop.env" );
        $ended = false;
        $page  = 1;
        $per   = 100;

        $fsIds = [
            ( $env == "local" ) ? 4454 : 4309, // 05 A
            ( $env == "local" ) ? 4453 : 3970, // padrao
            ( $env == "local" ) ? 4451 : 4310, // 14 B
            ( $env == "local" ) ? 4345 : 4311, // 33 C
        ];

        do
        {
            try
            {
                $search = [
                    "per_page"     => $per,
                    "page"         => $page,
                    "manage_stock" => true,
                ];

                $aux    = Product::all( $search );

                if ( $aux->count() < 100 ) $ended = true;

                $page++;

                foreach ( $aux as $p )
                {
                    // not empty sku
                    if ( !empty( $p->sku ) ) continue;

                    // not shipping-class of FS
                    if ( !in_array( $p->shipping_class_id, $fsIds ) ) continue;

                    Product::delete( $p->id, [ "force" => true ] );
                }
            }
            catch ( \Exception $e )
            {
                echo $e->getMessage() . "\n";
                $ended = false;
            }
        }
        while ( !$ended );
    }
}
