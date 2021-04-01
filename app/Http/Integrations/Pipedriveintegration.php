<?php

namespace App\Http\Integrations;

use Devio\Pipedrive\Pipedrive;

class PipedriveIntegration
{

    /**
     * Undocumented function
     *
     * @param String $class
     * @param Array $ids
     * @return void
     */
    public static function updateMauticId(String $class, Array $ids)
    {
        $token     = config('pipedrive.token');
        $pipedrive = new Pipedrive($token);
        // $res       = $pipedrive->$class->all();

        switch ($class)
        {
            case 'persons':
                $res = $pipedrive->persons->update($ids['pipe_id'], [
                    'bd7287f0a9a02de60a8aff83b5905c489b7cf007' => $ids['mautic_id']
                ]);
            break;

            case 'organizations':
            break;
        }
    }

}
