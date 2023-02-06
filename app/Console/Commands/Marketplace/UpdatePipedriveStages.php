<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        Log::channel( "marketplace" )->info( "update-stages", [ "Start processing of update Stages/Status/Labels" ] );

        MauticIntegration::updateStages();

        Log::channel( "marketplace" )->info( "update-stages", [ "End Stages/Status/Labels processing" ] );
    }
}
