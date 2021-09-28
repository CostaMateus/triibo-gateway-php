<?php

namespace App\Http\Integrations;

use Devio\Pipedrive\Pipedrive;
use Illuminate\Support\Facades\Log;

class PipedriveIntegration
{

    /**
     * Undocumented function
     *
     * @param string $class
     * @param array $ids
     * @return void
     */
    public static function updateMauticId(string $class, array $ids)
    {
        // Log::channel("marketplace")->info(ucfirst($class), [ "data" => "selecionando API class: $class" ]);

        $pipedrive = new Pipedrive(config("pipedrive.token"));

        switch ($class)
        {
            case "persons":
                $pipedrive->persons->update($ids["pd_id"], [
                    "bd7287f0a9a02de60a8aff83b5905c489b7cf007" => $ids["mautic_id"]
                ]);
            break;

            case "organizations":
                $pipedrive->organizations->update($ids["pd_id"], [
                    "918dace587a4707755926d7c02c3bb46ed53d539" => $ids["mautic_id"]
                ]);
            break;
        }

        // Log::channel("marketplace")->info(ucfirst($class), [ "data" => "chamada API finalizada" ]);
    }

}
