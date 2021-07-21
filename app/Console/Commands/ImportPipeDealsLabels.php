<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Integrations\MauticIntegration;

class ImportPipeDealsLabels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:dealslabels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports all deals labels (etiquetas) from Pipedrive to Mautic';

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
        $integ = new MauticIntegration();
        $integ->importAllDealsLabels();
    }
}
