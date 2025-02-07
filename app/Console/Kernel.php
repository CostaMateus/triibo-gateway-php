<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\Log\ClearLogFile::class,

        \App\Console\Commands\Affiliates\Awin\ProcessDataAwin::class,

        // \App\Console\Commands\Affiliates\Cofry\DeleteImages::class,
        // \App\Console\Commands\Affiliates\Cofry\ProcessImages::class,
        // \App\Console\Commands\Affiliates\Cofry\ProcessStoresPromos::class,

        \App\Console\Commands\Marketplace\DeleteDuplicateSku::class,
        \App\Console\Commands\Marketplace\DeleteProductWithoutSku::class,
        \App\Console\Commands\Marketplace\GetCategories::class,
        \App\Console\Commands\Marketplace\ProductPrivateWithoutImg::class,

        // \App\Console\Commands\Triibo\SyncIdsMP::class,
        // \App\Console\Commands\Triibo\UnsetUpdateDate::class,
        // \App\Console\Commands\Triibo\UnsetVouchers::class,

        \App\Console\Commands\Vtex\ProcessProducts::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule( Schedule $schedule )
    {
        if ( config( "app.env" ) == "production" )
        {
            // Clear Logs - sunday at 00h00
            $schedule->command( "log:clear" )->weekly()->runInBackground();

            // ################################
            // ### Awin Triibo Integration ####
            // ################################
            // ! MOVIDO PARA O AIRFLOW - TODO adicionar o dia
            // Lojas, promoções e imagens - segunda a sexta às 03h
            $schedule->command( "awin:process --option=all"     )->weekdays()->at( "03:00" )->runInBackground();
            // Relatórios - Diariamente às 01h -- relatórios do dia anterior
            $schedule->command( "awin:process --option=reports" )->dailyAt( "01:30" )->runInBackground();


            // ################################
            // ### Cofry Triibo Integration ###
            // ################################
            // ! desativado 17/12/2024
            // Lojas e promoções - Diariamente às 02 e às 14h
            // $schedule->command( "cofry:process_sp --option=all" )->twiceDaily( 2, 14 )->runInBackground();
            // Imagens - Diariamente às 02h05
            // $schedule->command( "cofry:delete_images"           )->dailyAt( "02:30" )->runInBackground();
            // Imagens - Diariamente às 02h10
            // $schedule->command( "cofry:process_images"          )->dailyAt( "02:40" )->runInBackground();

            ################################
            ### Marketplace ################
            ################################
            // Delete duplicate SKUs
            $schedule->command( "mkt:delete_duplicate_sku"                 )->dailyAt( "01:00" )->runInBackground();

            // Change status of products without images
            $schedule->command( "mkt:product_private_without_img"          )->dailyAt( "02:15" )->runInBackground();

            // Remove FS products without SKU
            $schedule->command( "mkt:delete_product_without_sku"           )->dailyAt( "05:00" )->runInBackground();

            // Get Products - weekly on sunday, tuesday, thursday
            $schedule->command( "mkt:get_products"                         )->dailyAt( "05:30" )->days( [ 0, 2, 4 ] )->runInBackground();

            // Get Categories - weekly on monday
            $schedule->command( "mkt:get_categories"                       )->weeklyOn( 1, "06:00" )->runInBackground();

            // Process product informations FS - every five minutes
            $schedule->command( "vtex:process_products --seller=fast_shop" )->everyFiveMinutes()->between( "6:00", "23:59" )->withoutOverlapping()->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load( __DIR__ . "/Commands" );

        require base_path( "routes/console.php" );
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone()
    {
        return "America/Sao_Paulo";
    }
}
