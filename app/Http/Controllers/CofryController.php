<?php

namespace App\Http\Controllers;

use File;
use DateTime;
use Throwable;
use Illuminate\Support\Facades\Log;
use App\Http\Integrations\CofryIntegration;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;

class CofryController extends Controller
{

    public function estabelecimentos()
    {
        // ~ 2 segundos
        echo date('Y-m-d H:i:s.u') . "\n";
        $stores = CofryIntegration::getEstabelecimentos();
        echo date('Y-m-d H:i:s.u') . "\n";
        dd($stores);
    }

    public function promocoes()
    {
        // ~ 17 segundos
        echo date('Y-m-d H:i:s.u') . "\n";
        $promos = CofryIntegration::getPromocoes();
        echo date('Y-m-d H:i:s.u') . "\n";
        dd($promos);
    }

    public function imagens()
    {
        // ~ 10 minutos
        echo date('Y-m-d H:i:s.u') . "\n";
        CofryIntegration::getImagens();
        echo date('Y-m-d H:i:s.u') . "\n";
        dd('OK');
    }





    /**
     *
     *
     * @return void
     */
    public function index()
    {
        try
        {
            $this->insertStoresFirebase();

            $this->insertPromosFirebase();

            self::registerLog(0, 'Importado com sucesso');
        }
        catch (FirebaseException $e)
        {
            self::registerLog($e->getCode(), "An error has occurred while working with the SDK: $e->getMessage()");
        }
        catch (Throwable $e)
        {
            self::registerLog($e->getCode(), "A not-Firebase specific error has occurred: $e->getMessage()");
        }
    }

    public function insertStoresFirebase()
    {
        $db             = Firebase::database();
        $firebaseStores = $db->getReference('Estabelecimento-v2');

        echo "SP : Buscando estabelecimentos\n";
        Log::info("SP : Buscando estabelecimentos");
        $stores         = CofryIntegration::getEstabelecimentos();

        echo "SP : Inicio upload Firebase\n";
        Log::info("SP : Inicio upload Firebase");
        $firebaseStores->update($stores);

        // $rStores = $firebaseStores->getValue();
    }

    public function insertPromosFirebase()
    {
        $db             = Firebase::database();
        $firebasePromos = $db->getReference('Store');

        echo "SP : Buscando promoção\n";
        Log::info("SP : Buscando promoção");
        $promos         = CofryIntegration::getPromocoes();

        echo "SP : Inicio upload Firebase\n";
        Log::info("SP : Inicio upload Firebase");
        $firebasePromos->update($promos);

        // $rPromos = $firebasePromos->getValue();
    }



    /**
     *
     *
     * @return void
     */
    public function uploadImages()
    {
        // Local
        $lcStore    = 'images/estabelecimentos/';
        $lcPromo    = 'images/promos/';

        // Firebase
        $fbStore    = 'estabelecimento/';
        $fbPromo    = 'promocao/';

        $imgsStores = self::getFileNames($lcStore);
        $imgsPromos = self::getFileNames($lcPromo);

        // ~ 20 min
        self::execUploadImages($imgsStores, $lcStore, $fbStore);

        // ~ 57 min
        self::execUploadImages($imgsPromos, $lcPromo, $fbPromo);
    }

    /**
     * Get the file names from some folder
     *
     * @param String $pathFolder
     * @return Array $fileNames
     */
    private static function getFileNames(String $pathFolder)
    {
        $path      = public_path($pathFolder);
        $files     = File::allFiles($path);
        $fileNames = [];

        if (empty($files))
        {
            echo "IMG : Gerando imagens\n";
            Log::info("IMG : Gerando imagens");
            CofryIntegration::getImagens();
        }
        else
        {
            $today = new DateTime(date('Y-m-d H:i:s'));
            $fDate = new DateTime(date('Y-m-d H:i:s', $files[0]->getCTime()));

            $diff  = $fDate->diff($today);
            $diff  = (int) $diff->format('%a');

            if ($diff >= 2)
            {
                echo "IMG : Gerando imagens\n";
                Log::info("IMG : Gerando imagens");
                CofryIntegration::getImagens();
            }
        }

        echo "IMG : Capturando nomes - $pathFolder\n";
        Log::info("IMG : Capturando nomes - $pathFolder");

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

        echo "IMG : Inicio upload Firebase - $lcFolder\n";
        Log::info("IMG : Inicio upload Firebase - $lcFolder");

        foreach ($images as $img)
        {
            $uploader = $bucket->getResumableUploader(
                fopen(public_path($lcFolder . $img), 'r'),
                [ 'name' => $fbFolder . $img ]
            );

            try
            {
                $uploader->upload();
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

        echo "IMG : Fim upload Firebase - $lcFolder\n";
        Log::info("IMG : Fim upload Firebase - $lcFolder");

        // $promises = [];
        // $objects  = [];

        // foreach ($images as $name)
        // {
        //     $objects[$name] = fopen(public_path($lcFolder . $name), 'r');

        //     echo $name . '<br><br>';
        // }

        // foreach ($objects as $k => $v)
        // {
        //     $promises[] = $bucket->uploadAsync($v, ['name' => $k])
        //         ->then(function (StorageObject $object) {
        //             self::registerLog(0, 'Importe novo, ok');

        //         }, function(\Exception $e) {
        //             $msg = 'An error has occurred in the matrix';
        //             self::registerLog($e->getCode(), $msg . ' - ' . $e->getMessage());
        //             throw new Exception($msg, null, $e);
        //         });
        // }

        // foreach ($promises as $promise) {
        //     $promise->wait();
        // }
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
        $log = "FirebaseAPI | code: $code | message: $message";

        ($code != 0) ? Log::critical($log) : Log::info($log);
    }
}
