<?php

namespace App\Console\Commands\Marketplace;

use Throwable;
use Illuminate\Console\Command;
use Codexshaper\WooCommerce\Facades\Product;

class FixProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "mkt:fix-products";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Get product list";

    /**
     * Execute the console command.
     *
     * @return  void
     */
    public function handle() : void
    {
        $page     = 1;
        $per_page = 100;

        $not_fs   = 0;
        $fs_new   = 0;
        $fs_old   = 0;

        $delete   = [];

        do
        {
            $this->print( $not_fs, $fs_new, $fs_old );

            $products = Product::all( [
                "page"     => $page,
                "per_page" => $per_page,
            ] );

            foreach ( $products as $product )
            {
                // não é da fast shop
                if ( $product->store->id != 4265 )
                {
                    $not_fs++;
                    continue;
                }

                $is_new_fs = false;

                // verifica se tem meta _fs_product_id
                foreach ( $product->meta_data as $meta )
                {
                    if ( $meta->key == "_fs_product_id" )
                    {
                        $is_new_fs = true;
                        break;
                    }
                }

                // tem meta _fs_product_id, continua
                if ( $is_new_fs )
                {
                    $fs_new++;
                    continue;
                }

                $fs_old++;

                // $delete[] = [
                //     "id"    => $product->id,
                //     "force" => true
                // ];
                $delete[] = $product->id;

                $this->info( "delete: " . str_pad( $product->id, 6, " ", STR_PAD_LEFT ) );

                if ( count ( $delete ) == 100 )
                {
                    try
                    {
                        Product::batch( [ "delete" => $delete ] );
                        $this->info( "Batch delete ===============================" );
                    }
                    catch( Throwable $e )
                    {
                        if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                            $this->error( $e->getMessage() );
                    }

                    $delete = [];
                }
            }

            if ( count( $delete ) != 0 )
            {
                $del = str_pad( count( $delete ), 5, " ", STR_PAD_LEFT );
                $this->info( "to_del: {$del}" );
            }

            $page++;
        }
        while( $products->count() == $per_page );

        $this->print( $not_fs, $fs_new, $fs_old );

        try
        {
            Product::batch( [ "delete" => $delete ] );
        }
        catch( Throwable $e )
        {
            if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                $this->error( $e->getMessage() );
        }

        $this->info( "Finished" );
    }

    private function print( $not_fs, $fs_new, $fs_old )
    {
        $not_fs = str_pad( $not_fs, 5, " ", STR_PAD_LEFT );
        $fs_new = str_pad( $fs_new, 5, " ", STR_PAD_LEFT );
        $fs_old = str_pad( $fs_old, 5, " ", STR_PAD_LEFT );

        $this->info( "not_fs: {$not_fs} | fs_old: {$fs_old} | fs_new: {$fs_new}" );
    }
}
