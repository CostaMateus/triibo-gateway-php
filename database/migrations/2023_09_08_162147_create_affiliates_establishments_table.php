<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliatesEstablishmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create( "affiliates_establishments", function ( Blueprint $table )
        {
            $table->id();
            $table->string( "origin" );                 // ex: awin
            $table->integer( "origin_id" );             // ex: 17532
            $table->string( "affiliate_id" )->unique(); // origin + origin_id
            $table->string( "push_id" )->nullable()->unique(); // firebase id
            $table->boolean( "is_published" );          // published on Triibo
            $table->string( "name" );                   // establishment name
            $table->json( "data" )->nullable();         // source data once formatted to Triibo format
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists( "affiliates_establishments" );
    }
}
