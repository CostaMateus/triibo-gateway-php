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

            $this->printMessage( message: "{$name}: Start processing" );

            // If the AwinController instance is not created yet, create it
            if ( !$awin )
            {
                $load = ( in_array( $option, [ "images", "reports" ] ) ) ? false : true;
                $awin = new AwinController( loadEstablishments: $load );
            }

            // Call the corresponding method on the AwinController instance
            $awin->{$option}();

            $this->printMessage( message: "{$name}: End processing" );
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

        // Check if the affiliates_establishments table is already populated
        if ( 0 != DB::table( "affiliates_establishments" )->count() )
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
        Storage::put( path: "{$type}_awin.json",     content: json_encode( $awin_data     ) );
        Storage::put( path: "{$type}_firebase.json", content: json_encode( $firebase_data ) );
        Storage::put( path: "{$type}_local.json",    content: json_encode( $local_data    ) );

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

        Log::channel( "awin" )->info( $message );
    }
}
