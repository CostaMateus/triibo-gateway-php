<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Category;

class GetCategories extends Command
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
     *
     * @var     string
     */
    protected $prefix      = "MKT Categories:";

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
        $page       = 1;
        $categories = [];
        $data       = [];
        $count      = 100;

        $start      = now();
        $this->log_message( message: "{$this->prefix} Starting process WC data..." );

        do
        {
            $this->info( "Searching for categories in the marketplace, page {$page}" );

            try
            {
                $response   = Category::all( [ "per_page" => 100, "page" => $page, ] );
                $count      = $response->count();
                $categories = array_merge( $response->toArray(), $categories );

                $page++;
            }
            catch ( Throwable $e )
            {
                if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                {
                    log_message( channel: self::LOG_CHANNEL, message: print_r( $e, true ), level: "error" );
                    $this->log_message( message: $e->getMessage(), level: "error" );
                    break;
                }
            }
        }
        while ( $count == 100 );

        $count      = count( $categories );
        $this->log_message( message: "{$this->prefix} {$count} loaded..." );

        foreach ( $categories as $category )
        {
            $data[] = [
                "cat_id"     => $category->id,
                "cat_slug"   => $category->slug,
                "cat_name"   => $category->name,
                "cat_parent" => $category->parent,
            ];
        }

        $this->log_message( message: "Truncate table: marketplace_product_categories" );

        DB::table( "marketplace_product_categories" )->truncate();

        $this->log_message( message: "MKT Inserting {$count} categories in table: marketplace_product_categories" );

        DB::table( "marketplace_product_categories" )->insert( $data );

        $this->log_message( message: "{$this->prefix} Finished WC data process..." );

        $diff       = $start->diffInMinutes( now() );
        $this->log_message( message: "{$this->prefix} Runtime {$diff} minutes..." );
    }

    /**
     * Undocumented function
     *
     * @param   string  $channel
     * @param   string  $message
     * @param   string  $level
     * @return  void
     */
    private function log_message( string $message, string $level = "info" ) : void
    {
        log_message( channel: self::LOG_CHANNEL, message: $message, level: $level );
        $this->{$level}( $message );
    }
}
