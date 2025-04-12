<?php

namespace App\Console\Commands\Marketplace\Old;

use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;

class GetStockPriceFastShop extends Command
{
    /**
     * Define a class constant for the log channel.
     *
     * @var string
     */
    private const LOG_CHANNEL = "marketplace";

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

        $skus = $this->updateFastShopStockPrice();

        if ( $skus );
        $this->updateMarketplaceStockPrice( $skus );

        $end = now();
        $diff = $start->diffInMinutes( $end );

        $message = "Stock|Price: Runtime {$diff} minutes";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );
    }

    // ==================== FASTSHOP =====================

    private function updateFastShopStockPrice()
    {
        $message = "Stock|Price: Starting update process...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $skus = DB::table( "marketplace_product_skus" )->get()->toArray();
        $skus = json_decode( json_encode( $skus ), true );

        if ( empty( $skus ) )
            return;

        $message = "Stock|Price: SKUs loaded from local table...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $aux = [];
        $now = Carbon::now();

        foreach ( $skus as $sku )
        {
            if ( in_array( $sku[ "status" ], [null, "active"] ) )
            {
                $upd = new Carbon( $sku[ "updated_at" ] );

                if ( $upd->diff( $now )->days <= 15 )
                {
                    $aux[] = $sku;

                    continue;
                }

                $sku[ "status"         ] = "inactive";
                $sku[ "inactivated_at" ] = now();

                DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );

                continue;

            }

            $inact = new Carbon( $sku[ "inactivated_at" ] );

            if ( $sku[ "status" ] === "inactive" && $inact->diff( $now )->days > 15 )
            {
                $aux[] = $sku;

                continue;
            }
        }

        if ( empty( $aux ) )
        {
            $message = "Stock|Price: No data to process...";

            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            return;
        }

        $fsData = Helper::prepareDataFs( $aux, "product_sku" );
        $response = Helper::getStockPriceFS( $fsData );

        if ( empty( $response ) )
            return;

        $data = $this->translateFsData( $response );

        $message = "Stock|Price: Updating the local table...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        foreach ( $aux as &$sku )
        {
            $index = array_search( $sku[ "product_sku" ], array_column( $data, "product_sku" ) );

            if ( $index !== false )
            {
                $product = $data[ $index ];

                $upd = new Carbon( $sku[ "updated_at" ] );

                if (
                    $product[ "stock"         ] === 0
                    && $product[ "stock"         ] === $sku[ "stock"         ]
                    && $product[ "regular_price" ] === $sku[ "regular_price" ]
                    && $product[ "sale_price"    ] === $sku[ "sale_price"    ] )
                {
                    if ( $upd->diff( $now )->days > 15 || in_array( $sku[ "status" ], [null, "inactive"] ) )
                    {
                        $sku[ "status"         ] = "inactive";
                        $sku[ "inactivated_at" ] = now();
                    }
                }
                else
                {
                    $sku[ "status"         ] = "active";
                    $sku[ "updated_at"     ] = now();
                    $sku[ "inactivated_at" ] = null;
                }

                $sku[ "regular_price" ] = $product[ "regular_price" ];
                $sku[ "sale_price"    ] = $product[ "sale_price"    ];
                $sku[ "stock"         ] = $product[ "stock"         ];
                $sku[ "consulted_at"  ] = now();

                $ok = DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );

                if ( ! $ok )
                {
                    $message = "Stock|Price: {$sku[ "product_sku" ]} fails to save SKU...";

                    log_message( channel: self::LOG_CHANNEL, message: $message );
                    $this->info( $message );
                }
            }
            else
            {
                $sku[ "stock"          ] = 0;
                $sku[ "status"         ] = "inactive";
                $sku[ "consulted_at"   ] = now();
                $sku[ "inactivated_at" ] = ( ! $sku[ "inactivated_at" ] ) ? $sku[ "inactivated_at" ] : now();

                $ok = DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );

                if ( ! $ok )
                {
                    $message = "Stock|Price: {$sku[ "product_sku" ]} fails to save SKU...";

                    log_message( channel: self::LOG_CHANNEL, message: $message );
                    $this->info( $message );
                }
            }

            if ( $ok )
            {
                $message = "Stock|Price: {$sku[ "product_sku" ]} successfully saved...";

                log_message( channel: self::LOG_CHANNEL, message: $message );
                $this->info( $message );
            }
        }

        $message = "Stock|Price: Finished update process...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        return $aux;
    }

    private function translateFsData( $array )
    {
        $message = "Stock|Price: Converting FS response data...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        try
        {
            $items = $array[ "items" ];
            $infos = $array[ "logisticsInfo" ];
            $data = [];

            foreach ( $infos as $info )
            {
                $index = $info[ "itemIndex" ];
                $key = array_search( $index, array_column( $items, "requestIndex" ) );
                $item = $items[ $key ];

                $sku = "FS-".$item[ "id" ];
                $regular = $item[ "listPrice"    ];
                $sale = $item[ "price"        ];
                $stock = $info[ "stockBalance" ];

                $data[] = [
                    "product_sku" => $sku,
                    "regular_price" => insert_decimal( $regular ),
                    "sale_price" => insert_decimal( $sale ),
                    "stock" => $stock,
                ];

                $message = "Stock|Price: {$sku} processed...";

                log_message( channel: self::LOG_CHANNEL, message: $message );
                $this->info( $message );
            }

            $message = "Stock|Price: Conversion finished...";

            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            return $data;
        }
        catch ( Throwable $e )
        {
            $message = "Stock|Price: Failure on conversion FS response data";

            log_exception( channel: self::LOG_CHANNEL, message: $message, method: "translateFsData", type: "Throwable", e: $e );
            $this->info( $message );
        }
    }

    // =================== MARKETPLACE ===================

    private function updateMarketplaceStockPrice( $array ) : void
    {
        if ( empty( $array ) ) return;

        $message = "Stock|Price: Starting MKT update process...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $data = $this->translateWcData( $array );
        $data = array_chunk( $data, 100 );
        $y = count( $data );

        foreach ( $data as $i => $skus )
        {
            $i++;
            $message = "Stock|Price: Sending SKUs to MKT - pack {$i} of {$y}";

            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( $message );

            try
            {
                Product::batch( ["update" => $skus] );
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() !== "JSON ERROR: Syntax error" )
                {
                    $message = "Stock|Price: Failure to send SKUs to MKT - pack {$i} of {$y}";

                    log_exception( channel: self::LOG_CHANNEL, message: $message, method: "updateMarketplaceStockPrice", type: "Throwable", e: $e );
                    $this->info( $message );
                }
            }
        }

        $message = "Stock|Price: Finished MKT update process...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );
    }

    private function translateWcData( $array )
    {
        $message = "Stock|Price: Converting data WC format...";

        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $data = [];

        foreach ( $array as $sku )
        {
            $data[] = [
                "id" => $sku[ "product_id"    ],
                "sku" => $sku[ "product_sku"   ],
                "regular_price" => $sku[ "regular_price" ],
                "sale_price" => $sku[ "sale_price"    ],
                "stock_quantity" => $sku[ "stock" ],
                "stock_status" => $sku[ "stock" ] === 0 ? "outofstock" : "instock",
            ];
        }

        return $data;
    }
}
