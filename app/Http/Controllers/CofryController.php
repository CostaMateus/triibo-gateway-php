<?php

namespace App\Http\Controllers;

use DateTime;
use DateInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Http\Integrations\CofryIntegration;
use Kreait\Laravel\Firebase\Facades\Firebase;

class CofryController extends Controller
{

    /**
     * Show the application dashboard.
     *
     * @return void
     */
    public function index()
    {
        // $db        = Firebase::database();
        // $reference = $db->getReference('Estabelecimento-v2');
        // $value     = $reference->getValue();

        // dd($value);
        dd('Nada aqui');
    }

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

}
