<?php

namespace App\Http\Controllers;

use Exception;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;
use App\Http\Integrations\FastshopIntegration\Log;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class FastshopController extends Controller
{
    private $env;
    private $fsUrl;
    private $fsHeaders;

    public function __construct()
    {
        $this->env       = config("fastshop.env");
        $this->fsUrl     = config("fastshop.$this->env.fs.url");
        $this->fsHeaders = config("fastshop.$this->env.fs.headers");
    }

    // --------- METODOS ABAIXO SÂO CONSUMIDOS PELA FASTSHOP
    /**
     * Notificação de catálogo
     * Fastshop sugere novos SKUs a serem vendidos no Marketplace e
     * informa ao Marketplace sobre as mudanças em seus SKUs que já foram integrados
     * como alterações de cadastro, estoque e preço. Ambas as ações começam com uma notificação de catálogo.
     *
     * @param Request $request
     * @param string $sellerId
     * @param string $sellerSkuId
     * @return Response
     */
    public function changeNotification(Request $request, $sellerId, $sellerSkuId)
    {
        // Verifição de chaves
        if (($verify = $this->keysVerification($request)) !== TRUE) return $verify;

        try
        {
            // Procura produto no Marketplace pelo SKU
            $product = Product::all(["sku" => "FS-" . strtoupper($sellerSkuId)])->first();

            if (!$product)
            {
                // remover em PROD
                $sku = "[" . date("Ymd-his") . "] " . strtoupper($sellerSkuId) . " NOT FOUND";
                Storage::append("fs-skus.txt", $sku);

                return response()->json([
                    "errorCode" => "404",
                    "message"   => "Seller StockKeepingUnit {$sellerSkuId} not found for this seller id {$sellerId}",
                ], 404);
            }

            // Atualiza produto no Marketplace
            $this->updateStockPrice($product, $sellerSkuId);

            return response()->json([
                "errorCode" => null,
                "message"   => "Ok",
            ], 200);
        }
        catch (Exception $e)
        {
            Log::exception("changeNotification", $e);

            return response()->json([
                "errorCode" => "500",
                "message"   => "something wrong",
            ], 500);
        }
    }

    /**
     * Registro de catálogo
     * Recebe sugestão de um novo produto para o Marketplace.
     * Toda vez que changeNotification() retornar o status 404.
     *
     * @param Request $request
     * @return Response
     */
    public function skuSuggestion(Request $request, $sellerId, $sellerSkuId)
    {
        // Verifição de chaves
        if (($verify = $this->keysVerification($request)) !== TRUE) return $verify;

        try
        {
            // Recupera dados do produto
            $fsData    = $request->all();

            // // LOG
            // $date      = date("Ymd-his");
            // Storage::put("fs-$sellerSkuId.$date.json", json_encode($fsData, JSON_UNESCAPED_SLASHES));

            $fsData["CategoryFullPath"] = self::getCategoriesId($fsData["CategoryFullPath"]);

            // Converte produto da FS para WC
            $wcData    = DataTranslator::translate("newProduct", $fsData);

            // Atualiza produto no Marketplace
            $searchSku = Product::all(["sku" => $wcData["sku"]]);

            if (!$searchSku->first())
            {
                $new = Product::create($wcData);
                $upd = false;
            }
            else
            {
                $new = Product::update($searchSku->first()->id, $wcData);
                $upd = true;
            }

            Log::createOrUpdateProduct("skuSuggestion", $new["sku"], $upd);

            return response()->json([
                "errorCode" => null,
                "message"   => "Ok",
            ], 200);
        }
        catch (Exception $e)
        {
            Log::exception("skuSuggestion", $e);

            return response()->json([
                "errorCode" => "500",
                "message"   => "something wrong",
            ], 500);
        }
    }

    /**
     * Atualização de catálogo
     *
     * @param Request $request
     * @return Response
     */
    public function updateSku(Request $request, $sellerId, $sellerSkuId)
    {
        // Verifição de chaves
        if (($verify = $this->keysVerification($request)) !== TRUE) return $verify;

        try
        {
            // Recupera dados do produto
            $fsData = $request->all();

            // remover em PROD
            $date   = date("Ymd-his");
            Storage::put("fs-$sellerSkuId.$date.json", json_encode($fsData, JSON_UNESCAPED_SLASHES));

            $fsData["CategoryFullPath"] = self::getCategoriesId($fsData["CategoryFullPath"]);

            // Converte produto da FS para WC
            $wcData = DataTranslator::translate("newProduct", $fsData);

            // VERIFICAR E TRATAR OS SkuSpecifications
            // Atualiza produto no Marketplace
            // $new    = Product::create($wcData);
            $searchSku = Product::all(["sku" => $wcData["sku"]]);

            if (!$searchSku->first())
            {
                return response()->json([
                    "errorCode" => "404",
                    "message"   => "something wrong on update product"
                ], 404);
            }

            $new = Product::update($searchSku->first()->id, $wcData);

            Log::createOrUpdateProduct("updateSku", $new["sku"], true);

            return response()->json([
                "errorCode" => null,
                "message"   => "Ok",
            ], 200);
        }
        catch (Exception $e)
        {
            Log::exception("updateSku", $e);

            return response()->json([
                "errorCode" => "500",
                "message"   => "something wrong",
            ], 500);
        }
    }


    // --------- METODOS ABAIXO SÃO CONSUMIDOS PELO WOOCOMMERCE, CONSULTAM AS APIs DA FASTSHOP
    /**
     * Atualização de preço/estoque
     * Consulta a Fastshop para atualizar os dados do produto.
     * Toda vez que changeNotification() retornar o status 200.
     *
     * @param Request $request
     * @return void
     */
    public function updateStockPrice($product, $sellerSkuId)
    {
        $url  = "$this->fsUrl/pvt/orderForms/simulation";
        $body = DataTranslator::translate("queryStockPrice", [$sellerSkuId]);

        try
        {
            // Consulta produto na FS
            $response = Http::withHeaders($this->fsHeaders)->withBody(json_encode($body), "application/json")->get($url);

            if ($response->successful())
            {
                $fsData = $response->json();

                // Converte produto da FS para WC
                $wcData = DataTranslator::translate("updateStockPrice", $fsData);

                // Atualiza produto no Marketplace
                $new    = Product::update($product->id, $wcData);

                // Registrar log de sucesso
                Log::updateStockPrice($product, $new);
            }
            else
            {
                // Registrar log de falha
                Log::updateStockPrice($product, null, true);
            }
        }
        catch (Exception $e)
        {
            Log::exception("updateStockPrice", $e);
        }
    }

    /**
     * Cálculo de frete
     * Consulta a Fastshop para obter informações comerciais sobre SKUs, tais como, preço, estoque, frete e prazos de entrega.
     * O "postalCode" deve ser preenchido no request e será obrigatório para o retorno das informações de logística.
     *
     * @param Request $request
     * @return Response
     */
    public function freightCalculation(Request $request)
    {
        // Verifição de chaves
        if (($verify = $this->keysVerification($request, true)) !== TRUE) return $verify;

        $wcData = $request->all();

        try
        {
            // Converte dados do WC para FS
            $body     = DataTranslator::translate("freightCalcFs", $wcData);

            // Consulta valor do frete na FS
            $url      = "$this->fsUrl/pvt/orderForms/simulation";
            $response = Http::withHeaders($this->fsHeaders)->withBody(json_encode($body), "application/json")->post($url);

            if (!$response->successful())
            {
                Log::freightCalculation($body);

                return response()->json([
                    "errorCode" => "5001", // codigo para tratar no WooCommerce
                    "message"   => "Something wrong when consulting the delivery price",
                    "data"      => DataTranslator::translate("freightCalcFailed", $wcData),
                ], 500);
            }

            $fsData = $response->json();

            $itemWithoutStock = array_search(0, array_column($fsData["logisticsInfo"], "stockBalance"));

            if (empty($fsData["logisticsInfo"]) || $itemWithoutStock !== false)
            {
                return response()->json([
                    "errorCode" => "4001", // codigo para tratar no WooCommerce
                    "message"   => "SKUs, currently out-of-stock",
                    "data"      => DataTranslator::translate("freightCalcFailed", $fsData),
                ], 400);
            }

            foreach ($fsData["logisticsInfo"] as $items)
            {
                if (empty($items["slas"]))
                {
                    return response()->json([
                        "errorCode" => "4002", // codigo para tratar no WooCommerce
                        "message"   => "Delivery unavailable for this zip code",
                        "data"      => DataTranslator::translate("freightCalcFailed", $fsData),
                    ], 400);
                }
            }

            // Converte dados do frete da FS para WC
            $wcData = DataTranslator::translate("freightCalcWc", $fsData);

            return response()->json([
                "errorCode" => null, // codigo para tratar no WooCommerce
                "message"   => "ok",
                "data"      => $wcData,
            ], 200);
        }
        catch (Exception $e)
        {
            Log::exception("freightCalculation", $e);

            return response()->json([
                "errorCode" => "5002", // codigo para tratar no WooCommerce
                "message"   => "Something wrong",
                "data"      => DataTranslator::translate("freightCalcFailed", $wcData),
            ], 500);
        }
    }

    /**
     * Criação de pedido
     * Notifica a Fastshop para validar e processar o pedido.
     * Deve-se enviará informações como os itens contidos no carrinho, dados do perfil do cliente, dados de envio e os dados de pagamento.
     *
     * @param Request $request
     * @return Response
     */
    public function orderCreation(Request $request)
    {
        // Verifição de chaves
        if (($verify = $this->keysVerification($request, true)) !== TRUE) return $verify;

        try
        {
            $wcData = $request->all();

            Storage::put("wc-order-" . date("Ymd-his") . ".json", json_encode($wcData, JSON_UNESCAPED_SLASHES));

            return response()->json([
                "errorCode" => null,
                "message"   => "ok"
            ], 200);
        }
        catch (Exception $e)
        {
            Log::exception("orderCreation", $e);

            return response()->json([
                "errorCode" => "500",
                "message"   => "something wrong",
            ], 500);
        }
    }

    /**
     * Pedido Aprovado e Pago
     * Após o pagamento ser concluído e aprovado, notifica a Fastshop avisando que este pode iniciar o processo de faturamento e entrega do pedido.
     *
     * @param Request $request
     * @return Response
     */
    public function orderApproved(Request $request)
    {
        // Verifição de chaves
        if (($verify = $this->keysVerification($request, true)) !== TRUE) return $verify;

        try
        {
            $wcData = $request->all();
        }
        catch (Exception $e)
        {
            Log::exception("orderCreation", $e);

            return response()->json([
                "errorCode" => "500",
                "message"   => "something wrong",
            ], 500);
        }
    }


    /**
     * Pedido Cancelado pelo Marketplace
     * Notifica a Fastshop de que o pedido foi cancelado.
     *
     * @param Request $request
     * @return Response
     */
    public function orderCanceledByMarketplace(Request $request)
    {
        // Verifição de chaves
        if (($verify = $this->keysVerification($request, true)) !== TRUE) return $verify;

        try
        {
            $wcData = $request->all();
        }
        catch (Exception $e)
        {
            Log::exception("orderCreation", $e);

            return response()->json([
                "errorCode" => "500",
                "message"   => "something wrong",
            ], 500);
        }
    }


    // --------- METODOS DE USO INTERNO
    /**
     * Consulta e cria categorias no Marketplace
     *
     * @param string $data
     * @return array
     */
    private static function getCategoriesId(string $data)
    {
        $auxData    = explode("/", $data);
        $categories = [];

        foreach ($auxData as $key => $ctg)
        {
            $slug           = Helper::hyphenize($ctg);

            $searchCategory = Category::all(["slug" => $slug]);

            if (!$searchCategory->first())
            {
                $new          = Category::create([
                    "name"   => $ctg,
                    "parent" => ($key == 0) ? 0 : $categories[$key - 1]
                ]);

                $categories[] = $new["id"];
            }
            else
            {
                $categories[] = $searchCategory->first()->id;
            }
        }

        return $categories;
    }

    /**
     * Valida as chaves das requisições recebidas
     *
     * @param Request $request
     * @param boolean $wc       Chaves do WooCommerce
     * @return true|Response
     */
    private function keysVerification(Request $request, bool $wc = false)
    {
        $env = $this->env;

        if (!$wc)
        {
            $appKey   = $request->header("x-vtex-api-appkey");
            $appToken = $request->header("x-vtex-api-apptoken");

            // Verifição de chaves
            if ($appKey != config("fastshop.$env.fs.key") || $appToken != config("fastshop.$env.fs.token"))
            {
                Log::invalidCredentials($appKey, $appToken);

                return response()->json([
                    "errorCode" => "400",
                    "message"   => "invalid credentials"
                ], 400);
            }
            else
            {
                return TRUE;
            }
        }
        else
        {
            $appKey   = $request->header("api-key");
            $appToken = $request->header("api-token");

            // Verifição de chaves
            if ($appKey != config("fastshop.$env.wc.key") || $appToken != config("fastshop.$env.wc.token"))
            {
                Log::invalidCredentials($appKey, $appToken);

                return response()->json([
                    "errorCode" => "400",
                    "message"   => "invalid credentials"
                ], 400);
            }
            else
            {
                return TRUE;
            }
        }
    }
}
