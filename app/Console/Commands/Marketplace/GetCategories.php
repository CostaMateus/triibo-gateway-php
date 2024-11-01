<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Category;

class GetCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:get_categories";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get marketplace product categories";

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
        try
        {
            $this->info( "Truncate table: marketplace_product_categories" );

            DB::table( "marketplace_product_categories" )->truncate();

            $page       = 1;
            $categories = [];
            $data       = [];

            do
            {
                $this->info( "Searching for categories in the marketplace, page {$page}" );

                $response   = Category::all( [
                    "per_page" => 100,
                    "page"     => $page,
                ] );

                $categories = array_merge( $response->toArray(), $categories );

                $page++;
            }
            while ( $response->count() == 100 );

            $this->info( "Loaded categories" );

            foreach ( $categories as $category )
            {
                $data[] = [
                    "cat_id"   => $category->id,
                    "cat_slug" => $category->slug,
                ];
            }

            $this->info( "Inserting in table: marketplace_product_categories" );

            DB::table( "marketplace_product_categories" )->insert( $data );

            $this->info( "Finished" );
        }
        catch ( Throwable $e )
        {
            $this->error( $e->getMessage() );
        }
    }
}
