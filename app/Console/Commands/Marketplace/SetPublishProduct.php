<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Product;

class SetPublishProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "sku:publish";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Deixa todos os SKUs da FS como públicos.";

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
        $pdts  = 0;
        $ended = false;
        $page  = 1;
        $per   = 100;

        do
        {
            try
            {
                $aux = Product::all( [
                    "per_page" => $per,
                    "page"     => $page,
                    "status"   => "private"
                ] );

                if ( $aux->count() < 100 ) $ended = true;

                $page++;

                foreach ( $aux as $p )
                {
                    if ( strpos( $p->sku, "FS-" ) !== false )
                    {
                        Product::update( $p->id, [ "status" => "publish" ] );

                        $this->info( "Product ID: $p->id" );

                        $pdts++;
                    }
                }

                $this->info( "Sleep 1" );
                sleep( 1 );
            }
            catch ( \Exception $e )
            {
                $page--;

                $ended = false;

                $this->info( "Error: " . $e->getMessage() );
                $this->info( "Sleep 15" );

                sleep( 15 );
            }
        }
        while ( !$ended );

        $this->info( "Total: $pdts" );
    }
}
