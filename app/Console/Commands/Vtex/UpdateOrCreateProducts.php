<?php

namespace App\Console\Commands\Vtex;

use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;

class UpdateOrCreateProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "vtex:update_or_create_products {--action= : Action of products that will be processed. Options: create or update }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Process update or create new products";

    private $action;

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
        if ( !in_array( $this->option( "action" ), [ "create", "update" ] ) )
            return;
    }
}
