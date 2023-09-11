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

        $this->{$option}();

        return 1;
    }

    /**
     * Process establishments, promotions and images
     *
     * @return  void
     */
    private function all()
    {
        $this->processOption( [ "establishments", "promotions", "images" ] );
    }

    /**
     * Process establishments
     *
     * @param   string|array  $option
     * @return  void
     */
    private function establishments()
    {
        $this->processOption( "establishments" );
    }

    /**
     * Process promotions
     *
     * @return  void
     */
    private function promotions()
    {
        $this->processOption( "promotions" );
    }

    /**
     * Process images
     *
     * @return  void
     */
    private function images()
    {
        $this->processOption( "images" );
    }

    /**
     * Process reports
     *
     * @return  void
     */
    private function reports()
    {
        $this->processOption( "reports" );
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

            $load = ( $option == "images" || $option == "reports" ) ? false : true;

            $awin = new AwinController( $load );
            $awin->{$option}();

            echo "Command : End {$name} processing\n";
            Log::channel( "awin" )->info( "{$name}", [ "End {$name} processing" ] );
        }
    }
}
