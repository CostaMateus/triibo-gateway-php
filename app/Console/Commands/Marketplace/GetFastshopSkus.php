<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;
use Exception;

class GetFastshopSkus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:get_fastshop_skus";

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
                        $dataInsert = [];

                        foreach( $data as $sku )
                        {
                            $product = DB::table( "marketplace_product_skus" )
                                        ->where( "product_id",  $sku[ "product_id"  ] )
                                        ->first();

                            if ( !$product )
                            {
                                $dataInsert[] = $sku;
                                continue;
                            }

                            DB::table( "marketplace_product_skus" )
                                ->where( "product_id",  $sku[ "product_id"  ] )
                                ->update( $sku );
                        }

                        DB::table( "marketplace_product_skus" )->insert( $dataInsert );

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

        echo "Finished sku processing\n";
    }
}
