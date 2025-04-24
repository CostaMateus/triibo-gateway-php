<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use App\Models\Marketplace\Order;
use Codexshaper\WooCommerce\Facades\Order as Wc_Order;
use Exception;

class GetOrdersStats extends Command
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
    protected $signature = "mkt:get-orders-stats";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get order stats and update integration order stats";

    /**
     * @var string
     */
    protected $prefix = "MKT Orders Stats:";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $start = now();

        $this->logMessage( message: "{$this->prefix} Starting process WC Orders stats..." );

        $ids = Order::select( [ "id", "mkt_id", "ord_price" ] )
            ->where( "ord_status", "approved" )
            ->whereNull( "ord_price" )
            ->get()
            ->pluck( "mkt_id", "id" )
            ->toArray();

        foreach ( $ids as $id => $mkt_id )
        {
            $this->logMessage( message: "{$this->prefix} Getting data {$id}:{$mkt_id}..." );

            try
            {
                $wc_order   = Wc_Order::find( $mkt_id );

                $created_at = str_replace( "T", " ", $wc_order[ "date_created" ] );
                $total      = (float) $wc_order[ "total" ];
                $shipping   = (float) $wc_order[ "shipping_total" ];
                $price      = $total - $shipping;

                $order      = Order::whereId( $id )
                ->update( [
                    "ord_price" => $price,
                    "ord_shipping" => $shipping,
                    "ord_created_at" => $created_at,
                ] );

                $this->logMessage( message: "{$this->prefix} Order {$id}:{$mkt_id} updated..." );
            }
            catch ( Exception $e )
            {
                $this->logMessage( message: "Fail {$id}:{$mkt_id} | {$e->getMessage()}", type: "error" );
            }
        }

        $this->logMessage( message: "{$this->prefix} Finished process..." );

        $diff = $start->diffInMinutes( date: now() );

        $this->logMessage( message: "{$this->prefix} Runtime {$diff} minutes..." );
    }

    /**
     * Log message with consistent format
     *
     * @param string $message
     * @param string $type
     *
     * @return void
     */
    private function logMessage( string $message, string $type = "info" ) : void
    {
        log_message(
            channel: self::LOG_CHANNEL,
            message: $message,
            level  : $type
        );

        $this->{$type}( string: $message );
    }
}
