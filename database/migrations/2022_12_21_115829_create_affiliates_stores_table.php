<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliatesStoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create( "affiliates_stores", function ( Blueprint $table ) {
            $table->id();

            $table->string( "domain" )->unique();
            $table->string( "affiliate_id"   );
            $table->string( "affiliate_name" );
            $table->string( "commission"     )->nullable()->default( null );

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
        Schema::dropIfExists( "affiliates_stores" );
    }
}
