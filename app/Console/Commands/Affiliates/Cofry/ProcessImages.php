<?php

namespace App\Console\Commands\Affiliates\Cofry;

use Illuminate\Console\Command;
use App\Http\Controllers\Affiliates\CofryController;

class ProcessImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "cofry:process_images";

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
        log_message( channel: "cofry", message: "Images: Start processing...", print: true );

        ( new CofryController() )->images();

        log_message( channel: "cofry", message: "Images: End processing...", print: true );
    }
}
