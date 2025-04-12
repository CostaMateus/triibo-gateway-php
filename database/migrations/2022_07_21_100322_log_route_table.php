<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class LogRouteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() : void
    {
        Schema::create( "log_route", static function ( Blueprint $table ) : void
        {
            $table->id();
            $table->string( "duration" );
            $table->string( "status" );
            $table->string( "method" );
            $table->string( "ip" );
            $table->string( "url" );
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
        Schema::drop( "log_route" );
    }
}
