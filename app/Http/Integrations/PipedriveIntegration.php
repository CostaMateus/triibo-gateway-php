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
        $aux       = ucfirst($class);

        $pipedrive = new Pipedrive(config("pipedrive.token"));

        Log::channel("marketplace")->info("$aux", [
            "data" => "selecionando API class: $class"
        ]);

        switch ($class)
        {
            case "persons":
                $res = $pipedrive->persons->update($ids["pipe_id"], [
                    "bd7287f0a9a02de60a8aff83b5905c489b7cf007" => $ids["mautic_id"]
                ]);
                Log::channel("marketplace")->info("$aux", [
                    "data" => "chamada API finalizada"
                ]);
            break;

            case "organizations":
                $res = $pipedrive->organizations->update($ids["pipe_id"], [
                    "918dace587a4707755926d7c02c3bb46ed53d539" => $ids["mautic_id"]
                ]);
                Log::channel("marketplace")->info("$aux", [
                    "data" => "chamada API finalizada"
                ]);
            break;
        }
    }

}
