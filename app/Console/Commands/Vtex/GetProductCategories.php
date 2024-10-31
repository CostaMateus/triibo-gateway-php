<?php

namespace App\Console\Commands\Vtex;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Vtex\ProductService;
use App\Services\Vtex\ConnectorService;
use Codexshaper\WooCommerce\Facades\Category;
use Throwable;

class GetProductCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "vtex:get-product-category-list";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get product category list";

    protected $sale_channel;
    protected $product_service;
    // protected $simulation_service;

    /**
     * Create a new command instance.
     *
     * @return  void
     */
    public function __construct()
    {
        parent::__construct();

        $seller                = "fast_shop";
        $env                   = config( "vtex.partners.{$seller}.env" );
        $this->sale_channel    = config( "vtex.partners.{$seller}.{$env}.sales_channel" );

        $connector             = new ConnectorService( $seller );
        $connector->enableLogNotify( false );

        $this->product_service = new ProductService( $connector );
    }

    /**
     * Execute the console command.
     *
     * @return  void
     */
    public function handle() : void
    {
        $skus = $this->getAllSkus();

        if ( count( $skus ) == 0 )
        {
            $this->error( "Error on load skus from connector" );
            return;
        }

        $this->getAllProducts( $skus );
    }

    /**
     * Get all skus list
     *
     * @return  array
     */
    private function getAllSkus() : array
    {
        $page      = 1;
        $skus_list = [];
        $count     = 500;

        do
        {
            $response = $this->product_service->initialSkuLoad( $page );

            if ( $response[ 0 ] != 200 )
            {
                $this->error( "Error on load skus from connector" );

                $count = 0;

                return [];
            }

            $count     = count( $response[ 1 ][ "data" ] );
            $skus_list = array_merge( $skus_list, $response[ 1 ][ "data" ] );

            $this->info( "Load {$count}" );

            $page++;
        }
        while ( $count == 500 );

        $this->info( "List of skus, loaded successfully" );

        return $skus_list;
    }

    /**
     * Get all products
     *
     * @param   array   $skus
     * @return  void
     */
    private function getAllProducts( array $skus ) : void
    {
        $fails    = [];
        $products = [];

        foreach ( $skus as $sku )
        {
            $context          = $this->getSkuContext( $sku );

            if ( is_null( $context ) )
            {
                $fails[] = $sku;
                continue;
            }

            $products[ $sku ] = $context;

            if ( count( $products ) == 50 )
            {
                $this->send_categories( $products );

                $products = [];

                $this->info( "=================== Reset ===================" );
            }
        }

        save_file( name: "vtex-fails-sku", data: $fails );

        $this->info( "=================== Finish ==================" );
    }

    /**
     * Undocumented function
     *
     * @param   integer     $sku
     * @return  array|null
     */
    private function getSkuContext( int $sku ) : array|null
    {
        $response   = $this->product_service->getSkuContext( $sku );

        if ( $response[ 0 ] != 200 )
            return null;

        $product    = $response[ 1 ][ "data" ];

        if ( $product[ "IsActive" ] == false && $product[ "IsProductActive" ] == false )
            return null;

        if ( !in_array( $this->sale_channel, $product[ "SalesChannels" ] ) )
            return null;

        $this->info( "SKU: {$sku} | {$product[ "CategoriesFullPath" ][ 0 ]}" );

        $categories = explode( "/", $product[ "CategoriesFullPath" ][ 0 ] );
        $categories = array_filter( $categories );
        $categories = array_values( $categories );

        $data       = [
            "id" => $product[ "Id" ],
            "categories_id"   => $categories,
            "categories_name" => $product[ "ProductCategories" ],
        ];

        return $data;
    }

    /**
     * Undocumented function
     *
     * @param   array   $products
     * @return  void
     */
    private function send_categories( array $products ) : void
    {
        foreach ( $products as $product )
        {
            try
            {
                $categories = [];
                $ids        = $product[ "categories_id"   ];
                $names      = $product[ "categories_name" ];

                foreach ( $ids as $index => $id )
                {
                    try
                    {
                        $slug     = Str::slug( $names[ $id ], "-" );
                        $category = DB::table( "marketplace_product_categories" )->where( "cat_slug", $slug )->first();

                        if ( $category == null )
                        {
                            $search = Category::where( "slug", $slug )->first();

                            if ( $search->isNotEmpty() )
                            {
                                $new = [
                                    "id"   => $search[ "id"   ],
                                    "slug" => $search[ "slug" ],
                                ];
                            }
                            else
                            {
                                $parent = ( $index == 0 ) ? 0 : $categories[ $index - 1 ];

                                $new    = Category::create( [
                                    "name"   => $names[ $id ],
                                    "parent" => $parent,
                                ] );
                            }

                            $categories[] = $new[ "id" ];

                            DB::table( "marketplace_product_categories" )->insert( [
                                "cat_id"   => $new[ "id"   ],
                                "cat_slug" => $new[ "slug" ]
                            ] );
                        }
                        else
                        {
                            $categories[] = $category->cat_id;
                        }
                    }
                    catch ( Throwable $e )
                    {
                        dd( $product, $categories, $index, $id, $e->getMessage() );
                    }
                }
            }
            catch ( Throwable $e )
            {
                dd( $categories, $product, $e->getMessage() );
            }
        }
    }
}
