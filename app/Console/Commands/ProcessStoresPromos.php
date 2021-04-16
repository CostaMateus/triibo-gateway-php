<?php

namespace App\Console\Commands;

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
    protected $signature = 'process:storepromo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes Cofry stores and promotions';

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
        echo "SP : Inicio processamento das stores-promos\n";
        Log::info("SP : Inicio processamento das stores-promos");

        $cofry = new CofryController();
        $cofry->index();

        echo "SP : Fim processamento das stores-promos\n";
        Log::info("SP : Fim processamento das stores-promos");
    }
}
