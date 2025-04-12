<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() : void
    {
        Schema::create( "vtex_products", static function ( Blueprint $table ) : void
        {
            $table->id();
            $table->string( "sku" )->unique();
            $table->string( "origin" )->default( "fastshop" );
            $table->string( "action" )->default( "create" ); // create / update / disable
            $table->string( "status" )->nullable();
            $table->json( "meta" )->nullable();
            $table->json( "data" )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down() : void
    {
        Schema::dropIfExists( "vtex_products" );
    }
};
