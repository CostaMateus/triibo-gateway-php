<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() : void
    {
        Schema::rename( "vtex_products", "vtex_product_notifications" );
    }

    /**
     * Reverse the migrations.
     */
    public function down() : void
    {
        Schema::rename( "vtex_product_notifications", "vtex_products" );
    }
};
