<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

final class CreateAffiliatesPromotionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() : void
    {
        Schema::create( "affiliates_promotions", static function ( Blueprint $table ) : void
        {
            $table->id();
            $table->string( "origin" );                       // ex: awin
            $table->string( "origin_id" );                       // ex: 17532
            $table->string( "affiliate_id" )->unique();             // origin + origin_id
            $table->string( "push_id" )->nullable()->unique(); // firebase id
            $table->json( "data" )->nullable();                     // source data once formatted to Triibo format
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
        Schema::dropIfExists( "affiliates_promotions" );
    }
}
