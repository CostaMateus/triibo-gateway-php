<?php

namespace App\Console\Commands\Marketplace;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateSKUs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sku:delete_duplicate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete duplicate SKUs in the marketplace';

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
        $DB = (config("app.env") == "local") ? "dev-mkt" : "prd-mkt";

        do {
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

            $posts = DB::connection($DB)->select($select_posts);

            if (count($posts) > 0)
            {
                $del_metas = "DELETE FROM wp_postmeta WHERE ";
                $del_posts = "DELETE FROM wp_posts    WHERE ";

                foreach ($posts as $key => $post)
                {
                    if ($key == array_key_last($posts))
                    {
                        $del_metas .= "post_id = $post->ID;";
                        $del_posts .= "ID      = $post->ID;";
                    }
                    else
                    {
                        $del_metas .= "post_id = $post->ID OR ";
                        $del_posts .= "ID      = $post->ID OR ";
                    }
                }

                $result_metas = DB::connection($DB)->delete($del_metas);
                $result_posts = DB::connection($DB)->delete($del_posts);

                Log::channel("fastshop")->info("Deleting duplicate SKUs", [
                    "posts" => $result_posts,
                    "metas" => $result_metas
                ]);
            }
        }
        while(count($posts) > 0);
    }
}
