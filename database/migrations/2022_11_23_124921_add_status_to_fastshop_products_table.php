<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

final class AddStatusToFastshopProductsTable extends Migration
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
            $table->string( "status" )->after( "action" )->nullable()->default( null );
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
            $table->dropColumn( "status" );
        } );
    }
}
