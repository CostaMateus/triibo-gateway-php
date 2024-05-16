<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use DateTime;
use Exception;
use Throwable;
use App\Helpers\Helper;
use Gregwar\Image\Image;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AwinIntegration
{
    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL = "awin";

    private const RG          = "BR";
    private const TZ          = "America/Sao_Paulo";

    // protected $env;
    protected $tkn;

    protected $url_promotions;

    protected $url_programmes;
    protected $url_programmedetails;

    protected $url_commissiongroups;
    protected $url_commissionsharingrules;

    protected $url_transactions;
    protected $url_linkbuilder;

    protected $establishments = [];
    // protected $storesLocal    = [];

    protected $base           = 100; // base de calculo, ex 100 reais gastos
    protected $rate           = 10;  // taxa de conversão, 10 pontos a cada 1 real

    /**
     * Constructor
     *
     * @param   boolean $loadEstablishments     Load establishments on init, default true
     * @return  void
     */
    public function __construct( bool $loadEstablishments = true )
    {
        $env       = config( "awin.env"                 );
        $url       = config( "awin.{$env}.url"          );
        $p_id      = config( "awin.{$env}.publisher_id" );
        $this->tkn = config( "awin.{$env}.token"        );

        // Establishments
        $this->url_programmes             = "{$url}/publishers/{$p_id}/programmes";             // countryCode
        $this->url_programmedetails       = "{$url}/publishers/{$p_id}/programmedetails";       // advertiserId

        // Promotions
        $this->url_promotions             = "{$url}/publisher/{$p_id}/promotions";              //

        $this->url_commissiongroups       = "{$url}/publishers/{$p_id}/commissiongroups";       // advertiserId
        $this->url_commissionsharingrules = "{$url}/publishers/{$p_id}/commissionsharingrules"; //
        $this->url_transactions           = "{$url}/publishers/{$p_id}/transactions";           // startDate - endDate - timezone

        // Url Tracking
        $this->url_linkbuilder            = "{$url}/publishers/{$p_id}/linkbuilder/generate";   //

        if ( $loadEstablishments )
            $this->establishments = $this->getEstablishments();
    }

    /**
     * Get and translate, Awin establishments (programmes)
     *
     * Awin programmes == Triibo Establishments (firebase)
     *
     * @param   boolean $withoutTranslate   Get establishments without translate, default false
     * @return  array
     */
    public function getEstablishments( bool $withoutTranslate = false )
    {
        if ( empty( $this->establishments ) )
        {
            $establishments = $this->searchEstablishments();

            if ( !$establishments[ "success" ] && empty( $establishments[ "data" ] ) ) return []; // falha

            triibo_usort( "id", $establishments[ "data" ] );

            $auxStores = [];
            $i = 0;

            foreach ( $establishments[ "data" ] as $key => &$estab )
            {
                $i++;

                if ( $i <= 5 ) continue;

                $key  += 1;
                $count = count( $establishments[ "data" ] );
                echo "Establishments: Loading establishments {$key} of {$count}\n";

                $estab[ "commissionRange" ] = $this->searchEstablishmentsDetails( $estab[ "id" ] );

                $auxStores[] = $estab;
                // if ( $key == 5 ) break;
                if ( $key == 10 ) break;
            }

            // return $establishments[ "data" ];
            return $auxStores;
        }

        if ( $withoutTranslate ) return $this->establishments;

        return $this->translateEstablishments( $this->establishments );
    }

    /**
     * Get and translate, Awin promotions
     *
     * Awin promotion == Triibo Store (firebase) / Promotion
     *
     * @return  array
     */
    public function getPromotions()
    {
        $establishmentsOrig = $this->getEstablishments( true );
        $promotions         = $this->translatePromotionDefault( $establishmentsOrig );

        $establishments     = $this->getEstablishments();

        foreach ( $establishments as $estab )
        {
            $id         = $estab[ "partnerEstablishmentId" ];
            echo "Promotions: Loading promotions for establishment: {$id}\n";
            $promos     = $this->searchPromotions( $id );

            if ( empty( $promos[ "data" ] ) ) continue;

            $promos     = $this->translatePromotions( $promos[ "data" ], $estab[ "cashbackDefault" ] );
            $promotions = $promotions + $promos;
        }

        return $promotions;
    }

    /**
     * Get Awin reports
     *
     * @param   boolean $isUpdate   Get only reports has dateType == 'validation', default false
     * @return  array
     */
    public function getReports( bool $isUpdate = false )
    {
        return $this->searchReports( $isUpdate );
    }

    /**
     * Get Awin establishments
     *
     * @return  array
     */
    private function searchEstablishments()
    {
        $url      = "{$this->url_programmes}?relationship=joined"; //&countryCode=" . self::RG;

        try
        {
            $response = Http::withToken( $this->tkn )->get( $url );
        }
        catch ( Throwable $e )
        {
            $message = "Establishments: Fail searchEstablishments(). Retry.";

            log_exception( channel: self::LOG_CHANNEL, message: $message, method: "searchEstablishments", type: "Throwable", e: $e );

            return $this->searchEstablishments();
        }

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
     * Get Awin establishments details
     *
     * @param   integer     $id        Establishment id
     * @param   boolean     $allInfo   Get complete establishment details, default false
     * @return  array
     */
    public function searchEstablishmentsDetails( int $id, bool $allInfo = false )
    {
        $url      = "{$this->url_programmedetails}?advertiserId={$id}";

        try
        {
            $response = Http::withToken( $this->tkn )->get( $url );
        }
        catch ( Throwable $e )
        {
            $message = "Establishments: Fail searchEstablishmentsDetails(). Retry.";

            log_exception( channel: self::LOG_CHANNEL, message: $message, method: "searchEstablishmentsDetails", type: "Throwable", e: $e );

            return $this->searchEstablishmentsDetails( $id, $allInfo );
        }

        if ( !$response->successful() )
        {
            $err = json_decode( $response->body(), true );

            if ( isset( $err[ "error" ] ) && $err[ "error" ] == "request.limit.exceeded" )
            {
                echo "Establishments: Retry in 10 seconds\n";
                sleep( 10 );
                return $this->searchEstablishmentsDetails( $id, $allInfo );
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
     * Get Awin promotions (promotions)
     *
     * Awin promotion == Triibo Store (firebase) / Default promotion
     *
     * @param   integer     $id        Establishment id
     * @return  array
     */
    private function searchPromotions( int $id )
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

        try
        {
            do
            {
                $body[ "pagination" ] = [ "page" => $page ];

                $response = Http::withToken( $this->tkn )
                                ->withBody( json_encode( $body ), "application/json" )
                                ->post( $this->url_promotions );

                if ( !$response->successful() )
                {
                    $err = json_decode( $response->body(), true );

                    if ( isset( $err[ "error" ] ) && $err[ "error" ] == "request.limit.exceeded" )
                    {
                        echo "Promotions: Retry in 10 seconds\n";
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
        catch ( Throwable $e )
        {
            $message = "Promotions: Fail searchPromotions(). Retry.";

            log_exception( channel: self::LOG_CHANNEL, message: $message, method: "searchPromotions", type: "Throwable", e: $e );

            return $this->searchPromotions( $id );
        }
    }

    /**
     * Get Awin reports
     *
     * @param   boolean     $isUpdate   Get only reports has dateType == 'validation', default false
     * @return  array
     */
    private function searchReports( bool $isUpdate = false )
    {
        $date   = date( "Y-m-d", strtotime( "yesterday" ) );
        $start  = "{$date}T00:00:00";
        $end    = "{$date}T23:59:59";

        $query  = "?startDate={$start}";
        $query .= "&endDate={$end}";
        $query .= "&timezone=" . self::TZ;
        $query .= ( $isUpdate ) ? "&dateType=validation" : "&dateType=transaction&status=pending";

        try
        {
            $response = Http::withToken( $this->tkn )->get( "{$this->url_transactions}/{$query}" );
        }
        catch ( Exception $e )
        {
            $message = "Reports: Fail searchReports(). Retry.";

            log_exception( channel: self::LOG_CHANNEL, message: $message, method: "searchReports", type: "Throwable", e: $e );

            return $this->searchReports( $isUpdate );
        }

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
                $aux                        = [];

                $establishmentId            = $report[ "advertiserId" ];
                $establishmentInfo          = $this->searchEstablishmentsDetails( $establishmentId, true );
                $cashback                   = get_cashback( $establishmentInfo[ "commissionRange" ] );
                $establishmentName          = explode( " BR", $establishmentInfo[ "programmeInfo" ][ "name" ] )[ 0 ];

                $aux[ "uid"               ] = ( isset( $report[ "clickRefs" ] ) && isset( $report[ "clickRefs" ][ "clickRef" ] ) )
                                                ? $report[ "clickRefs" ][ "clickRef" ]
                                                : null;

                $aux[ "partner"           ] = "awin";
                $aux[ "transactionId"     ] = $report[ "id" ];
                $aux[ "transactionDate"   ] = Helper::getDateTimeUnix( $report[ "transactionDate" ], true );
                $aux[ "processingDate"    ] = null;
                $aux[ "status"            ] = Helper::awinReportStatus( $report[ "commissionStatus" ] );

                $aux[ "updateDate"        ] = ( !$isUpdate && $aux[ "status" ] == "pending" )
                                                ? $report[ "transactionDate" ]
                                                : $report[ "validationDate"  ];

                $aux[ "updateDate"        ] = Helper::getDateTimeUnix( $aux[ "updateDate" ], true );

                $aux[ "advertiserId"      ] = $establishmentId;
                $aux[ "advertiserName"    ] = $establishmentName;


                $totalOrder                 = ( float ) $report[ "saleAmount"       ][ "amount" ];
                $commission                 = ( float ) $report[ "commissionAmount" ][ "amount" ];

                $totalCommission            = ( $commission > 0 ) ? ( 100 * $commission ) / $totalOrder : 0;
                $totalCommission            = ( float ) number_format( $totalCommission, 2, ".", "" );

                $commissionClient           = ( float ) str_replace( ",", ".", $cashback );

                $commissionTriibo           = $totalCommission - $commissionClient;
                $commissionTriibo           = ( float ) number_format( $commissionTriibo, 2, ".", "" );

                $amountClient               = ( $totalOrder * $commissionClient ) / 100;
                $amountTriibo               = ( $totalOrder * $commissionTriibo ) / 100;

                $aux[ "amount"            ] = $totalOrder;
                $aux[ "amountCommission"  ] = $commission;
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
     * Translates Awin establishments into Triibo Establishments
     *
     * @param   array   $data     Establishments data
     * @return  array
     */
    private function translateEstablishments( array $data )
    {
        // $newStores = [];
        $establishments = [];
        $dateEn         = Helper::getDateTimeUnix();
        $dateDis        = Helper::getDateTimeUnixInterval( "P7Y" );

        foreach ( $data as $estab )
        {
            $id            = $estab[ "id" ];
            $cashback      = "";

            if ( $estab[ "commissionRange" ] )
                $cashback = get_cashback( $estab[ "commissionRange" ] );

            $auxName       = hyphenize( strtolower( $estab[ "name" ] ) );
            $imageName     = "awin_{$id}_{$auxName}.png";
            $imageName     = ( $estab[ "logoUrl" ] )
                                ? ( ( self::saveImage( "establishments", $imageName, $estab[ "logoUrl" ] ) )
                                    ? $imageName
                                    : "awin_standard.png" )
                                : "awin_standard.png";

            $establishments[ $id ] = [
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

                "nome"                   => explode( " BR", $estab[ "name" ] )[ 0 ],
                "descricao"              => $estab[ "description" ],
                "email"                  => "contato@triibo.com.br",
                "contatoTriibo"          => [ [
                    "type"  => "telefone",
                    "value" => "+5511944965799"
                ] ],

                "fotoThumb"              => $imageName,
                "listaFotos"             => [ $imageName ],

                "funcionamento"          => [ ...get_schedules_operation()        ],
                "horarios"               => [ ...get_schedules_operation( false ) ],

                "tags"                   => [ "awin" ],

                "keyWordsSet"            => [],

                "origin"                 => "awin",
            ];
        }

        return $establishments;
    }

    /**
     * Translates Awin establishments into Triibo promotions default
     *
     * @param   array   $data     Establishments data
     * @return  array
     */
    private function translatePromotionDefault( array $data )
    {
        $promotions = [];
        $dateEn     = Helper::getDateTimeUnix();
        $dateDis    = Helper::getDateTimeUnixInterval( "P7Y" );

        foreach ( $data as $estab )
        {
            $establishmentName = $estab[ "name" ];
            $establishmentName = explode( " BR", $establishmentName )[ 0 ];

            $idEstab           = $estab[ "id" ];
            $idPromo           = strtolower( str_replace( " ", "", Str::slug( $establishmentName, "" ) ) );
            $id                = $idEstab . $idPromo;

            $cashback          = "";

            if ( $estab[ "commissionRange" ] )
                $cashback = get_cashback( $estab[ "commissionRange" ] );

            $title             = "Ganhe {$cashback}% de volta!";

            $cashback          = str_replace( ",", ".", $cashback );
            $points            = $this->base * $this->rate * ( $cashback / 100 );

            $description       = "Receba parte do valor de suas compras de volta em pontos.";
            $description      .= "\nGanhe {$points} pontos a cada R\${$this->base} em compras.";

            $imageNamePromo    = "awin_common_promotion.webp";

            $url               = $this->urlTracking( $estab[ "id" ], $estab[ "displayUrl" ] );

            $promotions[ $id ] = [
                "partnerEstablishmentId" => $idEstab,
                "partnerPromotionId" => $id,

                "establishmentId"    => "",
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
     * Translates Awin promotions into Triibo promotions
     *
     * @param   array   $data       Promotions data
     * @param   string  $cashback   Cashback value
     * @return  array
     */
    private function translatePromotions( array $data, string $cashback )
    {
        $promotions = [];

        foreach ( $data as $promotion )
        {
            $idEstab           = $promotion[ "advertiser" ][ "id" ];
            $id                = $idEstab . $promotion[ "promotionId" ];

            $establishmentName = $promotion[ "advertiser" ][ "name" ];
            $establishmentName = explode( " BR", $establishmentName )[ 0 ];

            $date              = str_replace( "T", " ", $promotion[ "startDate" ] );
            $date              = new DateTime( date( $date ) );
            $dateEn            = ( int ) $date->format( "U" ) * 1000;

            $date              = str_replace( "T", " ", $promotion[ "endDate" ] );
            $date              = new DateTime( date( $date ) );
            $dateDis           = ( int ) $date->format( "U" ) * 1000;

            $cashback          = str_replace( ",", ".", $cashback );
            $points            = $this->base * $this->rate * ( $cashback / 100 );

            $description       = $promotion[ "description" ];
            $description      .= "\n\nGanhe {$points} pontos a cada R\${$this->base} em compras.";

            $detailsDescrip    = $promotion[ "description" ];

            if ( $promotion[ "type" ] == "voucher" )
                $detailsDescrip .= "\n\nUtilize o código de desconto " . $promotion[ "voucher" ][ "code" ] . " no fechamento da compra.";

            $detailsDescrip   .= "\n\nVálido até " . convert_date( $promotion[ "endDate" ], true );

            $imageNamePromo    = "awin_common_promotion.webp";
            $imageNameVoucher  = "awin_common_coupon.webp";

            $url               = $this->urlTracking( $promotion[ "advertiser" ][ "id" ], $promotion[ "url" ] );

            $promotions[ $id ] = [
                "partnerEstablishmentId" => $idEstab,
                "partnerPromotionId" => $id,

                "establishmentId"    => "",
                "establishmentName"  => $establishmentName,

                "title"              => $promotion[ "description" ],
                "detailsTitle"       => $promotion[ "description" ],

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

            if ( $promotion[ "type" ] == "voucher" )
            {
                $promotions[ $id ][ "checkoutCode" ] = $promotion[ "voucher" ][ "code" ];
                $promotions[ $id ][ "tags"         ] = [ "Cashback", "Cupom" ];
                $promotions[ $id ][ "photo"        ] = $imageNameVoucher;
                $promotions[ $id ][ "thumbnail"    ] = $imageNameVoucher;
            }
        }

        return $promotions;
    }

    /**
     * Saves images from Awin
     *
     * @param   string  $path       Folder to save image
     * @param   string  $imageName  Name of image
     * @param   string  $url        Url of image to download and save
     * @return  boolean
     */
    private static function saveImage( string $path, string $imageName, string $url )
    {
        $fullPath = public_path( "images/affiliates/awin/{$path}" ) . "/{$imageName}";

        try
        {
            $response = Http::get( $url );
        }
        catch ( Throwable $e )
        {
            $message = "Establishments: Fail saveImage(). Retry.";

            log_exception( channel: self::LOG_CHANNEL, message: $message, method: "saveImage", type: "Throwable", e: $e );

            return self::saveImage( $path, $imageName, $url );
        }

        if ( !$response->successful() ) return false;

        file_put_contents( $fullPath, $response->body() );

        $handle = Image::open( $fullPath );
        $handle->resize( 200, 150, "#FFF" );
        $handle->save( $fullPath );

        return true;
    }

    /**
     * Generates url to track clicks
     *
     * @param   integer     $establishmentId    Establishment id
     * @param   string      $url                Destination url
     * @return  string
     */
    private function urlTracking( int $establishmentId, string $url )
    {
        $body     = [
            "shorten"        => false,
            "advertiserId"   => $establishmentId,
            "destinationUrl" => $url,
            "parameters"     => [
                "clickref" => "{uid}",
            ],
        ];

        try
        {
            $response = Http::withToken( $this->tkn )
                            ->withBody( json_encode( $body ), "application/json" )
                            ->post( $this->url_linkbuilder );
        }
        catch ( Throwable $e )
        {
            $message = "Promotions: Fail urlTracking(). Retry.";

            log_exception( channel: self::LOG_CHANNEL, message: $message, method: "urlTracking", type: "Throwable", e: $e );

            return $this->urlTracking( $establishmentId, $url );
        }

        if ( !$response->successful() )
        {
            $err = json_decode( $response->body(), true );

            if ( isset( $err[ "error" ] ) && $err[ "error" ] == "request.limit.exceeded" )
            {
                sleep( 10 );
                return $this->urlTracking( $establishmentId, $url );
            }

            return $url;
        }

        $response = $response->json();

        return parse_url_awin( $response[ "url" ] );
    }

    /**
     * Get Awin establishments
     *
     * @return  array
     */
    public function schGetEstablishments()
    {
        return $this->establishments;
    }
}
