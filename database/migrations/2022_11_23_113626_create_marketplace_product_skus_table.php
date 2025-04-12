<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMarketplaceProductSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() : void
    {
        Schema::create( "marketplace_product_skus", static function ( Blueprint $table ) : void
        {
            $table->id();
            $table->integer( "product_id" );
            $table->string( "product_sku" );
        } );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() : void
    {
        Schema::dropIfExists( "marketplace_product_skus" );
    }
}
