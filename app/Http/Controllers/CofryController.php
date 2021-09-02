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
            echo "SP : Establishments sent successfully\n";
            Log::channel("cofry")->info("Stores|Promos", [
                "code"    => 0,
                "info"    => "Establishments sent successfully",
                "error"   => NULL,
                "message" => NULL
            ]);
        }
        catch (FirebaseException $e)
        {
            echo "SP : Establishment, Firebase process error - aborted\n";
            Log::channel("cofry")->critical("Stores|Promos", [
                "code"    => "C-401",
                "info"    => "Establishment, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ]);
        }
        catch (Throwable $e)
        {
            echo "SP : Unexpected error - aborted\n";
            Log::channel("cofry")->critical("Stores|Promos", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e
            ]);
        }


        // PROMO
        try
        {
            $this->insertPromosFirebase();
            echo "SP : Promotions sent successfully\n";
            Log::channel("cofry")->info("Stores|Promos", [
                "code"    => 0,
                "info"    => "Promotions sent successfully",
                "error"   => NULL,
                "message" => NULL
            ]);
        }
        catch (FirebaseException $e)
        {
            echo "SP : Promotions, Firebase process error - aborted\n";
            Log::channel("cofry")->critical("Stores|Promos", [
                "code"    => "C-402",
                "info"    => "Promotions, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ]);
        }
        catch (Throwable $e)
        {
            echo "SP : Unexpected error - aborted\n";
            Log::channel("cofry")->critical("Stores|Promos", [
                "code"    => "C-502",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e
            ]);
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

        echo "SP : Searching establishments\n";
        Log::channel("cofry")->info("Stores|Promos", [ "Searching establishments" ]);
        $stores    = CofryIntegration::getEstabelecimentos();
        $allStores = $firebaseStores->getValue();
        foreach ($allStores as $id => $s)
        {
            if (strpos($id, "-cofry") !== false)
            {
                if (!isset($stores[$id]))
                {
                    echo "SP : Disab. Estab. ID = $id\n";
                    $s["isPublished"] = false;
                    $firebaseStores->update([$id => $s]);
                }
            }
        }

        echo "SP : Sending establishments to Firebase\n";
        Log::channel("cofry")->info("Stores|Promos", [ "Sending establishments to Firebase" ]);
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

        echo "SP : Searching for promos\n";
        Log::channel("cofry")->info("Stores|Promos", [ "Searching for promos" ]);
        $promos    = CofryIntegration::getPromocoes();
        $allPromos = $firebasePromos->getValue();
        foreach ($allPromos as $id => $p)
        {
            if (strpos($id, "-cofry") !== false)
            {
                // echo "SP : Del Promo ID = $id\n";
                // $db->getReference("Store/$id")->remove();

                if (!isset($stores[$id]))
                {
                    echo "SP : Disab. Promo. ID = $id\n";
                    $s["isPublished"] = false;
                    $firebasePromos->update([$id => $p]);
                }
            }
        }

        echo "SP : Sending promos to Firebase\n";
        Log::channel("cofry")->info("Stores|Promos", [ "Sending promos to Firebase" ]);
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
            echo "IMG : Generating images\n";
            Log::channel("cofry")->info("Images", [ "Generating images" ]);
            CofryIntegration::getImagens();

            $filesStores = File::allFiles(public_path($lcStore));
            $filesPromos = File::allFiles(public_path($lcPromo));
        }

        echo "IMG : Capturing names - $lcStore\n";
        Log::channel("cofry")->info("Images", [ "Capturing names - $lcStore" ]);
        $fileNameStores = self::getFileNames($filesStores);

        echo "IMG : Capturing names - $lcPromo\n";
        Log::channel("cofry")->info("Images", [ "Capturing names - $lcPromo" ]);
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

        echo "IMG : Start sending images to Firebase - $lcFolder\n";
        Log::channel("cofry")->info("Images", [ "Start sending images to Firebase - $lcFolder" ]);

        foreach ($images as $img)
        {
            $uploader = $bucket->getResumableUploader(
                fopen(public_path($lcFolder . $img), "r"),
                [ "name" => $fbFolder . $img ]
            );

            try
            {
                echo "IMG : Image ID = $img\n";
                $uploader->upload();
            }
            catch (FirebaseException $e)
            {
                $resumeUri = $uploader->getResumeUri();
                $object    = $uploader->resume($resumeUri);

                Log::channel("cofry")->critical("Images", [
                    "code"    => "C-403",
                    "info"    => $img,
                    "error"   => "An error has occurred while working with the SDK",
                    "message" => $e
                ]);
            }
            catch (Throwable $e)
            {
                Log::channel("cofry")->critical("Images", [
                    "code"    => "C-503",
                    "info"    => $img,
                    "error"   => "A not-Firebase specific error has occurred",
                    "message" => $e
                ]);
            }
        }

        echo "IMG : End uploading images to Firebase - $lcFolder\n";
        Log::channel("cofry")->info("Images", [ "End uploading images to Firebase - $lcFolder" ]);
    }

}
