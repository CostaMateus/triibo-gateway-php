<?php

namespace App\Http\Integrations;

use DateTime;
use Exception;
use DateInterval;
use Gregwar\Image\Image;
use Verot\Upload\Upload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CofryIntegration
{

    const URL_PROMO    = 'https://www.cofry.com.br/offer/import';
    const URL_STORE    = 'https://www.cofry.com.br/store/import';
    const URL_STATUS   = 'https://www.cofry.com.br/store-check/import';
    const TOKEN        = [ 'token' => 'b078c85a534c11ea80311e1746133021' ];
    const PREFIX_STORE = 'cofry0_';
    const PREFIX_PROMO = 'promo';

    /**
     *
     *
     * @return Array $promos
     */
    public static function getPromocoes()
    {
        $stores    = self::getStoresCofry();
        $promos    = self::getPromosCofry();

        if ($stores) self::usort($stores);
        if ($promos) self::usort($promos);

        $promos1   = self::translatorPromotionDefault($stores);
        $promos2   = self::translatorPromotion($promos);

        $promos    = array_merge_recursive($promos1, $promos2);
        $promos    = array_unique($promos, SORT_REGULAR);

        $keys      = array_column($promos, 'establishmentName');
		array_multisort($keys, SORT_ASC, $promos);

        self::generatePromosImage($promos);

        // return json_encode($promos);
        return $promos;
    }

    /**
     *
     *
     * @return Array $stores
     */
    public static function getEstabelecimentos()
    {
        $stores = self::getStoresCofry();
        $status = self::getStatusStores();

        if ($stores) self::usort($stores);
        if ($status) self::usort($status);

        foreach ($stores as &$store)
        {
            $index           = array_search($store['uuid'], $status);
            $store['Active'] = $status[$index]['Active'];
        }

        $stores = self::translatorStore($stores);

        // return json_encode($stores);
        return $stores;
    }

    /**
     *
     *
     * @return void
     */
    public static function getImagens()
    {
        $stores = self::getStoresCofry();

        // if ($stores) self::usort($stores);

        self::generatorImages($stores);
    }

    /**
     * Get promotions Cofry.
     *
     * @return Array $res | null
     */
    private static function getPromosCofry()
    {
        $client = Http::asForm();

        $res    = $client->post(self::URL_PROMO, self::TOKEN)->json();

        self::registerLog($res['errorCode'], $res['errorMessage'], 'getPromosCofry');

        if ($res['errorCode'] == 0)
        {
            return $res['store'];
        }

        return null;
    }

    /**
     * Get stores Cofry.
     *
     * @return Array $res | null
     */
    private static function getStoresCofry()
    {
        $client = Http::asForm();

        $res    = $client->post(self::URL_STORE, self::TOKEN)->json();

        self::registerLog($res['errorCode'], $res['errorMessage'], 'getStoresCofry');

        if ($res['errorCode'] == 0)
        {
            return $res['store'];
        }

        return null;
    }

    /**
     * Get status stores.
     *
     * @return Array $res | null
     */
    private static function getStatusStores()
    {
        $client = Http::asForm();

        $res    = $client->post(self::URL_STATUS, self::TOKEN)->json();

        self::registerLog($res['errorCode'], $res['errorMessage'], 'getStatusStores');

        if ($res['errorCode'] == 0)
        {
            return $res['store'];
        }

        return null;
    }

    /**
     *
     *
     * @param Array $data
     * @return Array $res
     */
    private static function translatorPromotion(Array $data)
    {
        $date    = new DateTime(date('Y-m-d H:i:s.u'));
        $dateEn  = (int) $date->format('U') * 1000;
        $date    = $date->add(new DateInterval('P7Y'));
        $dateDis = (int) $date->format('U') * 1000;

        $i       = 1;
        foreach ($data as $key => $promo)
        {
            ($key > 0 && $promo['uuid'] == $data[$key - 1]['uuid']) ? $i++ : $i = 1;

            $cashB = number_format((float) $promo['CashbackClient'], $promo['CashbackFormat'], ',', '');

            if ($promo['ItemTitle'] === "******") $promo['ItemTitle'] = '';

            $idStore            = '-cofry' . $promo['uuid'];
            $id                 = $idStore . str_replace('.', '', $promo['ProductAPIID']);

            $store['Logo']      = self::PREFIX_PROMO . $i . '_' .
                                  strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9]/', '', $promo['Advertiser']))) . '_' .
                                  strtolower($promo['ItemCode']) . '.png';

            $promo['ItemCode']  = str_replace(' ',                  '',                          $promo['ItemCode']);

            $promo['ItemTitle'] = str_replace('( Código: ******)',  ' (Código: ******)',         $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('Código ******',      '(Código: ******)',          $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('(código: ******)',   '(Código: ******)',          $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('(cÓdigo: ******)',   '(Código: ******)',          $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('(código: ******  )', '(Código: ******)',          $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('Cupom******',        'Cupom: ******',             $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('Cupom ******',       'Cupom: ******',             $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('Cupom:******',       'Cupom: ******',             $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('Cupom = ******',     'Cupom: ******',             $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('(cupom: ******)',    'Cupom: ******',             $promo['ItemTitle']);

            $promo['ItemTitle'] = str_replace(' ****** ',           ' ',                         $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace(' ****** ',           ' ',                         $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('****** de ',         '',                          $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('****** & ',          '',                          $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace(' ******!',           '',                          $promo['ItemTitle']);

            $promo['ItemTitle'] = str_replace('   ',                ' ',                         $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('  ',                 ' ',                         $promo['ItemTitle']);

            $promo['ItemTitle'] = str_replace(': ******',           ': '   . $promo['ItemCode'], $promo['ItemTitle']);
            $promo['ItemTitle'] = str_replace('Use ******',         'Use ' . $promo['ItemCode'], $promo['ItemTitle']);

            $res[$id]           = [
                'establishmentId'   => $idStore,
                'establishmentName' => trim($promo['Advertiser']),

                'title'             => 'Ganhe ' . $cashB . '% de volta!',
                'description'       => $promo['ItemTitle'] . '\n\nUtilize o código de desconto ' . $promo['ItemCode'] . ' no fechamento da compra.',

                'checkoutCode'      => $promo['ItemCode'],

                'enableDate'        => $dateEn,
                'disableDate'       => $dateDis,
                'dueDate'           => $dateDis,

                'isPublished'       => true,
                'active'            => true,

                'lat'               => -15.52047783763576,
                'long'              => -48.015408891158984,

                'level'             => 99,

                'photo'             => $store['Logo'],
                'thumbnail'         => $store['Logo'],

                'region'            => 'nacional',
                'type'              => 'businessPartner',
                'url'               => '-' // montar com o Velho
            ];
        }

        return $res;
    }

    /**
     * Default promotion, one per store
     *
     * @param Array $data
     * @return Array $res
     */
    private static function translatorPromotionDefault(Array $data)
    {
        $date    = new DateTime(date('Y-m-d H:i:s.u'));
        $dateEn  = (int) $date->format('U') * 1000;
        $date    = $date->add(new DateInterval('P7Y'));
        $dateDis = (int) $date->format('U') * 1000;

        foreach ($data as $store)
        {
            $idStore             = '-cofry' . $store['uuid'];

            $store['Advertiser'] = trim($store['Advertiser']);

            $auxAdv              = preg_replace('/[^\w\s]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $store['Advertiser']));
            $idPromo             = $idStore . strtolower(str_replace(' ', '', $auxAdv));

            $store['Logo']       = str_replace('https://imgcofrynew.s3.amazonaws.com/', '', $store['Logo']);
            $store['Logo']       = self::PREFIX_PROMO . '0_' . ((basename($store['Logo']) != '') ? basename($store['Logo']) : 'standard.png');

            $store['About']      = trim($store['About']);
            $store['About']      = str_replace('  ', ' ', $store['About']);

            $rules               = str_replace("\t", "", $store['GeneralRules']);
            $rules               = json_decode($rules, true);
            $rules['general']    = str_replace('<ol><li>',   '',   $rules['general']);
            $rules['general']    = str_replace('</li></ol>', '',   $rules['general']);
            $rules['general']    = str_replace('</li><li>',  '\n', $rules['general']);
            $rules['specific']   = '';

            if ($store['SpecificRules'])
            {
                $rules['specific'] = json_decode($store['SpecificRules'], true);
                $rules['specific'] = str_replace('<b>',  '', $rules['specific']['rules'][0]['description']);
                $rules['specific'] = str_replace('</b>', '', $rules['specific']);
            }

            $description   = $store['About'] . '\n\n' . $rules['general'] . ($rules['additional'] != '' ? '\n\n' . $rules['additional'] : '') . ($rules['specific'] != '' ? '\n\n' . $rules['specific'] : '');

            $res[$idPromo] = [
                'establishmentId'   => $idStore,
                'establishmentName' => $store['Advertiser'],

                'title'             => $store['Advertiser'],
                'description'       => $description,

                'enableDate'        => $dateEn,
                'disableDate'       => $dateDis,
                'dueDate'           => $dateDis,

                'isPublished'       => true,
                'active'            => true,

                'lat'               => -15.52047783763576,
                'long'              => -48.015408891158984,

                'level'             => 99,

                'photo'             => $store['Logo'],
                'thumbnail'         => $store['Logo'],

                'region'            => 'nacional',
                'type'              => 'businessPartner',
                'url'               => '' // montar com o Velho
            ];
        }

        return $res;
    }

    /**
     *
     *
     * @param Array $data
     * @return Array $res
     */
    private static function translatorStore(Array $data)
    {
        $date    = new DateTime(date('Y-m-d H:i:s.u'));
        $dateEn  = (int) $date->format('U') * 1000;
        $date    = $date->add(new DateInterval('P7Y'));
        $dateDis = (int) $date->format('U') * 1000;

        foreach ($data as $store)
        {
            $id               = '-cofry'.$store['uuid'];

            $store['Logo']    = str_replace('https://imgcofrynew.s3.amazonaws.com/', '', $store['Logo']);
            $store['Logo']    = self::PREFIX_STORE . ((basename($store['Logo']) != '') ? basename($store['Logo']) : 'standard.png');

            $store['About']   = trim($store['About']);
            $store['About']   = str_replace('  ', ' ', $store['About']);

            $rules            = str_replace("\t", "", $store['GeneralRules']);
            $rules            = json_decode($rules, true);
            $rules['general'] = str_replace('<ol><li>',   '',   $rules['general']);
            $rules['general'] = str_replace('</li></ol>', '',   $rules['general']);
            $rules['general'] = str_replace('</li><li>',  '\n', $rules['general']);
            $descricao        = $store['About'] . '\n\n' . $rules['general'] . ($rules['additional'] != '' ? '\n\n' . $rules['additional'] : '');

            if ($store['Segment'])
            {
                // $tags = explode(',', $store['Segment']);
                $tags = preg_split("/\b,\b/", $store['Segment']);
                array_unshift($tags, 'Cofry');
            }
            else
            {
                $tags = [ 'Cofry' ];
            }

            $res[$id]         = [
                'enableDate'    => $dateEn,
                'disableDate'   => $dateDis,
                'dataAlteracao' => $dateEn,
                'dataInclusao'  => $dateEn,

                'idGestor'      => '+5511949289507@sms,triibo,com,br',
                'isPublished'   => $store['Active'] ? true : false,

                'endereco'      => 'Brasil',
                'lat'           => -15.52047783763576,
                'long'          => -48.015408891158984,

                'nome'          => trim($store['Advertiser']),
                'descricao'     => $descricao,

                'fotoThumb'     => $store['Logo'],
                'listaFotos'    => [
                    $store['Logo'],
                ],

                'funcionamento' => [
                    ... self::getSchedulesOperation()
                ],
                'horarios'      => [
                    ... self::getSchedulesOperation(false)
                ],

                'tags'          => [
                    ...$tags
                ]
            ];
        }

        return $res;
    }

    private static function getSchedulesOperation($operation = true)
    {
        $res = [];

        if ($operation)
        {
            // funcionamento
            foreach (range(1,7) as $key) {
                $res[] = [
                    'ativo'         => true,
                    'horarioFim'    => '23:59',
                    'horarioInicio' => '00:00'
                ];
            }
        }
        else
        {
            // horarios
            foreach (range(1,7) as $key) {
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
     * Log access to cofry APIs
     *
     * @param  Int    $code
     * @param  String $message
     * @return void
     */
    private static function registerLog(Int $code, String $message, String $class)
    {
        $log = 'CofryAPI | code: ' . $code . ' | message: ' . $class . ', ' . $message;

        ($code != 0) ? Log::critical($log) : Log::info($log);
    }

    /**
     * @param Array $arr
     * @return void
     */
    private static function usort(Array &$arr)
    {
        usort($arr, function($a, $b) {
            return $a['uuid'] <=> $b['uuid'];
        });
    }

    /**
     *
     *
     * @param Array $data
     * @return void
     */
    private static function generatorImages(Array $data)
    {
        $pathStore = public_path('images/estabelecimentos');
        $pathPromo = public_path('images/promos');

        foreach ($data as $store)
        {
            $imgName = str_replace('https://imgcofrynew.s3.amazonaws.com/', '', $store['Logo']);
            $imgName = basename($imgName);

            if ($imgName)
            {
                $fullPathStore = $pathStore . '/' . self::PREFIX_STORE . $imgName;
                $fullPathPromo = $pathPromo . '/' . self::PREFIX_PROMO . '0_' . $imgName;

                $image         = self::execUrl($store['Logo']);

                if ($image)
                {
                    file_put_contents($fullPathStore, $image);

                    $handle = Image::open($fullPathStore);
                    $handle->scaleResize(200, 150, "#FFF");
                    $handle->save($fullPathStore);
                    $handle->save($fullPathPromo);
                }
            }
        }
    }

    /**
     *
     *
     * @param String $url
     * @return String $data
     */
    private static function execUrl(String $url)
    {
        $ch   = curl_init();

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);

        $data = curl_exec($ch);

        if (curl_getinfo($ch)['http_code'] != 200) $data = null;

        curl_close($ch);

        return $data;
    }

    private static function generatePromosImage(Array $data)
    {
        $pathPromo = public_path('images/promos');
        $handle    = Image::open(public_path('images/standard.png'));

        foreach ($data as $promo)
        {
            $auxHandle = clone $handle;

            if (isset($promo['checkoutCode']))
            {
                $font      = public_path('fonts/OpenSans-Bold.ttf');
                $f_size    = 48;
                $f_color   = '#333';
                $text      = strtoupper($promo['checkoutCode']);
                $width     = $auxHandle->width()  / 2;
                $height    = $auxHandle->height() / 2 + $f_size / 2;
                $angle     = 0;
                $position  = 'center';

                $auxHandle->write($font, $text, $width, $height, $f_size, $angle, $f_color, $position);
                $auxHandle->save($pathPromo . '/' . $promo['photo']);
            }
        }
    }
}
