<?php

namespace App\Console\Commands\Vtex;

use Illuminate\Console\Command;
use App\Services\Vtex\CategoryService;
use App\Services\Vtex\ConnectorService;

class SendCategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "vtex:send-category";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Send category mapping";

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
        $connector = new ConnectorService();
        $connector->enableLogNotify( false );

        $category  = new CategoryService( $connector );

        $response  = $category->registerMarketplaceConnector();

        if ( $response[ 0 ] != 200 )
        {
            $this->error( "Error registering connector" );
            return;
        }

        $this->info( "Connector registered successfully" );

        $id         = $response[ 1 ][ "data" ][ "id" ];
        $categories = get_marketplace_categories();
        $categories = vtex_format_categories( $categories );

        $response   = $category->sendCategoryMapping( $id, $categories );

        $this->info( print_r( $response, true ) );
    }
}
