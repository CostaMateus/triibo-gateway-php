<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductSkuToFastshopProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table( "fastshop_products", function ( Blueprint $table ) {
            $table->string( "product_sku" )->unique()->after( "id" )->nullable()->default( null );
        } );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table( "fastshop_products", function ( Blueprint $table ) {
            $table->dropColumn( "product_sku" );
        } );
    }
}
