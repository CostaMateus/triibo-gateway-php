<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use DateTime;
use DateInterval;
use App\Helpers\Helper;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Kreait\Laravel\Firebase\Facades\Firebase;

class CofryIntegration
{
    const ID           = "-cofry";
    const URL_PROMO    = "https://www.cofry.com.br/offer/import";
    const URL_STORE    = "https://www.cofry.com.br/store/import";
    const URL_STATUS   = "https://www.cofry.com.br/store-check/import";
    const TOKEN        = [ "token" => "b078c85a534c11ea80311e1746133021" ];
    const PREFIX_STORE = "cofry0_";
    const PREFIX_PROMO = "promo0_";

    /**
     *
     * @return  array|null
     */
    public static function getPromocoes()
    {
        $stores    = self::getStoresCofry();
        $promos    = self::getPromosCofry();

        if ( !$stores || !$promos ) return null;

        if ( $stores ) self::usort( $stores );
        if ( $promos ) self::usort( $promos );

        $promos1   = self::translatorPromotionDefault( $stores );
        $promos2   = self::translatorPromotion( $promos );

        $promos    = array_merge_recursive( $promos1, $promos2 );
        $promos    = array_unique( $promos, SORT_REGULAR );

        $keys      = array_column( $promos, "establishmentName" );
		array_multisort( $keys, SORT_ASC, $promos );

        $cupom     = public_path( "images" ) . "/cupom.png";
        $pathPromo = public_path( "images/promos" );
        File::copy( $cupom, $pathPromo . "/common_promotion.png" );

        return $promos;
    }

    /**
     *
     * @return  array|null
     */
    public static function getEstabelecimentos()
    {
        $stores = self::getStoresActive();

        if ( !$stores ) return null;

        $stores = self::translatorStore( $stores );

        return $stores;
    }

    /**
     * Get promotions Cofry.
     *
     * @return  array|null
     */
    private static function getPromosCofry()
    {
        $client = Http::withoutVerifying()->asForm();

        $res    = $client->post( self::URL_PROMO, self::TOKEN )->json();

        if ( $res )
        {
            Log::channel( "cofry" )->info( "CofryAPI", [
                "code"    => $res[ "errorCode" ],
                "method"  => "getPromosCofry",
                "message" => $res[ "errorMessage" ]
            ] );

            if ( $res[ "errorCode" ] == 0 ) return $res[ "store" ];
        }

        return null;
    }

    /**
     * Get stores Cofry.
     *
     * @return  array|null
     */
    private static function getStoresCofry()
    {
        $client = Http::withoutVerifying()->asForm();

        $res    = $client->post( self::URL_STORE, self::TOKEN )->json();

        if ( $res )
        {
            Log::channel( "cofry" )->info( "CofryAPI", [
                "code"    => $res[ "errorCode" ],
                "method"  => "getStoresCofry",
                "message" => $res[ "errorMessage" ]
            ] );

            if ( $res[ "errorCode" ] == 0 ) return $res[ "store" ];
        }

        return null;
    }

    /**
     * Get status stores.
     *
     * @return  array|null
     */
    private static function getStatusStores()
    {
        $client = Http::withoutVerifying()->asForm();

        $res    = $client->post( self::URL_STATUS, self::TOKEN )->json();

        if ( $res )
        {
            Log::channel( "cofry" )->info( "CofryAPI", [
                "code"    => $res[ "errorCode" ],
                "method"  => "getStatusStores",
                "message" => $res[ "errorMessage" ]
            ] );

            if ( $res[ "errorCode" ] == 0 ) return $res[ "store" ];
        }

        return null;
    }

    /**
     * Get Cofry actives stores
     *
     * @return array|null
     */
    private static function getStoresActive()
    {
        $stores = self::getStoresCofry();
        $status = self::getStatusStores();

        if ( !$stores || !$status ) return null;

        if ( $stores ) self::usort( $stores );
        if ( $status ) self::usort( $status );

        /** Lojas tag Destaques */
            // - 123  Booking
            // - 137  Nike
            // - 347  Amazon - removido
            // - 349  Americanas
            // - 351  Submarino
            // - 357  Centauro
            // - 445  Carrefour
            // - 503  Cobasi
        /************************/

        $highlights = [ 123, 137, 349, 351, 357, 445, 503 ];

        foreach ( $stores as $id => &$store )
        {
            $index             = array_search( $store[ "uuid" ], array_column( $status, 'uuid' ) );
            $store[ "Active" ] = $status[ $index ][ "Active" ];

            if ( $store[ "Segment" ] )
            {
                $tags = preg_split( "/\b,\b/u", $store[ "Segment" ] );
                array_unshift( $tags, "Cofry" );
            }
            else
            {
                $tags = [ "Cofry" ];
            }

            if ( in_array( $store[ "uuid" ], $highlights ) ) array_push( $tags, "Destaques" );

            $store[ "Segment" ] = $tags;

            // Estabelecimentos com esse segmento são ignorados
            if ( in_array( "Seguros e Finanças", $tags ) ) unset( $stores[ $id ] );
        }

        return $stores;
    }

    /**
     * Translates Cofry promotions into Triibo promotions
     *
     * @param array $data
     * @return array $res
     */
    private static function translatorPromotion( array $data )
    {
        $date    = new DateTime( date( "Y-m-d H:i:s.u" ) );
        $dateEn  = ( int ) $date->format( "U" ) * 1000;
        $date    = $date->add( new DateInterval( "P7Y" ) );
        $dateDis = ( int ) $date->format( "U" ) * 1000;

        foreach ( $data as $promo )
        {
            $idStore              = self::ID . $promo[ "uuid" ];
            $idPromo              = str_replace( ".", "", $promo[ "ProductAPIID" ] );
            $id                   = $idStore . $idPromo;

            $cashback             = number_format( ( float ) $promo[ "CashbackClient" ], $promo[ "CashbackFormat" ], ",", "" );

            $establishmentName    = trim( $promo[ "Advertiser" ] );

            $denylist             = self::getDenylist( "Mapfre" );

            $promo[ "ItemCode" ]  = str_replace( " ",      "", $promo[ "ItemCode" ] );
            $promo[ "ItemCode" ]  = str_replace( "Cupom:", "", $promo[ "ItemCode" ] );
            $promo[ "ItemCode" ]  = str_replace( "\t",     "", $promo[ "ItemCode" ] );

            if ( $promo[ "ItemTitle" ] === "******" ) $promo[ "ItemTitle" ] = "Cupom: ******";

            $promo[ "ItemTitle" ] = str_replace( "( Código: ******)",  " (Código: ******)",           $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "Código ******",      "(Código: ******)",            $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "(código: ******)",   "(Código: ******)",            $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "(cÓdigo: ******)",   "(Código: ******)",            $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "(código: ******  )", "(Código: ******)",            $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "Cupom******",        "Cupom: ******",               $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "Cupom ******",       "Cupom: ******",               $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "Cupom:******",       "Cupom: ******",               $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "Cupom = ******",     "Cupom: ******",               $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "(cupom: ******)",    "Cupom: ******",               $promo[ "ItemTitle" ] );

            $promo[ "ItemTitle" ] = str_replace( " ****** ",           " ",                           $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( " ****** ",           " ",                           $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "****** de ",         "",                            $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "****** & ",          "",                            $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( " ******!",           "",                            $promo[ "ItemTitle" ] );

            $promo[ "ItemTitle" ] = str_replace( "   ",                " ",                           $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "  ",                 " ",                           $promo[ "ItemTitle" ] );

            $promo[ "ItemTitle" ] = str_replace( ": ******",           ": "   . $promo[ "ItemCode" ], $promo[ "ItemTitle" ] );
            $promo[ "ItemTitle" ] = str_replace( "Use ******",         "Use " . $promo[ "ItemCode" ], $promo[ "ItemTitle" ] );

            $res[ $id ]           = [
                "partnerPromotionId" => $idPromo,

                "establishmentId"    => $idStore,
                "establishmentName"  => $establishmentName,

                "title"              => "Ganhe " . $cashback . "% de volta!",
                "detailsTitle"       => "Ganhe " . $cashback . "% de volta!",

                "description"        => $promo[ "ItemTitle" ],
                "detailsDescription" => $promo[ "ItemTitle" ] . "\n\nUtilize o código de desconto " . $promo[ "ItemCode" ] . " no fechamento da compra.",

                "checkoutCode"       => $promo[ "ItemCode" ],

                "enableDate"         => $dateEn,
                "disableDate"        => $dateDis,
                "dueDate"            => $dateDis,

                "isPublished"        => ( in_array( $establishmentName, $denylist ) ) ? false : true,
                "active"             => true,

                "lat"                => -15.52047783763576,
                "long"               => -48.015408891158984,

                "level"              => 99,

                "photo"              => "common_promotion.png",
                "thumbnail"          => "common_promotion.png",

                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => "{urlCofry}",

                "keyWordsSet"        => [],

                "origin"             => "cofry",
                "buttonLabel"        => "Conferir",
                "id"                 => "",
            ];
        }

        return $res;
    }

    /**
     * Translates Cofry promotions default into Triibo promotions
     *
     * @param array $data
     * @return array $res
     */
    private static function translatorPromotionDefault( array $data )
    {
        $date    = new DateTime( date( "Y-m-d H:i:s.u" ) );
        $dateEn  = ( int ) $date->format( "U" ) * 1000;
        $date    = $date->add( new DateInterval( "P7Y" ) );
        $dateDis = ( int ) $date->format( "U" ) * 1000;

        foreach ( $data as $store )
        {
            $idStore             = self::ID . $store[ "uuid" ];

            $auxAdv              = preg_replace( "/[^\w\s]/", "", iconv( "UTF-8", "ASCII//TRANSLIT//IGNORE", $store[ "Advertiser" ] ) );
            $auxAdv              = str_replace( "\t", "", $auxAdv );
            $idPromo             = strtolower( str_replace( " ", "", $auxAdv ) );
            $id                  = $idStore . $idPromo;

            $establishmentName   = trim( $store[ "Advertiser" ] );

            $denylist            = self::getDenylist( "Mapfre" );

            $cashback            = number_format( ( float ) $store[ "CashbackClient" ], $store[ "CashbackFormat" ], ",", "" );

            $store[ "Logo"     ] = str_replace( "https://imgcofrynew.s3.amazonaws.com/", "", $store[ "Logo" ] );
            $store[ "Logo"     ] = self::PREFIX_PROMO . ( ( basename( $store[ "Logo" ] ) != "" ) ? basename( $store[ "Logo" ] ) : "standard.png" );

            $store[ "About"    ] = trim( $store[ "About" ] );
            $store[ "About"    ] = str_replace( "  ", " ", $store[ "About" ] );

            $rules               = str_replace( "\t", "", $store[ "GeneralRules" ] );
            $rules               = json_decode( $rules, true );
            $rules[ "general"  ] = str_replace( "\t",         "",   $rules[ "general" ] );
            $rules[ "general"  ] = str_replace( "<ol><li>",   "",   $rules[ "general" ] );
            $rules[ "general"  ] = str_replace( "</li></ol>", "",   $rules[ "general" ] );
            $rules[ "general"  ] = str_replace( "</li><li>",  "\n", $rules[ "general" ] );
            $rules[ "specific" ] = "";

            if ( $store[ "SpecificRules" ] )
            {
                $rules[ "specific" ] = json_decode( $store[ "SpecificRules" ], true );
                $rules[ "specific" ] = str_replace( "<b>",  "", $rules[ "specific" ][ "rules" ][ 0 ][ "description" ] );
                $rules[ "specific" ] = str_replace( "</b>", "", $rules[ "specific" ] );
            }

            $description     = $rules[ "general" ] . ( $rules[ "additional" ] != "" ? "\n\n" . $rules[ "additional" ] : "" ) . ( $rules[ "specific" ] != "" ? "\n\n" . $rules[ "specific" ] : "" );

            $res[ $id ]      = [
                "partnerPromotionId" => $idPromo,

                "establishmentId"    => $idStore,
                "establishmentName"  => $establishmentName,

                "title"              => "Ganhe " . $cashback . "% de volta!",
                "detailsTitle"       => "Ganhe " . $cashback . "% de volta!",

                "description"        => $store[ "About" ],
                "detailsDescription" => $description,

                "enableDate"         => $dateEn,
                "disableDate"        => $dateDis,
                "dueDate"            => $dateDis,

                "isPublished"        => ( in_array( $establishmentName, $denylist ) ) ? false : true,
                "active"             => true,

                "lat"                => -15.52047783763576,
                "long"               => -48.015408891158984,

                "level"              => 99,

                "photo"              => $store[ "Logo" ],
                "thumbnail"          => $store[ "Logo" ],

                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => "{urlCofry}",

                "keyWordsSet"        => [],

                "origin"             => "cofry",
                "buttonLabel"        => "Conferir",
                "id"                 => "",
            ];
        }

        return $res;
    }

    /**
     * Translates Cofry store into Triibo Establishments
     *
     * @param array $data
     * @return array $res
     */
    private static function translatorStore( array $data )
    {
        $date    = new DateTime( date( "Y-m-d H:i:s.u" ) );
        $dateEn  = ( int ) $date->format( "U" ) * 1000;
        $date    = $date->add( new DateInterval( "P7Y" ) );
        $dateDis = ( int ) $date->format( "U" ) * 1000;

        foreach ( $data as $store )
        {
            $id                 = self::ID . $store[ "uuid" ];

            $storeName          = trim( $store[ "Advertiser" ] );

            $denylist           = self::getDenylist( "Mapfre" );

            $cashback           = number_format( ( float ) $store[ "CashbackClient" ], $store[ "CashbackFormat" ], ",", "" );

            $store[ "Logo"    ] = str_replace( "https://imgcofrynew.s3.amazonaws.com/", "", $store[ "Logo" ] );
            $store[ "Logo"    ] = self::PREFIX_STORE . ( ( basename( $store[ "Logo" ] ) != "" ) ? basename( $store[ "Logo" ] ) : "standard.png" );

            $store[ "About"   ] = trim( $store[ "About" ] );
            $store[ "About"   ] = str_replace( "  ", " ", $store[ "About" ] );

            $rules              = str_replace( "\t", "", $store[ "GeneralRules" ] );
            $rules              = json_decode( $rules, true );
            $rules[ "general" ] = str_replace( "<ol><li>",   "",   $rules[ "general" ] );
            $rules[ "general" ] = str_replace( "</li></ol>", "",   $rules[ "general" ] );
            $rules[ "general" ] = str_replace( "</li><li>",  "\n", $rules[ "general" ] );
            $description        = $store[ "About" ] . "\n\n" . $rules[ "general" ] . ( $rules[ "additional" ] != "" ? "\n\n" . $rules[ "additional" ] : "" );
            $description        = Helper::addTagsToDescriptionCofry( $description, $store[ "Segment" ] );

            $res[ $id ]         = [
                "partnerEstablishmentId" => $store[ "uuid" ],
                "cashbackDefault"        => $cashback,

                "enableDate"             => $dateEn,
                "disableDate"            => $dateDis,
                "dataAlteracao"          => $dateEn,
                "dataInclusao"           => $dateEn,

                "idGestor"               => "+5511949289507@sms,triibo,com,br",
                "isPublished"            => ( in_array( $storeName, $denylist ) ) ? false : ( $store[ "Active" ] ? true : false ),

                "endereco"               => "Brasil",
                "lat"                    => -15.52047783763576,
                "long"                   => -48.015408891158984,

                "nome"                   => $storeName,
                "descricao"              => $description,

                "fotoThumb"              => $store[ "Logo" ],
                "listaFotos"             => [ $store[ "Logo" ], ],

                "funcionamento"          => [ ...self::getSchedulesOperation() ],
                "horarios"               => [ ...self::getSchedulesOperation( false ) ],

                "tags"                   => [ ...$store[ "Segment" ] ],

                "keyWordsSet"            => [],

                "origin"                 => "cofry",
            ];
        }

        return $res;
    }

    /**
     *
     * @param boolean $operation
     * @return array
     */
    private static function getSchedulesOperation( $operation = true )
    {
        $res = [];

        if ( $operation )
        {
            // funcionamento
            foreach ( range( 1,7 ) as $key ) {
                $res[] = [
                    "ativo"         => true,
                    "horarioFim"    => "23:59",
                    "horarioInicio" => "00:00"
                ];
            }
        }
        else
        {
            // horarios
            foreach ( range( 1,7 ) as $key ) {
                $res[] = [
                    "tipo"   => "aberto",
                    "turnos" => [
                        [
                            "fim"    => "23:59",
                            "inicio" => "00:00"
                        ]
                    ]
                ];
            }
        }

        return $res;
    }

    /**
     *
     * @param array $arr
     * @return void
     */
    private static function usort( array &$arr )
    {
        usort( $arr, function( $a, $b ) {
            return $a[ "uuid" ] <=> $b[ "uuid" ];
        } );
    }

    /**
     *
     * @return void
     */
    public static function getImagens()
    {
        $stores    = self::getStoresActive();

        if ( !$stores ) return;

        foreach ( $stores as $store )
            if ( $store[ "Logo" ] ) self::saveImage( $store[ "Logo" ] );

        $pathStore = public_path( "images/estabelecimentos" );
        $pathPromo = public_path( "images/promos" );

        $default   = public_path( "images/standard.png" );
        File::copy( $default, $pathStore . "/cofry0_standard.png" );
        File::copy( $default, $pathPromo . "/promo0_standard.png" );

        $common    = public_path( "images/cupom.png" );
        File::copy( $common, $pathPromo . "/common_promotion.png" );
    }

    /**
     *
     * @param string $logo
     * @return void
     */
    private static function saveImage( string $logo )
    {
        $pathStore = public_path( "images/estabelecimentos" );
        $pathPromo = public_path( "images/promos" );

        $imgName   = str_replace( "https://imgcofrynew.s3.amazonaws.com/", "", $logo );
        $imgName   = basename( $imgName );

        if ( $imgName )
        {
            $fullPathStore = $pathStore . "/" . self::PREFIX_STORE . $imgName;
            $fullPathPromo = $pathPromo . "/" . self::PREFIX_PROMO . $imgName;

            $response      = Http::withoutVerifying()->get( $logo );

            if ( $response->successful() )
            {
                file_put_contents( $fullPathStore, $response->body() );

                $handle = Image::open( $fullPathStore );
                $handle->scaleResize( 200, 150, "#FFF" );
                $handle->save( $fullPathStore );
                $handle->save( $fullPathPromo );
            }
        }
    }

    /**
     * Retrieves an exclusion list of stores, given the partner
     *
     * @param string $partner
     * @return array
     */
    private static function getDenylist( $partner )
    {
        return Firebase::database()->getReference( "/Partners-Denylist/$partner" )->getValue();
    }
}
