<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use DateTime;
use Exception;
use DateInterval;
use App\Helpers\Helper;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class AwinIntegration
{
    const RG = "BR";
    const ID = "-awin";
    const TZ = "America/Sao_Paulo";

    // protected $env;
    protected $tkn;

    protected $url_offers;

    protected $url_programmes;
    protected $url_programmedetails;

    protected $url_commissiongroups;
    protected $url_commissionsharingrules;

    protected $url_transactions;
    protected $url_linkbuilder;

    protected $stores      = [];
    protected $storesLocal = [];

    protected $base        = 100; // base de calculo, ex 100 reais gastos
    protected $rate        = 10;  // taxa de conversão, 10 pontos a cada 1 real

    public function __construct()
    {
        $env       = config( "awin.env"                 );
        $url       = config( "awin.{$env}.url"          );
        $p_id      = config( "awin.{$env}.publisher_id" );
        $this->tkn = config( "awin.{$env}.token"        );

        // Stores
        $this->url_programmes             = "{$url}/publishers/{$p_id}/programmes";             // countryCode
        $this->url_programmedetails       = "{$url}/publishers/{$p_id}/programmedetails";       // advertiserId

        // Promotions
        $this->url_offers                 = "{$url}/publisher/{$p_id}/promotions";              //

        $this->url_commissiongroups       = "{$url}/publishers/{$p_id}/commissiongroups";       // advertiserId
        $this->url_commissionsharingrules = "{$url}/publishers/{$p_id}/commissionsharingrules"; //
        $this->url_transactions           = "{$url}/publishers/{$p_id}/transactions";           // startDate - endDate - timezone

        // Url Tracking
        $this->url_linkbuilder            = "{$url}/publishers/{$p_id}/linkbuilder/generate";   //

        $this->stores                     = $this->getStores();
        $this->storesLocal                = json_decode( DB::table( "affiliates_stores" )->get()->toJson(), true );
    }

    /**
     * Awin programmes == Triibo Establishments (FB)
     *
     * @return array
     */
    public function getStores()
    {
        if ( empty( $this->stores ) )
        {
            $stores  = $this->searchStores();

            if ( !$stores[ "success" ] && empty( $stores[ "data" ] ) ) return []; // falha

            Helper::usort( "id", $stores[ "data" ] );

            /**
             * TODO remover em PRD - inicio
             */
            $aux = [];
            $ids = [ "17762", "17697", "24534", "17665", "17854", "17648", "17665", "17729", ];
            foreach ( $ids as $id )
            {
                $key = array_search( $id, array_column( $stores[ "data" ], "id" ) );
                if ( $key !== false )
                    $aux[] = $stores[ "data" ][ $key ];
            }
            $stores[ "data" ] = $aux;
            /**
             * TODO remover em PRD - final
             */

            foreach ( $stores[ "data" ] as &$store )
            {
                $store[ "commissionRange" ] = $this->searchStoresDetails( $store[ "id" ] );
            }

            return $stores[ "data" ];
        }

        return $this->translateStores( $this->stores );
    }

    /**
     * Awin offer == Triibo Store (FB) / Promotion
     */
    public function getOffers()
    {
        $promotions = [];
        $stores     = $this->getStores();

        foreach ( $stores as $key => $store )
        {
            $id         = $store[ "partnerEstablishmentId" ];
            $offers     = $this->searchOffers( $id );

            if ( empty( $offers[ "data" ] ) ) continue;

            $offers     = $this->translateOffers( $offers[ "data" ], $store[ "cashbackDefault" ] );
            $promotions = array_merge( $promotions, $offers );
        }

        return $promotions;
    }

    public function getReports()
    {
        $reports = $this->searchReports();

        return $reports;
    }

    /**
     * Get stores Awin
     *
     * @return array
     */
    private function searchStores()
    {
        $url      = "{$this->url_programmes}?relationship=joined"; //&countryCode=" . self::RG;
        $response = Http::withToken( $this->tkn )->get( $url );

        if ( !$response->successful() )
        {
            return [
                "success" => false,
                "code"    => 4001,
                "data"    => []
            ];
        }

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $response->json(),
        ];
    }

    /**
     * Get stores details Awin
     *
     * @return array
     */
    public function searchStoresDetails( int $id )
    {
        $url      = "{$this->url_programmedetails}?advertiserId={$id}";

        $response = Http::withToken( $this->tkn )->get( $url );

        if ( !$response->successful() ) return [];

        $data     = $response->json();

        return $data[ "commissionRange" ];
    }

    /**
     * SocialSoul offer == Triibo Store (FB) / Default promotion
     *
     * @param [type] $id
     * @return array
     */
    private function searchOffers( string $id )
    {
        $data  = [];

        $page  = 1;
        $ended = false;

        $body  = [
            "filters" => [
                "advertiserIds" => [ $id ],
                "exclusiveOnly" => false,
                "membership"    => "joined",
                "regionCodes"   => [ self::RG ],
                "status"        => "active",
                "type"          => "all"
            ]
        ];

        do
        {
            $body[ "pagination" ] = [ "page" => $page ];

            $response = Http::withToken( $this->tkn )
                            ->withBody( json_encode( $body ), "application/json" )
                            ->post( $this->url_offers );

            if ( !$response->successful() )
            {
                $err = json_decode( $response->body(), true );

                if ( isset( $err[ "error" ] ) && $err[ "error" ] == "request.limit.exceeded" )
                {
                    sleep( 10 );
                }
                else
                {
                    return [
                        "success" => false,
                        "code"    => 4001,
                        "data"    => $data
                    ];
                }
            }
            else
            {
                $page++;

                $resp       = $response->json();

                if ( !empty( $resp[ "data" ] ) )
                    $data = array_merge( $data, $resp[ "data" ] );

                $pagination = $resp[ "pagination" ];

                if ( empty( $resp[ "data" ] ) || $pagination[ "total" ] < $page || $pagination[ "total" ] == $page )
                    $ended = true;
            }
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    private function searchReports()
    {
        $sunday   = mktime( 0, 0, 0, date( "m" ), date( "d" ) - date( "w" ) - 7 );
        $saturday = mktime( 0, 0, 0, date( "m" ), date( "d" ) - date( "w" ) - 1 );
        $start    = date( "Y-m-d", $sunday   ) . "T00:00:00";
        $end      = date( "Y-m-d", $saturday ) . "T23:59:59";

        // $start    = "2022-12-01T00:00:00";
        // $end      = "2022-12-07T23:59:59";

        $url      = "{$this->url_transactions}/?startDate={$start}&endDate={$end}&timezone=" . self::TZ;
        $response = Http::withToken( $this->tkn )->get( $url );
        $data     = json_decode( $response->body(), true );

        // tratar dados

        return $data;
    }

    /**
     * Translates Awin stores into Triibo Establishments
     *
     * @param array $data
     * @return array
     */
    private function translateStores( array $data )
    {
        $newStores = [];
        $stores    = [];
        $date      = new DateTime( date( "Y-m-d H:i:s.u" ) );
        $dateEn    = ( int ) $date->format( "U" ) * 1000;
        $date      = $date->add( new DateInterval( "P7Y" ) );
        $dateDis   = ( int ) $date->format( "U" ) * 1000;

        foreach ( $data as $store )
        {
            $id            = self::ID . $store[ "id" ];
            $cashback      = "";
            $url           = Helper::parseUrl( $store[ "displayUrl" ] );
            $key           = array_search( $url, array_column( $this->storesLocal, "domain" ) );

            if ( $cashback = reset( $store[ "commissionRange" ] ) )
            {
                $cashback = ( float ) $cashback[ "max" ] / 2;
                $cashback = number_format( $cashback, 1, ",", "" );
            }

            if ( $key !== false )
            {
                if ( $this->storesLocal[ $key ][ "affiliate_name" ] !== "awin" )
                    continue;
            }
            else
            {
                $newStores[] = [
                    "affiliate_id"   => $store[ "id" ],
                    "affiliate_name" => "awin",
                    "domain"         => $url,
                    "commission"     => $cashback,
                    "created_at"     => now(),
                    "updated_at"     => now()
                ];
            }

            $auxName       = Helper::hyphenize( strtolower( $store[ "name" ] ) );
            $imageName     = "awin_{$store[ "id" ]}_{$auxName}.png";
            $imageName     = self::saveImage( "establishments", $imageName, $store[ "logoUrl" ] ) ? $imageName : "awin_standard.png";

            $stores[ $id ] = [
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

                "nome"                   => explode( " BR", $store[ "name" ] )[ 0 ],
                "descricao"              => $store[ "description" ],
                "email"                  => "contato@triibo.com.br",
                "contatoTriibo"          => [ [
                    "type"  => "telefone",
                    "value" => "+5511944965799"
                ] ],
                "contatos"               => [ "+5511944965799" ],

                "fotoThumb"              => $imageName,
                "listaFotos"             => [ $imageName ],

                "funcionamento"          => [ ...Helper::getSchedulesOperation()        ],
                "horarios"               => [ ...Helper::getSchedulesOperation( false ) ],

                "tags"                   => [ "awin" ],

                "keyWordsSet"            => []
            ];
        }

        if ( !empty( $newStores ) ) DB::table( "affiliates_stores" )->insert( $newStores );

        return $stores;
    }

    /**
     * Translates Awin offer into Triibo promotions
     *
     * @param array $data
     * @return array
     */
    private function translateOffers( array $data, string $cashback )
    {
        $promotions = [];

        foreach ( $data as $offer )
        {
            $idStore           = self::ID . $offer[ "advertiser" ][ "id" ];
            $id                = $idStore . $offer[ "promotionId" ];

            $establishmentName = $offer[ "advertiser" ][ "name" ];
            $establishmentName = explode( " BR", $establishmentName )[ 0 ];

            $date              = $offer[ "startDate" ] . " 00:00:00";
            $date              = new DateTime( date( $date ) );
            $dateEn            = ( int ) $date->format( "U" ) * 1000;

            $date              = $offer[ "endDate"   ] . "23:59:00";
            $date              = new DateTime( date( $date ) );
            $dateDis           = ( int ) $date->format( "U" ) * 1000;

            $cashback          = str_replace( ",", ".", $cashback );
            $points            = $this->base * $this->rate * ( $cashback / 100 );

            $description       = $offer[ "description" ];
            $description      .= "\n\nGanhe {$points} pontos Triibo a cada R\${$this->base}.";

            $detailsDescrip    = $offer[ "description" ];

            if ( $offer[ "type" ] == "voucher" )
                $detailsDescrip .= "\n\nUtilize o código de desconto " . $offer[ "voucher" ][ "code" ] . " no fechamento da compra.";

            $detailsDescrip   .= "\n\nVálido até " . Helper::convertDate( $offer[ "endDate" ], true );

            $imageNamePromo    = "awin_common_promotion.webp";
            $imageNameVoucher  = "awin_common_coupon.webp";

            $url               = $this->urlTracking( $offer[ "advertiser" ][ "id" ], $offer[ "url" ] );

            $promotions[ $id ] = [
                "establishmentId"    => $idStore,
                "establishmentName"  => $establishmentName,

                "title"              => $offer[ "description" ],
                "detailsTitle"       => $offer[ "description" ],

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

                "photo"              => $imageNamePromo,
                "thumbnail"          => $imageNamePromo,

                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => $url,

                "tags"               => [ "Cashback", "Promoção" ],

                "keyWordsSet"        => [],
            ];

            if ( $offer[ "type" ] == "voucher" )
            {
                $promotions[ $id ][ "checkoutCode" ] = $offer[ "voucher" ][ "code" ];
                $promotions[ $id ][ "tags"         ] = [ "Cashback", "Cupom" ];
                $promotions[ $id ][ "photo"        ] = $imageNameVoucher;
                $promotions[ $id ][ "thumbnail"    ] = $imageNameVoucher;
            }
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
        $fullPath = public_path( "images/affiliates/awin/{$path}" ) . "/{$imageName}";

        try
        {
            $response = Http::get( $url );

            if ( $response->successful() )
            {
                file_put_contents( $fullPath, $response->body() );

                $handle = Image::open( $fullPath );
                $handle->resize( 200, 150, "#FFF" );
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

    private function urlTracking( int $storeId, string $url )
    {
        $body     = [
            "shorten"        => false,
            "advertiserId"   => $storeId,
            "destinationUrl" => $url,
            "parameters"     => [
                "clickref" => "{user-triibo-id}",
            ],
        ];

        $response = Http::withToken( $this->tkn )
                        ->withBody( json_encode( $body ), "application/json" )
                        ->post( $this->url_linkbuilder );

        if( !$response->successful() ) return $url;

        $response = $response->json();

        $url      = $response[ "url" ];
        $url      = str_replace( "&campaign=",  "", $url );
        $url      = str_replace( "&clickref2=", "", $url );
        $url      = str_replace( "&clickref3=", "", $url );
        $url      = str_replace( "&clickref4=", "", $url );
        $url      = str_replace( "&clickref5=", "", $url );
        $url      = str_replace( "&clickref6=", "", $url );
        $url      = str_replace( "%7Buser-triibo-id%7D", "{user-triibo-id}", $url );

        return $url;
    }

    public function schGetStores()
    {
        return $this->stores;
    }
}
