<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
        $cofry = new CofryController();
        $cofry->index();
    }
}
