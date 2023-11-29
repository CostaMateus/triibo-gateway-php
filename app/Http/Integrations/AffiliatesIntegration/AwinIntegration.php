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
use Illuminate\Support\Facades\Storage;

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

    public function __construct( bool $loadStores = true )
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

        if ( $loadStores )
        {
            $this->stores      = $this->getStores();
            $this->storesLocal = json_decode( DB::table( "affiliates_stores" )->get()->toJson(), true );
        }
    }

    /**
     * Awin programmes == Triibo Establishments (FB)
     *
     * @return array
     */
    public function getStores( $withoutTranslate = false )
    {
        if ( empty( $this->stores ) )
        {
            $stores  = $this->searchStores();

            if ( !$stores[ "success" ] && empty( $stores[ "data" ] ) ) return []; // falha

            Helper::usort( "id", $stores[ "data" ] );

            // $auxStores = [];

            foreach ( $stores[ "data" ] as $key => &$store )
            {
                $key  += 1;
                $count = count( $stores[ "data" ] );
                echo "Stores : Loading establishments {$key} of {$count}\n";

                $store[ "commissionRange" ] = $this->searchStoresDetails( $store[ "id" ] );

                // $auxStores[] = $store;
                // if ( $key == 5 ) break;
            }

            return $stores[ "data" ];
            // return $auxStores;
        }

        if ( $withoutTranslate ) return $this->stores;

        return $this->translateStores( $this->stores );
    }

    /**
     * Awin offer == Triibo Store (FB) / Promotion
     */
    public function getOffers()
    {
        $storesOrig = $this->getStores( true );
        $promotions = $this->translatePromotionDefault( $storesOrig );

        $stores     = $this->getStores();

        foreach ( $stores as $store )
        {
            $id         = $store[ "partnerEstablishmentId" ];
            echo "Promotions : Loading promotions for store: {$id}\n";
            $offers     = $this->searchOffers( $id );

            if ( empty( $offers[ "data" ] ) ) continue;

            $offers     = $this->translateOffers( $offers[ "data" ], $store[ "cashbackDefault" ] );
            $promotions = $promotions + $offers;
        }

        return $promotions;
    }

    /**
     *
     * @param boolean $isUpdate
     * @return array
     */
    public function getReports( bool $isUpdate = false )
    {
        return $this->searchReports( $isUpdate );
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
    public function searchStoresDetails( int $id, bool $allInfo = false )
    {
        $url      = "{$this->url_programmedetails}?advertiserId={$id}";

        $response = Http::withToken( $this->tkn )->get( $url );

        if ( !$response->successful() )
        {
            $err = json_decode( $response->body(), true );

            if ( isset( $err[ "error" ] ) && $err[ "error" ] == "request.limit.exceeded" )
            {
                sleep( 5 );
                return $this->searchStoresDetails( $id, $allInfo );
            }

            $data = [
                "commissionRange" => [ [ "min" => 0, "max" => 0 ] ],
                "programmeInfo"   => [
                    "name" => "Não encontrado"
                ]
            ];

            return ( $allInfo ) ? $data : $data[ "commissionRange" ][ 0 ];
        }

        $data     = $response->json();
        $key      = array_search( "percentage", array_column( $data[ "commissionRange" ], "type" ) );

        return ( $allInfo ) ? $data : $data[ "commissionRange" ][ $key ];
    }

    /**
     * Awin offer == Triibo Store (FB) / Default promotion
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

                $resp = $response->json();

                if ( !empty( $resp[ "data" ] ) )
                {
                    $data = array_merge( $data, $resp[ "data" ] );
                }

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

    /**
     *
     * @param boolean $isUpdate
     * @return array
     */
    private function searchReports( bool $isUpdate = false )
    {
        // $sunday   = mktime( 0, 0, 0, date( "m" ), date( "d" ) - date( "w" ) - 7 );
        // $saturday = mktime( 0, 0, 0, date( "m" ), date( "d" ) - date( "w" ) - 1 );
        // $start    = date( "Y-m-d", $sunday   ) . "T00:00:00";
        // $end      = date( "Y-m-d", $saturday ) . "T23:59:59";

        // if ( $isUpdate )
        // {
        //     $date     = "2023-07-01";
        //     $start    = "{$date}T00:00:00";
        //     $date     = "2023-07-31";
        //     $end      = "{$date}T23:59:59";
        // }
        // else
        // {
        //     $date     = "2023-08-01";
        //     $start    = "{$date}T00:00:00";
        //     $date     = "2023-08-31";
        //     $end      = "{$date}T23:59:59";
        // }

        $date     = date( "Y-m-d", strtotime( "yesterday" ) );
        $start    = "{$date}T00:00:00";
        $end      = "{$date}T23:59:59";

        $query    = "?startDate={$start}";
        $query   .= "&endDate={$end}";
        $query   .= "&timezone=" . self::TZ;
        $query   .= ( $isUpdate ) ? "&dateType=validation" : "&dateType=transaction&status=pending";

        $response = Http::withToken( $this->tkn )->get( "{$this->url_transactions}/{$query}" );

        if ( !$response->successful() )
        {
            $err = json_decode( $response->body(), true );

            if ( isset( $err[ "error" ] ) && $err[ "error" ] == "request.limit.exceeded" )
            {
                sleep( 10 );

                $data = $this->searchReports( $isUpdate );
            }
            else
            {
                $data = [
                    "success" => false,
                    "code"    => 4001,
                    "data"    => null
                ];
            }
        }
        else
        {
            $data = [
                "success" => true,
                "code"    => 0,
                "data"    => $response->json()
            ];
        }

        if ( !empty( $data[ "data" ] ) )
        {
            foreach ( $data[ "data" ] as &$report )
            {
                $aux       = [];

                $storeId   = $report[ "advertiserId" ];
                $storeInfo = $this->searchStoresDetails( $storeId, true );
                $cashback  = Helper::getCashback( $storeInfo[ "commissionRange" ] );
                $storeName = explode( " BR", $storeInfo[ "programmeInfo" ][ "name" ] )[ 0 ];

                $aux[ "uid"               ] = ( isset( $report[ "clickRefs" ] ) && isset( $report[ "clickRefs" ][ "clickRef" ] ) )
                                                ? $report[ "clickRefs" ][ "clickRef" ]
                                                : null;

                $aux[ "partner"           ] = "awin";
                $aux[ "transactionId"     ] = $report[ "id" ];
                $aux[ "transactionDate"   ] = Helper::convertDatetime( $report[ "transactionDate" ] );
                $aux[ "processingDate"    ] = null;
                $aux[ "status"            ] = Helper::awinReportStatus( $report[ "commissionStatus" ] );

                $aux[ "updateDate"        ] = ( !$isUpdate && $aux[ "status" ] == "pending" )
                                                ? $report[ "transactionDate" ]
                                                : $report[ "validationDate"  ];

                $aux[ "updateDate"        ] = Helper::convertDatetime( $aux[ "updateDate" ] );

                $aux[ "advertiserId"      ] = $storeId;
                $aux[ "advertiserName"    ] = $storeName;


                $totalOrder                 = ( float ) $report[ "saleAmount"       ][ "amount" ];            // 500  | 500
                $commission                 = ( float ) $report[ "commissionAmount" ][ "amount" ];            //  25  |   0

                $totalCommission            = ( $commission > 0 ) ? ( 100 * $commission ) / $totalOrder : 0;  //   5% |   0
                $totalCommission            = ( float ) number_format( $totalCommission, 2, ".", "" );

                $commissionClient           = ( float ) str_replace( ",", ".", $cashback ); //   2% |   0,8%

                $commissionTriibo           = $totalCommission - $commissionClient;                           //   3% |  -0,8%
                $commissionTriibo           = ( float ) number_format( $commissionTriibo, 2, ".", "" );

                $amountClient               = ( $totalOrder * $commissionClient ) / 100;                      //  10  |   4
                $amountTriibo               = ( $totalOrder * $commissionTriibo ) / 100;                      //  15  |  -4

                $aux[ "amount"            ] = $totalOrder;                                                    // 500  | 500
                $aux[ "amountCommission"  ] = $commission;                                                    // 500  | 500
                $aux[ "amountClient"      ] = ( float ) number_format( $amountClient, 2, ".", "" );
                $aux[ "amountTriibo"      ] = ( float ) number_format( $amountTriibo, 2, ".", "" );
                $aux[ "amountPartner"     ] = null;

                $aux[ "commissionClient"  ] = $commissionClient;
                $aux[ "commissionTriibo"  ] = $commissionTriibo;
                $aux[ "commissionPartner" ] = null;

                $report[ "metaData" ]       = $aux;
            }
        }

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
            $id            = $store[ "id" ];
            $cashback      = "";
            $url           = Helper::parseUrl( $store[ "displayUrl" ] );
            $key           = array_search( $url, array_column( $this->storesLocal, "domain" ) );

            if ( $store[ "commissionRange" ] )
                $cashback = Helper::getCashback( $store[ "commissionRange" ] );

            if ( $key !== false )
            {
                if ( $this->storesLocal[ $key ][ "affiliate_name" ] !== "awin" )
                    continue;
            }
            else
            {
                $newStores[] = [
                    "affiliate_id"   => $id,
                    "affiliate_name" => "awin",
                    "domain"         => $url,
                    "commission"     => $cashback,
                    "created_at"     => now(),
                    "updated_at"     => now()
                ];
            }

            $auxName       = Helper::hyphenize( strtolower( $store[ "name" ] ) );
            $imageName     = "awin_{$id}_{$auxName}.png";
            $imageName     = ( $store[ "logoUrl" ] )
                                ? ( ( self::saveImage( "establishments", $imageName, $store[ "logoUrl" ] ) )
                                    ? $imageName
                                    : "awin_standard.png" )
                                : "awin_standard.png";

            $stores[ $id ] = [
                "partnerEstablishmentId" => $id,
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

                "fotoThumb"              => $imageName,
                "listaFotos"             => [ $imageName ],

                "funcionamento"          => [ ...Helper::getSchedulesOperation()        ],
                "horarios"               => [ ...Helper::getSchedulesOperation( false ) ],

                "tags"                   => [ "awin" ],

                "keyWordsSet"            => [],

                "origin"                 => "awin",
            ];
        }

        if ( !empty( $newStores ) ) DB::table( "affiliates_stores" )->insertOrIgnore( $newStores );

        return $stores;
    }

    /**
     * Translates Awin stores into Triibo promotions default
     *
     * @param array $data
     * @return array
     */
    private function translatePromotionDefault( array $data )
    {
        $promotions = [];

        $date       = new DateTime( date( "Y-m-d H:i:s.u" ) );
        $dateEn     = ( int ) $date->format( "U" ) * 1000;
        $date       = $date->add( new DateInterval( "P7Y" ) );
        $dateDis    = ( int ) $date->format( "U" ) * 1000;

        foreach ( $data as $store )
        {
            $establishmentName = $store[ "name" ];
            $establishmentName = explode( " BR", $establishmentName )[ 0 ];

            $idStore           = $store[ "id" ];
            $idPromo           = strtolower( str_replace( " ", "", Helper::slugify( $establishmentName, "" ) ) );
            $id                = $idStore . $idPromo;

            $cashback          = "";

            if ( $store[ "commissionRange" ] )
                $cashback = Helper::getCashback( $store[ "commissionRange" ] );

            $title             = "Ganhe {$cashback}% de volta!";

            $cashback          = str_replace( ",", ".", $cashback );
            $points            = $this->base * $this->rate * ( $cashback / 100 );

            $description       = "Receba parte do valor de suas compras de volta em pontos.";
            $description      .= "\nGanhe {$points} pontos a cada R\${$this->base} em compras.";

            $imageNamePromo    = "awin_common_promotion.webp";

            $url               = $this->urlTracking( $store[ "id" ], $store[ "displayUrl" ] );

            $promotions[ $id ] = [
                "partnerPromotionId" => $id,

                "establishmentId"    => $idStore,
                "establishmentName"  => $establishmentName,

                "title"              => $title,
                "detailsTitle"       => $title,

                "description"        => $description,
                "detailsDescription" => $description,

                "enableDate"         => $dateEn,
                "disableDate"        => $dateDis,
                "dueDate"            => $dateDis,

                "isPublished"        => true,
                "active"             => true,

                "lat"                => -15.52047783763576,
                "long"               => -48.015408891158984,

                "level"              => 1,

                "photo"              => $imageNamePromo,
                "thumbnail"          => $imageNamePromo,

                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => $url,

                "tags"               => [ "Cashback" ],

                "keyWordsSet"        => [],

                "origin"             => "awin",
                "buttonLabel"        => "Conferir",
                "id"                 => "",
            ];
        }

        return $promotions;
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
            $idStore           = $offer[ "advertiser" ][ "id" ];
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
            $description      .= "\n\nGanhe {$points} pontos a cada R\${$this->base} em compras.";

            $detailsDescrip    = $offer[ "description" ];

            if ( $offer[ "type" ] == "voucher" )
                $detailsDescrip .= "\n\nUtilize o código de desconto " . $offer[ "voucher" ][ "code" ] . " no fechamento da compra.";

            $detailsDescrip   .= "\n\nVálido até " . Helper::convertDate( $offer[ "endDate" ], true );

            $imageNamePromo    = "awin_common_promotion.webp";
            $imageNameVoucher  = "awin_common_coupon.webp";

            $url               = $this->urlTracking( $offer[ "advertiser" ][ "id" ], $offer[ "url" ] );

            $promotions[ $id ] = [
                "partnerPromotionId" => $id,

                "establishmentId"    => $idStore,
                "establishmentName"  => $establishmentName,

                "title"              => $offer[ "description" ],
                "detailsTitle"       => $offer[ "description" ],

                "description"        => $description,
                "detailsDescription" => $detailsDescrip,

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

                "origin"             => "awin",
                "buttonLabel"        => "Conferir",
                "id"                 => "",
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
     * @param string $logo
     * @param string $path
     * @param string $prefix
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

    /**
     *
     * @param integer $storeId
     * @param string $url
     * @return string
     */
    private function urlTracking( int $storeId, string $url )
    {
        $body     = [
            "shorten"        => false,
            "advertiserId"   => $storeId,
            "destinationUrl" => $url,
            "parameters"     => [
                "clickref" => "{uid}",
            ],
        ];

        $response = Http::withToken( $this->tkn )
                        ->withBody( json_encode( $body ), "application/json" )
                        ->post( $this->url_linkbuilder );

        if ( !$response->successful() )
        {
            $err = json_decode( $response->body(), true );

            if ( isset( $err[ "error" ] ) && $err[ "error" ] == "request.limit.exceeded" )
            {
                sleep( 10 );
                return $this->urlTracking( $storeId, $url );
            }

            return $url;
        }

        $response = $response->json();
        return Helper::treatUrlAwin( $response[ "url" ] );
    }

    /**
     *
     * @return array
     */
    public function schGetStores()
    {
        return $this->stores;
    }
}
