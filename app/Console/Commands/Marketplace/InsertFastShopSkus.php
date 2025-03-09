<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;
use Storage;

class InsertFastShopSkus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var     string
     */
    protected $signature   = "mkt:insert-fs-skus";

    /**
     * The console command description.
     *
     * @var     string
     */
    protected $description = "Insert Fast Shop skus in marketplace";

    protected $prefix      = "MKT INS Products:";

    /**
     *
     * @var     string
     */
    protected const LOG_CHANNEL = "marketplace";

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
        $start   = now();
        $message = "{$this->prefix} Starting process WC data...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $indexes = range( 1, 23 );

        DB::table( "marketplace_product_skus" )->truncate();

        foreach ( $indexes as $i )
            $this->process( $i );

        $message = "{$this->prefix} Finished WC data process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff    = $start->diffInMinutes( now() );
        $message = "{$this->prefix} Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );
    }

    /**
     *
     * @param   integer     $i
     * @return  void
     */
    private function process( int $i ) : void
    {
        $raw_products = Storage::get( path: "fast-shop-products/fs-products-{$i}.json" );
        $fs_products  = json_decode( $raw_products, true );

        foreach ( $fs_products as $id => &$product )
        {
            if ( in_array( false, [ $product[ "data" ][ "IsActive" ], $product[ "context" ][ "IsActive" ] ] ) )
            {
                unset( $fs_products[ $id ] );
                continue;
            }

            $this->info( string: "Product ID: {$id}" );
        }

        $message     = "{$this->prefix} Translating data...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        //
        $wc_products = $this->translateProducts( products: $fs_products );

        $message     = "{$this->prefix} Inserting in local, " . count( $wc_products ) . " products...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        //
        $this->createLocalProduct( products: $wc_products );

        $message     = "{$this->prefix} Sending to marketplace, " . count( $wc_products ) . " products...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        //
        $this->sendToMarketplace( wc_products: $wc_products, fs_products: $fs_products );
    }

    /**
     *
     * @param   array   $products
     * @return  array|null
     */
    private function translateProducts( array $products ) : array|null
    {
        try
        {
            $wc_data = [];

            foreach( $products as $sku => $product )
            {
                $s               = str_pad( $sku, 5, " ", STR_PAD_LEFT );
                $message         = "{$this->prefix}: sku {$s} | " . $product[ "data" ][ "Name" ];

                log_message( channel: self::LOG_CHANNEL, message: $message );
                $this->info( string: $message );

                $wc_data[ $sku ] = vtex_product_to_mkt(
                    data     : $product[ "data"     ],
                    context  : $product[ "context"  ],
                    simulate : $product[ "simulate" ]
                );
            }

            return $wc_data;
        }
        catch( \Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "translateProducts",
                type    : "Throwable",
                e       : $e
            );

            $this->error( string: $e->getMessage() );

            return null;
        }
    }

    /**
     *
     * @param   array   $products
     * @return  void
     */
    private function createLocalProduct( array $products ) : void
    {
        $data = [];

        foreach ( $products as $sku => $product )
        {
            $data[] = [
                "store_id"      => 4265,
                "metadata_id"   => $sku,
                "product_sku"   => $product[ "sku"           ],
                "regular_price" => $product[ "regular_price" ],
                "sale_price"    => $product[ "sale_price"    ],
                "stock_status"  => $product[ "stock_status"  ],
            ];
        }

        DB::table( "marketplace_product_skus" )->insert( $data );
    }

    /**
     * Undocumented function
     *
     * @param   array   $products
     * @return  void
     */
    private function sendToMarketplace( array $wc_products, array $fs_products ) : void
    {
        foreach ( $wc_products as $sku => $product )
        {
            $message = "{$this->prefix} Sending sku {$sku}...";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->info( string: $message );

            try
            {
                $wcp = Product::create( $product );
                DB::table( "marketplace_product_skus" )->where( "metadata_id", $sku )->update( [ "product_id" => $wcp[ "id" ] ] );
            }
            catch ( \Throwable $e )
            {
                if ( $e->getMessage() !== "JSON ERROR: Syntax error" )
                {
                    log_exception(
                        channel: self::LOG_CHANNEL,
                        message : $e->getMessage(),
                        method  : "sendToMarketplace",
                        type    : "Throwable",
                        e       : $e
                    );

                    $this->error( $e->getMessage() );

                    $encoded = json_encode( $fs_products[ $sku ], JSON_UNESCAPED_SLASHES );
                    Storage::put( "fast-shop-products/fs-fails-{$sku}.json", $encoded );
                }
            }
        }
    }
}
