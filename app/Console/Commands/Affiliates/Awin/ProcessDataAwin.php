<?php

namespace App\Console\Commands\Affiliates\Awin;

use DB;
use Storage;
use Illuminate\Console\Command;
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
        // "populate",
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
        // Get the option from the command line argument
        $option = $this->option( key: "option" );

        // Check if the option is valid
        if ( !in_array( $option, $this->allowed_options ) )
        {
            $message = "Invalid option: Terminating process!";
            log_message( channel: "awin", message: $message );
            $this->info( string: $message );

            return 1;
        }

        // // If the option is "populate", populate the database with establishments and promotions
        // if ( $option == "populate" )
        // {
        //     // Create a new AwinIntegration instance
        //     $integration = new AwinIntegration( loadEstablishments: true );
        //
        //     $this->populate( type: "establishments", integration: $integration );
        //     $this->populate( type: "promotions",     integration: $integration );
        //     return 1;
        // }

        $start   = now();

        // Determine the options to process based on the provided option
        $options = ( $option == "all" )
                    ? [ "establishments", "promotions", "images" ]
                    : [ $option ];

        // Process the selected options
        $this->processOptions( options: $options );

        $diff    = $start->diffInMinutes( date: now() );
        $this->info( string: "Products: Runtime {$diff} minutes..." );

        return 0;
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
            $name    = ucfirst( $option );

            $message = "{$name}: Start processing...";
            log_message( channel: "awin", message: $message );
            $this->info( $message );

            // If the AwinController instance is not created yet, create it
            if ( !$awin )
            {
                $load = ( in_array( $option, [ "images", "reports" ] ) ) ? false : true;
                $awin = new AwinController( loadEstablishments: $load );
            }

            // Call the corresponding method on the AwinController instance
            $awin->{$option}();

            $message = "{$name}: End processing...";
            log_message( channel: "awin", message: $message );
            $this->info( $message );
        }
    }

    /**
     * One time execution, when migrating the DB
     *
     * @param   string              $type           Establishments or Promotions
     * @param   AwinIntegration     $integration
     * @return  void
     */
    private function populate( string $type, AwinIntegration $integration )
    {
        // Check if the type is "establishments"
        $__is_establishments = ( $type == "establishments" );

        // Check if the affiliates_{$type} table is already populated
        if ( 0 != DB::table( "affiliates_{$type}" )->count() )
        {
            $this->info( string: "Populate: DB already populated, terminating process!" );
            return;
        }

        $this->info( string: "Populate: Start processing of {$type}" );

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
            $new_data     = [
                "origin"       => $data[ "origin" ],
                "origin_id"    => $origin_id,
                "affiliate_id" => $data[ "origin" ] . $origin_id,
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
            $partner   = ( $__is_establishments ) ? "partnerEstablishmentId" : "partnerPromotionId";

            $search_id = $data[ "origin" ] . $data[ $partner ];
            $index     = array_search_by_key( needle: $search_id, column: "affiliate_id", array: $local_data );

            if ( $index !== false )
            {
                $local_data[ $index ][ "push_id" ] = $push;
                $this->info( string: "Populate: {$search_id} : {$index} : {$push}" );
            }
        }

        foreach ( $local_data as $index => $data )
            if ( $data[ "push_id" ] == null )
                unset( $local_data[ $index ] );

        // Truncate and insert the data into the database
        DB::table( table: "affiliates_{$type}" )->truncate();
        DB::table( table: "affiliates_{$type}" )->insert( values: $local_data );

        // Store the data in JSON files
        Storage::put( path: "{$type}_awin.json",     contents: json_encode( $awin_data     ) );
        Storage::put( path: "{$type}_firebase.json", contents: json_encode( $firebase_data ) );
        Storage::put( path: "{$type}_local.json",    contents: json_encode( $local_data    ) );

        $this->info( string: "Populate: End {$type} processing" );

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
}
