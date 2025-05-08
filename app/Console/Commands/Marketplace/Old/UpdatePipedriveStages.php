<?php

namespace App\Console\Commands\Marketplace\Old;

use Illuminate\Console\Command;
use App\Http\Integrations\MauticIntegration;

class UpdatePipedriveStages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:update_pipedrive_stages";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Update stages from Pipedrive to Mautic";

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
        $message = "Stages|Status|Labels: Start processing of update...";

        log_message( channel: "marketplace", message: $message );
        $this->info( $message );

        MauticIntegration::updateStages();

        $message = "Stages|Status|Labels: End processing...";

        log_message( channel: "marketplace", message: $message );
        $this->info( $message );
    }
}
