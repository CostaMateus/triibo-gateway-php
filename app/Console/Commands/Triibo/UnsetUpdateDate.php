<?php

namespace App\Console\Commands\Triibo;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        $message = "Unset updateDate: Start processing...";

        log_message( channel: "triibo", message: $message );
        $this->info( $message );

        ( new TriiboController() )->unsetUpdateDate();

        $message = "Unset updateDate: End processing...";

        log_message( channel: "triibo", message: $message );
        $this->info( $message );
    }
}
