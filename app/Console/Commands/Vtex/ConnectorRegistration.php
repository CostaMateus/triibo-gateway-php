<?php

namespace App\Console\Commands\Vtex;

use Illuminate\Console\Command;
use App\Services\Vtex\ConnectorService;

class ConnectorRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "vtex:register-connector";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Register connector";

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

        $response = $connector->register();

        save_file( name: "vtex-register-connector", data: $response );

        $this->info( print_r( $response, true ) );

        if ( $response[ 0 ] !== 200 )
        {
            $this->error( "Error registering connector" );

            return Command::FAILURE;
        }

        $this->info( "Connector registered successfully" );

        return Command::SUCCESS;
    }
}
