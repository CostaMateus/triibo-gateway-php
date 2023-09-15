<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliatesPromotionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create( "affiliates_promotions", function ( Blueprint $table )
        {
            $table->id();
            $table->string( "origin" );                        // ex: awin
            $table->integer( "origin_id" );                    // ex: 17532
            $table->string( "affiliate_id" )->unique();        // origin + origin_id
            $table->string( "push_id" )->nullable()->unique(); // firebase id
            $table->json( "data" )->nullable();                // source data once formatted to Triibo format
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
        Schema::dropIfExists( "affiliates_promotions" );
    }
}
