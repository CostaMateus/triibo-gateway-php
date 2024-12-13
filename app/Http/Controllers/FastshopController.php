<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\Log;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class FastshopController extends Controller
{
    private $env;
    private $fs_url;
    private $fs_headers;

    public function __construct()
    {
        $this->env        = config( "fastshop.env"                     );
        $this->fs_url     = config( "fastshop.{$this->env}.fs.url"     );
        $this->fs_headers = config( "fastshop.{$this->env}.fs.headers" );
    }

    /**
     * Atualiza preço e estoque
     *
     * API chamada a partir do ../plugins/triibo-api-services | triibo-fastshop
     *
     * @param   Request     $request
     * @param   string      $sku
     * @return  Response
     */
    public function updateSkuWc( Request $request, $sku )
    {
        // Verifição de chaves
        if ( ( $verify = $this->keysVerification( $request, true ) ) !== TRUE )
            return $verify;

        try
        {
            // Procura produto no Marketplace pelo SKU
            $product = Product::all( [ "sku" => strtoupper( $sku ) ] )->first();

            if ( !$product )
            {
                return response()->json( [
                    "errorCode" => "4001",
                    "message"   => "Unable to retrieve product data",
                ], 404 );
            }

            $sku     = explode( "FS-", $sku )[ 1 ];

            // Atualiza produto no Marketplace
            $this->updateStockPrice( $product, $sku );

            return response()->json( [
                "errorCode" => null,
                "message"   => "Ok",
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "MP - updateSkuWc", $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Valida as chaves das requisições recebidas
     *
     * @param Request $request
     * @param boolean $wc       Valida chaves do WooCommerce
     * @return true|Response
     */
    private function keysVerification( Request $request, bool $wc = false )
    {
        if ( !$wc )
        {
            $app_key   = $request->header( "x-vtex-api-appkey"   );
            $app_token = $request->header( "x-vtex-api-apptoken" );

            // Verifição de chaves
            if ( $app_key != config( "fastshop.{$this->env}.fs.key" ) || $app_token != config( "fastshop.{$this->env}.fs.token" ) )
            {
                Log::invalidCredentials( $app_key, $app_token );

                return response()->json( [
                    "errorCode" => "400",
                    "message"   => "invalid credentials"
                ], 400 );
            }
            else
            {
                return TRUE;
            }
        }
        else
        {
            $app_key   = $request->header( "api-key"   );
            $app_token = $request->header( "api-token" );

            // Verifição de chaves
            if ( $app_key != config( "fastshop.{$this->env}.wc.key" ) || $app_token != config( "fastshop.{$this->env}.wc.token" ) )
            {
                Log::invalidCredentials( $app_key, $app_token );

                return response()->json( [
                    "errorCode" => "400",
                    "message"   => "invalid credentials"
                ], 400 );
            }
            else
            {
                return TRUE;
            }
        }
    }

    /**
     * Atualização de preço/estoque
     * Consulta a Fastshop para atualizar os dados do produto.
     * Toda vez que changeNotification() retornar o status 200.
     *
     * @param Product $Product
     * @param string $sellerSkuId
     * @return void
     */
    private function updateStockPrice( $product, $seller_sku_id )
    {
        $url  = "{$this->fs_url}/pvt/orderForms/simulation";
        $body = DataTranslator::translate( "queryStockPrice", [ $seller_sku_id ] );

        try
        {
            // Consulta produto na FS
            $response = Http::withHeaders( $this->fs_headers )->withBody( json_encode( $body ), "application/json" )->post( $url );

            if ( $response->successful() )
            {
                $fsData = $response->json();

                // Converte produto da FS para WC
                $wcData = DataTranslator::translate( "updateStockPrice", [ $product, $fsData ] );

                // Atualiza produto no Marketplace
                Product::update( $product->id, $wcData );
            }
            else
            {
                // Registrar log de falha
                Log::updateStockPrice( $product, null, true );
            }
        }
        catch ( Exception $e )
        {
            Log::exception( "INT - updateStockPrice", $e, $seller_sku_id );
        }
    }
}
