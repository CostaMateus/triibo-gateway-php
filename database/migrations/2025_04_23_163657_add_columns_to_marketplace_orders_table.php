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
        Schema::table( "marketplace_orders", static function ( Blueprint $table ) : void
        {
            $table->float( "max_price" )->change()->after( "mkt_id" );
            $table->string( "strong_key" )->after( "file_name" )->nullable()->default( null );
            $table->float( "ord_price" )->after( "ord_status" )->nullable()->default( null );
            $table->float( "ord_shipping" )->after( "ord_price" )->nullable()->default( null );
            $table->timestamp( "ord_created_at" )->after( "ord_shipping" )->nullable()->default( null );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down() : void
    {
        Schema::table( "marketplace_orders", static function ( Blueprint $table ) : void
        {
            $table->dropColumn( "ord_created_at" );
            $table->dropColumn( "ord_shipping" );
            $table->dropColumn( "ord_price" );
            $table->dropColumn( "strong_key" );
        } );
    }
};
