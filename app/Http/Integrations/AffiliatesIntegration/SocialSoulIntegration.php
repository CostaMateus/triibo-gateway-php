<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use DateTime;
use DateInterval;
use Exception;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SocialSoulIntegration
{

    protected $url_category;
    protected $url_coupon;
    protected $url_offer;
    protected $url_store;
    protected $source_id;

    public function __construct()
    {
        $env                = config( "socialsoul.env"              );
        $url                = config( "socialsoul.{$env}.url"       );
        $tkn                = config( "socialsoul.{$env}.token"     );
        $this->source_id    = config( "socialsoul.{$env}.source_id" );

        $this->url_coupon   = "{$url}/v2/{$tkn}/coupon";
        $this->url_category = "{$url}/v3/{$tkn}/category";
        $this->url_offer    = "{$url}/v3/{$tkn}/offer";
        $this->url_store    = "{$url}/v3/{$tkn}/store";
    }

    public function getCoupons()
    {
        $partial_url = "/_all?sourceId={$this->source_id}";
        $data        = [];
        $page        = 1;
        $size        = 100;
        $ended       = false;

        do
        {
            $response = Http::get( $this->url_coupon . "{$partial_url}&page={$page}&size={$size}" );

            if( !$response->successful() )
            {
                return [
                    "success" => false,
                    "code"    => 4001,
                    "data"    => $data
                ];
            }
            else
            {
                $page++;

                $body = $response->json();
                $data = array_merge( $data, $body[ "coupons" ] );

                $pagination = $body[ "pagination" ];
                $pg_size    = $pagination[ "size"      ];
                $pg_total   = $pagination[ "totalPage" ];
                $pg_current = $pagination[ "page"      ];

                if ( $pg_total == $pg_current || $pg_size < 100 ) $ended = true;
            }
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    public function getStores()
    {
        $partial_url = "/_all?sourceId={$this->source_id}";
        $data        = [];
        $page        = 1;
        $size        = 100;
        $ended       = false;

        do
        {
            $response = Http::get( $this->url_store . "{$partial_url}&page={$page}&size={$size}" );

            if( !$response->successful() )
            {
                return [
                    "success" => false,
                    "code"    => 4001,
                    "data"    => $data
                ];
            }
            else
            {
                $page++;

                $body = $response->json();
                $data = array_merge( $data, $body[ "stores" ] );

                $pagination = $body[ "pagination" ];
                $pg_size    = $pagination[ "size"      ];
                $pg_total   = $pagination[ "totalPage" ];
                $pg_current = $pagination[ "page"      ];

                if ( $pg_total == $pg_current || $pg_size < 100 ) $ended = true;
            }
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    public function getOffers( $id )
    {
        $partial_url = "/_store/{$id}?sourceId={$this->source_id}";
        $data        = [];
        $page        = 1;
        $size        = 100;
        $ended       = false;

        $counter     = 0;

        do
        {
            $full = $this->url_offer . "{$partial_url}&page={$page}&size={$size}";
            $response = Http::get( $full );

            if( !$response->successful() )
            {
                return [
                    "success" => false,
                    "code"    => 4001,
                    "data"    => $data
                ];
            }
            else
            {
                $page++;

                $body = $response->json();
                $data = array_merge( $data, $body[ "offers" ] );

                $counter += count( $body[ "offers" ] );

                echo $counter . "\n";

                $pagination = $body[ "pagination" ];
                $pg_size    = $pagination[ "size"      ];
                $pg_total   = $pagination[ "totalPage" ];
                $pg_current = $pagination[ "page"      ];

                if ( $pg_total == $pg_current || $pg_size < 100 || $page > 100 ) $ended = true;
            }

            sleep( 2 );
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

}
