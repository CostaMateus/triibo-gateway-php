<?php

namespace App\Console\Commands\Cofry;

use File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "delete:images";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete images from on folder 'images/estabelecimentos/' and 'images/promos/'.";

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
        echo "DEL : Deleting images\n";
        Log::channel( "cofry" )->info( "Images", [ "Deleting images" ] );

        File::deleteDirectory( public_path( "images/estabelecimentos/" ), true );
        File::deleteDirectory( public_path( "images/promos/" ),           true );

        echo "DEL : Successfully deleted\n";
        Log::channel( "cofry" )->info( "Images", [ "Successfully deleted" ] );
    }
}
