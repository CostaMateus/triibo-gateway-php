<?php

namespace App\Console\Commands\Cofry;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Affiliates\CofryController;

class ProcessImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "process:images";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Processes images from Cofry stores and promotions";

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
        echo "IMG : Start processing of images\n";
        Log::channel( "cofry" )->info( "Images", [ "Start processing of images" ] );

        $cofry = new CofryController();
        $cofry->uploadImages();

        echo "IMG : End images processing\n";
        Log::channel( "cofry" )->info( "Images", [ "End images processing" ] );
    }
}
