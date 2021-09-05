<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;

class TriiboController extends Controller
{
    public function fix()
    {
        $w = "Wallet";

        $db      = Firebase::database();
        $fb      = $db->getReference($w);
        $wallets = $fb->getChildKeys();
        // $wallets = $fb->shallow()->getSnapshot();

        $i_ids = [];
        $i_aux = 0;
        $i     = 0;

        $y_ids = [];
        $y_aux = 0;
        $y     = 0;

        foreach ($wallets as $wallet_id)
        {
            $fb     = $db->getReference("$w/$wallet_id");
            $wallet = $fb->getValue();

            if (gettype($wallet) == "array")
            {
                foreach ($wallet as $key => $value)
                {
                    if ($key == "updateDate")
                    {
                        $i++;
                        $i_ids[] = $wallet_id;

                        unset($wallet[$key]);

                        dd($wallet);

                        // Log::channel("triibo")->info("updateDate", [ $wallet_id ]);
                    }

                    if (isset($value["vouchers"][0]))
                    {
                        $y++;
                        $y_ids[] = $wallet_id;
                        // Log::channel("triibo")->info("vouchers", [ $wallet_id ]);
                    }
                }
            }

            echo "$wallet_id - $i:$y\n";

            if ($i >= 800)
            {
                Log::channel("triibo")->info("updateDate", [ $i_ids ]);
                $i     = 0;
                $i_ids = [];
                $i_aux += $i;
            }

            if ($y >= 800)
            {
                Log::channel("triibo")->info("vouchers",   [ $y_ids ]);
                $y     = 0;
                $y_ids = [];
                $y_aux += $y;
            }
        }

        $i_aux += $i;
        $y_aux += $y;

        Log::channel("triibo")->info("updateDate", [ $i_ids ]);
        Log::channel("triibo")->info("vouchers",   [ $y_ids ]);

        dd($i_aux, $y_aux);
    }


    public function unsetUpdateDate()
    {
        $w = "Wallet";

        $db      = Firebase::database();
        $fb      = $db->getReference($w);
        $wallets = $fb->getChildKeys();
        // $i = 0;

        foreach ($wallets as $wallet_id)
        {
            $fb     = $db->getReference("$w/$wallet_id");
            $wallet = $fb->getValue();

            if (gettype($wallet) == "array")
            {
                foreach ($wallet as $key => $value)
                {
                    if ($key == "updateDate")
                    {
                        unset($wallet[$key]);
                        // $i++;
                        echo "$wallet_id\n";
                        // Log::channel("triibo")->info("unsetUpdateDate", [ $wallet_id ]);
                    }
                }
            }
        }
    }
}
