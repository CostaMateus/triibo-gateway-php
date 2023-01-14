<?php

namespace App\Console\Commands\Cofry;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Affiliates\CofryController;

class ProcessStoresPromos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "cofry:process_sp
                            {--option= : Options: promotions, stores or all }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Processes Cofry stores or promotions";

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
            "promotions",
            "stores",
            "all",
        ];

        $option = $this->option( "option" );

        if ( !in_array( $option, $opts ) )
        {
            echo "Invalid option, terminating process!\n";
            return;
        }

        if ( $option != "all" )
        {
            $logTitle = ucfirst( $option );
            $logStart = "{$logTitle} : Start processing of {$option}\n";
            $logEnd   = "{$logTitle} : End {$option} processing\n";
        }
        else
        {
            $logTitle = "Stores|Promotions";
            $logStart = "S|P : Start processing of stores|promotions\n";
            $logEnd   = "S|P : End stores|promotions processing\n";
        }

        echo $logStart;
        Log::channel( "cofry" )->info( $logTitle, [ $logStart ] );

        $cofry = new CofryController();
        $cofry->$option();

        echo $logEnd;
        Log::channel( "cofry" )->info( $logTitle, [ $logEnd ] );
    }
}
