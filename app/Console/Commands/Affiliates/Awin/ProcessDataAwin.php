<?php

namespace App\Console\Commands\Affiliates\Awin;

use DB;
use Storage;
use App\Helpers\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Database\Reference;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Http\Controllers\Affiliates\AwinController;
use App\Http\Integrations\AffiliatesIntegration\AwinIntegration;

class ProcessDataAwin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var     string
     */
    protected $signature   = "awin:process {--option= : Options: establishments, promotions, images, all (EPI) or reports }";

    /**
     * The console command description.
     *
     * @var     string
     */
    protected $description = "Process Awin data";

    /**
     * The options that require loading data.
     *
     * @var     array
     */
    protected $allowed_options = [
        "populate",
        "establishments",
        "promotions",
        "images",
        "all",
        "reports"
    ];

    /**
     * Create a new command instance.
     *
     * @return  void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return  int
     */
    public function handle()
    {
        // echo "Establishments ============================================================\n";
        // // Establishments
        // $db                       = Firebase::database();
        // $establishments_reference = $db->getReference( "/Estabelecimento-v2"            );
        // $e_approvals_reference    = $db->getReference( "/Estabelecimento-Aprovacoes"    );
        // $e_changes_reference      = $db->getReference( "/Establishment-changes-request" );

        // $establishments           = $establishments_reference->getValue();
        // $e_approvals              = $e_approvals_reference->getValue();
        // $e_changes                = $e_changes_reference->getValue();

        // echo "Approvals =================================================================\n";

        // foreach ( $e_approvals as $ea_push => $e_approval )
        // {
        //     if ( !isset( $e_approval[ "id"          ] ) ) continue;
        //     if ( !isset( $e_approval[ "status"      ] ) ) continue;
        //     if ( !isset( $e_approval[ "origin"      ] ) ) continue;
        //     if ( !isset( $e_approval[ "requesterId" ] ) ) continue;

        //     if ( "J4NjCHtvxuRojrXb29whbsNPAy33" != $e_approval[ "requesterId" ] ) continue;

        //     if ( !isset( $establishments[ $e_approval[ "id" ] ]             ) ) continue;
        //     if ( !isset( $establishments[ $e_approval[ "id" ] ][ "origin" ] ) ) continue;

        //     if ( "awin" != $e_approval[ "origin" ] ) continue;

        //     if ( "awin" != $establishments[ $e_approval[ "id" ] ][ "origin" ] ) continue;

        //     echo "{$ea_push} | {$e_approval[ "id" ]} | {$establishments[ $e_approval[ "id" ] ][ "nome" ]}\n";

        //     $e_approvals_reference->update( [ $ea_push => null ] );
        // }

        // echo "Changes ===================================================================\n";

        // foreach ( $e_changes as $ec_push => $e_change )
        // {
        //     if ( !isset( $e_change[ "id"              ] ) ) continue;
        //     if ( !isset( $e_change[ "type"            ] ) ) continue;
        //     if ( !isset( $e_change[ "requesterUid"    ] ) ) continue;
        //     if ( !isset( $e_change[ "establishmentId" ] ) ) continue;

        //     if ( !isset( $establishments[ $e_change[ "establishmentId" ] ] ) ) continue;

        //     if ( !isset( $establishments[ $e_change[ "establishmentId" ] ][ "origin" ] ) ) continue;

        //     if ( "J4NjCHtvxuRojrXb29whbsNPAy33" != $e_change[ "requesterUid" ] ) continue;

        //     if ( "awin" != $establishments[ $e_change[ "establishmentId" ] ][ "origin" ] ) continue;

        //     echo "{$ec_push} | {$e_change[ "establishmentId" ]} | {$establishments[ $e_change[ "establishmentId" ] ][ "nome" ]}\n";

        //     $e_changes_reference->update( [ $ec_push => null ] );
        // }

        // echo "===========================================================================\n";
        // echo "===========================================================================\n";
        // echo "Promotions ================================================================\n";
        // // Promotions
        // $db                    = Firebase::database();
        // $promotions_reference  = $db->getReference( "/Store"                 );
        // $p_approvals_reference = $db->getReference( "/Store-Aprovacoes"      );
        // $p_changes_reference   = $db->getReference( "/Store-changes-request" );

        // $promotions            = $promotions_reference->getValue();
        // $p_approvals           = $p_approvals_reference->getValue();
        // $p_changes             = $p_changes_reference->getValue();

        // echo "Approvals =================================================================\n";

        // foreach ( $p_approvals as $pa_push => $p_approval )
        // {
        //     if ( !isset( $p_approval[ "id"                ] ) ) continue;
        //     if ( !isset( $p_approval[ "status"            ] ) ) continue;
        //     if ( !isset( $p_approval[ "origin"            ] ) ) continue;
        //     if ( !isset( $p_approval[ "requesterId"       ] ) ) continue;
        //     if ( !isset( $p_approval[ "establishmentId"   ] ) ) continue;
        //     if ( !isset( $p_approval[ "establishmentName" ] ) ) continue;

        //     if ( "J4NjCHtvxuRojrXb29whbsNPAy33" != $p_approval[ "requesterId" ] ) continue;

        //     if ( !isset( $promotions[ $p_approval[ "id" ] ]             ) ) continue;
        //     if ( !isset( $promotions[ $p_approval[ "id" ] ][ "origin" ] ) ) continue;

        //     if ( "awin" != $p_approval[ "origin" ] ) continue;

        //     if ( "awin" != $promotions[ $p_approval[ "id" ] ][ "origin" ] ) continue;

        //     echo "{$pa_push} | {$p_approval[ "id" ]} | {$p_approval[ "establishmentName" ]}\n";

        //     $p_approvals_reference->update( [ $pa_push => null ] );
        // }

        // echo "Changes ===================================================================\n";

        // foreach ( $p_changes as $pc_push => $p_change )
        // {
        //     if ( !isset( $p_change[ "id"                ] ) ) continue;
        //     if ( !isset( $p_change[ "type"              ] ) ) continue;
        //     if ( !isset( $p_change[ "requesterUid"      ] ) ) continue;
        //     if ( !isset( $p_change[ "storeId"           ] ) ) continue;
        //     if ( !isset( $p_change[ "establishmentId"   ] ) ) continue;
        //     if ( !isset( $p_change[ "establishmentName" ] ) ) continue;

        //     if ( !isset( $promotions[ $p_change[ "storeId" ] ] ) ) continue;

        //     if ( !isset( $promotions[ $p_change[ "storeId" ] ][ "origin" ] ) ) continue;

        //     if ( "J4NjCHtvxuRojrXb29whbsNPAy33" != $p_change[ "requesterUid" ] ) continue;

        //     if ( "awin" != $promotions[ $p_change[ "storeId" ] ][ "origin" ] ) continue;

        //     echo "{$pc_push} | {$p_change[ "storeId" ]} | {$p_change[ "establishmentName" ]}\n";

        //     $p_changes_reference->update( [ $pc_push => null ] );
        // }

        // dd( "ok" );
        // =============================================
        // =============================================

        // Get the option from the command line argument
        $option = $this->option( key: "option" );

        // Check if the option is valid
        if ( !in_array( $option, $this->allowed_options ) )
        {
            $this->printMessage( message: "Invalid option: Terminating process!" );
            return 0;
        }

        // If the option is "populate", populate the database with establishments and promotions
        if ( $option == "populate" )
        {
            $this->populate( type: "establishments" );
            $this->populate( type: "promotions"     );
            return 1;
        }

        // Determine the options to process based on the provided option
        $options = ( $option == "all" )
                    ? [ "establishments", "promotions", "images" ]
                    : [ $option ];

        // Process the selected options
        $this->processOptions( options: $options );
        return 1;
    }

    /**
     * Process options
     *
     * @param   array  $options
     * @return  void
     */
    private function processOptions( array $options )
    {
        $awin = null;

        // Loop through each option
        foreach ( $options as $option )
        {
            $name = ucfirst( $option );

            log_message( channel: "awin", message: "{$name}: Start processing...", print: true );

            // If the AwinController instance is not created yet, create it
            if ( !$awin )
            {
                $load = ( in_array( $option, [ "images", "reports" ] ) ) ? false : true;
                $awin = new AwinController( loadEstablishments: $load );
            }

            // Call the corresponding method on the AwinController instance
            $awin->{$option}();

            log_message( channel: "awin", message: "{$name}: End processing...", print: true );
        }
    }

    /**
     * One time execution, when migrating the DB
     *
     * @param   string  $type   Establishments or Promotions
     * @return  void
     */
    private function populate( string $type )
    {
        // Check if the type is "establishments"
        $__is_establishments = ( $type == "establishments" );

        // Check if the affiliates_{$type} table is already populated
        if ( 0 != DB::table( "affiliates_{$type}" )->count() )
        {
            $this->printMessage( message: "Populate: DB already populated, terminating process!" );
            return;
        }

        $this->printMessage( message: "Populate: Start processing of {$type}" );

        // Create a new AwinIntegration instance
        $integration   = new AwinIntegration( loadEstablishments: true );

        $now           = now();

        $awin_data     = [];
        $firebase_data = [];
        $local_data    = [];
        $empty_data    = $this->empty_data( type: $type );

        // Get the data from Awin and Firebase based on the type
        if ( $__is_establishments )
        {
            $awin_data     = $integration->getEstablishments();
            $firebase_data = $this->filterEstablishments( reference: Firebase::database()->getReference( "/Estabelecimento-v2" ) );
        }
        else
        {
            $awin_data     = $integration->getPromotions();
            $firebase_data = $this->filterPromotions( reference: Firebase::database()->getReference( "/Store" ) );
        }

        // Prepare the data for insertion into the database
        foreach ( $awin_data as $origin_id => $data )
        {
            $affiliate_id = $data[ "origin" ] . $origin_id;

            $new_data     = [
                "origin"       => $data[ "origin" ],
                "origin_id"    => $origin_id,
                "affiliate_id" => $affiliate_id,
                "push_id"      => null,
                "data"         => json_encode( $empty_data ),
                "created_at"   => $now,
                "updated_at"   => $now,
            ];

            if ( $__is_establishments )
                $new_data[ "name" ] = $data[ "nome" ];

            $local_data[] = $new_data;
        }

        // Update the push_id for existing data in Firebase
        foreach ( $firebase_data as $push => $data )
        {
            $partner = ( $__is_establishments ) ? "partnerEstablishmentId" : "partnerPromotionId";

            $search_id = $data[ "origin" ] . $data[ $partner ];
            $index     = Helper::arraySearchByKey( needle: $search_id, column: "affiliate_id", arr: $local_data );

            if ( $index !== false )
                $local_data[ $index ][ "push_id" ] = $push;
            else
                $this->printMessage( message: "Populate: {$search_id} : false : {$push}" );
        }

        // Truncate and insert the data into the database
        DB::table( table: "affiliates_{$type}" )->truncate();
        DB::table( table: "affiliates_{$type}" )->insert( values: $local_data );

        // Store the data in JSON files
        Storage::put( path: "{$type}_awin.json",     contents: json_encode( $awin_data     ) );
        Storage::put( path: "{$type}_firebase.json", contents: json_encode( $firebase_data ) );
        Storage::put( path: "{$type}_local.json",    contents: json_encode( $local_data    ) );

        $this->printMessage( message: "Populate: End {$type} processing" );

        // Dump the counts of the data
        dump(
            "{$type} local : " . count( $local_data    ),
            "{$type} awin  : " . count( $awin_data     ),
            "{$type} fire  : " . count( $firebase_data )
        );
    }

    /**
     * Get the empty data structure for the given type.
     *
     * @param   string  $type   Establishments or Promotions
     * @return  array
     */
    private function empty_data( string $type )
    {
        // Return the empty data structure for establishments
        if ( $type == "establishments" )
        {
            return [
                "partnerEstablishmentId" => null,
                "cashbackDefault"        => null,
                "enableDate"             => null,
                "disableDate"            => null,
                "dataAlteracao"          => null,
                "dataInclusao"           => null,
                "idGestor"               => null,
                "isPublished"            => null,
                "endereco"               => null,
                "lat"                    => null,
                "long"                   => null,
                "nome"                   => null,
                "descricao"              => null,
                "email"                  => null,
                "contatoTriibo"          => null,
                "fotoThumb"              => null,
                "listaFotos"             => null,
                "funcionamento"          => null,
                "horarios"               => null,
                "tags"                   => null,
                "keyWordsSet"            => null,
                "origin"                 => null,
            ];
        }

        // Return the empty data structure for promotions
        if ( $type == "promotions" )
        {
            return [
                "partnerPromotionId" => null,
                "establishmentId"    => null,
                "establishmentName"  => null,
                "title"              => null,
                "detailsTitle"       => null,
                "description"        => null,
                "detailsDescription" => null,
                "enableDate"         => null,
                "disableDate"        => null,
                "dueDate"            => null,
                "isPublished"        => null,
                "active"             => null,
                "lat"                => null,
                "long"               => null,
                "level"              => null,
                "photo"              => null,
                "thumbnail"          => null,
                "region"             => null,
                "type"               => null,
                "url"                => null,
                "tags"               => null,
                "keyWordsSet"        => null,
                "origin"             => null,
                "buttonLabel"        => null,
                "id"                 => null,
            ];
        }
    }

    /**
     * Filter Establishments Awin data from Firebase.
     *
     * @param   Reference   $ref
     * @return  array       $establishments
     */
    private function filterEstablishments( Reference $reference )
    {
        $blacklist = [ "-awin", "-cofry", "-socialsoul"   ];
        $fields    = [ "partnerEstablishmentId", "origin" ];

        return filter_data( data: $reference->getValue(), blacklist: $blacklist, fields: $fields, origin: "awin" );
    }

    /**
     * Filter Promotions Awin data from Firebase.
     *
     * @param   Reference   $reference
     * @return  array       $promotions
     */
    private function filterPromotions( Reference $reference )
    {
        $blacklist = [ "-awin", "-cofry", "-socialsoul"                  ];
        $fields    = [ "establishmentId", "partnerPromotionId", "origin" ];

        return filter_data( data: $reference->getValue(), blacklist: $blacklist, fields: $fields, origin: "awin" );
    }

    /**
     * Print a message to the console and log it.
     *
     * @param   string $message
     * @return  void
     */
    private function printMessage( string $message )
    {
        echo "{$message}\n";
    }
}
