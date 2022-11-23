<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;
use Exception;

class GetMktProductSkus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:get_skus";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get marketplace product skus";

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
        DB::table( "marketplace_product_skus" )->truncate();

        $ended = false;
        $page  = 1;
        $data  = [];

        echo "Starting sku processing\n";

        do
        {
            try
            {
                $search = [
                    "per_page" => 100,
                    "page"     => $page,
                ];

                $aux    = Product::all( $search );

                if ( $aux->count() < 100 ) $ended = true;

                $page++;

                foreach ( $aux as $p )
                {
                    // empty sku
                    if ( empty( $p->sku ) ) continue;

                    // not FS product
                    if ( strpos( $p->sku, "FS-" ) === false ) continue;

                    echo "Add sku : {$p->sku}\n";

                    $data[] = [
                        "product_id"  => $p->id,
                        "product_sku" => $p->sku,
                    ];

                    if ( count( $data ) == 100 )
                    {
                        DB::table( "marketplace_product_skus" )->insert( $data );
                        $data = [];
                    }
                }
            }
            catch ( Exception $e )
            {
                $ended = false;
            }
        }
        while ( !$ended );

        DB::table( "marketplace_product_skus" )->insert( $data );

        echo "Finished sku processing\n";
    }
}
