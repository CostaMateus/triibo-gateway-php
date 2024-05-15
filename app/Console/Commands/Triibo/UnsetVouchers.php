<?php

namespace App\Console\Commands\Triibo;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\TriiboController;

class UnsetVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unset:vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unset Vouchers';

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
        log_message( channel: "triibo", message: "Unset vouchers: Start processing...", print: true );

        ( new TriiboController() )->unsetVouchers();

        log_message( channel: "triibo", message: "Unset vouchers: End processing...", print: true );
    }
}
