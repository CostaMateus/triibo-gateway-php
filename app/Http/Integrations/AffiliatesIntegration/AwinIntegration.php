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
        $this->url_offers                 = "{$url}/publisher/{$p_id}/promotions";             //

        $this->url_commissiongroups       = "{$url}/publishers/{$p_id}/commissiongroups";       // advertiserId
        $this->url_commissionsharingrules = "{$url}/publishers/{$p_id}/commissionsharingrules"; //
        $this->url_reports_advertiser     = "{$url}/publishers/{$p_id}/reports/advertiser";     // startDate - endDate - timezone - region
        $this->url_reports_creative       = "{$url}/publishers/{$p_id}/reports/creative";       // startDate - endDate - timezone - region
        $this->url_reports_campaign       = "{$url}/publishers/{$p_id}/reports/campaign";       // startDate - endDate - timezone - region
        $this->url_transactions           = "{$url}/publishers/{$p_id}/transactions";           // startDate - endDate - timezone
    }

    /**
     * Awin programmes == Triibo Establishments (FB)
     *
     * @return array
     */
    public function getStores()
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

        return $this->translateStores( $stores[ "data" ] );
    }

    /**
     * Get stores Awin
     *
     * @return array
     */
    private function searchStores()
    {
        $url      = "{$this->url_programmes}?countryCode=" . self::RG;

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

                "nome"                   => $store[ "name"        ],
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
}
