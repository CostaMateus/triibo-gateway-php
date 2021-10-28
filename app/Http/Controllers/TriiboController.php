<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;

class TriiboController extends Controller
{
    public function unsetUpdateDate()
    {
        $i = 0;
        $w = "Wallet";

        $db      = Firebase::database();
        $fb      = $db->getReference($w);
        $wallets = $fb->getChildKeys();

        foreach ($wallets as $wallet_id)
        {
            $fb2    = $db->getReference("$w/$wallet_id");
            $wallet = $fb2->getValue();

            if (gettype($wallet) == "array")
            {
                foreach ($wallet as $key => $value)
                {
                    if ($key == "updateDate")
                    {
                        unset($wallet[$key]);
                        $fb->update([$wallet_id => $wallet]);
                        // echo "$wallet_id\n";
                        $i++;
                    }
                }
            }
        }

        Log::channel("triibo")->info("unset-updateDate", [ "unset-updateDate: $i wallets fixed" ]);
    }


    public function unsetVouchers()
    {
        $i = 0;
        $w = "Wallet";

        $db      = Firebase::database();
        $fb      = $db->getReference($w);
        $wallets = $fb->getChildKeys();

        foreach ($wallets as $wallet_id)
        {
            $fb2    = $db->getReference("$w/$wallet_id");
            $wallet = $fb2->getValue();

            if (gettype($wallet) == "array")
            {
                foreach ($wallet as &$value)
                {
                    if (isset($value["vouchers"]))
                    {
                        foreach ($value["vouchers"] as $key => $id)
                        {
                            if (strlen($key) > 2) continue;

                            $value["vouchers"][$id] = [ "value" => 1 ];
                            unset($value["vouchers"][$key]);

                            $fb->update([$wallet_id => $wallet]);
                            // echo "$wallet_id\n";
                            $i++;
                        }
                    }
                }
            }
        }

        Log::channel("triibo")->info("unset-vouchers", [ "unset-vouchers: $i wallets fixed" ]);
    }


    public function chatguru()
    {

    }
}
