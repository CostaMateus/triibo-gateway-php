<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ChatGuru;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\{JsonResponse, Request};

class ChatGuruController extends Controller
{
    /**
     * Define a class constant for the log channel.
     *
     * @var string
     */
    private const LOG_CHANNEL = "triibo";
    private const URL = "https://s12.chatguru.app/api/v1";
    private const KEY = "5IZM3MLVUXTII7DA8A6JHH2OCBTLESW0I1KQ73DQKLEN4TD0H93MTPTFOZ091ILS";
    private const ACCOUNT_ID = "61546dbab16ea78bdf8c9416";
    private const PHONE_ID = "615471676402a34e330fa10b";

    /**
     * Undocumented function
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function index( Request $request )
    {
        if ( ! self::checkDataRequest( $request->all() ) )
            return self::error( $request->all() );

        $data = $request->all()[ "fields" ];
        $meta = $request->all()[ "meta"   ];

        $phone = preg_replace( "/\D/", "", $data[ "phone" ][ "value" ] );
        $name = $data[ "name"     ][ "value" ];
        $email = $data[ "email"    ][ "value" ];
        $physical = $data[ "physical" ][ "value" ] ? "Sim" : "Não";

        $segment = self::getCompanySegment( $data[ "company_segment" ][ "value" ] );
        $size = self::getCompanySize( $data[ "company_size"    ][ "value" ] );

        $objective = ""; // self::getCompanyObjective( $data[ "objective" ][ "value" ] );

        $data_email = self::getEmailMessage( $name, $phone, $email, $physical, $segment[ "text" ], $size, $objective, $meta );

        $count = 0;

        if ( ! in_array( mb_strlen( $phone ), [12, 13] ) )
        {
            self::sendMailNotification( $data_email );

            return self::success();
        }

        do
        {
            // request UM e DOIS
            $response = self::sendToChatGuru( $name, $phone, $segment[ "dialog_id" ], $data_email );

            if ( $response[ "code" ] === "create" && $response[ "error" ] )
            {
                self::sendMailNotification( $data_email );

                return self::success();
            }

            if ( $response[ "code" ] === "check" && $response[ "error" ] )
            {
                if ( $count === 1 )
                {
                    self::sendMailNotification( $data_email );

                    return self::success();
                }

                $code = mb_substr( $phone, 0, 4 );

                if ( mb_strlen( $phone ) === 13 )
                {
                    $count++;
                    $num = mb_substr( $phone, 5, mb_strlen( $phone ) );
                    $phone = $code.$num;
                }
                elseif ( mb_strlen( $phone ) === 12 )
                {
                    $count++;
                    $num = mb_substr( $phone, 4, mb_strlen( $phone ) );
                    $phone = $code."9".$num;
                }
            }
        }
        while ( $response[ "code" ] === "check" && $response[ "error" ] );

        // request TRÊS
        self::createNote(
            $phone,
            $email,
            $segment[ "text" ],
            $size,
            $objective,
            $physical,
            $data_email,
            $meta[ "date"     ][ "value" ],
            $meta[ "time"     ][ "value" ],
            $meta[ "page_url" ][ "value" ]
        );

        return self::success();
    }

    /**
     * Recupera o segmento e o dialog_id
     *
     * @param int $id
     *
     * @return array Nome do segmento e dialog_id
     */
    private static function getCompanySegment( string $id ) : array
    {
        $id = explode( "-", $id )[ 0 ];

        $segments = [
            "0" => [
                "text" => "OUTROS",
                "dialog_id" => "61707828175ef6239c960b16",
            ],
            "1" => [
                "text" => "Alimentação",
                "dialog_id" => "617077b392b2831fcbc1214d",
            ],
            "2" => [
                "text" => "Arte, Música e Design",
                "dialog_id" => "6170779abd1a09f5c03fe4cf",
            ],
            "3" => [
                "text" => "Auto/moto",
                "dialog_id" => "6170777a26f61f8967d18a60",
            ],
            "4" => [
                "text" => "Beleza e estética",
                "dialog_id" => "6170775f68cbb3442e177e05",
            ],
            "5" => [
                "text" => "Esportes",
                "dialog_id" => "617077489eeadd4016ef5455",
            ],
            "6" => [
                "text" => "Gráfica",
                "dialog_id" => "6170772092b2831fcbc1211c",
            ],
            "7" => [
                "text" => "Head shop/Tabacaria",
                "dialog_id" => "6170767332a827d861b6fdb6",
            ],
            "8" => [
                "text" => "Moda",
                "dialog_id" => "61707640ba0255314c7cbfeb",
            ],
            "9" => [
                "text" => "Pet",
                "dialog_id" => "61707625f1b864c5ec4cb8ef",
            ],
            "10" => [
                "text" => "Refeição",
                "dialog_id" => "616d5fa4f56b8ddb61aa8271",
            ],
            "11" => [
                "text" => "Saúde e bem estar",
                "dialog_id" => "616d57dc32a827d861b669d1",
            ],
            "12" => [
                "text" => "Serviços gerais",
                "dialog_id" => "616d5f6f7e343e60dfeb17e2",
            ],
        ];

        $id = ( array_key_exists( $id, $segments ) ) ? $id : "0";

        return $segments[ $id ];
    }

    /**
     * Recupera o tamanho da empresa
     *
     * @param int $id
     *
     * @return string
     */
    private static function getCompanySize( string $id ) : string
    {
        $id = explode( "-", $id )[ 0 ];

        $sizes = [
            "1" => "Autônomo",
            "2" => "2 a 3 colaboradores",
            "3" => "4 a 10 colaboradores",
            "4" => "11 a 50 colaboradores",
            "5" => "51 a 200 colaboradores",
            "6" => "Mais de 200 colaboradores",
        ];

        return $sizes[ $id ];
    }

    /**
     * Recupera o objetivo da empresa com o Triibo
     *
     * @param int $id
     *
     * @return string
     */
    private static function getCompanyObjective( string $id ) : string
    {
        $id = explode( "-", $id )[ 0 ];

        $objectives = [
            "1" => "Aquisição de novos clientes",
            "2" => "Fidelizar meus clientes",
            "3" => "Publicidade/divulgação nas redes sociais",
        ];

        return $objectives[ $id ];
    }

    /**
     * Undocumented function
     *
     * @param string $name
     *
     * @return string
     */
    private static function getFirstMessage( string $name ) : string
    {
        $name = explode( " ", $name )[ 0 ];

        return "Olá {$name}! tudo bem?";
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $phone
     * @param string $email
     * @param string $physical
     * @param string $segment
     * @param string $size
     * @param string $objective
     * @param array  $meta
     *
     * @return string
     */
    private static function getEmailMessage( string $name, string $phone, string $email, string $physical, string $segment, string $size, string $objective, array $meta ) : string
    {
        $text = "<b>Nome</b>: {$name} \n";
        $text .= "<b>E-mail</b>: {$email} \n";
        $text .= "<b>Telefone</b>: {$phone} \n\n";
        $text .= "<b>Segmento da Empresa</b>: {$segment} \n";
        $text .= "<b>Tamanho da Empresa</b>: {$size} \n";
        $text .= "<b>Estabelecimento físico</b>: {$physical} \n\n";
        // $text .= "<b>Objetivo</b>: $objective \n\n";

        $date = $meta[ "date"       ][ "value" ];
        $time = $meta[ "time"       ][ "value" ];
        $url = $meta[ "page_url"   ][ "value" ];
        $ip = $meta[ "remote_ip"  ][ "value" ];
        $user = $meta[ "user_agent" ][ "value" ];

        $text .= "<b>Data</b>: {$date} \n";
        $text .= "<b>Hora</b>: {$time} \n";
        $text .= "<b>Página</b>: {$url} \n";
        $text .= "<b>IP</b>: {$ip} \n";
        $text .= "<b>User Agent</b>: {$user}";

        return $text;
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $phone
     * @param string $dialog_id
     * @param string $data_email
     *
     * @return array
     */
    private static function sendToChatGuru( string $name, string $phone, string $dialog_id, string $data_email ) : array
    {
        // request UM
        $res_add_chat = self::createChat( $name, $phone, $dialog_id, $data_email );

        log_message( channel: self::LOG_CHANNEL, message: "ChatGuru: Add chat | {$res_add_chat}" );

        if ( $res_add_chat[ "result" ] === "error" )
        {
            return [
                "code" => "create",
                "error" => true
            ];
        }

        // request DOIS
        $res_check_chat = self::checkChat( $res_add_chat[ "chat_add_id" ], $data_email );

        if ( ! $res_check_chat )
        {
            return [
                "code" => "check",
                "error" => true
            ];
        }

        return [
            "code" => "added",
            "error" => false
        ];
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $phone
     * @param string $dialog_id
     * @param string $data_email
     *
     * @return string
     */
    private static function createChat( string $name, string $phone, string $dialog_id, string $data_email ) : string
    {
        $res_add_chat = Http::post(
            self::URL
            ."?key=".self::KEY
            ."&account_id=".self::ACCOUNT_ID
            ."&phone_id=".self::PHONE_ID
            ."&action=chat_add"
            ."&name={$name}"
            ."&text=".self::getFirstMessage( $name )
            ."&chat_number={$phone}"
            ."&dialog_id={$dialog_id}"
        );

        if ( $res_add_chat->failed()
             || $res_add_chat[ "code"   ] === 400
             || $res_add_chat[ "result" ] === "error" )
        {
            log_message( channel: self::LOG_CHANNEL, level: "error", message: "ChatGuru: Add chat | ".$res_add_chat->json() );

            self::sendMailNotification( $data_email );
        }

        return $res_add_chat->json();
    }

    /**
     * Undocumented function
     *
     * @param string $chat_id
     * @param string $data_email
     *
     * @return bool
     */
    private static function checkChat( string $chat_id, string $data_email ) : bool
    {
        sleep( 2.5 );

        $res_check_chat = Http::post(
            self::URL
            ."?key=".self::KEY
            ."&account_id=".self::ACCOUNT_ID
            ."&phone_id=".self::PHONE_ID
            ."&action=chat_add_status"
            ."&chat_add_id={$chat_id}"
        );

        if ( $res_check_chat->failed()
             || $res_check_chat[ "code"            ] === 400
             || $res_check_chat[ "result"          ] === "error"
             || $res_check_chat[ "chat_add_status" ] === "error" )
        {
            log_message( channel: self::LOG_CHANNEL, level: "error", message: "ChatGuru: Check add chat | ".$res_check_chat->json() );

            return false;
        }
        elseif ( in_array( $res_check_chat[ "chat_add_status" ], ["fetched", "pending"] ) )
        {
            return self::checkChat( $chat_id, $data_email );
        }

        log_message( channel: self::LOG_CHANNEL, message: "ChatGuru: Check add chat | ".$res_check_chat->json() );

        return true;

    }

    /**
     * Undocumented function
     *
     * @param string $phone
     * @param string $email
     * @param string $segment
     * @param string $size
     * @param string $objective
     * @param string $physical
     * @param string $data_email
     * @param string $data
     * @param string $hora
     * @param string $url
     * @param int    $count
     *
     * @return bool
     */
    private static function createNote( string $phone, string $email, string $segment, string $size, string $objective, string $physical, string $data_email, string $data, string $hora, string $url, int $count = 0 ) : bool
    {
        // request TRÊS
        sleep( 2.5 );

        $note_text = "E-mail: {$email}\n\n";
        $note_text .= "Segmento da Empresa: {$segment}\n\n";
        $note_text .= "Tamanho da Empresa: {$size}\n\n";
        // $note_text    .= "Objetivo: $objective\n\n";
        $note_text .= "Estabelecimento físico: {$physical}\n\n";
        $note_text .= "Data: {$data}\n\n";
        $note_text .= "Hora: {$hora}\n\n";
        $note_text .= "Página: {$url}";

        $res_add_note = Http::post(
            self::URL
            ."?key=".self::KEY
            ."&account_id=".self::ACCOUNT_ID
            ."&phone_id=".self::PHONE_ID
            ."&action=note_add"
            ."&chat_number={$phone}"
            ."&note_text={$note_text}"
        );

        if ( $res_add_note->failed()
             || $res_add_note[ "code"   ] === 400
             || $res_add_note[ "result" ] === "error" )
        {
            if ( $count === 1 )
            {
                log_message( channel: self::LOG_CHANNEL, level: "error", message: "ChatGuru: Add note | ".$res_add_note->json() );

                self::sendMailNotification( $data_email, "Chat criado com sucesso. \nFalha na criação da anotação." );

                return false;
            }

            $code = mb_substr( $phone, 0, 4 );

            if ( mb_strlen( $phone ) === 13 )
            {
                $count++;
                $num = mb_substr( $phone, 5, mb_strlen( $phone ) );
                $phone = $code.$num;
            }
            elseif ( mb_strlen( $phone ) === 12 )
            {
                $count++;
                $num = mb_substr( $phone, 4, mb_strlen( $phone ) );
                $phone = $code."9".$num;
            }

            return self::createNote( $phone, $email, $segment, $size, $objective, $physical, $data_email, $data, $hora, $url, $count );
        }

        log_message( channel: self::LOG_CHANNEL, message: "ChatGuru: Add note | ".$res_add_note->json() );

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string      $params
     * @param null|string $title
     *
     * @return void
     */
    private static function sendMailNotification( string $params, ?string $title = null ) : void
    {
        $mail = [
            "subject" => "Formulário de contato",
            "title" => $title ?? "Os dados abaixo não puderam ser enviados para o ChatGuru",
            "params" => $params,
            "error" => false
        ];

        $users = User::where( "email", "atendimento@triibo.com.br" )
            ->orWhere( "email", "mateus.costa@triibo.com.br" )
            ->get();

        foreach ( $users as $user )
            $user->notify( new ChatGuru( $mail ) );
    }

    /**
     * Undocumented function
     *
     * @param array $data
     *
     * @return bool
     */
    private static function checkDataRequest( array $data ) : bool
    {
        if ( ! isset( $data[ "fields" ] ) )
            return false;

        if ( ! isset( $data[ "meta"   ] ) )
            return false;

        $data = $data[ "fields" ];

        $error = [
            "name" => false,
            "phone" => false,
            "email" => false,
            "company_segment" => false,
            "company_size" => false,
            "physical" => false,
            // "objective"       => false,
        ];

        foreach ( $error as $key => &$value )
            if ( ! isset( $data[ $key ] ) )
                $value = true;

        if ( in_array( true, $error ) )
        {
            log_message( channel: self::LOG_CHANNEL, level: "error", message: "ChatGuru: Request missing data | ".json_encode( $error ) );

            $mail = [
                "subject" => "Formulário de contato",
                "title" => "Erro na configuração do formulário",
                "params" => $error,
                "error" => true,
            ];

            $user = User::firstWhere( "email", "mateus.costa@triibo.com.br" );
            $user->notify( new ChatGuru( $mail ) );

            return false;
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @return JsonResponse
     */
    private static function success() : JsonResponse
    {
        return response()->json( [
            "message" => "Success",
            "code" => 200
        ], 200 );
    }

    /**
     * Undocumented function
     *
     * @param array $data
     *
     * @return JsonResponse
     */
    private static function error( array $data ) : JsonResponse
    {
        log_message( channel: self::LOG_CHANNEL, level: "error", message: "ChatGuru: Form | ".json_encode( $data ) );

        return response()->json( [
            "message" => "Fail",
            "code" => 500
        ], 500 );
    }
}
