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
            $table->string( "origin" );
            $table->integer( "origin_id" );
            $table->string( "affiliate_id" )->unique(); // origin + origin_id
            $table->string( "push_id"      )->unique(); // firebase id
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
