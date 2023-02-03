<?php

namespace App\Console\Commands\Marketplace;

use Exception;
use App\Helpers\Helper;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\Notification;

class GetStockPriceFastShop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:get_stock_price_FS";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get updated stock/price of FS products";

    private $env;
    private $fsUrl;
    private $fsHeaders;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->env       = config( "fastshop.env"                   );
        $this->fsUrl     = config( "fastshop.$this->env.fs.url"     );
        $this->fsHeaders = config( "fastshop.$this->env.fs.headers" );
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->updateFastShopStockPrice();
    }

    private function updateFastShopStockPrice()
    {
        echo "Starting FS stock/price update process\n";

        $skus = DB::table( "marketplace_product_skus" )
                    ->where( "status", "active" )
                    ->orWhereNull( "status" )
                    ->limit( 100 )
                    ->get()->toArray();

        $skus = json_decode( json_encode( $skus ), true );

        echo "SKUs loaded from local table\n";

        $aux  = [];

        foreach ( $skus as $sku )
        {
            if ( $sku[ "updated_at" ] == null )
            {
                $aux[] = $sku;
                continue;
            }

            $upd   = new Carbon( $sku[ "updated_at"     ] );
            $inact = new Carbon( $sku[ "inactivated_at" ] );
            $now   = Carbon::now();

            if ( $upd->diff( $now )->days <= 15 )
            {
                $aux[] = $sku;
                continue;
            }
            else if ( $sku[ "status" ] == "active" )
            {
                $sku[ "status"         ] = "inactive";
                $sku[ "inactivated_at" ] = now();

                $ok = DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );
                continue;
            }

            if ( $inact->diff( $now )->days > 15 )
            {
                $aux[] = $sku;
                continue;
            }
        }

        echo "SKUs with update date less than 15 days\n";

        if ( empty( $aux ) ) return;

        $fsData   = $this->prepareData( $aux );
        $response = $this->getStockPrice( $fsData );
        $data     = $this->translateFsData( $response );

        echo "Updating the local table\n";

        foreach ( $aux as $sku )
        {
            $index = array_search( $sku[ "product_sku" ], array_column( $data, "product_sku" ) );

            if ( $index !== false )
            {
                $product = $data[ $index ];

                if ( $sku[ "stock" ] == 0 && $product[ "stock" ] == 0 )
                    unset( $sku[ "updated_at" ] );
                else
                    $sku[ "updated_at" ] = now();

                if ( $sku[ "inactive" ] )
                {
                    if ( $product[ "stock" ] == 0 )
                    {
                        $sku[ "inactivated_at" ] = now();
                    }
                    else
                    {
                        $sku[ "inactivated_at" ] = null;
                        $sku[ "status"         ] = "active";
                    }
                }

                $sku[ "regular_price"  ] = $product[ "regular_price" ];
                $sku[ "sale_price"     ] = $product[ "sale_price"    ];
                $sku[ "stock"          ] = $product[ "stock"         ];
                $sku[ "consulted_at"   ] = now();

                $ok = DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );

                if ( !$ok ) echo "{$sku[ "product_sku" ]} fails to save SKU\n";
            }
            else
            {
                $sku[ "stock"          ] = 0;
                $sku[ "status"         ] = "inactive";
                $sku[ "consulted_at"   ] = now();
                $sku[ "inactivated_at" ] = now();

                $ok = DB::table( "marketplace_product_skus" )->where( "id", $sku[ "id" ] )->update( $sku );

                if ( !$ok ) echo "{$sku[ "product_sku" ]} fails to save SKU\n";
            }

            if ( $ok ) echo "{$sku[ "product_sku" ]} successfully saved\n";
        }

        echo "Finished FS stock/price update process\n";
    }

    private function prepareData( $array )
    {
        echo "Formatting SKUs to query in FS\n";

        $items  = [];

        foreach ( $array as $sku )
        {
            $items[] = [
                "id"       => substr( $sku[ "product_sku" ], 3 ),
                "quantity" => 1,
                "seller"   => "FSH"
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => "",
            "country"        => "",
            "geoCoordinates" => []
        ];
    }

    private function getStockPrice( $body )
    {
        $count = count( $body[ "items" ] );
        echo "Querying {$count} SKUs at once\n";

        try
        {
            $url      = "$this->fsUrl/pvt/orderForms/simulation";
            $response = Http::withHeaders( $this->fsHeaders )->withBody( json_encode( $body ), "application/json" )->get( $url );

            if ( $response->successful() ) return $response->json();

            throw new Exception( "Unable to query FastShop products data" );
        }
        catch ( Exception $e )
        {
            echo "FS SKUs query failed\n";
            Helper::sendMailNotification( "marketplace", "getStockPrice()", "Exception", $e );
        }
    }

    private function translateFsData( $array )
    {
        echo "Converting FS response data\n";

        try
        {
            $items = $array[ "items" ];
            $infos = $array[ "logisticsInfo" ];
            $data  = [];

            foreach ( $infos as $info )
            {
                $index   = $info[ "itemIndex" ];
                $key     = array_search( $index, array_column( $items, "requestIndex" ) );
                $item    = $items[ $key ];

                $sku     = "FS-" . $item[ "id" ];
                $regular = $item[ "listPrice"    ];
                $sale    = $item[ "price"        ];
                $stock   = $info[ "stockBalance" ];

                $data[]  = [
                    "product_sku"   => $sku,
                    "regular_price" => Helper::insertDecimal( $regular ),
                    "sale_price"    => Helper::insertDecimal( $sale    ),
                    "stock"         => $stock,
                ];

                echo "{$sku} processed\n";
            }

            echo "Conversion finished\n";

            return $data;
        }
        catch ( Exception $e )
        {
            echo "FS response data conversion failed\n";
            Helper::sendMailNotification( "marketplace", "translateFsData()", "Exception", $e );
        }
    }
}
