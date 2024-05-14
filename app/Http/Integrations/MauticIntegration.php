<?php

namespace App\Http\Integrations;

use Mautic\Api\Api;
use Mautic\MauticApi;
use App\Helpers\Helper;
use Mautic\Auth\ApiAuth;
use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Log;
use Triibo\Mautic\Models\MauticConsumer;
use App\Http\Integrations\PipedriveIntegration;

class MauticIntegration
{
    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL    = "marketplace";

    // Static property to hold API instances to prevent multiple initializations.
    private static $apiInstances = [];

    /**
     * Get the specific API instance given the class name.
     *
     * @param   string  $class  The class name of the API to retrieve.
     * @return  Api             The API instance for the given class.
     */
    private static function getApi( string $class )
    {
        // Check if an API instance for the given class already exists in the cache.
        if ( isset( self::$apiInstances[ $class ] ) )
            return self::$apiInstances[ $class ];

        // Get the Mautic factory object to create or refresh tokens.
        $factory   = Mautic::getFactory();

        // Retrieve the most recent MauticConsumer record from the database.
        $consumer  = MauticConsumer::whereNotNull( "id" )->orderBy( "created_at", "desc" )->first();

        // Check if the token has expired and refresh it if necessary.
        $expired   = $factory->checkExpirationTime( $consumer->expires );
        $consumer  = ( $expired ) ? $factory->refreshToken( $consumer->refresh_token ) : $consumer;

        // Get the Mautic connection settings from the configuration.
        $settings  = config( "mautic.connections.main" );

        // Update the settings with the latest access token.
        $settings[ "accessToken" ] = $consumer->access_token;

        // Initialize the API authentication and create the API instance.
        $init_auth = new ApiAuth();
        $auth      = $init_auth->newAuth( $settings );
        $api_url   = $settings[ "baseUrl" ];
        $api       = new MauticApi();

        // Store the new API instance in the static cache.
        self::$apiInstances[ $class ] = $api->newApi( $class, $auth, $api_url );

        // Return the new API instance.
        return self::$apiInstances[ $class ];
    }

    /**
     * Updates an existing entity or creates a new one based on the provided class and data.
     *
     * It logs the process and handles the deletion flag to reset certain fields if needed.
     *
     * @param   string      $class      The class name of the entity to update or create.
     * @param   array       $data       The data to be used for updating or creating the entity.
     * @param   boolean     $delete     Indicates whether to reset certain fields (used when deleting).
     * @return  void
     */
    public static function updateOrCreate( string $class, array $data, bool $delete )
    {
        // Retrieve the API instance for the given class.
        $mautic_api  = self::getApi( $class );
        // Translate the data according to the class-specific requirements.
        $data        = self::dataTranslator( $class, $data );

        // Check if an ID is provided and search for the entity by ID.
        if ( !empty( $data[ "id" ] ) )
        {
            $response      = self::searchMauticByField( $mautic_api, "ids", $data[ "id" ] );
            log_message( channel: self::LOG_CHANNEL, message: "Persons: Searching by id" );
        }
        // If no ID, check if an email is provided and search by email.
        elseif ( !empty( $data[ "email" ] ) )
        {
            $response      = self::searchMauticByField( $mautic_api, "email", $data[ "email" ] );
            log_message( channel: self::LOG_CHANNEL, message: "Persons: Searching by email" );

            // If an entity is found, update the Mautic ID in the organizations.
            if ( !empty( $response[ "total" ] ) )
                self::updateMauticId( "organizations", key( $response[ "object" ] ), $data[ "pd_org_id" ], true );
        }
        // If no ID or email is provided, log the absence of searchable data and return early.
        else
        {
            $message_ids   = "| mautic_id: 0 | pipedrive_id: " . ( $data[ "pd_org_id" ] ?? 0 );

            log_message( channel: self::LOG_CHANNEL, message: "Persons: No data to search {$message_ids}" );

            return;
        }

        // If an entity is found, update it; otherwise, create a new one.
        if ( !empty( $response[ "total" ] ) )
        {
            // If the delete flag is set, reset certain fields.
            if ( $delete )
            {
                $data[ "pd_pipeline"        ] = 0;
                $data[ "pd_pipeline_status" ] = 0;
                $data[ "pd_pipeline_label"  ] = 0;
            }

            // Set the ID for the entity to be updated.
            $data[ "id" ]  = key( $response[ "object" ] );

            log_message( channel: self::LOG_CHANNEL, message: "Persons: Contact updated" );
        }
        else
        {
            // Set the ID to 0 for creating a new entity.
            $data[ "id" ]  = 0;

            log_message( channel: self::LOG_CHANNEL, message: "Persons: Contact created" );
        }

        // Save the changes or create the new entity using the Mautic API.
        self::saveCreateOrUpdateMautic( $mautic_api, "persons", $data, "pd_org_id" );

        // Log the final outcome of the operation.
        $message_ids = "mautic_id: " . ( $data[ "id" ] ?? 0 ) . " | pipedrive_id: " . ( $data[ "pd_org_id" ] ?? 0 );
        log_message( channel: self::LOG_CHANNEL, message: "Persons: {$message_ids}" );
    }

    /**
     * Updates the stage of a contact in Mautic based on the provided data.
     *
     * It logs the process and handles the update or creation of the contact's deal stage.
     *
     * @param   string  $class  The class name of the entity to update.
     * @param   array   $data   The data to be used for updating the contact's deal stage.
     * @return  void
     */
    public static function dealUpdate( string $class, array $data )
    {
        // Get the Mautic API instance for contacts.
        $mautic_api  = self::getApi( "contacts" );
        // Translate the data according to the class-specific requirements.
        $data        = self::dataTranslator( $class, $data );

        // Check if the deal is marked as deleted and log it.
        if ( !$data[ "aux_active" ] && $data[ "aux_deleted" ] )
            log_message( channel: self::LOG_CHANNEL, message: "Deals: Deleted deal" );

        // Remove auxiliary fields that are not needed for the update.
        unset( $data[ "aux_active" ], $data[ "aux_deleted" ] );

        // Check if an ID is provided and search for the contact by ID.
        if ( !empty( $data[ "id" ] ) )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Deals: Searching by id" );

            $response      = self::searchMauticByField( $mautic_api, "ids", $data[ "id" ] );
        }
        // If no ID, check if an email is provided and search by email.
        elseif ( !empty( $data[ "email" ] ) )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Deals: Searching by email" );

            $response      = self::searchMauticByField( $mautic_api, "email", $data[ "email" ] );

            // If a contact is found, update the Mautic ID in the organizations.
            if ( !empty( $response[ "total" ] ) )
                self::updateMauticId( "organizations", key( $response[ "object" ] ), $data[ "pd_org_id" ], true );
        }
        // If no ID or email is provided, log the absence of searchable data and return early.
        else
        {
            $message_ids   = "| mautic_id: 0 | pipedrive_id: " . ( $data[ "pd_org_id" ] ?? 0 );
            log_message( channel: self::LOG_CHANNEL, message: "Deals: No data to search {$message_ids}" );
            return;
        }

        // If no matching contact is found in Mautic, log the event and return.
        if ( empty( $response[ "total" ] ) )
        {
            $message_ids   = "| mautic_id: " . ( $data[ "id" ] ?? 0 ) . " | pipedrive_id: " . ( $data[ "pd_org_id" ] ?? 0 );
            log_message( channel: self::LOG_CHANNEL, message: "Deals: Contact not found {$message_ids}" );
            return;
        }

        // Proceed with updating the deal stage.
        $data[ "id" ] = $data[ "id" ] ?? key( $response[ "object" ] );
        $old_data     = $response[ "object" ][ $data[ "id" ] ][ "fields" ][ "all" ];

        // Update the deal fields with the new stage, status, and label.
        self::treatFieldsDeal( $data, ( $old_data[ "pd_pipeline"        ] ?? "" ), "pd_pipeline",        "new_stage_id", "old_stage_id" );
        self::treatFieldsDeal( $data, ( $old_data[ "pd_pipeline_status" ] ?? "" ), "pd_pipeline_status", "new_status",   "old_status"   );
        self::treatFieldsDeal( $data, ( $old_data[ "pd_pipeline_label"  ] ?? "" ), "pd_pipeline_label",  "new_label",    "old_label"    );

        // Perform the edit operation on the contact.
        $mautic_api->edit( $data[ "id" ], $data );

        // Log the final outcome of the operation.
        $message_ids   = "| mautic_id: " . ( $data[ "id" ] ?? 0 ) . " | pipedrive_id: " . ( $data[ "pd_org_id" ] ?? 0 );
        log_message( channel: self::LOG_CHANNEL, message: "Deals: Contact deal updated {$message_ids}" );
    }

    /**
     * Converts Pipedrive data to the format required by Mautic.
     *
     * This function takes data from Pipedrive and translates it into the format
     * needed by Mautic, depending on the type of entity being processed.
     * It supports translating data for deals, contacts, and contact fields.
     *
     * @param   string  $class  The type of entity to translate data for.
     * @param   array   $data   The data to be translated.
     * @return  array           The translated data ready for use with Mautic.
     */
    private static function dataTranslator( string $class, array $data )
    {
        switch ( $class )
        {
            // Translate deal-related data for Mautic.
            case "deals":
                $response = [
                    "id"           => $data[ "mautic_id" ] ?? NULL,
                    "email"        => $data[ "email"     ],
                    "pd_org_id"    => $data[ "org_id"    ],
                    "pd_org_name"  => $data[ "org_name"  ],

                    // New deal stage information.
                    "new_stage_id" => $data[ "new_stage_id" ] ?? 0,
                    "new_status"   => Helper::statusLabelDeal( $data[ "new_status" ], $data[ "new_stage_id" ] ),
                    "new_label"    => Helper::statusLabelDeal( $data[ "new_label"  ], $data[ "new_stage_id" ], false ),

                    // Old deal stage information.
                    "old_stage_id" => $data[ "old_stage_id" ] ?? 0,
                    "old_status"   => Helper::statusLabelDeal( $data[ "old_status" ], $data[ "old_stage_id" ] ),
                    "old_label"    => Helper::statusLabelDeal( $data[ "old_label"  ], $data[ "old_stage_id" ], false ),

                    // Auxiliary information about the deal's active and deleted status.
                    "aux_active"   => $data[ "active"  ],
                    "aux_deleted"  => $data[ "deleted" ],
                ];
            break;

            // Translate contact-related data for Mautic.
            case "contacts":
                $id       = $data[ "918dace587a4707755926d7c02c3bb46ed53d539" ] ?? null; // Mautic Id
                $email    = $data[ "a007cb7cef80c909678503cbcfe49def03cad573" ] ?? null; // E-mail
                $phone    = $data[ "a93024fa0b5785ae4b3b1b49a18bb5267b87dfdd" ];         // Telefone
                $lead     = $data[ "c917493d194dbeac05ccbfd26d152f95b8763ec9" ] ?? 0;    // Fonte do Lead
                $resp     = $data[ "fa0b068c55533f0298d1d3d26997cdbc2f17eca6" ] ?? 0;    // Vendedor resposavel

                $response = [
                    "id"                    => $id,
                    "firstname"             => "",
                    "lastname"              => "",
                    "email"                 => $email,
                    "pd_lead"               => Helper::getOrgCustomField( 4028, $lead ),
                    "pd_responsible_seller" => Helper::getOrgCustomField( 4032, $resp ),
                    "pd_org_id"             => $data[ "id"   ],
                    "pd_org_name"           => $data[ "name" ],
                    "pd_org_phone"          => $phone,
                ];
            break;

            // Translate contact field data for Mautic.
            case "contactFields":
                $response = [
                    "id"                => $data[ "id"   ],
                    "name"              => $data[ "name" ],
                ];
            break;
        }

        return $response;
    }

    /**
     * Searches in Mautic given a filter field.
     *
     * This function queries the Mautic API and returns a list of objects based on
     * the provided field and search term. It supports pagination through the start and
     * end parameters, allowing for retrieval of a specific subset of results.
     *
     * @param   Api     $mautic_api     The Mautic API object used to perform the search.
     * @param   string  $field          The field to filter by. If empty, all records are returned.
     * @param   string  $search         The search term to apply to the filter field. If empty, no specific search is performed.
     * @param   int     $start          The start index for the search results, used for pagination.
     * @param   int     $end            The end index for the search results, used for pagination.
     * @return  array                   An associative array containing the 'total' number of results found and the 'object' list of results.
     */
    private static function searchMauticByField( Api $mautic_api, string $field = "", string $search = "", int $start = 0, int $end = 100000 )
    {
        // Perform the search using the Mautic API. If no field is specified, retrieve all records.
        $obj         = empty( $field ) ? $mautic_api->getList( "", $start, $end ) : $mautic_api->getList( "$field:$search" );

        // Retrieve the name of the list from the Mautic API object.
        $list_name   = $mautic_api->listName();

        // Return the total number of results and the list of objects.
        return [
            "total"  => $obj[ "total"    ] ?? null,
            "object" => $obj[ $list_name ] ?? null
        ];
    }

    /**
     * Creates or updates a contact or company in Mautic and updates the mautic_id in Pipedrive if needed.
     *
     * @param   Api     $mautic_api     Mautic API object for making requests.
     * @param   string  $entity_type    The type of entity to update ('persons' for contact, otherwise 'company').
     * @param   array   $data           The data to be sent to Mautic for creating or updating.
     * @param   string  $pipe_id_name   The field name of the Pipedrive ID in the data array.
     * @return  void
     */
    private static function saveCreateOrUpdateMautic( Api $mautic_api, string $entity_type, array $data, string $pipe_id_name )
    {
        // Determine if the entity is new based on the presence of an 'id' in the data array.
        $entity_id     = $data[ "id" ] ?? 0;
        $is_new_entity = $entity_id === 0;

        // Attempt to create or update the entity in Mautic.
        $response      = $mautic_api->edit( $entityId, $data, $is_new_entity );

        // If there are errors in the response, log them and exit the function.
        if ( isset( $response[ "errors" ] ) )
        {
            Helper::log( "Persons", json_encode( $response, true ), $entity_id, ( $data[ "pd_org_id" ] ?? 0 ) );
            return;
        }

        // Determine the Mautic entity ID from the response, depending on whether it's a contact or a company.
        $mautic_entity_id = $response[ $entity_type === "persons" ? "contact" : "company" ][ "id" ];

        // Update the mautic_id in Pipedrive with the new or updated Mautic entity ID.
        self::updateMauticId( $entity_type, $mautic_entity_id, $data[ $pipe_id_name ], true );
    }

    /**
     * Updates the Mautic ID in Pipedrive.
     *
     * This function logs the action of creating or updating an entity in the 'marketplace' channel
     * and updates the Mautic ID in Pipedrive if the entity already has an ID.
     *
     * @param   string      $entity_type    The type of entity ('persons' for contact, 'organizations' for company).
     * @param   int         $mautic_id      The Mautic ID to be updated in Pipedrive.
     * @param   int         $pipedrive_id   The Pipedrive ID of the entity.
     * @param   boolean     $has_id         Flag indicating whether the entity already has an ID.
     * @return  void
     */
    private static function updateMauticId( string $entity_type, int $mautic_id, int $pipedrive_id, bool $has_id )
    {
        // Log the creation or update action in the 'marketplace' channel.
        Log::channel( "marketplace" )->info( ucfirst( $entity_type ), [
            "data"      => "created/updated",
            "mautic_id" => $mautic_id,
            "pd_org_id" => $pipedrive_id
        ] );

        // If the entity already has an ID, update the Mautic ID in Pipedrive.
        if ( $has_id )
        {
            // The entity type is hardcoded to 'organizations', which may need to be dynamic based on the $entity_type.
            PipedriveIntegration::updateMauticId( "organizations", [
                "pd_id"     => $pipedrive_id,
                "mautic_id" => $mautic_id,
            ] );
        }
    }

    /**
     * Updates the stages, status, and labels in Mautic with the data imported from Pipedrive.
     *
     * It fetches all stages from Pipedrive, processes and sorts them, and then updates the corresponding fields in Mautic.
     * It also logs the outcome of the import process.
     *
     * @return void
     */
    public static function updateStages()
    {
        // Initialize Mautic and Pipedrive APIs.
        $mautic_api    = self::getApi( "contactFields" );
        $pipedrive_api = new Pipedrive( config( "pipedrive.token" ) );

        // Fetch all stages from Pipedrive.
        $stages = $pipedrive_api->stages()->all()->getData();

        // Process and sort stages.
        $processed_stages = [];

        foreach ( $stages as $stage )
        {
            $processed_stages[] = [
                "value"    => $stage->id,
                "label"    => $stage->name,
                "pd_id"    => $stage->pipeline_id,
                "order_nr" => $stage->order_nr
            ];
        }

        // Sort the stages by pipeline ID and order number.
        array_multisort(
            array_column( $processed_stages, "pd_id"    ), SORT_ASC,
            array_column( $processed_stages, "order_nr" ), SORT_ASC,
            $processed_stages
        );

        // Prepare data for Mautic field update.
        $data_stages         = [
            "properties" => [
                "list" => array_merge(
                    [ [ "value" => 0, "label" => "Sem Funil" ] ],
                    array_map( function ( $stage )
                    {
                        return [
                            "value" => $stage[ "value" ],
                            "label" => $stage[ "label" ]
                        ];
                    }, $processed_stages )
                ),
            ]
        ];

        // Update Mautic field for Pipedrive Funil with the processed stages.
        // 214 = Mautic field ID for Pipedrive Funil.
        $response_1          = $mautic_api->edit( 214, $data_stages );

        // =======================
        // Process status and labels once and use for both updates.
        $stage_status_labels = self::processStages($processedStages, self::getLabels($pipedrive_api, 12464)); // 12464 = Pipe ID for Status

        $data_status         = [
            "properties" => [
                "list" => array_merge( [ [ "value" => 0, "label" => "Sem Status" ] ], $stage_status_labels )
            ]
        ];

        // Update Mautic field for Pipedrive Funil Status with the processed status labels.
        // 215 = Mautic field ID for Pipedrive Funil Status.
        $response_2          = $mautic_api->edit( 215, $data_status );

        // =======================
        // Update labels in Mautic
        $data_labels         = [
            "properties" => [
                "list" => array_merge( [ [ "value" => 0, "label" => "Sem Etiqueta" ] ], $stage_status_labels )
            ]
        ];

        // Update Mautic field for Pipedrive Funil Etiqueta with the processed labels
        // 218 = Mautic field ID for Pipedrive Funil Etiqueta
        $response_3          = $mautic_api->edit( 218, $data_labels );

        // Determine the log message based on the presence of errors in the responses
        $message             = ( !isset( $response_1[ "errors" ] ) && !isset( $response_2[ "errors" ] ) && !isset( $response_3[ "errors" ] ) )
                                ? "Successfully imported"
                                : "Import failed";

        // Log the outcome of the import process
        log_message( channel: self::LOG_CHANNEL, message: "Stages: $message" );
    }

    /**
     * Retrieves and processes labels from Pipedrive deal fields based on specific criteria.
     *
     * It modifies the label to capitalize the second word if the label matches predefined criteria.
     *
     * @param   Pipedrive   $pipedrive_api  An instance of the Pipedrive API client.
     * @param   int         $id             The identifier of the deal field to retrieve options from.
     * @return  array                       An array of processed labels.
     */
    private static function getLabels( Pipedrive $pipedrive_api, int $id )
    {
        // Retrieve the deal field data and extract the options.
        $response = $pipedrive_api->DealFields->find( $id )->getData();

        // Define the array of labels to check against outside the loop.
        $search_labels = [
            "em aberto",
            "em espera",
            "em degustação"
        ];

        // Use array_map to process each option and modify the label if it matches the criteria.
        $data = array_map( function ( $option ) use ( $search_labels )
        {
            // Convert the label to lowercase for comparison.
            $label_lower = mb_strtolower( $option->label );

            // Check if the lowercase label is in the array of search labels.
            if ( in_array( $label_lower, $search_labels, true ) )
            {
                // Split the label into parts and capitalize the second word if it exists.
                $label_parts   = explode( " ", $option->label );
                $option->label = ucfirst( $label_parts[ 1 ] ?? "" );
            }

            // Return the possibly modified label.
            return $option->label;

        }, $response->options );

        // Return the array of processed labels.
        return $data;
    }

    /**
     * Processes and combines stage and label information into a single array.
     *
     * This function processes an array of stages and an array of labels to create a new array
     * that combines the value and label from each stage with each label.
     *
     * @param   array   $stages     An array of stages, each with 'value' and 'label' keys.
     * @param   array   $labels     An array of labels.
     * @return  array               The combined array of processed stages and labels.
     */
    private static function processStages( array $stages, array $labels )
    {
        // Initialize the combined array that will store the final results.
        $combined = [];

        // Use array_walk to iterate over each stage in the stages array.
        array_walk( $stages, function ( $stage ) use ( $labels, &$combined )
        {
            // Merge the combined array with the result of array_map, which processes each label.
            $combined = array_merge( $combined, array_map( function ( $label ) use ( $stage )
            {
                // Return an associative array with combined 'value' and 'label' keys.
                return [
                    "value" => $stage[ "value" ] .  ":"  . $label[ 0 ],
                    "label" => $stage[ "label" ] . " : " . $label,
                ];
            }, $labels ) );
        } );

        // Return the combined array after processing all stages and labels.
        return $combined;
    }

    /**
     * Processes the deal fields by updating, adding, or removing values based on new and old data.
     *
     * It manipulates a pipe-delimited string to reflect changes in field values.
     *
     * @param   array   &$data      Reference to the data array that will be processed.
     * @param   string  $old_data   A string containing old data delimited by "|".
     * @param   string  $field      The key of the field in the data array that will be updated.
     * @param   string  $new        The key for the new value in the data array.
     * @param   string  $old        The key for the old value in the data array.
     * @return  void
     */
    private static function treatFieldsDeal( array &$data, string $old_data, string $field, string $new, string $old )
    {
        $arr = explode( "|", $old_data );
        $arr = array_filter( $arr, function( $value )
        {
            return $value !== "0";
        } );

        if ( isset( $data[ $new ] ) )
        {
            if ( isset( $data[ $old ] ) )
            {
                // Update: removes the old value and adds the new one.
                $key = array_search( $data[ $old ], $arr, true );

                if ( $key !== false )
                    unset( $arr[ $key ] );

                $arr[] = $data[ $new ];
            }
            else
            {
                // Add: just adds the new value.
                $arr[] = $data[ $new ];
            }
        }
        elseif ( isset( $data[ $old ] ) )
        {
            // Remove: just removes the old value.
            $key = array_search( $data[ $old ], $arr, true );

            if ( $key !== false )
                unset( $arr[ $key ] );
        }

        // Remove temporary fields.
        unset( $data[ $new ], $data[ $old ] );

        // Update the field with the processed values or set to "0" if empty.
        $data[ $field ] = $arr ? implode( "|", $arr ) : "0";
    }

    /**
     * Synchronizes the organization IDs between Mautic and Pipedrive.
     *
     * Updates the Mautic ID (pd_org_id) in Pipedrive and the Pipedrive ID (mautic_id) in Mautic.
     *
     * @return void
     */
    public static function syncIdsMauticPipe()
    {
        // Initialize the Mautic and Pipedrive APIs.
        $mautic_api    = self::getApi( "contacts" );
        $pipedrive_api = new Pipedrive( config( "pipedrive.token" ) );

        // Array to hold all organizations from Pipedrive.
        $all_orgs      = [];
        $start         = 0;

        // Fetch organizations from Pipedrive in batches of 500 until there are no more.
        do
        {
            $objs = $pipedrive_api->organizations->all( [
                "start" => $start,
                "limit" => 500
            ] )->getData();

            // Merge the fetched organizations into the all_orgs array.
            $all_orgs = array_merge( $all_orgs, $objs );

            // Increment the start position for the next batch.
            $start += 500;
        }
        while ( count( $objs ) === 500 );

        echo "\nE-mail : pipe ID : mautic ID";

        // Iterate over all organizations to synchronize IDs.
        foreach ( $all_orgs as $objs )
        {
            foreach ( $objs as $org )
            {
                // Extract the email from the organization using the custom field hash.
                $email          = $org->{"a007cb7cef80c909678503cbcfe49def03cad573"};

                // Skip if the email is not set.
                if ( !$email ) continue;

                // Search for the contact in Mautic by email.
                $response       = self::searchMauticByField( $mautic_api, "email", $email );

                // Skip if no matching contact is found in Mautic.
                if ( !$response[ "total" ] ) continue;

                // Initialize Mautic ID.
                $mautic_id      = null;

                // If multiple contacts are found, find the correct one by matching the email.
                if ( $response[ "total" ] > 1 )
                {
                    foreach ( $response[ "object" ] as $key => $org )
                    {
                        if ( isset( $org[ "fields" ] ) &&
                             isset( $org[ "fields" ][ "all" ] ) &&
                             isset( $org[ "fields" ][ "all" ][ "email" ] ) )
                        {
                            if ( $org[ "fields" ][ "all" ][ "email" ] === $email )
                            {
                                $mautic_id = $key;
                                break;
                            }
                        }
                    }
                }

                $mautic_id      = $mautic_id ?? key( $response[ "object" ] );

                // If a Mautic ID is found, update both Mautic and Pipedrive with the corresponding IDs
                $data_mautic    = [ "pd_org_id"                                => $org->id   ];
                $data_pipedrive = [ "918dace587a4707755926d7c02c3bb46ed53d539" => $mautic_id ];

                // Update the contact in Mautic with the Pipedrive organization ID.
                $mautic_api->edit( $mautic_id, $data_mautic, false );

                // Update the organization in Pipedrive with the Mautic contact ID.
                $pipedrive_api->organizations->update( $org->id, $data_pipedrive );

                echo "\n$email : $org->id : $mautic_id";
            }
        }
    }
}
