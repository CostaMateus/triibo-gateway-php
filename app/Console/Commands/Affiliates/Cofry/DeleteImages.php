<?php

namespace App\Console\Commands\Affiliates\Cofry;

use File;
use Illuminate\Console\Command;

class DeleteImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "cofry:delete_images";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete images from on folder 'images/estabelecimentos/' and 'images/promos/'.";

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
        $message = "Images: Deleting images...";

        log_message( channel: "cofry", message: $message );
        $this->info( $message );

        File::deleteDirectory( public_path( "images/estabelecimentos/" ), true );
        File::deleteDirectory( public_path( "images/promos/" ), true );

        $message = "Images: Successfully deleted...";

        log_message( channel: "cofry", message: $message );
        $this->info( $message );

        return Command::SUCCESS;
    }
}
