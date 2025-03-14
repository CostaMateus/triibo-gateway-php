<?php

namespace App\Console\Commands\Marketplace\Reset;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DeleteFastShopData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var     string
     */
    protected $signature = "mkt:delete_fs_data {--option= : Options: images, products }";

    /**
     * The console command description.
     *
     * @var     string
     */
    protected $description = "Delete Fast Shop data";

    /**
     * @var     string
     */
    protected $prefix      = "MKT FS Delete:";

    /**
     * @var     string
     */
    protected $url_sufix   = "/wp-json/triibo/v1/delete/fs-data";

    /**
     * @var     array
     */
    protected $headers     = [
        "Accept"       => "application/json",
        "Content-Type" => "application/json",
        "x-api-key"    => "54723162306C6F6A7052646D4C6170693030"
    ];

    /**
     * @var     array
     */
    protected $allowed_options = [ null, "image", "images", "product", "products" ];

    /**
     * @var     string
     */
    protected const LOG_CHANNEL = "fastshop";

    /**
     * Create a new command instance.
     *
     * @return  void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return  int
     */
    public function handle()
    {
        $start      = now();
        $option     = $this->option( key: "option" );

        if ( !in_array( needle: $option, haystack: $this->allowed_options ) )
        {
            $message = "{$this->prefix} Invalid option: {$option}";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->error( string: $message );

            return Command::FAILURE;
        }

        $option     = match ( $option )
        {
            "product"   => "product",
            "products"  => "product",
            "image"     => "attachment",
            "images"    => "attachment",
            null        => "attachment",
            default     => "attachment",
        };

        $message    = "{$this->prefix} Starting process, {$option}";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $db         = "dev-mkt";
        $url        = "https://dev-marketplace.triibo.com.br" . $this->url_sufix;

        if ( config( key: "app.env" ) === "production" )
        {
            $db  = "prd-mkt";
            $url = "https://marketplace.triibo.com.br" . $this->url_sufix;
        }

        $message    = "{$this->prefix} Selecting DB: {$db}...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $conn       = DB::connection( name: $db );
        $unfinished = true;
        $response   = null;

        try
        {
            do
            {
                $posts   = $conn->select( query: $this->getSelect( type: $option ) );
                $ids     = [];

                foreach ( $posts as $post )
                    $ids[] = ( string ) $post->id;

                $message  = "{$this->prefix} Selected: " . implode( ", ", $ids ) ;
                log_message( channel: self::LOG_CHANNEL, message: $message, context: $ids );
                $this->info( $message );

                $content  = json_encode( [
                    "posts" => $ids,
                    "type"  => $option,
                ] );

                $response = retry(
                    times             : 3,
                    sleepMilliseconds : 1000,
                    callback          : function () use ( $url, $content )
                    {
                        $response_2 = Http::withHeaders( headers: $this->headers )
                                        ->withBody( content : $content, contentType : "application/json" )
                                        ->post( url: $url );

                        if ( !$response_2->successful() )
                            throw new \Exception( $response_2->json()[ "message" ], $response_2->status() );

                        return $response_2;
                    },
                );

                if ( !$response->successful() )
                    throw new \Exception( $response->json()[ "message" ], $response->status() );

                $message = "{$this->prefix} Successful processed";
                log_message( channel: self::LOG_CHANNEL, message: $message, context: $response->json() );
                $this->info( $message );

                if ( $posts < 10 )
                    $unfinished = false;
            }
            while ( $unfinished );
        }
        catch ( \Throwable $e )
        {
            $message    = "{$this->prefix} Error: {$e->getMessage()}";
            log_message ( channel: self::LOG_CHANNEL, message: $message );
            $this->error( string: $message );

            log_exception(
                channel : self::LOG_CHANNEL,
                message : $message,
                e       : $e,
                context : [ "ids" => $ids ],
            );
        }

        $message = "{$this->prefix} Finished process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff    = $start->diffInMinutes( now() );
        $message = "{$this->prefix} Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        return Command::SUCCESS;
    }

    /**
     *
     * @param   int     $start
     * @param   int     $end
     * @return  string
     */
    private function getSelect( string $type ) : string
    {
        $limit = $type == "attachment" ? 100 : 25;

        return "
            SELECT
                p.ID as id,
                p.post_author,
                p.post_title,
                p.post_date,
                p.post_modified,
                p.guid as post_image,
                p.post_type,
                p.post_mime_type
            FROM
                wp_posts p
            WHERE
                p.post_author = 4265
            AND
                p.post_type = '{$type}'
            ORDER BY
                p.ID ASC
            LIMIT
                {$limit};
        ";
    }
}
