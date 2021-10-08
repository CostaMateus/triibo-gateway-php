<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Integrations\MauticIntegration;

class SyncIdsMP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza os ids do Mautic (pd_org_id) e do Pipedrive (mautic_id / 918dace587a4707755926d7c02c3bb46ed53d539)';

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
        MauticIntegration::syncIdsMauticPipe();
    }
}
