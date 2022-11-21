<?php

namespace App\Console\Commands\Affiliates\SocialSoul;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Affiliates\SocialSoulController;

class ProcessStores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "socialsoul:process
                            {--option= : Options: stores, promotions or all }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Command description";

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
        $opts   = [ "stores", "promotions", "all" ];
        $option = $this->option( "option" );

        if ( !in_array( $option, $opts ) )
        {
            echo "Invalid option, terminating process!\n";
            return;
        }

        if ( $option != "all" )
        {
            $option     = ucfirst( $option );

            echo "Command : Start processing of {$option}\n";
            Log::channel( "socialsoul" )->info( $option, [ "Start processing of {$option}" ]);

            $socialsoul = new SocialSoulController();
            $socialsoul->{$option}();

            echo "Command : End {$option} processing\n";
            Log::channel( "socialsoul" )->info( $option, [ "End {$option} processing" ] );
        }
        else
        {
            echo "Command : Start processing of Stores\n";
            Log::channel( "socialsoul" )->info( "Stores", [ "Start processing of Stores" ]);

            $socialsoul = new SocialSoulController();
            $socialsoul->stores();

            echo "Command : End Stores processing\n";
            Log::channel( "socialsoul" )->info( "Stores", [ "End Stores processing" ] );


            echo "Command : Start processing of Promotions\n";
            Log::channel( "socialsoul" )->info( "Promotions", [ "Start processing of Promotions" ]);

            $socialsoul = new SocialSoulController();
            $socialsoul->promotions();

            echo "Command : End Promotions processing\n";
            Log::channel( "socialsoul" )->info( "Promotions", [ "End Promotions processing" ] );
        }
    }
}
