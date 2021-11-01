<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Notifications\ChatGuru;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ChatGuruController extends Controller
{
    const URL        = "https://s12.chatguru.app/api/v1";
    const KEY        = "5IZM3MLVUXTII7DA8A6JHH2OCBTLESW0I1KQ73DQKLEN4TD0H93MTPTFOZ091ILS";
    const ACCOUNT_ID = "61546dbab16ea78bdf8c9416";
    const PHONE_ID   = "615471676402a34e330fa10b";

    public function index(Request $request)
    {
        $data      = $request->all()["fields"];

        $name      = $data["name"]["value"];
        $phone     = preg_replace("/\D/", "", $data["phone"]["value"]);
        $email     = $data["email"]["value"];
        $physical  = $data["physical"]["value"] ? "Sim" : "Não";
        $segment   = self::getCompanySegment($data["company_segment"]["value"]);
        $size      = self::getCompanySize($data["company_size"]["value"]);
        $objective = self::getCompanyObjective($data["objective"]["value"]);

        $dataEmail = self::getEmailMessage($name, $phone, $email, $physical, $segment["text"], $size, $objective);

        $count     = 0;

        do
        {
            // request UM e DOIS
            $res = self::sendToChatGuru($name, $phone, $segment["dialog_id"], $dataEmail);

            if ($res["code"] == "create" && $res["error"]) return;

            if ($res["code"] == "check"  && $res["error"])
            {
                if ($count == 1)
                {
                    self::sendMailNotification($dataEmail);
                    return self::success();
                    // exit();
                }

                $code = substr($phone, 0, 4);

                if (strlen($phone) == 13)
                {
                    $count++;
                    $num   = substr($phone, 5, strlen($phone));
                    $phone = $code . $num;
                }
                else if (strlen($phone) == 12)
                {
                    $count++;
                    $num   = substr($phone, 4, strlen($phone));
                    $phone = $code . "9" . $num;
                }
            }
        }
        while ($res["code"] == "check" && $res["error"]);

        // request TRÊS
        self::createNote($phone, $email, $segment["text"], $size, $objective, $physical, $dataEmail);

        return self::success();
        // exit();
    }


    /**
     * Recupera o segmento e o dialog_id
     *
     * @param integer $id
     * @return array  nome do segmento e dialog_id
     */
    private static function getCompanySegment(string $id)
    {
        $segments = [
            "0" => [
                "text"      => "OUTROS",
                "dialog_id" => "61707828175ef6239c960b16",
            ],
            "1" => [
                "text"      => "Alimentação",
                "dialog_id" => "617077b392b2831fcbc1214d",
            ],
            "2" => [
                "text"      => "Arte, Música e Design",
                "dialog_id" => "6170779abd1a09f5c03fe4cf",
            ],
            "3" => [
                "text"      => "Auto/moto",
                "dialog_id" => "6170777a26f61f8967d18a60",
            ],
            "4" => [
                "text"      => "Beleza e estética",
                "dialog_id" => "6170775f68cbb3442e177e05",
            ],
            "5" => [
                "text"      => "Esportes",
                "dialog_id" => "617077489eeadd4016ef5455",
            ],
            "6" => [
                "text"      => "Gráfica",
                "dialog_id" => "6170772092b2831fcbc1211c",
            ],
            "7" => [
                "text"      => "Head shop/Tabacaria",
                "dialog_id" => "6170767332a827d861b6fdb6",
            ],
            "8" => [
                "text"      => "Moda",
                "dialog_id" => "61707640ba0255314c7cbfeb",
            ],
            "9" => [
                "text"      => "Pet",
                "dialog_id" => "61707625f1b864c5ec4cb8ef",
            ],
            "10" => [
                "text"      => "Refeição",
                "dialog_id" => "616d5fa4f56b8ddb61aa8271",
            ],
            "11" => [
                "text"      => "Saúde e bem estar",
                "dialog_id" => "616d57dc32a827d861b669d1",
            ],
            "12" => [
                "text"      => "Serviços gerais",
                "dialog_id" => "616d5f6f7e343e60dfeb17e2",
            ],
        ];

        $id = (array_key_exists($id, $segments)) ? $id : "0";

        return $segments[$id];
    }

    /**
     * Recupera o tamanho da empresa
     *
     * @param integer $id
     * @return string
     */
    private static function getCompanySize(string $id)
    {
        $sizes = [
            "1" => "Autônomo",
            "2" => "2 a 3 colaboradores",
            "3" => "4 a 10 colaboradores",
            "4" => "11 a 50 colaboradores",
            "5" => "51 a 200 colaboradores",
            "6" => "Mais de 200 colaboradores",
        ];

        return $sizes[$id];
    }

    /**
     * Recupera o objetivo da empresa com o Triibo
     *
     * @param integer $id
     * @return string
     */
    private static function getCompanyObjective(string $id)
    {
        $objectives = [
            "1" => "Aquisição de novos clientes",
            "2" => "Fidelizar meus clientes",
            "3" => "Publicidade/divulgação nas redes sociais",
        ];

        return $objectives[$id];
    }

    private static function getFirstMessage(string $name)
    {
        $name = explode(" ", $name)[0];

        return "Olá $name! tudo bem?\nSou a *DEV TESTE* da Triibo, recebi seu formulário e a partir de agora vou te ajudar a engajar e fidelizar clientes.";
    }

    private static function getEmailMessage(string $name, string $phone, string $email, string $physical, string $segment, string $size, string $objective)
    {
        $text  = "<b>Nome</b>: $name\n";
        $text .= "<b>E-mail</b>: $email\n";
        $text .= "<b>Telefone</b>: $phone\n\n";
        $text .= "<b>Segmento da Empresa</b>: $segment\n";
        $text .= "<b>Tamanho da Empresa</b>: $size\n";
        $text .= "<b>Objetivo</b>: $objective\n";
        $text .= "<b>Estabelecimento físico</b>: $physical";

        return $text;
    }

    private static function sendToChatGuru(string $name, string $phone, string $dialog_id, string $dataEmail)
    {
        // request UM
        $res_add_chat = self::createChat($name, $phone, $dialog_id, $dataEmail);
        self::log("info", "add_chat", $res_add_chat);

        if ($res_add_chat["result"] == "error")
        {
            return [
                "code"  => "create",
                "error" => true
            ];
        }

        // request DOIS
        $res_check_chat = self::checkChat($res_add_chat["chat_add_id"], $dataEmail);

        if (!$res_check_chat)
        {
            return [
                "code"  => "check",
                "error" => true
            ];
        }

        return [
            "code"  => "added",
            "error" => false
        ];
    }

    private static function createChat(string $name, string $phone, string $dialog_id, string $dataEmail)
    {
        $res_add_chat = Http::post(
            self::URL .
            "?key=" . self::KEY .
            "&account_id=" . self::ACCOUNT_ID .
            "&phone_id=" . self::PHONE_ID .
            "&action=chat_add" .
            "&name=$name" .
            "&text=" . self::getFirstMessage($name) .
            "&chat_number=$phone" .
            "&dialog_id=$dialog_id"
        );

        if ($res_add_chat->failed() ||
            $res_add_chat["code"]   == 400 ||
            $res_add_chat["result"] == "error")
        {
            self::log("error", "add_chat", $res_add_chat->json());
            self::sendMailNotification($dataEmail);
        }

        return $res_add_chat->json();
    }

    private static function checkChat(string $chat_id, string $dataEmail)
    {
        sleep(2.5);
        $res_check_chat = Http::post(
            self::URL .
            "?key=" . self::KEY .
            "&account_id=" . self::ACCOUNT_ID .
            "&phone_id=" . self::PHONE_ID .
            "&action=chat_add_status" .
            "&chat_add_id=$chat_id"
        );

        if ($res_check_chat->failed() ||
            $res_check_chat["code"]   == 400 ||
            $res_check_chat["result"] == "error" ||
            $res_check_chat["chat_add_status"] == "error")
        {
            self::log("error", "check_add_chat", $res_check_chat->json());
            return false;
        }
        else if ($res_check_chat["chat_add_status"] == "fetched" ||
                 $res_check_chat["chat_add_status"] == "pending")
        {
            return self::checkChat($chat_id, $dataEmail);
        }
        else
        {
            self::log("info", "check_add_chat", $res_check_chat->json());
            return true;
        }
    }

    private static function createNote(string $phone, string $email, string $segment, string $size, string $objective, string $physical, string $dataEmail)
    {
        // request TRÊS
        sleep(1);
        $note_text  = "E-mail: $email\n\n";
        $note_text .= "Segmento da Empresa: $segment\n\n";
        $note_text .= "Tamanho da Empresa: $size\n\n";
        $note_text .= "Objetivo: $objective\n\n";
        $note_text .= "Estabelecimento físico: $physical";

        $res_add_note = Http::post(
            self::URL .
            "?key=" . self::KEY .
            "&account_id=" . self::ACCOUNT_ID .
            "&phone_id=" . self::PHONE_ID .
            "&action=note_add" .
            "&chat_number=$phone" .
            "&note_text=$note_text"
        );

        if ($res_add_note->failed() ||
            $res_add_note["code"]   == 400 ||
            $res_add_note["result"] == "error")
        {
            self::log("error", "add_note", $res_add_note->json());
            self::sendMailNotification($dataEmail);
            return false;
        }

        self::log("info", "add_note", $res_add_note->json());

        return true;
    }

    private static function log(string $method, string $title, array $data)
    {
        Log::channel("triibo")->{$method}("ChatGuru", [ $title => $data ]);
    }

    private static function sendMailNotification(string $params)
    {
        $mail = [
            "subject"    => "Formulário de contato",
            "title"      => "Os dados abaixo não puderam ser enviados para o ChatGuru",
            "params"     => $params,
        ];

        // $users = User::where("email", "contato@triibo.com.br")
        //              ->orWhere("email", "atendimento@triibo.com.br")
        //              ->orWhere("email", "mateus.costa@triibo.com.br")->get();
        // $users[0]->notify(new ChatGuru($mail));
        // $users[1]->notify(new ChatGuru($mail));
        // $users[2]->notify(new ChatGuru($mail));

        // $users = User::where("email", "mateus.costa@triibo.com.br")->get();
        $users = User::where("email", "pedro.lobato@triibo.com.br")
                     ->orWhere("email", "mateus.costa@triibo.com.br")->get();
        $users[0]->notify(new ChatGuru($mail));
        $users[1]->notify(new ChatGuru($mail));
    }

    private static function success()
    {
        return response()->json([
            "message" => "Apenas teste",
            "code"    => 200
        ], 200);
    }
}
