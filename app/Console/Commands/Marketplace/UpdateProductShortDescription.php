<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Product;

class UpdateProductShortDescription extends Command
{
    /**
     * @var string
     */
    protected const LOG_CHANNEL = "marketplace";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:update_product_short_description";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Update product short description";

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
        $message = "MKT Products: Starting process WC data...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $this->processProducts();

        $message = "MKT Products: Finished WC data process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff = $start->diffInMinutes( now() );
        $message = "MKT Products: Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );
    }

    private function processProducts() : void
    {
        $page = 1;
        $per = 100;
        $count = 100;
        $update = [];

        do
        {
            try
            {
                $response = Product::all( [
                    "per_page" => $per,
                    "page" => $page,
                ] );

                $count = $response->count();

                $page++;

                foreach ( $response as $product )
                {
                    if ( $product->store->id !== 4265 )
                        continue;

                    if ( empty( $product->attributes ) )
                        continue;

                    $product = json_decode( json_encode( $product ), true );
                    $short = vtex_append_voltage( $product[ "short_description" ], $product[ "attributes" ] );

                    if ( $short === $product[ "short_description" ] )
                        continue;

                    $update[] = [
                        "id" => $product[ "id" ],
                        "short_description" => $short,
                    ];

                    $c = count( $update );

                    $this->info( "Page {$page} | Count {$c}" );

                    if ( $c === 100 )
                    {
                        $response = $this->updateProducts( products: $update );

                        $this->info( "Update count {$c}" );

                        if ( $response )
                            $update = [];
                        else dd( $update, $page );
                    }
                }
            }
            catch ( Throwable $e )
            {
                log_message( channel: self::LOG_CHANNEL, message: $e->getMessage() );
                log_message( channel: self::LOG_CHANNEL, message: "page {$page}" );
            }
        }
        while ( $count === 100 );

        if ( count( $update ) === 100 )
            $this->updateProducts( products: $update );

        dd();
    }

    /**
     * @param array $products
     *
     * @return void
     */
    private function updateProducts( array $products ) : bool
    {
        try
        {
            Product::batch( ["update" => $products] );

            return true;
        }
        catch ( Throwable $e )
        {
            if ( $e->getMessage() === "JSON ERROR: Syntax error" )
                return true;

            log_message( channel: self::LOG_CHANNEL, message: $e->getMessage() );

            return false;
        }
    }
}
