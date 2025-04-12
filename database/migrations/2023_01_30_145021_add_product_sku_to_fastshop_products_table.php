<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProductSkuToFastshopProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() : void
    {
        Schema::table( "fastshop_products", static function ( Blueprint $table ) : void
        {
            $table->string( "product_sku" )->unique()->after( "id" )->nullable()->default( null );
        } );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() : void
    {
        Schema::table( "fastshop_products", static function ( Blueprint $table ) : void
        {
            $table->dropColumn( "product_sku" );
        } );
    }
}
