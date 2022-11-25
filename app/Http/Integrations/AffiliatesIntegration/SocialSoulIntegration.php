<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use DateTime;
use Exception;
use DateInterval;
use App\Helpers\Helper;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Affiliates\CofryController;

class SocialSoulIntegration
{
    const ID           = "-socialsoul";

    protected $env;
    protected $url_category;
    protected $url_coupon;
    protected $url_offer;
    protected $url_store;
    protected $source_id;

    public function __construct()
    {
        $this->env          = config( "socialsoul.env"                    );
        $url                = config( "socialsoul.{$this->env}.url"       );
        $tkn                = config( "socialsoul.{$this->env}.token"     );
        $this->source_id    = config( "socialsoul.{$this->env}.source_id" );

        $this->url_coupon   = "{$url}/v2/{$tkn}/coupon";
        $this->url_category = "{$url}/v3/{$tkn}/category";
        $this->url_offer    = "{$url}/v3/{$tkn}/offer";
        $this->url_store    = "{$url}/v3/{$tkn}/store";
    }

    /**
     * SocialSoul store == Triibo Establishments (FB)
     *
     * @return array
     */
    public function getStores( bool $flag = false )
    {
        $path    = public_path( "images/affiliates/socialsoul/establishments" );
        $default = public_path( "images/standard.png" );

        File::copy( $default, $path . "/standard.png" );

        $stores  = $this->searchStores();

        if ( !$stores[ "success" ] && empty( $stores[ "data" ] ) ) return []; // falha

        Helper::usort( "id", $stores[ "data" ] );

        $establishments = $this->translateStores( $stores[ "data" ], $flag );

        return $establishments;
    }

    /**
     * SocialSoul coupon == Triibo Store (FB) / Promotion
     *
     * @return array
     */
    public function getCoupons()
    {
        $coupons    = $this->searchCoupons();

        if ( !$coupons[ "success" ] && empty( $coupons[ "data" ] ) ) return []; // falha

        Helper::usort( "id", $coupons[ "data" ] );

        $promotions = $this->translateCoupons( $coupons[ "data" ] );

        return $promotions;
    }

    /**
     * SocialSoul offer == Triibo Store (FB) / Promotion
     */
    public function getOffers()
    {
        $promotions = [];
        $stores     = $this->getStores( true );

        foreach ( $stores as $key => $store )
        {
            if ( !isset( $store[ "hasOffer" ] ) || $store[ "hasOffer" ] == 0 ) continue;

            $id     = $store[ "partnerEstablishmentId" ];
            $offers = $this->searchOffers( $id );

            if ( !$offers[ "success" ] && empty( $offers[ "data" ] ) ) continue;

            Helper::usort( "id", $offers[ "data" ] );

            $offers     = $this->translateOffers( $offers[ "data" ], $store[ "cashbackDefault" ] );
            $promotions = array_merge( $promotions, $offers );
        }

        return $promotions;
    }

    /**
     * Get stores SocialSoul
     *
     * @return array
     */
    private function searchStores()
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

                $body       = $response->json();
                $data       = array_merge( $data, $body[ "stores" ] );

                $pagination = $body[ "pagination" ];
                $pg_size    = $pagination[ "size"      ];
                $pg_total   = $pagination[ "totalPage" ];
                $pg_current = $pagination[ "page"      ];

                if ( $pg_total == $pg_current || $pg_size < 100 ) $ended = true;
                if ( $this->env == "local" && $page > 100       ) $ended = true;
            }
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    /**
     * Get coupon SocialSoul
     *
     * @return array
     */
    private function searchCoupons()
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

                $body       = $response->json();
                $data       = array_merge( $data, $body[ "coupons" ] );

                $pagination = $body[ "pagination" ];
                $pg_size    = $pagination[ "size"      ];
                $pg_total   = $pagination[ "totalPage" ];
                $pg_current = $pagination[ "page"      ];

                if ( $pg_total == $pg_current || $pg_size < 100 ) $ended = true;
                if ( $this->env == "local" && $page > 100       ) $ended = true;
            }
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    /**
     * SocialSoul offer == Triibo Store (FB) / Default promotion
     *
     * @param [type] $id
     * @return array
     */
    private function searchOffers( string $id )
    {
        $partial_url = "/_store/{$id}?sourceId={$this->source_id}";
        $data        = [];
        $page        = 1;
        $size        = 100;
        $ended       = false;

        do
        {
            $response = Http::get( $this->url_offer . "{$partial_url}&page={$page}&size={$size}" );

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

                $body       = $response->json();
                $data       = array_merge( $data, $body[ "offers" ] );

                $pagination = $body[ "pagination" ];
                $pg_size    = $pagination[ "size"      ];
                $pg_total   = $pagination[ "totalPage" ];
                $pg_current = $pagination[ "page"      ];

                if ( $pg_total == $pg_current || $pg_size < 100 ) $ended = true;
                if ( $this->env == "local" && $page > 100       ) $ended = true;
            }
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    /**
     * Translates SocialSoul stores into Triibo Establishments
     *
     * @param array $data
     * @return array
     */
    private function translateStores( array $data, bool $flag )
    {
        $stores  = [];
        $date    = new DateTime( date( "Y-m-d H:i:s.u" ) );
        $dateEn  = ( int ) $date->format( "U" ) * 1000;
        $date    = $date->add( new DateInterval( "P7Y" ) );
        $dateDis = ( int ) $date->format( "U" ) * 1000;

        /**
         * TODO remover em PRD
         */
        $i = 1;

        foreach ( $data as $store )
        {
            /**
             * TODO remover em PRD
             */
            if ( $i > 10 ) continue;
            $i++;


            $id                   = self::ID . $store[ "id" ];

            $cashback             = ( float ) $store[ "maxCommission" ] / 2;
            $cashback             = number_format( $cashback, 1, ",", "" );

            $auxName              = Helper::hyphenize( strtolower( $store[ "name" ] ) );
            $imageName            = "socialsoul_{$store[ "id" ]}_{$auxName}.png";
            $imageName            = self::saveImage( "establishments", $imageName, $store[ "thumbnail" ] ) ? $imageName : "standard.png";

            $stores[ $id ]        = [
                "partnerEstablishmentId" => $store[ "id" ],
                "cashbackDefault"        => $cashback,

                "enableDate"             => $dateEn,
                "disableDate"            => $dateDis,
                "dataAlteracao"          => $dateEn,
                "dataInclusao"           => $dateEn,

                "idGestor"               => "+5511949289507@sms,triibo,com,br",
                "isPublished"            => true,

                "endereco"               => "Brasil",
                "lat"                    => -15.52047783763576,
                "long"                   => -48.015408891158984,

                "nome"                   => $store[ "name" ],
                "descricao"              => "", // ???

                "fotoThumb"              => $imageName,
                "listaFotos"             => [ $imageName ],

                "funcionamento"          => [ ...Helper::getSchedulesOperation()        ],
                "horarios"               => [ ...Helper::getSchedulesOperation( false ) ],

                "tags"                   => [],

                "keyWordsSet"            => []
            ];

            if ( $flag ) $stores[ $id ][ "hasOffer" ] = $store[ "hasOffer" ];
        }

        return $stores;
    }

    /**
     * Translates SocialSoul coupons into Triibo promotions
     *
     * @param array $data
     * @return array
     */
    private function translateCoupons( array $data )
    {
        $promotions = [];
        $date       = new DateTime(date( "Y-m-d H:i:s.u" ) );
        $dateEn     = ( int ) $date->format( "U" ) * 1000;

        foreach ( $data as $key => $coupon )
        {
            $date              = Helper::convertDateSS( $coupon[ "vigency" ] );
            $date              = new DateTime( date( $date ) );
            $dateDis           = ( int ) $date->format( "U" ) * 1000;

            $idStore           = self::ID . $coupon[ "store" ][ "id" ];
            $id                = $idStore . $coupon[ "id" ];

            $establishmentName = $coupon[ "store" ][ "name" ];

            $description       = $coupon[ "description" ];
            $description      .= "\n\nGanhe X pontos a cada Y reais.";
            $description      .= "\n\nCategoria: " . $coupon[ "category" ][ "name" ];

            $detailsDescrip    = $coupon[ "description" ];
            $detailsDescrip   .= "\n\nUtilize o código de desconto " . $coupon[ "code" ] . " no fechamento da compra.";
            $detailsDescrip   .= "\n\nVálido até " . explode( " ", $coupon[ "vigency" ] )[ 0 ];

            $promotions[ $id ] = [
                "establishmentId"    => $idStore,
                "establishmentName"  => $establishmentName,

                "title"              => $coupon[ "description" ],
                "detailsTitle"       => $coupon[ "description" ],

                "description"        => $description,
                "detailsDescription" => $detailsDescrip,

                "checkoutCode"       => $coupon[ "code" ],

                "enableDate"         => $dateEn,
                "disableDate"        => $dateDis,
                "dueDate"            => $dateDis,

                "isPublished"        => true,
                "active"             => true,

                "lat"                => -15.52047783763576,
                "long"               => -48.015408891158984,

                "level"              => 99,

                "photo"              => "standard.png", // substituir pelas imagens geradas pela Triibo
                "thumbnail"          => "standard.png", // substituir pelas imagens geradas pela Triibo

                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => "{urlSocialSoul}",

                "tags"               => [ "Cashback", "Cupom", $coupon[ "category" ][ "name" ] ],

                "keyWordsSet"        => [],
            ];
        }

        return $promotions;
    }

    /**
     * Translates SocialSoul offer into Triibo promotions
     *
     * @param array $data
     * @return array
     */
    private function translateOffers( array $data, string $cashback )
    {
        $promotions = [];
        $date       = new DateTime(date( "Y-m-d H:i:s.u" ) );
        $dateEn     = ( int ) $date->format( "U" ) * 1000;
        $date       = $date->add( new DateInterval( "P7Y" ) );
        $dateDis    = ( int ) $date->format( "U" ) * 1000;

        /**
         * TODO remover em PRD
         */
        $i = 1;

        foreach ( $data as $offer )
        {
            /**
             * TODO remover em PRD
             */
            if ( $i > 10 ) continue;
            $i++;


            $idStore           = self::ID . $offer[ "store" ][ "id" ];
            $id                = $idStore . $offer[ "id" ];

            $establishmentName = $offer[ "store" ][ "name" ];

            $description       = "";

            if ( isset( $offer[ "priceFrom" ] ) )
            {
                $fullPrice        = number_format( $offer[ "priceFrom" ], 2, ",", "" );
                $salePrice        = number_format( $offer[ "price"     ], 2, ",", "" );
                $description      = "De R\${$fullPrice} por R\${$salePrice}.\n\n";
            }

            if ( isset( $offer[ "installment" ] ) )
            {
                $installmentQntt  = $offer[ "installment" ][ "quantity" ];
                $installmentValue = $offer[ "installment" ][ "value"    ];
                $installmentValue = number_format( $installmentValue, 2, ",", "" );
                $description     .= "Em {$installmentQntt}x de R\${$installmentValue}.\n\n";
            }

            $base              = 10; // base de calculo, ex 10 reais gastos
            $rate              = 10; // taxa de conversão, 10 pontos a cada 1 real
            $cashback          = str_replace( ",", ".", $cashback );
            $points            = $base * $rate * ( $cashback / 100 );

            $description      .= "Ganhe {$points} pontos a cada {$base} reais.";
            $description      .= "\n\nCategoria: " . $offer[ "category" ][ "name" ];

            $imageUrl          = $offer[ "thumbnail" ];
            $imageName         = "socialsoul_{$offer[ "store" ][ "id" ]}{$offer[ "id" ]}.png";
            $imageName         = self::saveImage( "promotions", $imageName, $imageUrl ) ? $imageName : "standard.png";

            $promotions[ $id ] = [
                "establishmentId"    => $idStore,
                "establishmentName"  => $establishmentName,

                "title"              => $offer[ "name" ],
                "detailsTitle"       => $offer[ "name" ],

                "description"        => $description,
                "detailsDescription" => $description,

                "enableDate"         => $dateEn,
                "disableDate"        => $dateDis,
                "dueDate"            => $dateDis,

                "isPublished"        => true,
                "active"             => true,

                "lat"                => -15.52047783763576,
                "long"               => -48.015408891158984,

                "level"              => 99,

                "photo"              => $imageName,
                "thumbnail"          => $imageName,

                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => "{urlSocialSoul}",

                "tags"               => [ "Cashback", "Promoção", $offer[ "category" ][ "name" ] ],

                "keyWordsSet"        => [],
            ];
        }

        return $promotions;
    }

    /**
     *
     *
     * @param String $logo
     * @param String $path
     * @param String $prefix
     * @return void
     */
    private static function saveImage( string $path, string $imageName, string $url )
    {
        $fullPath = public_path( "images/affiliates/socialsoul/{$path}" ) . "/{$imageName}";

        try
        {
            $response = Http::get( $url );

            if ( $response->successful() )
            {
                file_put_contents( $fullPath, $response->body() );

                $handle = Image::open( $fullPath );
                $handle->scaleResize( 200, 150, "#FFF" );
                $handle->save( $fullPath );

                return true;
            }
        }
        catch ( Exception $e )
        {
            return false;
        }

        return false;
    }

}
