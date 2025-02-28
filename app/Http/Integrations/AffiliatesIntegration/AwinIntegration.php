<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use App\Helpers\Helper;
use Gregwar\Image\Image;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Console\Commands\Affiliates\Awin\ProcessDataAwin;

class AwinIntegration
{
    /**
     * Log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL       = "awin";

    /**
     * Region.
     *
     * @var     string
     */
    private const RG                = "BR";

    /**
     * Timezone.
     *
     * @var     string
     */
    private const TZ                = "America/Sao_Paulo";

    /**
     * API token.
     *
     * @var     string
     */
    protected $token                = "";

    /**
     * API promotions url.
     *
     * @var     string
     */
    protected $url_promotions       = "";

    /**
     * API establishments url.
     *
     * @var     string
     */
    protected $url_programmes       = "";

    /**
     * API establishment details url.
     *
     * @var     string
     */
    protected $url_programmedetails = "";

    /**
     * API transactions url.
     *
     * @var     string
     */
    protected $url_transactions     = "";

    /**
     * API linkbuilder url.
     *
     * @var     string
     */
    protected $url_linkbuilder      = "";

    /**
     * Establishments.
     *
     * @var     array
     */
    protected $establishments       = [];

    /**
     * Ccashback calculation basis.
     *
     * @var     int
     */
    protected $base                 = 100; // base de calculo, ex 100 reais gastos

    /**
     * Cashback conversion rate.
     *
     * @var     int
     */
    protected $rate                 = 10;  // taxa de conversão, 10 pontos a cada 1 real

    /**
     * Logger.
     *
     * @var     ProcessDataAwin
     */
    protected $logger;

    /**
     * @param   ProcessDataAwin     $logger     Logger instance for logging data processing.
     * @param   boolean             $load       Load establishments from Awin, default true.
     * @return  void
     */
    public function __construct( ProcessDataAwin $logger, bool $load = true )
    {
        $env                        = config( key: "awin.env"                 );
        $url                        = config( key: "awin.{$env}.url"          );
        $p_id                       = config( key: "awin.{$env}.publisher_id" );
        $this->token                = config( key: "awin.{$env}.token"        );

        // Establishments
        $this->url_programmes       = "{$url}/publishers/{$p_id}/programmes";             // countryCode
        $this->url_programmedetails = "{$url}/publishers/{$p_id}/programmedetails";       // advertiserId

        // Promotions
        $this->url_promotions       = "{$url}/publisher/{$p_id}/promotions";              //

        $this->url_transactions     = "{$url}/publishers/{$p_id}/transactions";           // startDate - endDate - timezone

        // Url Tracking
        $this->url_linkbuilder      = "{$url}/publishers/{$p_id}/linkbuilder/generate";   //

        $this->logger               = $logger;

        if ( $load )
            $this->establishments = $this->getEstablishments();
    }

    /**
     * Get and translate, Awin establishments (programmes).
     *
     * Awin programmes == Triibo Establishments (firebase).
     *
     * @param   boolean     $without_translate  Get establishments without translate, default false.
     * @return  array
     */
    public function getEstablishments( bool $without_translate = false ) : array
    {
        if ( config( "app.env" ) === "local" )
        {
            if ( empty( $this->establishments ) )
            {
                // establishments in the awin format
                $establishments = Storage::get( "awin/establishments_awin.json" );
                $establishments = json_decode( $establishments,  true );

                return $establishments;
            }

            if ( $without_translate )
                return $this->establishments;

            return $this->translateEstablishments( data: $this->establishments );

            // // establishments in the triibo format
            // $establishments = Storage::get( "awin/establishments_triibo.json" );
            // $establishments = json_decode( $establishments, true );

            // return $establishments;
        }

        // ==================================
        // production

        if ( empty( $this->establishments ) )
        {
            $response = $this->searchEstablishments();

            if ( !$response[ "success" ] && empty( $response[ "data" ] ) )
                return [];

            triibo_usort( key: "id", data: $response[ "data" ] );

            $count    = count( $response[ "data" ] );

            return collect( $response[ "data" ] )
                    // ->slice( 0, 10 )
                    ->map( function ( $establishment, $index ) use ( $count )
                    {
                        $this->logger->log_message( message: "Establishments: Loading establishment " . ++$index . " of {$count} | ID " . $establishment[ "id" ] );

                        $establishment[ "commissionRange" ] = $this->searchEstablishmentsDetails( id: $establishment[ "id" ] );

                        return $establishment;
                    } )
                    ->toArray();
        }

        return $without_translate
            ? $this->establishments
            : $this->translateEstablishments( data: $this->establishments );
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
        $origins        = $this->getEstablishments( without_translate: true );
        $promotions     = $this->translatePromotionDefault( data: $origins );

        $establishments = $this->getEstablishments();

        foreach ( $establishments as $establishment )
        {
            $id         = $establishment[ "partnerEstablishmentId" ];

            $this->logger->log_message( message: "Promotions: Loading for establishment ID {$id}" );

            $promos     = $this->searchPromotions( id: $id );

            if ( empty( $promos[ "data" ] ) ) continue;

            $promos     = $this->translatePromotions( data: $promos[ "data" ], cashback: $establishment[ "cashbackDefault" ] );
            $promotions = $promotions + $promos;
        }

        return $promotions;
    }

    /**
     * Get Awin reports
     *
     * @param   boolean $is_update   Get only reports has dateType == 'validation', default false
     * @return  array
     */
    public function getReports( bool $is_update = false )
    {
        return $this->searchReports( is_update: $is_update );
    }

    /**
     * Get Awin establishments.
     *
     * @return  array
     */
    private function searchEstablishments()
    {
        $url = "{$this->url_programmes}?relationship=joined"; //&countryCode=" . self::RG;

        try
        {
            $response = retry(
                times: 7,
                sleepMilliseconds: 10000,
                callback: function () use ( $url )
                {
                    return Http::withToken( token: $this->token )->get( url: $url );
                },
                when: function ( $e ) use ( $url )
                {
                    $this->logger->log_exception(
                        message : "Establishments: Fail searchEstablishments(). Retry in 10 seconds...",
                        method  : "searchEstablishments",
                        type    : "Throwable",
                        e       : $e,
                        context : [ "url" => $url ]
                    );

                    return true;
                }
            );
        }
        catch ( \Throwable $e )
        {
            $this->logger->log_exception(
                message : "Establishments: Fail searchEstablishments(). Catch.",
                method  : "searchEstablishments",
                type    : "Throwable",
                e       : $e,
                context : [ "url" => $url ]
            );

            return [
                "success" => false,
                "code"    => 4002,
                "data"    => []
            ];
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
     * @param   integer     $id         Establishment id
     * @param   boolean     $all_info   Get complete establishment details, default false
     * @return  array
     */
    public function searchEstablishmentsDetails( int $id, bool $all_info = false ) : array
    {
        $url  = "{$this->url_programmedetails}?advertiserId={$id}";

        $data = [
            "commissionRange" => [ [ "min" => 0, "max" => 0 ] ],
            "programmeInfo"   => [ "name" => "Não encontrado" ]
        ];

        try
        {
            $response = retry(
                times: 7,
                sleepMilliseconds: 10000,
                callback: function () use ( $url, $id )
                {
                    $response_2 = Http::withToken( $this->token )->get( $url );

                    if ( !$response_2->successful() )
                    {
                        $err = json_decode( $response_2->body(), true );

                        if ( isset( $err[ "error" ] ) && $err[ "error" ] === "request.limit.exceeded" )
                        {
                            $this->logger->log_message( message: "Establishments: Request limit exceeded. Retry ID {$id} in 10 seconds..." );

                            throw new \Exception( "request.limit.exceeded" );
                        }
                    }

                    return $response_2;
                },
                when: function ( \Throwable $e ) use ( $url, $id )
                {
                    if ( $e->getMessage() !== "request.limit.exceeded" )
                    {
                        $this->logger->log_exception(
                            message: "Establishments: Fail searchEstablishmentsDetails ID = {$id}. Retry.",
                            method: "searchEstablishmentsDetails",
                            type: "Throwable",
                            e: $e,
                            context: [ "url" => $url ]
                        );
                    }

                    return true;
                }
            );
        }
        catch ( \Throwable $e )
        {
            $this->logger->log_exception(
                message: "Establishments: Fail searchEstablishmentsDetails ID = {$id}. Catch.",
                method: "searchEstablishmentsDetails",
                type: "Throwable",
                e: $e,
                context: [ "url" => $url ]
            );

            return $all_info ? $data : $data[ "commissionRange" ][ 0 ];
        }

        if ( !$response->successful() )
            return $all_info ? $data : $data[ "commissionRange" ][ 0 ];

        $data = $response->json();
        $key  = array_search_by_key( needle: "percentage", column: "type", array: $data[ "commissionRange" ] );

        return $all_info ? $data : $data[ "commissionRange" ][ $key ];
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
        $this->logger->log_message( message: "Promotions: Start search promotions for ID {$id}" );

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

        while ( !$ended )
        {
            $this->logger->log_message( message: "Promotions: Loading page {$page} for ID {$id}" );

            $body[ "pagination" ] = [ "page" => $page ];

            try
            {
                $response = retry(
                    times: 7,
                    sleepMilliseconds: 10000,
                    callback: function () use ( $body )
                    {
                        $response_2 = Http::withToken( token: $this->token )
                                          ->withBody( content: json_encode( $body ), contentType: "application/json" )
                                          ->post( url: $this->url_promotions );

                        if ( !$response_2->successful() )
                        {
                            $err = json_decode( $response_2->body(), true );

                            if ( isset( $err[ "error" ] ) && $err[ "error" ] === "request.limit.exceeded" )
                            {
                                $this->logger->log_message( message: "Promotions: Request limit exceeded. Retry ID {$id} in 10 seconds..." );

                                throw new \Exception( "request.limit.exceeded" );
                            }
                        }

                        return $response_2;
                    },
                    when: function ( \Throwable $e ) use ( $body, $id )
                    {
                        if ( $e->getMessage() !== "request.limit.exceeded" )
                        {
                            $this->logger->log_exception(
                                message: "Promotions: Fail searchPromotions ID = {$id}. Retry.",
                                method: "searchPromotions",
                                type: "Throwable",
                                e: $e,
                                context: [
                                    "page" => $page,
                                    "body" => $body
                                ]
                            );
                        }

                        return true;
                    }
                );
            }
            catch ( \Throwable $e )
            {
                $this->logger->log_exception(
                    message: "Promotions: Fail searchPromotions ID = {$id}. Catch.",
                    method: "searchPromotions",
                    type: "Throwable",
                    e: $e,
                    context: [
                        "page" => $page,
                        "body" => $body
                    ]
                );

                return [
                    "success" => false,
                    "code"    => 4002,
                    "data"    => $data,
                ];
            }

            if ( !$response->successful() )
            {
                return [
                    "success" => false,
                    "code"    => 4001,
                    "data"    => $data,
                ];
            }

            $page++;

            $response_data = $response->json();

            if ( !empty( $response_data[ "data" ] ) )
                $data = array_merge( $data, $response_data[ "data" ] );

            $pagination    = $response_data[ "pagination" ] ?? [];

            if ( empty( $response_data[ "data" ] ) || ( isset( $pagination[ "total" ] ) && $pagination[ "total" ] <= $page ) )
                $ended = true;
        }

        $this->logger->log_message( message: "Promotions: End search promotions for ID {$id}" );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    /**
     * Get Awin reports
     *
     * @param   boolean     $is_update   Get only reports has dateType == 'validation', default false
     * @return  array
     */
    private function searchReports( bool $is_update = false )
    {
        $date   = date( "Y-m-d", strtotime( "yesterday" ) );
        $start  = "{$date}T00:00:00";
        $end    = "{$date}T23:59:59";

        $query  = "?startDate={$start}";
        $query .= "&endDate={$end}";
        $query .= "&timezone=" . self::TZ;
        $query .= ( $is_update ) ? "&dateType=validation" : "&dateType=transaction&status=pending";

        $this->logger->log_message( message: "Reports: Start search reports..." );

        try
        {
            $response = retry(
                times: 7,
                sleepMilliseconds: 10000,
                callback: function () use ( $query )
                {
                    $response_2 = Http::withToken( token: $this->token )->get( url: "{$this->url_transactions}/{$query}");

                    if ( !$response_2->successful() )
                    {
                        $err = json_decode( $response_2->body(), true );

                        if ( isset( $err[ "error" ] ) && $err[ "error" ] === "request.limit.exceeded" )
                        {
                            $this->logger->log_message( message: "Reports: Request limit exceeded. Retry in 10 seconds..." );

                            throw new \Exception( "request.limit.exceeded" );
                        }
                    }

                    return $response_2;
                },
                when: function ( \Throwable $e ) use ( $query )
                {
                    if ( $e->getMessage() !== "request.limit.exceeded" )
                    {
                        $this->logger->log_exception(
                            message: "Reports: Fail searchReports. Retry.",
                            method: "searchReports",
                            type: "Throwable",
                            e: $e,
                            context: [ "query" => $query ]
                        );
                    }

                    return true;
                }
            );
        }
        catch ( \Throwable $e )
        {
            $this->logger->log_exception(
                message: "Reports: Fail searchReports. Catch.",
                method: "searchReports",
                type: "Throwable",
                e: $e,
                context: [ "query" => $query ]
            );

            return [
                "success" => false,
                "code"    => 4001,
                "data"    => null,
            ];
        }

        if ( !$response->successful() )
        {
            return [
                "success" => false,
                "code"    => 4001,
                "data"    => null,
            ];
        }

        $this->logger->log_message( message: "Reports: End search reports..." );

        $data = [
            "success" => true,
            "code"    => 0,
            "data"    => $response->json(),
        ];

        if ( !empty( $data[ "data" ] ) )
        {
            $this->logger->log_message( message: "Reports: Start translating awin reports..." );

            foreach ( $data[ "data" ] as &$report )
            {
                $id                   = $report[ "advertiserId" ];
                $name                 = explode( " BR", $info[ "programmeInfo" ][ "name" ] )[ 0 ];
                $info                 = $this->searchEstablishmentsDetails( id: $id, all_info: true );
                $cashback             = get_cashback( data: $info[ "commissionRange" ][ 0 ] );

                $has_click_ref        = isset( $report[ "clickRefs" ] ) && isset( $report[ "clickRefs" ][ "clickRef" ] );

                $uid                  = ( $has_click_ref ) ? $report[ "clickRefs" ][ "clickRef" ] : null;

                $status               = Helper::awinReportStatus( status: $report[ "commissionStatus" ] );

                $transaction_date     = get_date_time_unix( format: $report[ "transactionDate" ], immutable: true );

                $update_date          = ( !$is_update && $status == "pending" ) ? $report[ "transactionDate" ] : $report[ "validationDate"  ];
                $update_date          = get_date_time_unix( format: $update_date, immutable: true );

                $total_order          = ( float ) $report[ "saleAmount"       ][ "amount" ];
                $commission           = ( float ) $report[ "commissionAmount" ][ "amount" ];

                $total_commission     = ( $commission > 0 ) ? ( ( 100 * $commission ) / $total_order ) : 0;
                $total_commission     = ( float ) number_format( $total_commission, 2, ".", "" );

                $commission_client    = ( float ) str_replace( ",", ".", $cashback );

                $commission_triibo    = $total_commission - $commission_client;
                $commission_triibo    = ( float ) number_format( $commission_triibo, 2, ".", "" );

                $amount_client        = ( $total_order * $commission_client ) / 100;
                $amount_triibo        = ( $total_order * $commission_triibo ) / 100;

                $report[ "metaData" ] = [
                    "uid"               => $uid,
                    "partner"           => "awin",
                    "transactionId"     => $report[ "id" ],
                    "transactionDate"   => $transaction_date,
                    "processingDate"    => null,
                    "status"            => $status,

                    "updateDate"        => $update_date,
                    "advertiserId"      => $id,
                    "advertiserName"    => $name,

                    "amount"            => $total_order,
                    "amountCommission"  => $commission,
                    "amountClient"      => ( float ) number_format( $amount_client, 2, ".", "" ),
                    "amountTriibo"      => ( float ) number_format( $amount_triibo, 2, ".", "" ),
                    "amountPartner"     => null,

                    "commissionClient"  => $commission_client,
                    "commissionTriibo"  => $commission_triibo,
                    "commissionPartner" => null,
                ];

                $this->logger->log_message(
                    message : "Reports: Translated report",
                    context : $report[ "metaData" ],
                );
            }

            $this->logger->log_message( message: "Reports: End translating awin reports..." );
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
        $establishments = [];
        $date_en        = get_date_time_unix();
        $date_dis       = get_date_time_unix_interval( interval: "P7Y" );

        $this->logger->log_message( message: "Establishments: Start translating awin establishments..." );

        foreach ( $data as $establishment )
        {
            $id                    = $establishment[ "id" ];
            $cashback              = "";

            if ( isset( $establishment[ "commissionRange" ] ) )
                $cashback = get_cashback( data: $establishment[ "commissionRange" ] );

            $name                  = $establishment[ "name" ];
            $name                  = explode( "BR",       $name )[ 0 ];
            $name                  = explode( "(Global)", $name )[ 0 ];
            $name                  = explode( "Brazil",   $name )[ 0 ];

            $aux_name              = Str::slug( title: strtolower( $establishment[ "name" ] ) );

            $image_name            = "awin_{$id}_{$aux_name}.png";
            $image_name            = ( $establishment[ "logoUrl" ] )
                                     ? ( ( self::saveImage( path: "establishments", image_name: $image_name, url: $establishment[ "logoUrl" ] ) )
                                         ? $image_name : "awin_standard.png" )
                                     : "awin_standard.png";

            $establishments[ $id ] = [
                "partnerEstablishmentId" => $establishment[ "id" ],
                "cashbackDefault"        => $cashback,
                "enableDate"             => $date_en,
                "disableDate"            => $date_dis,
                "dataAlteracao"          => $date_en,
                "dataInclusao"           => $date_en,
                "idGestor"               => "+5511949289507@sms,triibo,com,br",
                "isPublished"            => true,
                "endereco"               => "Brasil",
                "lat"                    => -15.52047783763576,
                "long"                   => -48.015408891158984,
                "nome"                   => trim( $name ),
                "descricao"              => $establishment[ "description" ],
                "email"                  => "contato@triibo.com.br",
                "contatos"               => [ "+55 (11) 94496-5799" ],
                "contatoTriibo"          => [ [
                    "type"  => "telefone",
                    "value" => "+5511944965799"
                ] ],
                "fotoThumb"              => $image_name,
                "listaFotos"             => [ $image_name ],
                "funcionamento"          => [ ...get_schedules_operation() ],
                "horarios"               => [ ...get_schedules_operation( operation: false ) ],
                "tags"                   => [ "awin" ],
                "keyWordsSet"            => [],
                "origin"                 => "awin",
                "id"                     => "",
            ];

            $this->logger->log_message(
                message : "Establishments: Translated establishment ID {$id}",
                context : $establishments[ $id ],
            );
        }

        $this->logger->log_message( message: "Establishments: End translating awin establishments..." );

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
        $date_en    = get_date_time_unix();
        $date_dis   = get_date_time_unix_interval( interval: "P7Y" );

        $this->logger->log_message( message: "Promotions: Start translating awin promotions default..." );

        foreach ( $data as $establishment )
        {
            $name              = explode( " BR", $establishment[ "name" ] )[ 0 ];

            $id                = $establishment[ "id" ] . strtolower( str_replace( " ", "", Str::slug( title: $name, separator: "" ) ) );

            $cashback          = "";

            if ( isset( $establishment[ "commissionRange" ] ) )
                $cashback = get_cashback( data: $establishment[ "commissionRange" ] );

            $title             = "Ganhe {$cashback}% de volta!";

            $cashback          = str_replace( ",", ".", $cashback );
            $points            = $this->base * $this->rate * ( $cashback / 100 );

            $description       = [
                "Receba parte do valor de suas compras de volta em pontos.",
                "Ganhe {$points} pontos a cada R\${$this->base} em compras.",
            ];

            $url               = $this->urlTracking( id: $establishment[ "id" ], url: $establishment[ "displayUrl" ] );

            $promotions[ $id ] = [
                "partnerEstablishmentId" => $establishment[ "id" ],
                "partnerPromotionId"     => $id,
                "establishmentId"        => "",
                "establishmentName"      => $name,
                "title"                  => $title,
                "detailsTitle"           => $title,
                "description"            => implode( "\n", $description ),
                "detailsDescription"     => implode( "\n", $description ),
                "enableDate"             => $date_en,
                "disableDate"            => $date_dis,
                "dueDate"                => $date_dis,
                "isPublished"            => true,
                "active"                 => true,
                "lat"                    => -15.52047783763576,
                "long"                   => -48.015408891158984,
                "level"                  => 1,
                "photo"                  => "awin_common_promotion.webp",
                "thumbnail"              => "awin_common_promotion.webp",
                "region"                 => "nacional",
                "type"                   => "businessPartner",
                "url"                    => $url,
                "tags"                   => [ "Cashback" ],
                "keyWordsSet"            => [],
                "origin"                 => "awin",
                "buttonLabel"            => "Conferir",
                "id"                     => "",
            ];

            $this->logger->log_message(
                message : "Promotions: Translated promotion default ID {$id}",
                context : $promotions[ $id ],
            );
        }

        $this->logger->log_message( message: "Promotions: Start translating awin promotions default..." );

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

        $this->logger->log_message( message: "Promotions: Start translating awin promotions..." );

        foreach ( $data as $promotion )
        {
            $id                = $promotion[ "advertiser" ][ "id" ] . $promotion[ "promotionId" ];

            $name              = explode( " BR", $promotion[ "advertiser" ][ "name" ] )[ 0 ];

            $date              = str_replace( "T", " ", $promotion[ "startDate" ] );
            $date_en           = get_date_time_unix( format: $date );

            $date              = str_replace( "T", " ", $promotion[ "endDate"   ] );
            $date_dis          = get_date_time_unix( format: $date );

            $cashback          = str_replace( ",", ".", $cashback );
            $points            = $this->base * $this->rate * ( $cashback / 100 );

            $description       = [
                $promotion[ "description" ],
                "Ganhe {$points} pontos a cada R\${$this->base} em compras."
            ];

            $details_descrip   = [
                $promotion[ "description" ]
            ];

            if ( $promotion[ "type" ] == "voucher" )
                $details_descrip[] = "Utilize o código de desconto " . $promotion[ "voucher" ][ "code" ] . " no fechamento da compra.";

            $details_descrip[] = "Válido até " . convert_date( date: $promotion[ "endDate" ] );

            $url               = $this->urlTracking( id: $promotion[ "advertiser" ][ "id" ], url: $promotion[ "url" ] );

            $promotions[ $id ] = [
                "partnerEstablishmentId" => $promotion[ "advertiser" ][ "id" ],
                "partnerPromotionId"     => $id,
                "establishmentId"        => "",
                "establishmentName"      => $name,
                "title"                  => $promotion[ "description" ],
                "detailsTitle"           => $promotion[ "description" ],
                "description"            => implode( "\n\n", $description     ),
                "detailsDescription"     => implode( "\n\n", $details_descrip ),
                "enableDate"             => $date_en,
                "disableDate"            => $date_dis,
                "dueDate"                => $date_dis,
                "isPublished"            => true,
                "active"                 => true,
                "lat"                    => -15.52047783763576,
                "long"                   => -48.015408891158984,
                "level"                  => 99,
                "photo"                  => "awin_common_promotion.webp",
                "thumbnail"              => "awin_common_promotion.webp",
                "region"                 => "nacional",
                "type"                   => "businessPartner",
                "url"                    => $url,
                "tags"                   => [ "Cashback", "Promoção" ],
                "keyWordsSet"            => [],
                "origin"                 => "awin",
                "buttonLabel"            => "Conferir",
                "id"                     => "",
            ];

            if ( $promotion[ "type" ] == "voucher" )
            {
                $promotions[ $id ][ "checkoutCode" ] = $promotion[ "voucher" ][ "code" ];
                $promotions[ $id ][ "tags"         ] = [ "Cashback", "Cupom" ];
                $promotions[ $id ][ "photo"        ] = "awin_common_coupon.webp";
                $promotions[ $id ][ "thumbnail"    ] = "awin_common_coupon.webp";
            }

            $this->logger->log_message(
                message : "Promotions: Translated promotion ID {$id}",
                context : $promotions[ $id ],
            );
        }

        $this->logger->log_message( message: "Promotions: End translating awin promotions..." );

        return $promotions;
    }

    /**
     * Saves images from Awin
     *
     * @param   string  $path           Folder to save image
     * @param   string  $image_name     Name of image
     * @param   string  $url            Url of image to download and save
     * @return  boolean
     */
    private static function saveImage( string $path, string $image_name, string $url )
    {
        $full_path = public_path( path: "images/affiliates/awin/{$path}" ) . "/{$image_name}";

        try
        {
            $response = retry(
                times: 7,
                sleepMilliseconds: 10000,
                callback: function () use ( $url )
                {
                    $response_2 = Http::get( url: $url );

                    if ( !$response_2->successful() )
                        throw new \Exception( "request.failed" );

                    return $response_2;
                },
                when: function ( \Throwable $e ) use ( $url, $full_path, $image_name )
                {
                    log_exception(
                        channel : self::LOG_CHANNEL,
                        message : "Images: Fail saveImage. Retry in 10 seconds...",
                        method  : "saveImage",
                        type    : "Throwable",
                        e       : $e,
                        context : [
                            "url"        => $url,
                            "path"       => $full_path,
                            "image_name" => $image_name,
                        ]
                    );

                    return true;
                }
            );
        }
        catch ( \Throwable $e )
        {
            log_exception(
                channel : self::LOG_CHANNEL,
                message : "Images: Fail saveImage. Catch.",
                method  : "saveImage",
                type    : "Throwable",
                e       : $e,
                context : [
                    "url"        => $url,
                    "path"       => $full_path,
                    "image_name" => $image_name,
                ]
            );

            return false;
        }

        if ( !$response->successful() )
            return false;

        file_put_contents( $full_path, $response->body() );

        $handle = Image::open( file: $full_path );
        $handle->resize( width: 200, height: 150, background: "#FFF" );
        $handle->save( file: $full_path );

        return true;
    }

    /**
     * Generates url to track clicks
     *
     * @param   integer     $id     Establishment id
     * @param   string      $url    Destination url
     * @return  string
     */
    private function urlTracking( int $id, string $url )
    {
        $body = [
            "shorten"        => false,
            "advertiserId"   => $id,
            "destinationUrl" => $url,
            "parameters"     => [
                "clickref" => "{uid}",
            ],
        ];

        try
        {
            $response = retry(
                times: 7,
                sleepMilliseconds: 10000,
                callback: function () use ( $body )
                {
                    $response_2 = Http::withToken( token: $this->token )
                                      ->withBody( content: json_encode( $body ), contentType: "application/json" )
                                      ->post( url: $this->url_linkbuilder );

                    if ( !$response_2->successful() )
                    {
                        $err = json_decode( $response_2->body(), true );

                        if ( isset( $err[ "error" ] ) && $err[ "error" ] === "request.limit.exceeded" )
                        {
                            $this->logger->log_message( message: "Promotions: Request limit exceeded. Retry in 10 seconds..." );

                            throw new \Exception( "request.limit.exceeded" );
                        }
                    }

                    return $response_2;
                },
                when: function ( \Throwable $e ) use ( $body, $id )
                {
                    if ( $e->getMessage() !== "request.limit.exceeded" )
                    {
                        $this->logger->log_exception(
                            message: "Promotions: Fail urlTracking ID = {$id}. Retry.",
                            method: "urlTracking",
                            type: "Throwable",
                            e: $e,
                            context: [ "body" => $body ]
                        );
                    }

                    return true;
                }
            );
        }
        catch ( \Throwable $e )
        {
            $this->logger->log_exception(
                message: "Promotions: Fail urlTracking ID = {$id}. Catch.",
                method: "urlTracking",
                type: "Throwable",
                e: $e,
                context: [ "body" => $body ]
            );

            return $url;
        }

        if ( !$response->successful() )
            return $url;

        return parse_url_awin( url: $response->json()[ "url" ] );
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
