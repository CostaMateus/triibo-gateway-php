<?php

namespace App\Console\Commands\Cofry;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\CofryController;

class ProcessStoresPromos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "process:storepromo";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Processes Cofry stores and promotions";

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
        // echo "SP : Start processing of stores|promos\n";
        Log::channel("cofry")->info("Stores|Promos", [ "Start processing of stores|promos" ]);

        $cofry = new CofryController();
        $cofry->index();

        // echo "SP : End stores|promos processing\n";
        Log::channel("cofry")->info("Stores|Promos", [ "End stores|promos processing" ]);
    }
}
