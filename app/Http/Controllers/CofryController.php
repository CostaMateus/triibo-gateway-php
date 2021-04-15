<?php

namespace App\Http\Controllers;

use File;
use DateTime;
use Throwable;
use DateInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Http\Integrations\CofryIntegration;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;

class CofryController extends Controller
{

    public function estabelecimentos()
    {
        // ~ 2 segundos
        echo date('Y-m-d H:i:s.u') . '<br>';
        $stores = CofryIntegration::getEstabelecimentos();
        echo date('Y-m-d H:i:s.u') . '<br>';
        dd($stores);
    }

    public function promocoes()
    {
        // ~ 17 segundos
        echo date('Y-m-d H:i:s.u') . '<br>';
        $promos = CofryIntegration::getPromocoes();
        echo date('Y-m-d H:i:s.u') . '<br>';
        dd($promos);
    }

    public function imagens()
    {
        // ~ 10 minutos
        echo date('Y-m-d H:i:s.u') . '<br>';
        CofryIntegration::getEstabelecimentosImagens();
        echo date('Y-m-d H:i:s.u') . '<br>';
        dd('OK');
    }





    /**
     *
     *
     * @return void
     */
    public function index()
    {
        // echo date('Y-m-d H:i:s.u') . '<br>';
        try
        {
            $this->insertStoresFirebase();

            // echo date('Y-m-d H:i:s.u') . '<br>';

            $this->insertPromosFirebase();

            self::registerLog(0, 'Importado com sucesso');
        }
        catch (FirebaseException $e)
        {
            echo date('Y-m-d H:i:s.u') . '<br>';
            $msg = 'An error has occurred while working with the SDK: ' . $e->getMessage();
            self::registerLog($e->getCode(), $msg);
        }
        catch (Throwable $e)
        {
            // echo date('Y-m-d H:i:s.u') . '<br>';
            $msg = 'A not-Firebase specific error has occurred: ' . $e->getMessage();
            self::registerLog($e->getCode(), $msg);
        }
        // echo date('Y-m-d H:i:s.u') . '<br>';
    }

    public function insertStoresFirebase()
    {
        $db             = Firebase::database();
        $firebaseStores = $db->getReference('Estabelecimento-v2');
        // $stores         = $this->estabelecimentos();
        $firebaseStores->update(CofryIntegration::getEstabelecimentos());
        $rStores        = $firebaseStores->getValue();
    }

    public function insertPromosFirebase()
    {
        $db             = Firebase::database();
        $firebasePromos = $db->getReference('Store');
        // $promos         = $this->promocoes();
        $firebasePromos->update(CofryIntegration::getPromocoes());
        $rPromos        = $firebasePromos->getValue();
    }



    /**
     *
     *
     * @return void
     */
    public function uploadImages()
    {
        CofryIntegration::getEstabelecimentosImagens();

        $imgsStores = self::getFileNames('images/estabelecimentos/');
        $imgsPromos = self::getFileNames('images/promos/');

        // Local
        $lcStore    = 'images/estabelecimentos/';
        $lcPromo    = 'images/promos/';

        // Firebase
        $fbStore    = 'estabelecimento/';
        $fbPromo    = 'promocao/';

        echo date('Y-m-d H:i:s.u') . '<br>';
        self::execUploadImages($imgsStores, $lcStore, $fbStore);

        echo date('Y-m-d H:i:s.u') . '<br>';
        self::execUploadImages($imgsPromos, $lcPromo, $fbPromo);

        echo date('Y-m-d H:i:s.u') . '<br>';
    }

    /**
     * Get the file names from some folder
     *
     * @param String $pathFolder
     * @return Array $fileNames
     */
    private static function getFileNames(String $pathFolder)
    {
        $path  = public_path($pathFolder);

        $files = File::allFiles($path);

        $fileNames = [];

        foreach($files as $file)
        {
            array_push($fileNames, pathinfo($file)['basename']);
        }

        return $fileNames;
    }

    /**
     * Send images to Storage
     *
     * @param Array $images
     * @param String $lcFolder
     * @param String $fbFolder
     * @return void
     */
    private static function execUploadImages(Array $images, String $lcFolder, String $fbFolder)
    {
        $st     = Firebase::storage();
        $bucket = $st->getBucket();

        foreach ($images as $img)
        {
            $uploader = $bucket->getResumableUploader(
                fopen(public_path($lcFolder . $img), 'r'),
                [
                    'name' => $fbFolder . $img
                ]
            );

            try
            {
                $uploader->upload();
                self::registerLog(0, 'Importado com sucesso');
            }
            catch (FirebaseException $e)
            {
                $resumeUri = $uploader->getResumeUri();
                $object    = $uploader->resume($resumeUri);

                self::registerLog($e->getCode(), $e->getMessage());
            }
            catch (Throwable $e)
            {
                self::registerLog($e->getCode(), $e->getMessage());
            }
        }
    }



    /**
     * Log access to cofry APIs
     *
     * @param  Int    $code
     * @param  String $message
     * @return void
     */
    private static function registerLog(Int $code, String $message)
    {
        $log = 'FirebaseAPI | code: ' . $code . ' | message: ' . $message;

        ($code != 0) ? Log::critical($log) : Log::info($log);
    }
}
