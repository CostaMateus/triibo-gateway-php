<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use DateTime;
use Exception;
use DateInterval;
use App\Helpers\Helper;
use Gregwar\Image\Image;
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

    protected $url_reports_advertiser;
    protected $url_reports_creative;
    protected $url_reports_campaign;

    protected $url_transactions;

    protected $stores = [];
    protected $base   = 100; // base de calculo, ex 100 reais gastos
    protected $rate   = 10;  // taxa de conversão, 10 pontos a cada 1 real

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
        $this->url_reports_advertiser     = "{$url}/publishers/{$p_id}/reports/advertiser";     // startDate - endDate - timezone - region
        $this->url_reports_creative       = "{$url}/publishers/{$p_id}/reports/creative";       // startDate - endDate - timezone - region
        $this->url_reports_campaign       = "{$url}/publishers/{$p_id}/reports/campaign";       // startDate - endDate - timezone - region
        $this->url_transactions           = "{$url}/publishers/{$p_id}/transactions";           // startDate - endDate - timezone

        // Url Tracking
        $this->url_linkbuilder            = "{$url}/publishers/{$p_id}/linkbuilder/generate";   //

        $this->stores                     = $this->getStores();
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
            $path    = public_path( "images/affiliates/awin/establishments" );
            $default = public_path( "images/standard.png" );

            File::copy( $default, $path . "/standard.png" );

            $stores  = $this->searchStores();

            if ( !$stores[ "success" ] && empty( $stores[ "data" ] ) ) return []; // falha

            Helper::usort( "id", $stores[ "data" ] );

            /**
             * TODO remover em PRD
             */
            $i = 1;
            foreach ( $stores[ "data" ] as &$store )
            {
                /**
                 * TODO remover em PRD
                 */
                if ( $i > 10 ) continue;
                $i++;

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
            $id     = $store[ "partnerEstablishmentId" ];
            $offers = $this->searchOffers( $id );

            if ( empty( $offers[ "data" ] ) ) continue;

            $offers     = $this->translateOffers( $offers[ "data" ], $store[ "cashbackDefault" ] );
            $promotions = array_merge( $promotions, $offers );
        }

        return $promotions;
    }

    /**
     * Get stores Awin
     *
     * @return array
     */
    private function searchStores()
    {
        $url      = "{$this->url_programmes}?relationship=joined&countryCode=" . self::RG;
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
    private function searchStoresDetails( int $id )
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

            if( !$response->successful() )
            {
                return [
                    "success" => false,
                    "code"    => 4001,
                    "data"    => $data
                ];
            }

            $page++;

            $resp       = $response->json();

            if ( !empty( $resp[ "data" ] ) )
                $data = array_merge( $data, $resp[ "data" ] );

            $pagination = $resp[ "pagination" ];

            if ( empty( $resp[ "data" ] ) || $pagination[ "total" ] < $page || $pagination[ "total" ] == $page )
                $ended = true;
        }
        while ( !$ended );

        return [
            "success" => true,
            "code"    => 0,
            "data"    => $data,
        ];
    }

    /**
     * Translates Awin stores into Triibo Establishments
     *
     * @param array $data
     * @return array
     */
    private function translateStores( array $data )
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


            $id            = self::ID . $store[ "id" ];

            $cashback      = "";

            if ( $cashback = reset( $store[ "commissionRange" ] ) )
            {
                $cashback = ( float ) $cashback[ "max" ] / 2;
                $cashback = number_format( $cashback, 1, ",", "" );
            }

            $auxName       = Helper::hyphenize( strtolower( $store[ "name" ] ) );
            $imageName     = "awin_{$store[ "id" ]}_{$auxName}.png";
            $imageName     = self::saveImage( "establishments", $imageName, $store[ "logoUrl" ] ) ? $imageName : "standard.png";

            // $url           = $store[ "clickThroughUrl" ];

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

                "fotoThumb"              => $imageName,
                "listaFotos"             => [ $imageName ],

                "funcionamento"          => [ ...Helper::getSchedulesOperation()        ],
                "horarios"               => [ ...Helper::getSchedulesOperation( false ) ],

                "tags"                   => [],

                "keyWordsSet"            => []
            ];
        }

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

            $imageName         = "standard.png";

            $urlTracking       = $this->urlTracking( $offer[ "advertiser" ][ "id" ], $offer[ "url" ] );
            $url               = ( $offer[ "url" ] == $urlTracking ) ? $offer[ "urlTracking" ] : $urlTracking;

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

                "photo"              => $imageName,
                "thumbnail"          => $imageName,

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
            }
        }

        return $promotions;

        // {
        //     "url": "https://www.awin1.com/cread.php
        //         ?awinmid=17762
        //         &awinaffid=1109413
        //         &campaign=Teste
        //         &clickref=uuid-teste-big-num
        //         &clickref2=
        //         &clickref3=
        //         &clickref4=
        //         &clickref5=
        //         &clickref6=
        //         &ued=https%3A%2F%2Fwww.madeiramadeira.com.br%2F%3Forigem%3Dzanox%26utm_source%3Dzanox%26utm_medium%3Dcpa%26utm_term%3D%21%21%21affid%21%21%21%26utm_campaign%3D%21%21%21clickref%21%21%21
        //         &pl=la",
        // }
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

    private function urlTracking( int $storeId, string $url )
    {
        $body     = [
            "shorten"        => false,
            "advertiserId"   => $storeId,
            "destinationUrl" => $url,
            "parameters"     => [
                "clickref" => "{triiboId}",
            ],
        ];

        $response = Http::withToken( $this->tkn )
                        ->withBody( json_encode( $body ), "application/json" )
                        ->post( $this->url_linkbuilder );

        if( !$response->successful() ) return $url;

        $response = $response->json();

        return $response[ "url" ];
    }
}
