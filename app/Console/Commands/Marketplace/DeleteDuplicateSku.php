<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB};

class DeleteDuplicateSku extends Command
{
    /**
     * @var string
     */
    protected const LOG_CHANNEL = "marketplace";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:delete_duplicate_sku";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete duplicate SKUs in the marketplace";

    /**
     * @var string
     */
    protected $prefix = "MKT Duplicate Products:";

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
        $message = "{$this->prefix} Starting process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( string: $message );

        $db = ( config( key: "app.env" ) === "local" ) ? "dev-mkt" : "prd-mkt";
        $conn = DB::connection( name: $db );

        do
        {
            $select_posts = "
                SELECT
                    p.ID,
                    m.meta_value as SKU,
                    m.count as COUNT
                FROM
                    wp_posts p
                INNER JOIN (
                    SELECT
                        meta_value,
                        min(post_id) AS post_id,
                        count(post_id) as count
                    FROM wp_postmeta
                    WHERE meta_key = '_sku'
                    AND meta_value IS NOT NULL
                    AND meta_value != ''
                    GROUP BY meta_value
                    HAVING COUNT(meta_value) > 1
                ) m ON p.ID = m.post_id
                WHERE post_type = 'product';
            ";

            $posts = $conn->select( query: $select_posts );

            if ( count( $posts ) > 0 )
            {
                $del_metas = "DELETE FROM wp_postmeta WHERE ";
                $del_posts = "DELETE FROM wp_posts    WHERE ";

                foreach ( $posts as $key => $post )
                {
                    if ( $key === array_key_last( $posts ) )
                    {
                        $del_metas .= "post_id = {$post->ID};";
                        $del_posts .= "ID      = {$post->ID};";
                    }
                    else
                    {
                        $del_metas .= "post_id = {$post->ID} OR ";
                        $del_posts .= "ID      = {$post->ID} OR ";
                    }
                }

                $data = [
                    "posts" => $conn->delete( $del_posts ),
                    "metas" => $conn->delete( $del_metas )
                ];

                log_message( channel: "marketplace", message: "Marketplace: Deleting duplicate SKUs...", context: $data );
            }
        }
        while ( count( $posts ) > 0 );

        $message = "{$this->prefix} Finished process...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );

        $diff = $start->diffInMinutes( now() );
        $message = "{$this->prefix} Runtime {$diff} minutes...";
        log_message( channel: self::LOG_CHANNEL, message: $message );
        $this->info( $message );
    }

    /**
     * Remove orphaned entries in the 'wc_product_meta_lookup' table.
     * This query deletes any rows from 'wp_wc_product_meta_lookup' where the associated
     * post in the 'wp_posts' table is missing (i.e., the post.ID is NULL).
     *
     * @return string
     */
    private function deletePostMetaLookupQuery() : string
    {
        return "
            DELETE
                wp_wc_product_meta_lookup
            FROM
                wp_wc_product_meta_lookup
            LEFT JOIN
                wp_posts
            ON
                wp_wc_product_meta_lookup.product_id = wp_posts.ID
            WHERE
                wp_posts.ID IS NULL;
        ";
    }

    /**
     * Remove orphaned entries in the 'postmeta' table.
     * This query deletes any rows from 'wp_postmeta' where the associated
     * post in the 'wp_posts' table is missing (i.e., the post.ID is NULL).
     *
     * @return string
     */
    private function deletePostMetaQuery() : string
    {
        return "
            DELETE
                wp_postmeta
            FROM
                wp_postmeta
            LEFT JOIN
                wp_posts
            ON
                wp_postmeta.post_id = wp_posts.ID
            WHERE
                wp_posts.ID IS NULL;
        ";
    }
}
