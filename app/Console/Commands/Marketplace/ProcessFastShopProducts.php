<?php

namespace App\Console\Commands\Marketplace;

use Exception;
use App\Helpers\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class ProcessFastShopProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "sku:process_fs_products
                            {--A|action= : Action of products that will be processed. Options: create, update or delete }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Process FastShop new products";

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
        if ( !in_array( $this->option( "action" ), [ "create", "update", "delete" ] ) ) return;

        echo "Command : Starting process_fs_products\n";
        Log::channel( "fastshop" )->info( "sku:process_fs_products", [ "Starting process_fs_products" ] );

        $action = $this->option( "action" );
        $ended  = false;
        $array  = [];
        $i      = 0;

        do
        {
            echo "Command : Get products of local table\n";

            $products = DB::table( "fastshop_products" )
                          ->where( "action", $action   )
                          ->limit( 100 )
                          ->get();

            if ( $products->count() == 0  )
            {
                echo "Command : No products to process\n";
                Log::channel( "fastshop" )->info( "sku:process_fs_products", [ "No products to process" ] );
                break;
            }

            if ( $products->count() < 100 ) $ended = true;

            foreach ( $products as $data )
            {
                echo "Processing product ID: " . $data->id . "\n";

                $product                       = json_decode( $data->data, true );
                $product[ "CategoryFullPath" ] = ( is_null( $product[ "CategoryFullPath" ] ) ) ? [] : self::getCategoriesId( $product[ "CategoryFullPath" ] );
                $wcData                        = DataTranslator::translate( "newProduct", $product );
                $searchSku                     = Product::all( [ "sku" => $wcData[ "sku" ] ] );

                if ( $p = $searchSku->first() )
                {
                    DB::table( "fastshop_products" )->where( "id", $data->id )->delete();

                    $wcData[ "id" ] = $p->id;
                    $array[]        = $wcData;
                }

                if ( count( $array ) == 100 )
                {
                    echo "Sending to WC 100 products...\n";

                    try
                    {
                        Product::batch( [ $action => $array ] );
                        $array = [];
                        $i++;

                        Log::channel( "fastshop" )->info( "sku:process_fs_products", [ "#{$i} processing round" ] );
                    }
                    catch ( Exception $e )
                    {
                        echo "Fail : {$e->getMessage()}\n";
                        Log::channel( "fastshop" )->error( "sku:process_fs_products", [ $e->getMessage() ] );
                    }
                }
            }
        }
        while ( !$ended );

        if ( !empty( $array ) )
        {
            try
            {
                $count = count( $array );

                echo "Sending to WC last {$count} products...\n";

                Product::batch( [ $action => $array ] );

                $i = count( $array );
                Log::channel( "fastshop" )->info( "sku:process_fs_products", [ "#{$i} last processing round" ] );
            }
            catch ( \Exception $e )
            {
                echo "Fail : {$e->getMessage()}\n";
                Log::channel( "fastshop" )->error( "sku:process_fs_products", [ $e->getMessage() ] );
            }
        }

        echo "Command : Finalizing process_fs_products\n";
        Log::channel( "fastshop" )->info( "sku:process_fs_products", [ "Finalizing process_fs_products" ] );
    }

    protected function getCategoriesId( $data )
    {
        $data = explode( "/", $data );
        $cats = [];

        foreach ( $data as $key => $catName )
        {
            try
            {
                $slug = Helper::slugify( $catName, "-" );

                $cat  = DB::table( "marketplace_product_categories" )
                           ->where( "cat_slug", $slug )
                           ->first();

                if ( $cat )
                {
                    $cats[] = $cat->cat_id;
                    continue;
                }
                else
                {
                    $new = Category::all( [ "slug" => $slug ] );

                    if ( $new->first() )
                    {
                        $new = $new->first();
                        $new = [
                            "id"   => $new->id,
                            "slug" => $new->slug
                        ];
                    }
                    else
                    {
                        $new = Category::create( [
                            "name"   => $catName,
                            "parent" => ( $key == 0 ) ? 0 : $cats[ $key - 1 ]
                        ] );
                    }

                    $cats[] = $new[ "id" ];

                    $insert = [
                        "cat_id"   => $new[ "id"   ],
                        "cat_slug" => $new[ "slug" ]
                    ];

                    DB::table( "marketplace_product_categories" )->insert( $insert );
                }
            }
            catch( Exception $e )
            {
                Log::channel( "fastshop" )->info( "sku:process_fs_products", [ "Fail get category ID: {$catName}" ] );
            }
        }

        return $cats;
    }
}
