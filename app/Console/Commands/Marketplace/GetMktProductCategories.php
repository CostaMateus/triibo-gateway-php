<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Category;

class GetMktProductCategories extends Command
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
        $date  = now();

        $ended = false;
        $page  = 1;
        $cats  = [];
        $data  = [];

        do
        {
            $search = [
                "per_page" => 100,
                "page"     => $page,
            ];

            $aux    = Category::all( $search );

            if ( $aux->count() < 100 ) $ended = true;

            $cats   = array_merge( $aux->toArray(), $cats );

            $page++;
        }
        while ( !$ended );

        foreach ( $cats as $cat )
        {
            $data[] = [
                "cat_id"   => $cat->id,
                "cat_slug" => $cat->slug,
            ];
        }

        \DB::table( "marketplace_product_categories" )->insert( $data );
    }
}
