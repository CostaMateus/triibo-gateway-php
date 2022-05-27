<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class UpdateSKUs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sku:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the entire FastShop SKU list on the marketplace';

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

        $select_posts = "
            SELECT
                post_id    AS ID,
                meta_value AS SKU
            FROM   wp_postmeta
            WHERE  meta_key = '_sku'
            AND    meta_value LIKE 'FS-%';
        ";

        $posts  = DB::connection($DB)->select($select_posts);

        $env    = config("fastshop.env");
        $fsUrl  = config("fastshop.$env.fs.url");
        $header = config("fastshop.$env.fs.headers");
        $url    = "$fsUrl/pvt/orderForms/simulation";

        foreach ($posts as $post)
        {
            $sku  = explode("FS-", $post->SKU)[1];
            $body = DataTranslator::translate("queryStockPrice", [$sku]);

            try
            {
                $response = Http::withHeaders($header)->withBody(json_encode($body), "application/json")->get($url);

                if ($response->successful())
                {
                    $fsData = $response->json();
                    $wcData = DataTranslator::translate("updateStockPrice", $fsData);

                    Product::update($post->ID, $wcData);

                    Log::channel("fastshop")->info("Scheduler - SKU updated", [ $post->SKU ]);
                }
            }
            catch (Exception $e)
            {
                Log::channel("fastshop")->error("Exception - updateStockPrice Schedule", [$e]);
            }
        }
    }
}
