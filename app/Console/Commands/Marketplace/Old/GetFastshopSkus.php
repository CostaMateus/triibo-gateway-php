<?php

namespace App\Console\Commands\Marketplace\Old;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;

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
        $has_ended = false;
        $page = 1;
        $data = [];

        $this->info( "Starting sku processing" );

        do
        {
            try
            {
                $search = [
                    "per_page" => 100,
                    "page" => $page,
                ];

                $aux = Product::all( $search );

                if ( $aux->count() < 100 )
                    $has_ended = true;

                $page++;

                foreach ( $aux as $product )
                {
                    // empty sku
                    if ( empty( $product->sku ) )
                        continue;

                    // not FS product
                    if ( ! str_contains( $product->sku, "FS-" ) )
                        continue;

                    $this->info( "Add sku : {$product->sku}" );

                    $data[] = [
                        "product_id" => $product->id,
                        "product_sku" => $product->sku,
                    ];

                    if ( count( $data ) === 100 )
                    {
                        $dataInsert = [];

                        foreach ( $data as $sku )
                        {
                            $product = DB::table( "marketplace_product_skus" )
                                ->where( "product_id", $sku[ "product_id"  ] )
                                ->first();

                            if ( ! $product )
                            {
                                $dataInsert[] = $sku;

                                continue;
                            }

                            DB::table( "marketplace_product_skus" )
                                ->where( "product_id", $sku[ "product_id"  ] )
                                ->update( $sku );
                        }

                        DB::table( "marketplace_product_skus" )->insert( $dataInsert );

                        $data = [];
                    }
                }
            }
            catch ( Throwable $e )
            {
                $has_ended = false;

                $this->error( $e->getMessage() );
            }
        }
        while ( ! $has_ended );

        $this->info( "Finished" );
    }
}
