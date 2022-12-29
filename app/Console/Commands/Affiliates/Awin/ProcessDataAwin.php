<?php

namespace App\Console\Commands\Affiliates\Awin;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Affiliates\AwinController;

class ProcessDataAwin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "awin:process
                            {--option= : Options: images, promotions, stores, all (IPS) or reports }";

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
        $opts   = [
            "images",
            "promotions",
            "stores",
            "all",
            "reports"
        ];

        $option = $this->option( "option" );

        if ( !in_array( $option, $opts ) )
        {
            echo "Invalid option, terminating process!\n";
            return;
        }

        if ( in_array( $option, [ "promotions", "stores" ] ) )
        {
            $this->storesAndPromotions( $option );
        }
        else
        {
            $this->{$option}();
        }
    }

    private function storesAndPromotions( string $option )
    {
        $optionLog  = ucfirst( $option );

        echo "Command : Start processing of {$optionLog}\n";
        Log::channel( "awin" )->info( $optionLog, [ "Start processing of {$optionLog}" ]);

        $awin = new AwinController();
        $awin->{$option}();

        echo "Command : End {$optionLog} processing\n";
        Log::channel( "awin" )->info( $optionLog, [ "End {$optionLog} processing" ] );
    }

    private function images()
    {
        echo "Command : Start processing of Images\n";
        Log::channel( "awin" )->info( "Images", [ "Start processing of Images" ]);

        $awin = new AwinController();
        $awin->images();

        echo "Command : End Images processing\n";
        Log::channel( "awin" )->info( "Images", [ "End Images processing" ] );
    }

    private function all()
    {
        // STORES
        echo "Command : Start processing of Stores\n";
        Log::channel( "awin" )->info( "Stores", [ "Start processing of Stores" ]);

        $awin = new AwinController();
        $awin->stores();

        echo "Command : End Stores processing\n";
        Log::channel( "awin" )->info( "Stores", [ "End Stores processing" ] );

        // PROMOTIONS
        echo "Command : Start processing of Promotions\n";
        Log::channel( "awin" )->info( "Promotions", [ "Start processing of Promotions" ]);

        $awin = new AwinController();
        $awin->promotions();

        echo "Command : End Promotions processing\n";
        Log::channel( "awin" )->info( "Promotions", [ "End Promotions processing" ] );

        // IMAGES
        echo "Command : Start processing of Images\n";
        Log::channel( "awin" )->info( "Images", [ "Start processing of Images" ]);

        $awin = new AwinController();
        $awin->images();

        echo "Command : End Images processing\n";
        Log::channel( "awin" )->info( "Images", [ "End Images processing" ] );
    }

    private function reports()
    {
        // REPORTS
        echo "Command : Start processing of Reports\n";
        Log::channel( "awin" )->info( "Reports", [ "Start processing of Reports" ]);

        // $awin = new AwinController();
        // $awin->reports();

        echo "Reports unavailable";

        echo "Command : End Reports processing\n";
        Log::channel( "awin" )->info( "Reports", [ "End Reports processing" ] );
    }
}
