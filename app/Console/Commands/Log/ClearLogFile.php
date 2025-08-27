<?php

namespace App\Console\Commands\Log;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "log:clear";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Clear Integration Logs";

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
        $date = date( "Y-m-d" );
        $log_folder = storage_path( "logs/" );
        $all_files = File::files( $log_folder );
        $bkp_folder = $log_folder . "/bkp-{$date}";

        if ( ! File::exists( $bkp_folder ) )
            File::makeDirectory( $bkp_folder, 0755, true, true );

        foreach ( $all_files as $file )
        {
            if ( pathinfo( $file, PATHINFO_EXTENSION ) === "log" )
            {
                $file = pathinfo( $file );

                File::move(
                    "{$log_folder}{$file[ "basename" ]}",
                    "{$bkp_folder}/{$file[ "basename" ]}"
                );
            }
        }

        $this->info( "Logs have been cleared" );
    }
}
