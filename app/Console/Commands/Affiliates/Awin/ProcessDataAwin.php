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
    protected $signature = "awin:process
                            {--option= : Options: images, promotions, stores, all (IPS) or reports }";

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
        $option = $this->option( "option" );

        $optionLog  = ucfirst( $option );

        echo "Command : Start processing of {$optionLog}\n";
        Log::channel( "awin" )->info( $optionLog, [ "Start processing of {$optionLog}" ]);

        $awin = new AwinController();
        $awin->{$option}();

        echo "Command : End {$optionLog} processing\n";
        Log::channel( "awin" )->info( $optionLog, [ "End {$optionLog} processing" ] );
    }
}
