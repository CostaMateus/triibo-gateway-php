<?php

namespace App\Console\Commands\Affiliates\Awin;

use Throwable;
use Illuminate\Console\Command;
use App\Http\Controllers\Affiliates\AwinController;

class ProcessDataAwin extends Command
{
    /**
     * Define a class constant for the log channel.
     *
     * @var string
     */
    private const LOG_CHANNEL = "awin";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "awin:process {--option= : Options: establishments, promotions, images, all (EPI) or reports }";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Process Awin data";

    /**
     * The options that require loading data.
     *
     * @var array
     */
    protected $allowed_options = [
        "establishments",
        "promotions",
        "images",
        "all",
        "reports"
    ];

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
        // Get the option from the command line argument
        $option = $this->option( key: "option" );

        // Check if the option is valid
        if ( ! in_array( $option, $this->allowed_options ) )
        {
            $this->log_message( message: "Invalid option: Terminating process!" );

            return Command::FAILURE;
        }

        $start = now();

        // Determine the options to process based on the provided option
        $options = ( $option === "all" )
                    ? ["establishments", "promotions", "images"]
                    : [$option];

        // Process the selected options
        $this->processOptions( options: $options );

        $diff = $start->diffInMinutes( date: now() );

        $this->log_message( message: "Products: Runtime {$diff} minutes..." );

        return Command::SUCCESS;
    }

    /**
     * Log a message.
     *
     * @param string $channel the name of the log channel to use
     * @param string $message the message to log
     * @param string $level   The log level (e.g., 'info', 'warning', 'error'). Default is 'info'.
     * @param array  $context additional context data to include with the log entry
     *
     * @return void
     */
    public function log_message( string $message, string $level = "info", array $context = [] ) : void
    {
        $this->{$level}( string: $message );

        log_message(
            channel : self::LOG_CHANNEL,
            message : $message,
            level   : $level,
            context : $context ?? null,
        );
    }

    /**
     * Log an exception.
     *
     * @param string         $channel the name of the log channel to use
     * @param string         $message the message to log
     * @param string         $level   The log level (e.g., 'info', 'warning', 'error'). Default is 'info'.
     * @param string         $method  the method that threw the exception
     * @param string         $type    the type of exception that was thrown
     * @param null|Throwable $e       the exception object
     * @param null|array     $context additional context data to include with the log entry
     *
     * @return void
     */
    public function log_exception( string $message, string $level = "error", string $method = "", string $type = "", ?Throwable $e = null, ?array $context = null ) : void
    {
        $this->{$level}( string: $message );

        log_exception(
            channel : self::LOG_CHANNEL,
            message : $message,
            level   : $level,
            method  : $method,
            type    : $type,
            e       : $e,
            context : $context,
        );
    }

    /**
     * Process options
     *
     * @param array $options
     *
     * @return void
     */
    private function processOptions( array $options ) : void
    {
        $awin = null;

        // Loop through each option
        foreach ( $options as $option )
        {
            $name = ucfirst( $option );

            $this->log_message( message: "{$name}: Start processing..." );

            // If the AwinController instance is not created yet, create it
            if ( ! $awin )
            {
                $load = ( in_array( $option, ["images", "reports"] ) ) ? false : true;

                $awin = new AwinController( logger: $this, load: $load );
            }

            // Call the corresponding method on the AwinController instance
            $awin->{$option}();

            $this->log_message( message: "{$name}: End processing..." );
        }
    }
}
