<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

final class AlterTableMarketplaceProductSkus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() : void
    {
        Schema::table( "marketplace_product_skus", static function ( Blueprint $table ) : void
        {

            $table->integer( "product_id" )->nullable()->default( null )->change();
            $table->string( "product_sku" )->nullable()->default( null )->change();

            $table->string( "regular_price" )->nullable()->default( null );     // preço normal
            $table->string( "sale_price" )->nullable()->default( null );     // preço promocional
            $table->string( "stock" )->nullable()->default( null );     // estoque
            $table->string( "status" )->nullable()->default( null );     // active/inactive

            $table->timestamp( "updated_at" )->nullable()->default( null ); // se mais de 15 dias sem mudanças, add inactivated_at e muda status
            $table->timestamp( "consulted_at" )->nullable()->default( null ); // att a cada consulta
            $table->timestamp( "inactivated_at" )->nullable()->default( null ); // se data maior que 15 dias, consulta de novo, se estoque zero ainda, att inactivated_at
        } );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() : void
    {
        Schema::table( "marketplace_product_skus", static function ( Blueprint $table ) : void
        {
            $table->integer( "product_id" )->change();
            $table->string( "product_sku" )->change();

            $table->dropColumn( "regular_price" );
            $table->dropColumn( "sale_price" );
            $table->dropColumn( "stock" );
            $table->dropColumn( "status" );

            $table->dropColumn( "updated_at" );
            $table->dropColumn( "consulted_at" );
            $table->dropColumn( "inactivated_at" );
        } );
    }
}
