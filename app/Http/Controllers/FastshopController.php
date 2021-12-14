<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Category;
use App\Http\Integrations\FastshopIntegration\Log;
use App\Http\Integrations\FastshopIntegration\DataTranslator;
use Exception;

class FastshopController extends Controller
{

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
        if (($verify = self::keysVerification($request)) !== TRUE) return $verify;

        try
        {
            // Procura produto no Marketplace pelo SKU
            $product = Product::all(["sku" => "FS-" . strtoupper($sellerSkuId)])->first();

            if (!$product)
            {
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
                "message" => "Ok",
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
     * @return void
     */
    public function skuSuggestion(Request $request, $sellerId, $sellerSkuId)
    {
        // Verifição de chaves
        if (($verify = self::keysVerification($request)) !== TRUE) return $verify;

        try
        {
            // Recupera dados do produto
            $fsData    = $request->all();

            // LOG
            $date      = date("Ymd-his");
            Storage::put("fs-$sellerSkuId.$date.json", json_encode($fsData, JSON_UNESCAPED_SLASHES));

            $fsData["CategoryFullPath"] = self::getCategoriesId($fsData["CategoryFullPath"]);

            // Converte produto da FS para WC
            $wcData  = DataTranslator::translate("newProduct", $fsData);

            // VERIFICAR E TRATAR OS SkuSpecifications
            // Atualiza produto no Marketplace
            // $new    = Product::create($wcData);
            $searchSku = Product::all(["sku" => $wcData["sku"]]);

            if (!$searchSku->first())
            {
                $new = Product::create($wcData);
            }
            else
            {
                $new = Product::update($searchSku->first()->id, $wcData);
            }

            return response()->json([
                "message" => "Ok",
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
     * @return void
     */
    public function updateSku(Request $request, $sellerId, $sellerSkuId)
    {
        // Verifição de chaves
        if (($verify = self::keysVerification($request)) !== TRUE) return $verify;

        try
        {
            // Recupera dados do produto
            $fsData = $request->all();

            // LOG
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

            return response()->json([
                "message" => "Ok",
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


    // --------- METODOS ABAIXO CONSULTAM AS APIs DA FASTSHOP
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
        $headers = [
            "Accept"              => "application/json",
            "Content-Type"        => "application/json",
            "X-VTEX-API-AppKey"   => config("fastshop.hml.key"),
            "X-VTEX-API-AppToken" => config("fastshop.hml.token")
        ];

        $url     = config("fastshop.hml.url") . "/pvt/orderForms/simulation";

        $body    = json_encode([
            "items" => [[
                "id"       => $sellerSkuId,
                "quantity" => 1,
                "seller"   => "FSH"
            ]],
            "postalCode"     => "",
            "country"        => "",
            "geoCoordinates" => []
        ]);

        // Consulta produto na FS
        $response = Http::withHeaders($headers)->withBody($body, "application/json")->get($url);

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

    /**
     * Cálculo de frete
     * Consulta a Fastshop para obter informações comerciais sobre SKUs, tais como, preço, estoque, frete e prazos de entrega.
     * O "postalCode" deve ser preenchido no request e será obrigatório para o retorno das informações de logística.
     *
     * @return void
     */
    public function freightCalculation(Request $request)
    {
        $headers = [
            "Accept"              => "application/json",
            "Content-Type"        => "application/json",
            "X-VTEX-API-AppKey"   => config("fastshop.hml.key"),
            "X-VTEX-API-AppToken" => config("fastshop.hml.token")
        ];

        $url  = config("fastshop.hml.url") . "/pvt/orderForms/simulation";

        $wcData = $request -> all();

        $cep = $wcData["postalCode"];
        $aux_itens = $wcData["items"];
        $itens = [];

        foreach ($aux_itens as $item) {
            $itens[] = [
                "id"       => $item['sku'],
                "quantity" => $item['quantity'],
                "seller"   => "FSH",
            ];
        }

        $body  = json_encode([
            "items"          => $itens,
            "postalCode"     => $cep,
            "country"        => "BRA",
            "geoCoordinates" => []
        ]);

        $response = Http::withHeaders($headers)->withBody($body, "application/json")->post($url);

        if(empty($cep)) {
            return response() -> json ([
                "errorCode" => "400",
                "message"   => "request does not meet the necessary requirements"
            ],400);
        }
        elseif ($response -> successful()) {
            $wcData = $response->json();
            
        }
        else {
            return response()->json([
                "errorCode" => "500",
                "message"   => "something wrong"
            ],500);
        }
        dd($response -> json());
    }

    /**
     * Criação de pedido
     * Notifica a Fastshop para validar e processar o pedido.
     * Deve-se enviará informações como os itens contidos no carrinho, dados do perfil do cliente, dados de envio e os dados de pagamento.
     *
     * @return void
     */
    public function orderCreation()
    {
        //
    }

    /**
     * Pedido Aprovado e Pago
     * Após o pagamento ser concluído e aprovado, notifica a Fastshop avisando que este pode iniciar o processo de faturamento e entrega do pedido.
     *
     * @return void
     */
    public function orderApproved()
    {
        //
    }
    

    /**
     * Pedido Cancelado pelo Marketplace
     * Notifica a Fastshop de que o pedido foi cancelado.
     *
     * @return void
     */
    public function orderCanceledByMarketplace()
    {
        //
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

    private static function keysVerification(Request $request)
    {
        $appKey   = $request->header("x-vtex-api-appkey");
        $appToken = $request->header("x-vtex-api-apptoken");

        // Verifição de chaves
        if ($appKey != config("fastshop.hml.key") || $appToken != config("fastshop.hml.token"))
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
