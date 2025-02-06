<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() : void
    {
        Schema::create( "marketplace_orders", function ( Blueprint $table )
        {
            $table->id();

            $table->string(  "file_name"    );

            $table->string(  "mkt_id"       )->nullable();
            $table->string(  "ord_id"       );
            $table->string(  "ord_status"   );
            $table->float(   "max_price"    );
            $table->integer( "product_id"   );
            $table->integer( "product_qntt" );
            $table->string(  "product_name" );
            $table->json(    "billing"      );
            $table->json(    "shipping"     );

            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down() : void
    {
        Schema::dropIfExists( "marketplace_orders" );
    }
};
