<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\CofryController;
use App\Http\Controllers\TriiboController;

class UnsetUpdateDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unset:updateDate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unset updateDate';

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
        Log::channel("triibo")->info("unset-updateDate", [ "Start processing of unset-updateDate" ]);

        $triibo = new TriiboController();
        $triibo->unsetUpdateDate();

        Log::channel("triibo")->info("unset-updateDate", [ "End unset-updateDate processing" ]);
    }
}
