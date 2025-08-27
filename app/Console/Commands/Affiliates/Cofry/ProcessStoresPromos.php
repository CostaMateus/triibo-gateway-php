<?php

namespace App\Console\Commands\Affiliates\Cofry;

use Illuminate\Console\Command;
use App\Http\Controllers\Affiliates\CofryController;

class ProcessStoresPromos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "cofry:process_sp
                            {--option= : Options: promotions, establishments or all }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Processes Cofry establishments or promotions";

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
        $channel = "cofry";

        $option = $this->option( "option" );
        $opts = [
            "promotions",
            "establishments",
            "all",
        ];

        if ( ! in_array( $option, $opts ) )
        {
            $message = "Invalid option, terminating process...";

            log_message( channel: $channel, message: $message );

            return;
        }

        $title = ( $option !== "all" ) ? ucfirst( $option ) : "Stores|Promotions";
        $message = "{$title}: Start processing...";

        log_message( channel: $channel, message: $message );
        $this->info( $message );

        ( new CofryController() )->{$option}();

        $message = "{$title}: End processing...";
        log_message( channel: $channel, message: $message );
        $this->info( $message );
    }
}
