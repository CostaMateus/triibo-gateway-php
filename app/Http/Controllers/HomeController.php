<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Illuminate\Http\Request;
use Devio\Pipedrive\Pipedrive;
use Ramsey\Collection\Collection;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Triibo\Mautic\Models\MauticConsumer;
use App\Notifications\WebhookMarketplace;
use App\Http\Integrations\MauticIntegration;

// use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    const APIKEY = "7f3a767e18d06afd45d8a09402ee687951953686729d7006a88c59c5195d7753";
    const URL    = "https://sandbox.asaas.com/api/v3/";

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware("auth");
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view("home");
    }

    public function atualizaEmailComEspacoEmBranco(Request $request)
    {
        $token     = config("pipedrive.token");
        $pipedrive = new Pipedrive($token);
        $arr       = [];
        for ($i = 1; $i <= 600; $i++)
        {
            try
            {
                $person = $pipedrive->persons->find($i);
                $found = true;
            }
            catch (Exception $e)
            {
                $found = false;
            }
            if ($found)
            {
                $data  = $person->getData();
                $email = $data->email[0]->value;
                if (preg_match("/\s/",$email))
                {
                    $arr[] = $email;
                    $new   = $pipedrive->persons->update($i, ["email" => trim($email)]);
                    $arr[] = $new->getData()->email[0]->value;
                }
            }
            else
            {
                continue;
            }
        }
        dd($arr);
    }

    public function teste(Request $request)
    {
        $header   = [
            "Content-Type" => "application/json",
            "access_token" => self::APIKEY,
        ];

        $customer = [
            "name"                 => "Mateus Costa",
            "email"                => "Mateus.costa@triibo.com.br",
            "phone"                => "11949289507",
            "mobilePhone"          => "11949289507",
            "cpfCnpj"              => "02681487242",
            "postalCode"           => "01218-011",
            "address"              => "Al. Eduardo Prado",
            "addressNumber"        => "170",
            "complement"           => "125A",
            "province"             => "Campos Eliseos",
            "notificationDisabled" => false,
            "creditCard"           => [
                "holderName"  => "Mateus Costa",
                "number"      => "5162306219378829",
                "expiryMonth" => "10",
                "expiryYear"  => "2022",
                "ccv"         => "318"
            ]
        ];

        $response = Http::withHeaders($header)->get(self::URL . "/customers?cpfCnpj=" . $customer["cpfCnpj"]);

        $data     = $response->json();

        // cpf não cadastrado
        if ($data["data"] == [] && empty($data["data"]))
        {
            $response       = Http::withHeaders($header)->post(self::URL . "/customers", $customer);
            $data           = $response->json();
            $customer["id"] = $data["id"];
        }
        else
        {
            $customer["id"] = $data["data"][0]["id"];
        }

        // $cobranca = self::gerarCobranca($customer, "CREDIT_CARD", 111, 2);
        $assinatura = self::gerarAssinatura($customer, "CREDIT_CARD", "MONTHLY", 19.90, 2);

        dd($data, $assinatura);
    }

    /**
     * Gerar cobrança no boleto e no cartão de credito
     *
     * @param array $customer
     * @param string $tipo
     * @param double $total
     * @param int $parcelas
     * @return void
     */
    private static function gerarCobranca($customer, $tipo, $total, $parcelas = NULL)
    {
        $header   = [
            "Content-Type" => "application/json",
            "access_token" => self::APIKEY,
        ];

        $payment = [
            "customer"          => $customer["id"],
            "billingType"       => $tipo,   // BOLETO, CREDIT_CARD, UNDEFINED
            "value"             => $total,
            "dueDate"           => "2021-07-28",
            "description"       => "Pedido 056984", // ID marketplace
            "externalReference" => "056984",        // ID marketplace
            "postalService"     => false,
            // "installmentCount"  => 5,
            // "installmentValue"  => 100,
        ];

        if ($tipo == "CREDIT_CARD")
        {
            $parcelas = $parcelas ?? 3;
            $payment["installmentCount"]     = $parcelas;
            $payment["installmentValue"]     = ($total / $parcelas);
            $payment["creditCard"]           = $customer["creditCard"];
            $payment["creditCardHolderInfo"] = [
                "name"              => $customer["name"],          //"Marcelo Henrique Almeida",
                "email"             => $customer["email"],         //"marcelo.almeida@gmail.com",
                "cpfCnpj"           => $customer["cpfCnpj"],       //"24971563792",
                "postalCode"        => $customer["postalCode"],    //"89223-005",
                "addressNumber"     => $customer["addressNumber"], //"277",
                "addressComplement" => $customer["complement"],    //NULL,
                "phone"             => $customer["phone"],         //"4738010919",
                "mobilePhone"       => $customer["mobilePhone"],   //"47998781877"
            ];
        }

        $response = Http::withHeaders($header)->post(self::URL . "/payments", $payment);

        return $response->json();
    }

    /**
     * Gera uma assinatura
     *
     * @param array $customer
     * @param string $tipo
     * @param string $ciclo
     * @param double $total
     * @param int $parcelas
     * @return void
     */
    private static function gerarAssinatura($customer, $tipo, $ciclo, $total, $parcelas)
    {
        $header   = [
            "Content-Type" => "application/json",
            "access_token" => self::APIKEY,
        ];

        $payment = [
            "customer"          => $customer["id"],
            "billingType"       => $tipo,           // BOLETO, CREDIT_CARD, UNDEFINED
            "value"             => $total,
            "nextDueDate"       => "2021-07-27",
            "endDate"           => "2022-01-26",
            "interest"          => [
                "value" => 2,
            ],
            "fine"              => [
                "value" => 1,
            ],
            "cycle"             => $ciclo,          // WEEKLY semana, BIWEEKLY quizenal, MONTHLY mensal, QUARTERLY trimestral, SEMIANNUALLY semestral, YEARLY anual,
            "description"       => "Pedido 056984", // ID marketplace
            "externalReference" => "056984",        // ID marketplace
            "maxPayments"       => $parcelas,
        ];

        if ($tipo == "CREDIT_CARD")
        {
            $payment["installmentCount"]     = $parcelas;
            $payment["installmentValue"]     = ($total / $parcelas);
            $payment["creditCard"]           = $customer["creditCard"];
            $payment["creditCardHolderInfo"] = [
                "name"              => $customer["name"],          //"Marcelo Henrique Almeida",
                "email"             => $customer["email"],         //"marcelo.almeida@gmail.com",
                "cpfCnpj"           => $customer["cpfCnpj"],       //"24971563792",
                "postalCode"        => $customer["postalCode"],    //"89223-005",
                "addressNumber"     => $customer["addressNumber"], //"277",
                "addressComplement" => $customer["complement"],    //NULL,
                "phone"             => $customer["phone"],         //"4738010919",
                "mobilePhone"       => $customer["mobilePhone"],   //"47998781877"
            ];
            // $payment["creditCardToken"]      = "c9d92c1e-7667-491f-be38-6e0ccc2fc1bd";
        }

        $response = Http::withHeaders($header)->post(self::URL . "/subscriptions", $payment);

        return $response->json();
    }
}
