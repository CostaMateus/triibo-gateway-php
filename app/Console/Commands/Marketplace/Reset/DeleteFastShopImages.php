<?php

namespace App\Console\Commands\Marketplace\Reset;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DeleteFastShopImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var     string
     */
    protected $signature = "mkt:delete_fastshop_images";

    /**
     * The console command description.
     *
     * @var     string
     */
    protected $description = "Delete Fast Shop Images";

    /**
     *
     * @var     string
     */
    protected $prefix      = "MKT Delete Images:";

    /**
     *
     * @var     string
     */
    protected $url_sufix   = "/wp-json/triibo/v1/delete-images";

    /**
     *
     * @var     array
     */
    protected $headers     = [
        "Accept"       => "application/json",
        "Content-Type" => "application/json",
        "x-api-key"    => "54723162306C6F6A7052646D4C6170693030"
    ];

    /**
     *
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
        $message    = "{$this->prefix} Starting process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        if ( config( key: "app.env" ) == "local" )
        {
            $db  = "dev-mkt";
            $url = "https://dev-marketplace.triibo.com.br" . $this->url_sufix;
        }
        else
        {
            $db  = "prd-mkt";
            $url = "https://marketplace.triibo.com.br" . $this->url_sufix;
        }

        $message    = "{$this->prefix} Selecting DB: {$db}...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $conn       = DB::connection( name: $db );

        $unfinished = true;

        $lm_start   = 0;
        $lm_end     = 10;

        $response   = null;

        try
        {
            do
            {
                $posts = $conn->select( query: $this->getSelect( start: $lm_start, end: $lm_end ) );
                $ids   = [];

                foreach ( $posts as $post )
                    $ids[] = ( string ) $post->id;

                $message = "{$this->prefix} Selected: " . implode( ", ", $ids ) ;
                log_message( channel: self::LOG_CHANNEL, message: $message, context: $ids );
                $this->info( $message );

                $response = retry(
                    times             : 3,
                    sleepMilliseconds : 1000,
                    callback          : function () use ( $url, $ids )
                    {
                        $response_2 = Http::withHeaders( headers: $this->headers )
                                        ->withBody( content : json_encode( [ "posts" => $ids ] ), contentType : "application/json" )
                                        ->post( url: $url );

                        if ( !$response_2->successful() )
                            throw new \Exception( $response_2->json()[ "message" ], $response_2->status() );

                        return $response_2;
                    },
                );

                if ( !$response->successful() )
                    throw new \Exception( $response->json()[ "message" ], $response->status() );

                $message = "{$this->prefix} Successful processed: " . implode( ", ", $ids ) ;
                log_message( channel: self::LOG_CHANNEL, message: $message, context: $response->json() );
                $this->info( $message );

                $lm_start = $lm_end + 1;
                $lm_end   = $lm_end + 10;

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
                context : [
                    "lm_start" => $lm_start,
                    "lm_end"   => $lm_end,
                    "ids"      => $ids,
                ],
            );
        }

        $message = "{$this->prefix} Finished process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff    = $start->diffInMinutes( now() );
        $message = "{$this->prefix} Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        return 1;
    }

    /**
     *
     * @param   int     $start
     * @param   int     $end
     * @return  string
     */
    private function getSelect( int $start, int $end ) : string
    {
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
                p.post_type = 'attachment'
            ORDER BY
                p.ID DESC
            LIMIT
                {$start}, {$end};
        ";
    }
}
