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
        echo date("Y-m-d H:i:s.u") . "\n";
        $stores = CofryIntegration::getEstabelecimentos();
        echo date("Y-m-d H:i:s.u") . "\n";
        dd($stores);
    }

    public function promocoes()
    {
        // ~ 17 segundos
        echo date("Y-m-d H:i:s.u") . "\n";
        $promos = CofryIntegration::getPromocoes();
        echo date("Y-m-d H:i:s.u") . "\n";
        dd($promos);
    }

    public function imagens()
    {
        // ~ 10 minutos
        echo date("Y-m-d H:i:s.u") . "\n";
        CofryIntegration::getImagens();
        echo date("Y-m-d H:i:s.u") . "\n";
        dd("OK");
    }



    /**
     * Process stores and promotions
     *
     * @return void
     */
    public function index()
    {
        // STORE
        try
        {
            $this->insertStoresFirebase();
            echo "SP : Estabelecimentos enviados com sucesso\n";
            self::registerLog(0, "Estabelecimentos enviados com sucesso");
        }
        catch (FirebaseException $e)
        {
            echo "SP : Estabelecimento, erro processo Firebase - abortando\n";
            self::registerLog(401, "An error has occurred while working with the SDK: $e");
        }
        catch (Throwable $e)
        {
            echo "SP : Erro inesperado - abortando\n";
            self::registerLog(501, "A not-Firebase specific error has occurred: $e");
        }


        // PROMO
        try
        {
            $this->insertPromosFirebase();
            echo "SP : Promoções enviadas sucesso\n";
            self::registerLog(0, "Promoções enviadas sucesso");
        }
        catch (FirebaseException $e)
        {
            echo "SP : Promoções, erro processo Firebase - abortando\n";
            self::registerLog(402, "An error has occurred while working with the SDK: $e");
        }
        catch (Throwable $e)
        {
            echo "SP : Erro inesperado - abortando\n";
            self::registerLog(502, "A not-Firebase specific error has occurred: $e");
        }
    }

    /**
     * Process stores and send to Firebase
     *
     * @return void
     */
    public function insertStoresFirebase()
    {
        $db             = Firebase::database();
        $firebaseStores = $db->getReference("Estabelecimento-v2");

        echo "SP : Buscando estabelecimentos\n";
        Log::info("SP : Buscando estabelecimentos");
        $stores = CofryIntegration::getEstabelecimentos();

        echo "SP : Enviando estabelecimentos para Firebase\n";
        Log::info("SP : Enviando estabelecimentos para Firebase");
        foreach ($stores as $id => $store)
        {
            echo "SP : Estab. ID = $id\n";
            $firebaseStores->update([$id => $store]);
        }

        // // $firebaseStores->update($stores);
        // // $rStores = $firebaseStores->getValue();
    }

    /**
     * Process promotions and send to Firebase
     *
     * @return void
     */
    public function insertPromosFirebase()
    {
        $db             = Firebase::database();
        $firebasePromos = $db->getReference("Store");

        echo "SP : Limpando promoções Cofry\n";
        Log::info("SP : Limpando promoções Cofry");
        $allPromos = $firebasePromos->getValue();
        foreach ($allPromos as $id => $p)
        {
            if (strpos($id, "-cofry") !== false)
            {
                $db->getReference("Store/$id")->remove();
            }
        }

        echo "SP : Buscando promoções\n";
        Log::info("SP : Buscando promoções");
        $promos = CofryIntegration::getPromocoes();

        echo "SP : Enviando promoções para Firebase\n";
        Log::info("SP : Enviando promoções para Firebase");
        foreach ($promos as $id => $p)
        {
            echo "SP : Promo ID = $id\n";
            $firebasePromos->update([$id => $p]);
        }

        // // $firebasePromos->update($promos);
        // // $rPromos = $firebasePromos->getValue();
    }



    /**
     * Process images of stores and promotions
     *
     * @return void
     */
    public function uploadImages()
    {
        // Local
        $lcStore     = "images/estabelecimentos/";
        $lcPromo     = "images/promos/";

        // Firebase
        $fbStore     = "estabelecimento/";
        $fbPromo     = "promocao/";

        $filesStores = File::allFiles(public_path($lcStore));
        $filesPromos = File::allFiles(public_path($lcPromo));

        if (empty($filesStores) || empty($filesPromos))
        {
            echo "IMG : Gerando imagens\n";
            Log::info("IMG : Gerando imagens");
            CofryIntegration::getImagens();

            $filesStores = File::allFiles(public_path($lcStore));
            $filesPromos = File::allFiles(public_path($lcPromo));
        }

        echo "IMG : Capturando nomes - $lcStore\n";
        Log::info("IMG : Capturando nomes - $lcStore");
        $fileNameStores = self::getFileNames($filesStores);

        echo "IMG : Capturando nomes - $lcPromo\n";
        Log::info("IMG : Capturando nomes - $lcPromo");
        $fileNamePromos = self::getFileNames($filesPromos);

        // ~ 20 min
        self::execUploadImages($fileNameStores, $lcStore, $fbStore);

        // ~ 57 min
        self::execUploadImages($fileNamePromos, $lcPromo, $fbPromo);
    }

    /**
     * Get the file names from list of files
     *
     * @param Array $files
     * @return Array $fileNames
     */
    private static function getFileNames(Array $files)
    {
        $fileNames = [];

        foreach($files as $file)
            array_push($fileNames, pathinfo($file)["basename"]);

        return $fileNames;
    }

    /**
     * Send images to Firebase Storage
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
                fopen(public_path($lcFolder . $img), "r"),
                [ "name" => $fbFolder . $img ]
            );

            try
            {
                $uploader->upload();
            }
            catch (FirebaseException $e)
            {
                $resumeUri = $uploader->getResumeUri();
                $object    = $uploader->resume($resumeUri);

                self::registerLog(403, "An error has occurred while working with the SDK: $e");
            }
            catch (Throwable $e)
            {
                self::registerLog(503, "A not-Firebase specific error has occurred: $e");
            }
        }

        echo "IMG : Fim upload Firebase - $lcFolder\n";
        Log::info("IMG : Fim upload Firebase - $lcFolder");
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
