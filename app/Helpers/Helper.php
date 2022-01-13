<?php

namespace App\Helpers;

use Devio\Pipedrive\Pipedrive;
use Illuminate\Support\Facades\Log;

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
    private static function cleanString($text) {
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
    public static function hyphenize($string) {
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
        return self:: convertWeight($price);
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

    public static function getOrderCancelation(array $data)
    {
        $msg = "Pedido cancelado:\n";

        foreach ($data as $key => $value)
        {
            $msg .= "$key: $value\n";
        }

        return $msg;
    }
  
    public static function getErrorNewOrderFS(array $data)
    {
        $code = $data["errorCode"];
        $msg  = "ERROR\n";

        switch ($code)
        {
            case "4001";
                $msg .= "SKU fora de estoque";
            break;

            case "4002";
                $msg .= "Entrega indisponível para o CEP informado";
            break;

            case "5001";
                $msg .= "Não foi possível informar o pedido a Fast Shop";
            break;
        }

        return "$msg \nCode: $code";
    }
}
