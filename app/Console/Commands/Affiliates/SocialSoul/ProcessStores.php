<?php

namespace App\Console\Commands\Affiliates\SocialSoul;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Affiliates\SocialSoulController;

class ProcessStores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "socialsoul:process
                            {--option= : Options: images, promotions, stores or all }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Command description";

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
        $opts   = [ "images", "promotions", "stores", "all" ];
        $option = $this->option( "option" );

        if ( !in_array( $option, $opts ) )
        {
            echo "Invalid option, terminating process!\n";
            return;
        }

        switch ( $option )
        {
            case "promotions":
            case "stores":
                $this->storesAndPromotions( $option );
            break;

            case "images":
                $this->images();
            break;

            case "all":
                $this->all();
            break;
        }
    }

    private function storesAndPromotions( string $option )
    {
        $optionLog  = ucfirst( $option );

        echo "Command : Start processing of {$optionLog}\n";
        Log::channel( "socialsoul" )->info( $optionLog, [ "Start processing of {$optionLog}" ]);

        $socialsoul = new SocialSoulController();
        $socialsoul->{$option}();

        echo "Command : End {$optionLog} processing\n";
        Log::channel( "socialsoul" )->info( $optionLog, [ "End {$optionLog} processing" ] );
    }

    private function images()
    {

    }

    private function all()
    {
        // STORES
        echo "Command : Start processing of Stores\n";
        Log::channel( "socialsoul" )->info( "Stores", [ "Start processing of Stores" ]);

        $socialsoul = new SocialSoulController();
        $socialsoul->stores();

        echo "Command : End Stores processing\n";
        Log::channel( "socialsoul" )->info( "Stores", [ "End Stores processing" ] );

        // PROMOTIONS
        echo "Command : Start processing of Promotions\n";
        Log::channel( "socialsoul" )->info( "Promotions", [ "Start processing of Promotions" ]);

        $socialsoul = new SocialSoulController();
        $socialsoul->promotions();

        echo "Command : End Promotions processing\n";
        Log::channel( "socialsoul" )->info( "Promotions", [ "End Promotions processing" ] );

        // IMAGES
        echo "Command : Start processing of Images\n";
        Log::channel( "socialsoul" )->info( "Images", [ "Start processing of Images" ]);

        $socialsoul = new SocialSoulController();
        $socialsoul->images();

        echo "Command : End Images processing\n";
        Log::channel( "socialsoul" )->info( "Images", [ "End Images processing" ] );
    }
}
