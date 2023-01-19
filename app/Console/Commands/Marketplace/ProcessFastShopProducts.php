<?php

namespace App\Console\Commands\Marketplace;

use Artisan;
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

        $action = $this->option( "action" );

        echo "Command : Starting process_fs_products - {$action}\n";
        Log::channel( "fastshop" )->info( "Product processing", [ "Starting process_fs_products - {$action}" ] );

        $this->processUpdate( $action );
    }

    private function processUpdate( $action )
    {
        $ended  = false;
        $array  = [];
        $i      = 0;

        do
        {
            echo "Command : Get products of local table\n";

            $products = DB::table( "fastshop_products" )
                          ->where( "action", $action   )
                          ->where( "status", null      )
                          ->limit( 100 )
                          ->get();

            // no products to process
            if ( $products->count() == 0 )
            {
                echo "Command : No products to process\n";
                Log::channel( "fastshop" )->info( "Product processing", [ "No products to process" ] );
                break;
            }

            if ( $products->count() < 100 ) $ended = true;

            foreach ( $products as $data )
            {
                echo "Processing local product_ID: " . $data->id . " | ";

                $product   = json_decode( $data->data, true );
                $product[ "CategoryFullPath" ] = ( is_null( $product[ "CategoryFullPath" ] ) ) ? [] : self::getCategoriesId( $product[ "CategoryFullPath" ] );

                $wcData    = DataTranslator::translate( "newProduct", $product );

                // create
                if ( $action == "create" )
                {
                    DB::table( "fastshop_products" )->where( "id", $data->id )->update( [ "status" => "processed" ] );
                    echo "Queued to create - {$wcData[ "sku" ]}\n";
                    $array[] = $wcData;
                }

                // update
                if ( $action == "update" )
                {
                    $searchSku = DB::table( "marketplace_product_skus" )->where( "product_sku", $wcData[ "sku" ] )->first();
                    DB::table( "fastshop_products" )->where( "id", $data->id )->update( [ "status" => "processed" ] );

                    if ( $searchSku )
                    {
                        echo "Match found, queued to update - {$wcData[ "sku" ]}\n";

                        // DB::table( "fastshop_products" )->where( "id", $data->id )->delete();

                        $wcData[ "id" ] = $searchSku->id;
                        $array[]        = $wcData;
                    }
                    else
                    {
                        echo "Match not find, skipping - {$wcData[ "sku" ]}\n";
                    }
                }

                // delete
                // if ( $action == "delete" ) {}

                if ( count( $array ) == 100 )
                {
                    echo "Sending to WC 100 products...\n";

                    try
                    {
                        foreach ( $array as $p )
                        {
                            Log::channel( "fastshop-upd" )->info( "Product update", [
                                "sku"            => $p[ "sku"            ],
                                "regular_price"  => $p[ "regular_price"  ],
                                "sale_price"     => $p[ "sale_price"     ],
                                "stock_quantity" => $p[ "stock_quantity" ],
                                "stock_status"   => $p[ "stock_status"   ],
                            ] );
                        }

                        Product::batch( [ $action => $array ] );
                        $array = [];
                        $i++;

                        Log::channel( "fastshop" )->info( "Product processing", [ "#{$i} processing round" ] );

                        $this->deleteSuccessfullyProcessed( $action );
                    }
                    catch ( Exception $e )
                    {
                        $this->treatException( $action, $array, $e );
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

                $this->deleteSuccessfullyProcessed( $action );

                $i++;
                Log::channel( "fastshop" )->info( "Product processing", [ "#{$i} last processing round" ] );
            }
            catch ( \Exception $e )
            {
                $this->treatException( $action, $array, $e );
            }
        }

        $this->reverseStatus( $action );

        echo "Command : Finalizing process_fs_products - {$action}\n";
        Log::channel( "fastshop" )->info( "Product processing", [ "Finalizing process_fs_products - {$action}" ] );

        if ( $action == "update" )
        {
            $products = DB::table( "fastshop_products" )
                          ->where( "action", "create"  )
                          ->where( "status", null      )
                          ->limit( 100 )
                          ->get();

            if ( $products ) Artisan::call( "sku:process_fs_products", [ "--action" => "create" ] );
        }
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
                Log::channel( "fastshop" )->info( "Product processing", [ "Fail get category ID: {$catName}" ] );
            }
        }

        return $cats;
    }

    protected function deleteSuccessfullyProcessed( $action )
    {
        DB::table( "fastshop_products" )
            ->where( "action", $action )
            ->where( "status", "processed" )
            ->delete();
    }

    protected function treatException( $action, &$array, $e )
    {
        if ( $e->getMessage() == "JSON ERROR: Syntax error" )
        {
            $array = [];
            $this->deleteSuccessfullyProcessed( $action );
        }
        else
        {
            $this->reverseStatus( $action );

            echo "Fail : {$e->getMessage()}\n";
            Log::channel( "fastshop" )->error( "Product processing", [ $e->getMessage() ] );
        }
    }

    protected function reverseStatus( $action )
    {
        $actionUpd = $action == "create" ? "update" : "create";

        DB::table( "fastshop_products"   )
          ->where( "status", "processed" )
          ->where( "action", $action     )
          ->update( [
                "action" => $actionUpd,
                "status" => null
            ] );
    }
}
