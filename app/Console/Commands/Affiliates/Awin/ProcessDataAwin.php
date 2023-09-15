<?php

namespace App\Console\Commands\Affiliates\Awin;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Affiliates\AwinController;

class ProcessDataAwin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature   = "awin:process {--option= : Options: establishments, promotions, images, all (EPI) or reports }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Process Awin data";

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
        $opts   = [
            "populate",       // TODO remover após usar em prod
            "populatePromos", // TODO remover após usar em prod
            "establishments",
            "promotions",
            "images",
            "all",
            "reports"
        ];

        $option = $this->option( "option" );

        if ( !in_array( $option, $opts ) )
        {
            echo "Invalid option, terminating process!\n";

            return 0;
        }

        if ( $option == "all" )
        {
            /**
             * Process establishments, promotions and images
             */
            $this->processOption( [ "establishments", "promotions", "images" ] );
        }
        else
        {
            /**
             * Process establishments / promotions / images / reports
             */
            $this->processOption( $option );
        }

        return 1;
    }

    /**
     * Process option
     *
     * @param   string|array  $option
     * @return  void
     */
    private function processOption( string|array $option )
    {
        if ( is_array( $option ) )
        {
            $isInit = false;
            $awin   = null;

            foreach ( $option as $opt )
            {
                $name = ucfirst( $opt );

                echo "Command : Start processing of {$name}\n";
                Log::channel( "awin" )->info( "{$name}", [ "Start processing of {$name}" ]);

                $load = ( $opt == "images" || $opt == "reports" ) ? false : true;

                if ( !$isInit || !$awin )
                    $awin = new AwinController( $load );

                $awin->{$opt}();

                echo "Command : End {$name} processing\n";
                Log::channel( "awin" )->info( "{$name}", [ "End {$name} processing" ] );
            }
        }
        else
        {
            $name = ucfirst( $option );

            echo "Command : Start processing of {$name}\n";
            Log::channel( "awin" )->info( "{$name}", [ "Start processing of {$name}" ]);

            $load = ( in_array( $option, [ "images", "reports" ] ) ) ? false : true;

            $awin = new AwinController( $load );
            $awin->{$option}();

            echo "Command : End {$name} processing\n";
            Log::channel( "awin" )->info( "{$name}", [ "End {$name} processing" ] );
        }
    }
}
