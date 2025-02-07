<?php

namespace App\Http\Controllers;

use Exception;
use Throwable;
use Illuminate\Http\{ Request, Response };
use App\Http\Integrations\FastshopIntegration\Log;
use Codexshaper\WooCommerce\Facades\Product as WC_Product;
use App\Services\Vtex\{ ProductService, ConnectorService, FulfillmentService };

class FastshopController extends Controller
{
    protected $sale_channel;
    protected $product_service;
    protected $simulation_service;
    protected const LOG_CHANNEL = "marketplace";

    /**
     * Atualiza preço e estoque
     *
     * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop
     *
     * @param   Request     $request
     * @param   string      $sku
     * @return  Response
     */
    public function updateSkuWc( Request $request, string $sku = "" )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request ) ) !== true )
            return $verify;

        if ( !isset( $sku ) )
            return $this->returnResponse( 400, "Empty data" );

        $seller                   = $request->header( "seller" );
        $env                      = config( "vtex.partners.{$seller}.env" );
        $this->sale_channel       = config( "vtex.partners.{$seller}.{$env}.sales_channel" );

        $connector                = new ConnectorService( $seller );
        $connector->enableLogNotify( false );

        $this->product_service    = new ProductService( $connector );
        $this->simulation_service = new FulfillmentService( $connector );

        try
        {
            [ $product_id, $sku ] = explode( "_", $sku );

            $context  = $this->getSkuContext( $sku );
            $data     = $this->getSkuData( $sku );

            if ( $context === null || $data === null )
                return $this->returnResponse( 400, "Empty data" );

            if ( $context === false || $data === false )
            {
                WC_Product::update( $product_id, [ "stock_status" => "outofstock" ] );
                return $this->returnResponse( 200, "ok" );
            }

            $simulate = $this->getSimulate( $data[ "Id" ] );

            if ( $simulate === null )
                return $this->returnResponse( 400, "Empty data" );

            if ( !isset( $simulate[ "items" ] ) )
            {
                WC_Product::update( $product_id, [ "stock_status" => "outofstock" ] );
                return $this->returnResponse( 200, "ok" );
            }

            WC_Product::update( $product_id, [
                "regular_price" => convert_price( $simulate[ "items" ][ 0 ][ "price"        ] ?? 0 ),
                "sale_price"    => convert_price( $simulate[ "items" ][ 0 ][ "sellingPrice" ] ?? 0 ),
                "stock_status"  => ( $simulate[ "items" ][ 0 ][ "availability" ] == "available" ) ? "instock" : "outofstock",
            ] );

            return $this->returnResponse( 200, "ok" );
        }
        catch ( Exception $e )
        {
            return $this->returnResponse( 500, $e->getMessage() );
        }
    }

    /**
     * Valida as chaves das requisições recebidas
     *
     * @param   Request     $request
     * @return  true|Response
     */
    private function keysVerification( Request $request )
    {
        $app_key   = $request->header( "api-key"   );
        $app_token = $request->header( "api-token" );

        // Verifição de chaves
        if ( $app_key != config( "woocommerce.plugins.key" ) || $app_token != config( "woocommerce.plugins.token" ) )
        {
            Log::invalidCredentials( $app_key, $app_token );

            return response()->json( [
                "error_code" => "400",
                "message"    => "invalid credentials"
            ], 400 );
        }

        return true;
    }

    /**
     *
     * @param   integer     $code
     * @param   string      $message
     * @return  JsonResponse
     */
    private function returnResponse( int $code, string $message )
    {
        return match ( $code )
        {
            200     => response()->json( [ "error_code" => 200, "message" => $message, ], 200 ),
            400     => response()->json( [ "error_code" => 400, "message" => $message, ], 400 ),
            default => response()->json( [ "error_code" => 500, "message" => $message, ], 500 ),
        };
    }

    /**
     *
     * @param   integer     $sku
     * @return  array|false|null
     */
    private function getSkuContext( int $sku ) : array|false|null
    {
        try
        {
            $response = $this->product_service->getSkuContext( $sku );

            if ( $response[ 0 ] != 200 )
                return null;

            $product  = $response[ 1 ][ "data" ];

            if ( $product[ "IsActive" ] == false && $product[ "IsProductActive" ] == false )
                return false;

            if ( !in_array( $this->sale_channel, $product[ "SalesChannels" ] ) )
                return false;

            return $product;
        }
        catch( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "getSkuContext",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            return null;
        }
    }

    /**
     *
     * @param   integer     $sku
     * @return  array|false|null
     */
    private function getSkuData( int $sku ) : array|false|null
    {
        try
        {
            $response = $this->product_service->getSkuData( $sku );

            if ( $response[ 0 ] != 200 )
                return null;

            $product  = $response[ 1 ][ "data" ];

            if ( $product[ "IsActive" ] == false )
                return false;

            return $product;
        }
        catch( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "getSkuData",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            return null;
        }
    }

    /**
     *
     * @param   integer     $id
     * @return  array|null
     */
    private function getSimulate( int $id ) : array|null
    {
        try
        {
            $data = [
                "items" => [
                    [
                        "id"       => $id,
                        "quantity" => 1,
                        "seller"   => "1"
                    ]
                ],
                "isCheckedIn" => false,
                "country"     => "BRA",
                "selectedSla" => "Normal",
            ];

            $response = $this->simulation_service->simulate( $data );

            if ( $response[ 0 ] != 200 )
                return null;

            return array_filter(
                $response[ 1 ][ "data" ],
                function( $value, $key )
                {
                    return $value !== null &&
                            $value !== [] &&
                            $key !== "paymentData" &&
                            $key !== "purchaseConditions" &&
                            $key !== "ratesAndBenefitsData";
                },
                ARRAY_FILTER_USE_BOTH
            );
        }
        catch ( Throwable $e )
        {
            log_exception(
                channel: self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "getSimulate",
                type    : "Throwable",
                e       : $e
            );

            $this->error( $e->getMessage() );

            return null;
        }
    }
}
