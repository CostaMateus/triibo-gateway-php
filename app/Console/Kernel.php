<?php

namespace App\Console;

use DateTimeZone;
use Illuminate\Console\Scheduling\{Schedule };
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\Log\ClearLogFile::class,

        Commands\Affiliates\Awin\ProcessDataAwin::class,

        Commands\Marketplace\DeleteDuplicateSku::class,
        Commands\Marketplace\DeleteProductWithoutSku::class,
        Commands\Marketplace\GetCategories::class,
        Commands\Marketplace\ProductPrivateWithoutImg::class,

        Commands\Marketplace\DeleteFastShopSkus::class,
        Commands\Marketplace\InsertFastShopSkus::class,

        Commands\Marketplace\Reset\DeleteFastShopData::class,

        Commands\Vtex\ProcessProducts::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     *
     * @return void
     */
    protected function schedule( Schedule $schedule ) : void
    {
        if ( config( "app.env" ) === "production" )
        {
            // Clear Logs - sunday at 00h00
            $schedule->command( "log:clear" )->weekly()->withoutOverlapping()->runInBackground();

            // ################################
            // ### Awin Triibo Integration ####
            // ################################
            // ! MOVIDO PARA O AIRFLOW - 12/02/2025
            // Lojas, promoções e imagens - segunda a sexta às 03h
            // $this->scheduleCommand( $schedule, "awin:process --option=all"     )->weekdays()->at( "03:00" );
            // Relatórios - Diariamente às 01h -- relatórios do dia anterior
            $schedule->command( "awin:process --option=reports" )->dailyAt( "01:30" )->withoutOverlapping()->runInBackground();

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

            // ###############################
            // ## Marketplace ################
            // ###############################
            // Delete duplicate SKUs
            $schedule->command( "mkt:delete_duplicate_sku" )->dailyAt( "01:00" )->withoutOverlapping()->runInBackground();

            // Change status of products without images
            $schedule->command( "mkt:product_private_without_img" )->dailyAt( "02:15" )->withoutOverlapping()->runInBackground();

            // Remove FS products without SKU
            $schedule->command( "mkt:delete_product_without_sku" )->dailyAt( "05:00" )->withoutOverlapping()->runInBackground();

            // Get Categories - weekly on monday
            $schedule->command( "mkt:get_categories" )->weeklyOn( 1, "06:00" )->withoutOverlapping()->runInBackground();

            // Process product informations FS - every five minutes
            $schedule->command( "vtex:process_products --seller=fast_shop" )->everyTenMinutes()->withoutOverlapping()->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands() : void
    {
        $this->load( __DIR__ . "/Commands" );

        require base_path( "routes/console.php" );
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return null|DateTimeZone|string
     */
    protected function scheduleTimezone() : mixed
    {
        return "America/Sao_Paulo";
    }
}
