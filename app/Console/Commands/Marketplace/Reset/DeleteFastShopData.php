<?php

namespace App\Console\Commands\Marketplace\Reset;

use Exception;
use Throwable;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\{Artisan, DB, Http};

class DeleteFastShopData extends Command
{
    /**
     * @var string
     */
    protected const LOG_CHANNEL = "fastshop";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:delete_fs_data {--option= : Options: images, products } {--meta} {--fix} {--outofstock}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete Fast Shop data";

    /**
     * @var string
     */
    protected $prefix = "MKT FS Delete:";

    /**
     * @var string
     */
    protected $url_sufix = "/wp-json/triibo/v1/delete/fs-data";

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $store_id;

    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var array
     */
    protected $headers = [
        "Accept" => "application/json",
        "Content-Type" => "application/json",
        "x-api-key" => "54723162306C6F6A7052646D4C6170693030"
    ];

    /**
     * @var array
     */
    protected $allowed_options = [null, "image", "images", "product", "products"];

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
        $start = now();
        $option = $this->option( key: "option" );
        $meta = $this->option( key: "meta" );
        $fix = $this->option( key: "fix" );
        $outofstock = $this->option( key: "outofstock" );

        if ( ! in_array( needle: $option, haystack: $this->allowed_options ) )
        {
            $message = "{$this->prefix} Invalid option: {$option}";
            log_message( channel: self::LOG_CHANNEL, message: $message );
            $this->error( string: $message );

            return Command::FAILURE;
        }

        $option = match ( $option )
        {
            "product" => "product",
            "products" => "product",
            "image" => "attachment",
            "images" => "attachment",
            default => null,
        };

        $this->log_message( message: "Starting process" );
        $this->log_message( message: implode( " ", [
            "--option:" . ( $option ?: "null" ) ,
            "--meta:" . ( $meta ?: "false" ) ,
            "--fix:" . ( $fix ?: "false" ) ,
            "--outofstock:" . ( $outofstock ?: "false" ),
        ] ) );

        $db = "dev-mkt";
        $this->url = "https://dev-marketplace.triibo.com.br" . $this->url_sufix;

        if ( config( key: "app.env" ) === "production" )
        {
            $db = "prd-mkt";
            $this->url = "https://marketplace.triibo.com.br" . $this->url_sufix;
        }

        $this->log_message( message: "Selecting DB: {$db}..." );

        $this->conn = DB::connection( name: $db );
        $this->store_id = config( key: "woocommerce.partners" )[ "fast_shop" ][ "store_id" ];

        try
        {
            if ( ! is_null( $option ) && ! in_array( true, [$meta, $fix, $outofstock] ) )
            {
                $unfinished = true;
                $response = null;

                do
                {
                    $posts = $this->conn->select( query: $this->getSelect( type: $option ) );

                    if ( ! $posts ) break;

                    $ids = [];

                    foreach ( $posts as $post )
                        $ids[] = (string) $post->id;

                    $this->log_message( message: "Selected: " . implode( ", ", $ids ) );

                    $content = json_encode( [
                        "posts" => $ids,
                        "type" => $option,
                    ] );

                    $response = retry(
                        times             : 5,
                        sleepMilliseconds : 2000,
                        callback          : function () use ( $content )
                        {
                            $response_2 = Http::timeout( 90 )
                                ->withHeaders( headers: $this->headers )
                                ->withBody( content : $content, contentType : "application/json" )
                                ->post( url: $this->url );

                            if ( ! $response_2->successful() )
                                throw new Exception( $response_2->json()[ "message" ], $response_2->status() );

                            return $response_2;
                        },
                        when              : function ( Throwable $e )
                        {
                            $this->log_message( message: "Retry. Error: {$e->getMessage()}", type: "error" );

                            return true;
                        }
                    );

                    if ( ! $response->successful() )
                        throw new Exception( $response->json()[ "message" ], $response->status() );

                    $this->log_message( message: "Successful processed", context: $response->json() );

                    if ( $posts < 10 )
                        $unfinished = false;
                }
                while ( $unfinished );
            }

            if ( $meta === true )
            {
                $this->conn->select( query: $this->deleteMetaLookup() );
                $this->conn->select( query: $this->deletePostMeta() );
            }

            if ( $fix === true )
                $this->fixPostGallery();

            if ( $outofstock === true )
                $this->deleteOutofstock();
        }
        catch ( Throwable $e )
        {
            $this->log_message( message: "Error: {$e->getMessage()}", type: "error" );

            log_exception(
                channel : self::LOG_CHANNEL,
                message : $message,
                e       : $e,
            );
        }

        $this->log_message( message: "Finished process..." );

        $diff = $start->diffInMinutes( now() );
        $this->log_message( message: "Runtime {$diff} minutes..." );

        return Command::SUCCESS;
    }

    /**
     * Log message
     *
     * @param string $message
     * @param string $type
     * @param array  $context
     *
     * @return void
     */
    private function log_message( string $message, string $type = "info", array $context = [] ) : void
    {
        log_message(
            channel: self::LOG_CHANNEL,
            message: "{$this->prefix} {$message}",
            level  : $type,
            context: $context
        );

        $this->{$type}( string: $message );
    }

    /**
     * @param int    $start
     * @param int    $end
     * @param string $type
     *
     * @return string
     */
    private function getSelect( string $type ) : string
    {
        $limit = 25;
        $where = "";

        if ( $type === "attachment" )
        {
            $limit = 1000;
            $where = "AND p.post_parent = 0";
        }

        return "
            SELECT p.ID as id
            FROM wp_posts p
            WHERE p.post_author = {$this->store_id}
            AND p.post_type = '{$type}'
            {$where}
            ORDER BY p.ID ASC
            LIMIT {$limit};
        ";
    }

    /**
     * @return string
     */
    private function deleteMetaLookup() : string
    {
        return "
            DELETE wp_wc_product_meta_lookup
            FROM wp_wc_product_meta_lookup
            LEFT JOIN wp_posts
            ON wp_wc_product_meta_lookup.product_id = wp_posts.ID
            WHERE wp_posts.ID IS NULL
            AND wp_wc_product_meta_lookup.product_id > 0;
        ";
    }

    /**
     * @return string
     */
    private function deletePostMeta() : string
    {
        return "
            DELETE wp_postmeta
            FROM wp_postmeta
            LEFT JOIN wp_posts
            ON wp_postmeta.post_id = wp_posts.ID
            WHERE wp_posts.ID IS NULL
            AND wp_postmeta.meta_id > 0;
        ";
    }

    /**
     * @return void
     */
    private function fixPostGallery() : void
    {
        $update = [];

        $query_0 = "
            UPDATE wp_posts AS img
            JOIN wp_postmeta AS meta ON img.ID = meta.meta_value
            JOIN wp_posts AS prod ON meta.post_id = prod.ID
            SET img.post_parent = prod.ID
            WHERE meta.meta_key = '_thumbnail_id'
            AND img.post_type = 'attachment'
            AND prod.post_type = 'product'
            AND prod.post_author = {$this->store_id};
        ";

        $query_1 = "
            SELECT ID as post_id
            FROM wp_posts
            WHERE post_author = {$this->store_id}
            AND post_type = 'attachment'
            AND post_parent = 0
            ORDER BY ID DESC;
        ";

        $query_2 = "
            SELECT post_id, meta_value
            FROM wp_postmeta
            WHERE meta_key = '_product_image_gallery'
            AND meta_value != ''
            ORDER BY post_id DESC;
        ";

        $this->conn->select( query: $query_0 );

        $post_imgs = $this->conn->select( query: $query_1 );
        $post_imgs = array_map( static function ( $obj )
        { return $obj->post_id; }, $post_imgs );

        $result = $this->conn->select( query: $query_2 );

        $chunked = array_chunk( $result, 500 );

        foreach ( $chunked as $i => $result )
        {
            $i++;

            $this->log_message( message: "Chunk {$i} de " . count( $chunked ) );

            foreach ( $result as $meta )
            {
                $product_id = $meta->post_id;
                $image_ids = explode( ",", $meta->meta_value );

                $intersect = array_intersect( $image_ids, $post_imgs );

                if ( empty( $intersect ) )
                    continue;

                foreach ( $image_ids as $image_id )
                {
                    $image_id = (int) trim( $image_id );

                    $update[] = "UPDATE wp_posts SET post_parent = {$product_id} WHERE ID = {$image_id};";
                }

                $this->log_message( message: $product_id );
            }

            foreach ( $update as $query )
            {
                $this->conn->select( query: $query );

                $this->log_message( message: $query );
            }
        }

        $post_imgs = $this->conn->select( query: $query_1 );
        $post_imgs = array_map( static function ( $obj )
        { return $obj->post_id; }, $post_imgs );

        if ( ! empty( $post_imgs ) )
            Artisan::call( "mkt:delete_fs_data --option=images" );
    }

    /**
     * @return void
     */
    private function deleteOutofstock() : void
    {
        $query_0 = "
            SELECT p.ID as post_id
            FROM wp_posts p
            LEFT JOIN wp_postmeta pm
            ON p.ID = pm.post_id
            WHERE p.post_author = {$this->store_id}
            AND pm.meta_key = '_stock_status'
            AND pm.meta_value = 'outofstock';
        ";

        $post_ids = $this->conn->select( query: $query_0 );
        $post_ids = array_map( static function ( $obj )
        { return $obj->post_id; }, $post_ids );
        $chunked = array_chunk( $post_ids, 100 );

        foreach ( $chunked as $i => $post_ids )
        {
            $i++;

            $this->log_message( message: "Chunk {$i} de " . count( $chunked ) );

            $ids = implode( ", ", $post_ids );
            $query_1 = "DELETE wp_postmeta   FROM wp_postmeta WHERE post_id     IN ({$ids});";
            $query_2 = "DELETE wp_posts      FROM wp_posts    WHERE ID          IN ({$ids});";
            $query_3 = "SELECT ID as post_id FROM wp_posts    WHERE post_parent IN ({$ids});";

            $this->log_message( message: $query_1 );
            $this->log_message( message: $query_2 );
            $this->log_message( message: $query_3 );

            $this->conn->select( query: $query_1 );
            $this->conn->select( query: $query_2 );

            $imgs_ids = $this->conn->select( query: $query_3 );
            $imgs_ids = array_map( static function ( $obj )
            { return (string) $obj->post_id; }, $imgs_ids );

            $content = json_encode( [
                "type" => "attachment",
                "posts" => $imgs_ids,
            ] );

            $response = Http::timeout( 90 )
                ->withHeaders( headers: $this->headers )
                ->withBody( content : $content, contentType : "application/json" )
                ->post( url: $this->url );

            $this->log_message( message: $response );
        }
    }
}
