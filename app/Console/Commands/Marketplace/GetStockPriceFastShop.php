<?php

namespace App\Console\Commands\Marketplace;

use Exception;
use App\Helpers\Helper;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Codexshaper\WooCommerce\Facades\Product;

class GetStockPriceFastShop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:get_stock_price_FS";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get updated stock/price of FS products";

    private $env;
    private $fsUrl;
    private $fsHeaders;

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
        $start = now();

        $skus  = $this->updateFastShopStockPrice();
        $this->updateMarketplaceStockPrice( $skus );

        $end   = now();
        $diff  = $start->diffInMinutes( $end );

        echo "Runtime {$diff} minutes\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Runtime {$diff} minutes" ] );
    }


    // ==================== FASTSHOP =====================

    private function updateFastShopStockPrice()
    {
        echo "Starting FS stock/price update process\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Starting FS stock/price update process" ] );

        $skus = DB::table( "marketplace_product_skus" )->get()->toArray();
        $skus = json_decode( json_encode( $skus ), true );

        if ( empty( $skus ) ) return null;

        echo "SKUs loaded from local table\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "SKUs loaded from local table" ] );

        $aux  = [];

        foreach ( $skus as $sku )
        {
            $now   = Carbon::now();

            if ( in_array( $sku[ "status" ], [ null, "active" ] ) )
            {
                $upd = new Carbon( $sku[ "updated_at" ] );

                if ( $upd->diff( $now )->days <= 15 )
                {
                    $aux[] = $sku;
                    continue;
                }
                else
                {
                    $sku[ "status"         ] = "inactive";
                    $sku[ "inactivated_at" ] = now();

                    DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );
                    continue;
                }
            }

            $inact = new Carbon( $sku[ "inactivated_at" ] );
            if ( $sku[ "status" ] == "inactive" && $inact->diff( $now )->days > 15 )
            {
                $aux[] = $sku;
                continue;
            }
        }

        if ( empty( $aux ) )
        {
            echo "Starting FS stock/price - no data to process\n";
            Log::channel( "fastshop" )->info( "FS stock-price", [ "Starting FS stock/price - no data to process" ] );
            return;
        }

        $fsData   = Helper::prepareDataFs( $aux, "product_sku" );
        $response = Helper::getStockPriceFS( $fsData );
        $data     = $this->translateFsData( $response );

        echo "Updating the local table\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Updating the local table" ] );

        foreach ( $aux as &$sku )
        {
            $index = array_search( $sku[ "product_sku" ], array_column( $data, "product_sku" ) );

            if ( $index !== false )
            {
                $product = $data[ $index ];

                $sku[ "updated_at" ] = ( $sku[ "stock" ] != $product[ "stock" ] ) ? now() : $sku[ "updated_at" ];

                if ( in_array( $sku[ "status" ], [ null, "inactive" ] ) )
                {
                    if (
                        $product[ "stock"         ] == 0 &&
                        $product[ "stock"         ] == $sku[ "stock"         ] &&
                        $product[ "regular_price" ] == $sku[ "regular_price" ] &&
                        $product[ "sale_price"    ] == $sku[ "sale_price"    ] )
                    {
                        $sku[ "status"         ] = "inactive";
                        $sku[ "inactivated_at" ] = now();
                    }
                    else
                    {
                        $sku[ "status"         ] = "active";
                        $sku[ "inactivated_at" ] = null;
                    }
                }

                $sku[ "regular_price"  ] = $product[ "regular_price" ];
                $sku[ "sale_price"     ] = $product[ "sale_price"    ];
                $sku[ "stock"          ] = $product[ "stock"         ];
                $sku[ "consulted_at"   ] = now();

                $ok = DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );

                if ( !$ok )
                {
                    echo "{$sku[ "product_sku" ]} fails to save SKU\n";
                    Log::channel( "fastshop" )->info( "FS stock-price", [ "{$sku[ "product_sku" ]} fails to save SKU" ] );
                }
            }
            else
            {
                $sku[ "stock"          ] = 0;
                $sku[ "status"         ] = "inactive";
                $sku[ "consulted_at"   ] = now();
                $sku[ "inactivated_at" ] = ( !$sku[ "inactivated_at" ] ) ? $sku[ "inactivated_at" ] : now();

                $ok = DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );

                if ( !$ok )
                {
                    echo "{$sku[ "product_sku" ]} fails to save SKU\n";
                    Log::channel( "fastshop" )->info( "FS stock-price", [ "{$sku[ "product_sku" ]} fails to save SKU" ] );
                }
            }

            if ( $ok ) echo "{$sku[ "product_sku" ]} successfully saved\n";
        }

        echo "Finished FS stock/price update process\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Finished FS stock/price update process" ] );

        return $aux;
    }

    private function translateFsData( $array )
    {
        echo "Converting FS response data\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Converting FS response data" ] );

        try
        {
            $items = $array[ "items" ];
            $infos = $array[ "logisticsInfo" ];
            $data  = [];

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
                    "regular_price" => Helper::insertDecimal( $regular ),
                    "sale_price"    => Helper::insertDecimal( $sale    ),
                    "stock"         => $stock,
                ];

                echo "{$sku} processed\n";
            }

            echo "Conversion finished\n";

            return $data;
        }
        catch ( Exception $e )
        {
            echo "FS response data conversion failed\n";
            Log::channel( "fastshop" )->info( "FS stock-price", [ "FS response data conversion failed" ] );
            Helper::sendMailNotification( "marketplace", "translateFsData()", "Exception", $e );
        }
    }


    // =================== MARKETPLACE ===================

    private function updateMarketplaceStockPrice( $array )
    {
        if ( empty( $array ) ) return;

        echo "Starting MKT stock/price update process\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Starting MKT stock/price update process" ] );

        $data = $this->translateWcData( $array );
        $data = array_chunk( $data, 100 );
        $y    = count( $data );

        foreach ( $data as $i => $skus )
        {
            ++$i;
            echo "Sending SKUs to MKT - pack {$i} of {$y}\n";
            Log::channel( "fastshop" )->info( "FS stock-price", [ "Sending SKUs to MKT - pack {$i} of {$y}" ] );

            try
            {
                Product::batch( [ "update" => $skus ] );
            }
            catch ( Exception $e )
            {
                echo "Failure to send SKUs to MKT - pack {$i} of {$y}\n";
                echo $e->getMessage() . "\n";
                Log::channel( "fastshop" )->info( "FS stock-price", [ "Failure to send SKUs to MKT - pack {$i} of {$y}" ] );
                Helper::sendMailNotification( "marketplace", "updateMarketplaceStockPrice()", "Exception", $e );
            }
        }

        echo "Finished MKT stock/price update process\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Finished MKT stock/price update process" ] );
    }

    private function translateWcData( $array )
    {
        echo "Converting data WC format\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Converting data WC format" ] );

        $data = [];

        foreach ( $array as $sku )
        {
            $data[] = [
                "id"             => $sku[ "product_id"    ],
                "sku"            => $sku[ "product_sku"   ],
                "regular_price"  => $sku[ "regular_price" ],
                "sale_price"     => $sku[ "sale_price"    ],
                "stock_quantity" => $sku[ "stock" ],
                "stock_status"   => $sku[ "stock" ] == 0 ? "outofstock" : "instock",
            ];
        }

        return $data;
    }
}
