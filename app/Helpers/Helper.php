<?php

namespace App\Helpers;

use Exception;
use App\Models\User;
use DateTimeImmutable;
use Devio\Pipedrive\Pipedrive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Notifications\ScheduleFailure;

class Helper
{

    /**
     * For recursive diff of multiple arrays
     *
     * @param array $array
     * @param array ...$arrays
     * @return array
     */
    public static function array_diff_assoc_recursive(array $array, array ...$arrays)
    {
        $func = function ($array1, $array2) use (&$func)
        {
            $difference = [];
            foreach ($array1 as $key => $value)
            {
                if (is_array($value))
                {
                    if (!isset($array2[$key]) || !is_array($array2[$key]))
                    {
                        $difference[$key] = $value;
                    }
                    else
                    {
                        $new_diff = $func($value, $array2[$key]);
                        if (!empty($new_diff))
                        {
                            $difference[$key] = $new_diff;
                        }
                    }
                }
                else
                {
                    if (!array_key_exists($key, $array2) || $array2[$key] !== $value)
                    {
                        $difference[$key] = $value;
                    }
                }
            }

            return $difference;
        };

        $diffs = $array;

        foreach ($arrays as $_array)
        {
            $diffs = $func($diffs, $_array);
        }

        return $diffs;
    }

    public static function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);

        foreach ($args as $n => $field)
        {
            if (is_string($field))
            {
                $tmp = array();

                foreach ($data as $key => $row)
                {
                    $tmp[$key] = $row[$field];
                }

                $args[$n] = $tmp;
            }
        }

        $args[] = &$data;

        call_user_func_array('array_multisort', $args);

        return array_pop($args);
    }

    /**
     * Registra log
     *
     * @param string  $class  Classe do objeto
     * @param string  $msg    Mensegame principal
     * @param integer $mautic ID do objeto no Mautic
     * @param integer $pipe   ID do objeto no Pipedrive
     * @return void
     */
    public static function log(string $class, string $msg, int $mautic, int $pipe)
    {
        Log::channel("marketplace")->info($class, [
            "message"   => $msg,
            "mautic_id" => $mautic??NULL,
            "pd_id"     => $pipe??NULL
        ]);
    }

    /**
     * Traduz o status/label do Deal
     *
     * @param [type]  $label Valor do campo
     * @param [type]  $id    ID do stage
     * @param boolean $type  TRUE para status, FALSE para label
     * @return void
     */
    public static function statusLabelDeal($label, $id, bool $type = true)
    {
        if (!$label || !$id) return 0;

        $resp = 0;

        if ($type)
        {
            switch ($label) {
                case "open":
                    $resp = "$id:A";
                break;

                case "won":
                    $resp = "$id:G";
                break;

                case "lost":
                    $resp = "$id:P";
                break;

                case "deleted":
                    $resp = "$id:E";
                break;

                default:
                    $resp = 0;
                break;
            }
        }
        else
        {
            $labels = self::getLabelsDeal();

            foreach ($labels as $lbl)
            {
                if ($lbl["id"] == (int) $label)
                {
                    $resp = "$id:" . $lbl["label"];
                    break;
                }
            }
        }

        return $resp;
    }

    /**
     * Auxilio para função statusLabelDeal()
     *
     * @return array
     */
    private static function getLabelsDeal()
    {
        // Etiqueta / id interno: 12463

        $token   = config('pipedrive.token');
        $pipeApi = new Pipedrive($token);
        $labels  = $pipeApi->DealFields->find(12463)->getData();

        foreach ($labels->options as $t)
        {
            if (in_array(mb_strtolower($t->label), ["em espera", "em degustação"]))
            {
                $label    = explode(" ", $t->label)[1];
                $t->label = ucfirst($label);
            }

            $resp[] = [
                "id"    => $t->id,
                "label" => $t->label[0]
            ];
        }

        return $resp;
    }

    /**
     * Recupera o campo segundo o $idField passado.
     * Apenas campos select / com options

     * @param integer $idField    ID do campo
     * @param integer $idSelected ID da opção selecionada
     * @return string label do campo
     */
    public static function getOrgCustomField(int $idField, int $idSelected)
    {
        // Fonte do Lead / id interno: 4028
        // Vendedor resp / id interno: 4032

        if ($idSelected > 0)
        {
            $token   = config('pipedrive.token');
            $pipeApi = new Pipedrive($token);
            $opts    = $pipeApi->OrganizationFields->find($idField)->getData()->options;

            foreach($opts as $opt)
            {
                if ($opt->id == $idSelected) return $opt->label;
            }
        }

        return " ";
    }

    /**
     * Retira acentos e caracteres especiais
     *
     * @param string $text
     * @return string
     */
    private static function cleanString($text)
    {
        $utf8 = array(
            '/[áàâãªä]/u' => 'a',
            '/[ÁÀÂÃÄ]/u'  => 'A',
            '/[ÍÌÎÏ]/u'   => 'I',
            '/[íìîï]/u'   => 'i',
            '/[éèêë]/u'   => 'e',
            '/[ÉÈÊË]/u'   => 'E',
            '/[óòôõºö]/u' => 'o',
            '/[ÓÒÔÕÖ]/u'  => 'O',
            '/[úùûü]/u'   => 'u',
            '/[ÚÙÛÜ]/u'   => 'U',
            '/ç/'         => 'c',
            '/Ç/'         => 'C',
            '/ñ/'         => 'n',
            '/Ñ/'         => 'N',
            '/–/'         => '-',
            '/[’‘‹›‚]/u'  => ' ',
            '/[“”«»„]/u'  => ' ',
            '/ /'         => ' ',
        );

        return preg_replace(array_keys($utf8), array_values($utf8), $text);
    }

    /**
     * Substitui espaços e dois ou mais traços com traços
     *
     * @param string $string
     * @return string
     */
    public static function hyphenize($string)
    {
        $str = strtolower(
            preg_replace(
                array('#[\\s-]+#', '#[^A-Za-z0-9. -]+#'),
                array('-', ''),
                self::cleanString(urldecode($string))
            )
        );

        return preg_replace('/-+/', '-', $str);
    }

    /**
     * Insere o ponto decimal
     * Ex: 100 = 1.00, 150 = 1.50
     *
     * @param string $number
     * @return string
     */
    public static function insertDecimal(string $number)
    {
        return substr_replace($number, ".", -2, 0);
    }

    /**
     * Formata o valor com duas casas decimais
     * Ex: 1 = 0.01, 15 = 0.15
     *
     * @param integer $weight
     * @return string
     */
    public static function convertWeight(int $weight)
    {
        $weight = (strlen($weight) == 2) ? "0" . $weight : ((strlen($weight) == 1) ? "00" . $weight : $weight);

        return self::insertDecimal($weight);
    }

    /**
     * Formata o valor com duas casas decimais
     * Ex: 1 = 0.01, 15 = 0.15
     *
     * @param integer $price
     * @return string
     */
    public static function convertPrice(int $price)
    {
        return self::convertWeight($price);
    }

    /**
     * Converte o tempo de entrega da FS para o WC
     *
     * @param string $time
     * @return string
     */
    public static function convertDeliveryTime(string $time)
    {
        if (strpos($time, "bd") !== false)
        {
            return explode("bd", $time)[0];
        }
        else
        {
            return "1";
        }
    }

    /**
     * Converte data de entrega do pedido, FS para o WC
     *
     * @param string $time
     * @return string
     */
    public static function convertDeliveryDate(string $time)
    {
        $time = explode("T", $time);

        $hour = $time[1];
        $date = implode("/", array_reverse(explode("-", $time[0])));

        return "$date às $hour";
    }

    public static function validateImageUrl(string $url)
    {
        if (isset(explode(".jpg", $url)[1]))
            return true;
        else if (isset(explode(".jpeg", $url)[1]))
            return true;
        else if (isset(explode(".png", $url)[1]))
            return true;
        else
            return false;
    }

    public static function treatExceptionMsgFS( string $message )
    {
        preg_match("/\[.*?\]/", $message, $matches);
        return ( empty( $matches ) ) ? null : $matches[ 0 ];
    }

    public static function convertDateSS( string $date )
    {
        // 18/11/2022 23:59:00
        $date      = explode( " ",  $date      );
        $date[ 0 ] = explode( "/",  $date[ 0 ] );
        $date[ 0 ] = array_reverse( $date[ 0 ] );
        $date[ 0 ] = implode( "-",  $date[ 0 ] );
        $date      = implode( " ",  $date );

        return $date;
    }

    /**
     * Converte data de 01/01/2021 para 2021-01-01
     * Ou converte data de 2021-01-01 para 01/01/2021
     *
     * @param string $date
     * @param boolean $r
     *
     * @return string $date
     */
    public static function convertDate( string $date, bool $reverse = false )
    {
        $e = ( !$reverse ) ? "/" : "-";
        $i = ( !$reverse ) ? "-" : "/";

        $date = explode( $e, $date );

        $date = array_reverse( $date );
        $date = implode( $i, $date );

        return $date;
    }

    public static function usort( string $key, array &$data )
    {
        usort( $data, function( $a, $b ) use ( $key ) {
            return $a[ $key ] <=> $b[ $key ];
        } );
    }

    public static function slugify( string $text, string $divider = "-" )
    {
        // replace non letter or digits by divider
        $text = preg_replace( "~[^\pL\d]+~u", $divider, $text );

        // transliterate
        $text = iconv( "utf-8", "us-ascii//TRANSLIT", $text );

        // remove unwanted characters
        $text = preg_replace( "~[^-\w]+~", "", $text );

        // trim
        $text = trim( $text, $divider );

        // remove duplicate divider
        $text = preg_replace( "~-+~", $divider, $text );

        // lowercase
        $text = strtolower( $text );

        return $text;
    }

    public static function getSchedulesOperation( bool $operation = true )
    {
        $response = [];

        if ( $operation )
        {
            // funcionamento
            foreach ( range( 1, 7 ) as $key )
            {
                $response[] = [
                    "ativo"         => true,
                    "horarioFim"    => "23:59",
                    "horarioInicio" => "00:00"
                ];
            }
        }
        else
        {
            // horarios
            foreach ( range( 1, 7 ) as $key )
            {
                $response[] = [
                    "tipo"   => "aberto",
                    "turnos" => [ [
                        "fim"    => "23:59",
                        "inicio" => "00:00"
                    ] ]
                ];
            }
        }

        return $response;
    }

    public static function sendMailNotification( $blade, $method, $catch, $e )
    {
        $env      = config( "app.env" ) == "local" ? "HML" : "PRD";
        $subjects = [
            "awin"        => "{$env} - Integração Awin",
            "cofry"       => "{$env} - Integração Cofry",
            "socialsoul"  => "{$env} - Integração SocialSoul",
            "marketplace" => "{$env} - Integração Marketplace",
        ];

        $mail     = [
            "subject"    => $subjects[ $blade ],
            "title"      => "Erro no schedule: ",
            "method"     => $method,
            "catch"      => $catch,
            "stacktrace" => $e,
        ];

        $user     = User::firstWhere( "email", "mateus.costa@triibo.com.br" );

        $user->notify( new ScheduleFailure( $blade, $mail ) );
    }

    public static function parseUrl( string $url )
    {
        $parse  = parse_url( $url, PHP_URL_HOST );
        preg_match( "/[a-z0-9\-]{1,63}\.[a-z\.]{2,6}$/", $parse, $domain );

        $domain = reset( $domain );
        $domain = str_replace( "www.", "", $domain );

        return $domain;
    }

    public static function getCashback( $data )
    {
        $min      = ( isset( $data[ "min" ] ) ) ? ( float ) $data[ "min" ] : 0;
        $max      = ( isset( $data[ "max" ] ) ) ? ( float ) $data[ "max" ] : 0;

        $media    = ( $max + $min ) / 2;
        $half     = $media / 2;

        if ( $half >= $min )
        {
            $cashback = ( float ) $min * 0.8;
            $cashback = ( $cashback < 1 ) ? 1.0 : $cashback;
        }
        else
        {
            $cashback = ( float ) $half;
        }

        $cashback = number_format( $cashback, 1, ",", "" );

        return $cashback;
    }

    public static function prepareDataFs( $array, $field )
    {
        echo "Formatting SKUs to query in FS\n";

        $items  = [];

        foreach ( $array as $sku )
        {
            $id      = ( strpos( $sku[ $field ], "FS-" ) === false )
                        ? $sku[ $field ]
                        : substr( $sku[ $field ], 3 );

            $items[] = [
                "id"       => $id,
                "quantity" => 1,
                "seller"   => "FSH"
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => "",
            "country"        => "",
            "geoCoordinates" => []
        ];
    }

    public static function getStockPriceFS( $body )
    {
        $env       = config( "fastshop.env"             );
        $fsUrl     = config( "fastshop.$env.fs.url"     );
        $fsHeaders = config( "fastshop.$env.fs.headers" );

        $count     = count( $body[ "items" ] );
        echo "Querying {$count} SKUs at once\n";
        Log::channel( "fastshop" )->info( "FS stock-price", [ "Querying {$count} SKUs at once" ] );

        try
        {
            $url      = "$fsUrl/pvt/orderForms/simulation";
            $response = Http::withHeaders( $fsHeaders )->withBody( json_encode( $body ), "application/json" )->get( $url );

            if ( $response->successful() ) return $response->json();

            throw new Exception( "Unable to query FastShop products data" );
        }
        catch ( Exception $e )
        {
            echo "FS SKUs query failed\n";
            Log::channel( "fastshop" )->info( "FS stock-price", [ "FS SKUs query failed" ] );
            Helper::sendMailNotification( "marketplace", "getStockPrice()", "Exception", $e );
        }
    }

    public static function treatUrlAwin( string $url )
    {
        $url = str_replace( "&campaign=",  "", $url );
        $url = str_replace( "&clickref2=", "", $url );
        $url = str_replace( "&clickref3=", "", $url );
        $url = str_replace( "&clickref4=", "", $url );
        $url = str_replace( "&clickref5=", "", $url );
        $url = str_replace( "&clickref6=", "", $url );
        $url = str_replace( "%7Buid%7D", "{uid}", $url );

        return $url;
    }

    public static function addTagsToDescriptionCofry( string $desc, array $tags )
    {
        foreach ( $tags as $tag )
        {
            if ( $tag == "Cofry" ) continue;

            $tag   = "\n#cofry_" . self::slugify( $tag, "_" );
            $desc .= $tag;
        }

        return $desc;
    }

    public static function awinReportStatus( string $status )
    {
        // pending, approved, declined
        $status = strtolower( $status );
        $status = ( $status == "declined" ) ? "denied" : $status;

        return $status;
    }

    public static function convertDatetime( string $datetime )
    {
        $date = new DateTimeImmutable( $datetime );
        $date = ( int ) $date->format( "Uv" ); // Timestamp in milliseconds

        return $date;
    }
}
