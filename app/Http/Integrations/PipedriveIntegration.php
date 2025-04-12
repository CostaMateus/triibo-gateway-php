<?php

namespace App\Http\Integrations;

use Devio\Pipedrive\Pipedrive;

class PipedriveIntegration
{
    // Define custom field keys as class constants
    private const PERSON_FIELD_KEY = "bd7287f0a9a02de60a8aff83b5905c489b7cf007";
    private const ORGANIZATION_FIELD_KEY = "918dace587a4707755926d7c02c3bb46ed53d539";

    /**
     * Updates the Mautic ID in Pipedrive for a given entity class and IDs.
     *
     * This method takes the entity class (either 'persons' or 'organizations') and an associative array
     * containing the Pipedrive ID ('pd_id') and the Mautic ID ('mautic_id'). It then updates the
     * corresponding entity in Pipedrive with the provided Mautic ID.
     *
     * @param string $class the class of the entity to update ('persons' or 'organizations')
     * @param array  $ids   associative array with 'pd_id' for the Pipedrive ID and 'mautic_id' for the Mautic ID
     *
     * @return void
     */
    public static function updateMauticId( string $class, array $ids ) : void
    {
        $message = ucfirst( $class ).": selecionando API...";

        log_message( channel: "marketplace", message: $message );

        echo $message;

        // Initialize Pipedrive API with the provided token from configuration.
        $pipedrive = new Pipedrive( config( "pipedrive.token" ) );

        // Determine the appropriate field key based on the entity class.
        $field_key = ( $class === "persons" ) ? self::PERSON_FIELD_KEY : self::ORGANIZATION_FIELD_KEY;

        // Update the entity in Pipedrive with the new Mautic ID.
        $pipedrive->{$class}->update( $ids[ "pd_id" ], [$field_key => $ids[ "mautic_id" ]] );

        $message = ucfirst( $class ).": chamada API finalizada...";

        log_message( channel: "marketplace", message: $message );

        echo $message;
    }
}
