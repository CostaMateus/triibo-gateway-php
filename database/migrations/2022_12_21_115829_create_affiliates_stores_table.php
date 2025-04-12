<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

final class CreateAffiliatesStoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() : void
    {
        Schema::create( "affiliates_stores", static function ( Blueprint $table ) : void
        {
            $table->id();

            $table->string( "domain" )->unique();
            $table->string( "affiliate_id" );
            $table->string( "affiliate_name" );
            $table->string( "commission" )->nullable()->default( null );

            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() : void
    {
        Schema::dropIfExists( "affiliates_stores" );
    }
}
