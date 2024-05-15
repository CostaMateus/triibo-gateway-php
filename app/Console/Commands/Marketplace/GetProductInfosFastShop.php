<?php

namespace App\Console\Commands\Marketplace;

use Exception;
use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;

class GetProductInfosFastShop extends Command
{
    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL = "fastshop";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:get_product_infos_fs
                            {--action= : Action of products that will be processed. Options: create or update }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Process FastShop new products";

    private $action;

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
        if ( !in_array( $this->option( "action" ), [ "create", "update" ] ) ) return;

        $start = now();

        $this->action = $this->option( "action" );

        log_message( channel: self::LOG_CHANNEL, message: "Products: Starting process FS data - {$this->action}...", print: true );

        $this->process();

        log_message( channel: self::LOG_CHANNEL, message: "Products: Finished FS data process - {$this->action}...", print: true );

        $end   = now();
        $diff  = $start->diffInMinutes( $end );

        log_message( channel: self::LOG_CHANNEL, message: "Products: Runtime {$diff} minutes...", print: true );
    }

    private function process()
    {
        log_message( channel: self::LOG_CHANNEL, message: "Products: Get products of local table...", print: true );

        $products = DB::table( "fastshop_products" )
                        ->where( "action", $this->action )
                        ->where( function( $query ) { $query->where( "status", "unprocessed" )->orWhereNull( "status" ); } )
                        ->get()->toArray();

        $products = json_decode( json_encode( $products ), true );

        // no products to process
        if ( empty( $products ) )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Products: No products to process...", print: true );
            return;
        }

        $wcData     = [];
        $auxProduct = [];

        foreach ( $products as $data )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Products: Processing local product_id: " . $data[ "id" ], print: true );

            $product  = json_decode( $data[ "data" ], true );
            $product[ "CategoryFullPath" ] = ( is_null( $product[ "CategoryFullPath" ] ) ) ? [] : self::getCategoriesId( $product[ "CategoryFullPath" ] );

            $temp     = $this->productFsToWc( $product );
            $wcData[] = $temp;

            $auxProduct[] = [
                "id"  => $data[ "id"  ],
                "sku" => $temp[ "sku" ],
            ];
        }

        if ( $this->action == "create" )
        {
            $dataFs = Helper::prepareDataFs( $wcData, "sku" );
            $resp   = Helper::getStockPriceFS( $dataFs );
            $data   = $this->translateFsToWc( $resp );

            foreach ( $wcData as &$product )
            {
                $sku   = substr( $product[ "sku" ], 3 );
                $index = array_search( $sku, array_column( $data, "product_sku" ) );

                $product[ "regular_price"  ] = ( $index !== false ) ? $data[ $index ][ "regular_price" ] : "0.0" ;
                $product[ "sale_price"     ] = ( $index !== false ) ? $data[ $index ][ "sale_price"    ] : "0.0" ;
                $product[ "stock_quantity" ] = ( $index !== false ) ? $data[ $index ][ "stock"         ] : 0;
                $product[ "stock_status"   ] = ( $index !== false ) ? ( $data[ $index ][ "stock" ] > 0 ? "instock" : "outofstock" ) : "outofstock";
            }
        }

        $wcData = array_chunk( $wcData, 100 );
        $y      = count( $wcData );

        $fail   = [];

        foreach ( $wcData as $i => $skus )
        {
            ++$i;
            log_message( channel: self::LOG_CHANNEL, message: "Products: Sending SKUs to MKT - pack {$i} of {$y}", print: true );

            try
            {
                Product::batch( [ $this->action => $skus ] );
            }
            catch ( Exception $e )
            {
                log_message( channel: self::LOG_CHANNEL, message: "Products: Failure to send SKUs to MKT - pack {$i} of {$y}", print: true );

                if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                {
                    Helper::sendMailNotification( "marketplace", "process()", "Exception", $e );
                    $fail = array_merge( $fail, $skus );
                }
            }
        }

        foreach ( $auxProduct as $product )
        {
            if ( !empty( $fail ) )
                if ( false !== array_search( $product[ "sku" ], array_column( $fail, "sku" ) ) )
                    continue;

            DB::table( "fastshop_products" )
                ->where( "id", $product[ "id" ] )
                ->update( [
                    "product_sku" => $product[ "sku" ],
                    "action"      => "update",
                    "status"      => "processed",
                ] );
        }
    }

    private function getCategoriesId( $data )
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
            catch( Throwable $e )
            {
                $message = "Products: Failure to get mkt product category";

                log_exception( channel: self::LOG_CHANNEL, message: $message, method: "getCategoriesId", type: "Throwable", e: $e );
            }
        }

        return $cats;
    }

    private function productFsToWc( array $data )
    {
        $attributes = [];
        $categories = [];
        $images     = [];

        $slug       = \Str::slug( $data[ "ProductName" ] );
        $sku        = $data[ "ProductId" ] == $data[ "SellerStockKeepingUnitId" ] ? $data[ "ProductId" ] : $data[ "SellerStockKeepingUnitId" ];

        if ( !empty( $data[ "CategoryFullPath" ] ) )
            foreach ( $data[ "CategoryFullPath" ] as $ctg )
                $categories[] = [ "id" => $ctg ];

        foreach ( $data[ "Images" ] as $img )
        {
            if ( !Helper::validateImageUrl( $img[ "imageUrl" ] ) ) continue;

            $images[] = [
                "src"  => $img[ "imageUrl"  ],
                "name" => $img[ "imageName" ],
                "alt"  => $img[ "imageName" ]
            ];
        }

        // VERIFICAR OUTROS PRODUTOS
        foreach ( $data[ "SkuSpecifications" ] as $key => $attr )
        {
            $attributes[] = [
                "name"      => $attr[ "fieldName" ],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [
                    $attr[ "fieldValue" ]
                ],
            ];
        }
        foreach ( $data[ "ProductSpecifications" ] as $key => $attr )
        {
            if ( $attr[ "fieldName" ] == "ProductLineId" ) continue;

            $fieldValue   = ( $attr[ "fieldValue" ] === "true" ) ? "SIM" : $attr[ "fieldValue" ];

            $attributes[] = [
                "name"      => $attr[ "fieldName" ],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [ $fieldValue ],
            ];
        }

        $description   = $data[ "ProductDescription" ];

        $shortDescript = explode( "\r\n", $description )[ 0 ];
        $shortDescript = ( strlen( $shortDescript ) <= 50 ) ? $data[ "ProductName" ] : $shortDescript;
        $shortDescript = Str::limit( $shortDescript, 140, " ( ... )" );

        $attr_name     = "";
        $attr_val      = "";

        foreach ( $attributes as $att )
        {
            $name = Str::lower( $att[ "name" ] );

            if ( $name == "voltagem" )
            {
                $attr_name = Str::ucfirst( $name );
                $val       = reset( $att[ "options" ] );
                $attr_val  = ( $val == "110" || $val == "220" ) ? $val . "V" : $val;
            }
        }

        if ( $attr_name && $attr_val && $attr_val != "NA" ) $shortDescript .= "\r\n\r\n$attr_name : $attr_val";

        $wcData         = [
            "name"              => $data[ "ProductName" ],
            "slug"              => $slug,

            "description"       => $description,
            "short_description" => $shortDescript,
            "sku"               => "FS-$sku",

            "manage_stock"      => true,
            "backorders"        => "no",
            "sold_individually" => false,

            "weight"            => convert_weight( $data[ "WeightKg" ] ),
            "dimensions"        => [
                "length" => ( string ) $data[ "Length" ],
                "width"  => ( string ) $data[ "Width"  ],
                "height" => ( string ) $data[ "Height" ]
            ],

            "shipping_class"    => "fast-shop",

            "upsell_ids"        => [],
            "cross_sell_ids"    => [],
            "parent_id"         => 0,
            "purchase_note"     => "",

            "tags"              => [ [ "id" => 289 ] ],

            "categories"        => $categories,
            "images"            => $images,
            "attributes"        => $attributes,
        ];

        $wcData = json_encode( $wcData, JSON_UNESCAPED_SLASHES );
        $wcData = json_decode( $wcData, true                   );

        return $wcData;
    }

    private function translateFsToWc( $array )
    {
        $items = $array[ "items" ];
        $infos = $array[ "logisticsInfo" ];
        $data  = [];

        if ( empty( $infos ) )
        {
            foreach ( $items as $item )
            {
                $data[] = [
                    "product_sku"   => $item[ "id" ],
                    "regular_price" => insert_decimal( $item[ "listPrice" ] ),
                    "sale_price"    => insert_decimal( $item[ "price"     ] ),
                    "stock"         => 0,
                ];

                log_message( channel: self::LOG_CHANNEL, message: "Products: {$item[ "id" ]} processed", print: true );
            }

            log_message( channel: self::LOG_CHANNEL, message: "Products: Conversion finished", print: true );

            return $data;
        }

        foreach ( $infos as $info )
        {
            $index   = $info[ "itemIndex" ];
            $key     = array_search( $index, array_column( $items, "requestIndex" ) );
            $item    = $items[ $key ];

            $sku     = "FS-" . $item[ "id" ];
            $regular = $item[ "listPrice"    ];
            $sale    = $item[ "price"        ];
            $stock   = $info[ "stockBalance" ];

            $data[]  = [
                "product_sku"   => $sku,
                "regular_price" => insert_decimal( $regular ),
                "sale_price"    => insert_decimal( $sale    ),
                "stock"         => $stock,
            ];

            log_message( channel: self::LOG_CHANNEL, message: "Products: {$sku} processed", print: true );
        }

        log_message( channel: self::LOG_CHANNEL, message: "Products: Conversion finished", print: true );

        return $data;
    }
}
