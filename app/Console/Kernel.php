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
        \App\Console\Commands\Affiliates\Awin\ProcessDataAwin::class,
        \App\Console\Commands\Affiliates\Cofry\DeleteImages::class,
        \App\Console\Commands\Affiliates\Cofry\ProcessImages::class,
        \App\Console\Commands\Affiliates\Cofry\ProcessStoresPromos::class,
        \App\Console\Commands\Affiliates\SocialSoul\ProcessDataSS::class,

        \App\Console\Commands\Log\ClearLogFile::class,

        \App\Console\Commands\Marketplace\DeleteDuplicateSku::class,
        \App\Console\Commands\Marketplace\DeleteProductWithoutSku::class,
        \App\Console\Commands\Marketplace\GetCategories::class,
        \App\Console\Commands\Marketplace\GetFastshopSkus::class,
        \App\Console\Commands\Marketplace\GetProductInfosFastShop::class,
        \App\Console\Commands\Marketplace\GetStockPriceFastShop::class,
        \App\Console\Commands\Marketplace\ProductPrivateWithoutImg::class,
        \App\Console\Commands\Marketplace\UpdateFastshopProducts::class,
        \App\Console\Commands\Marketplace\UpdatePipedriveStages::class,

        \App\Console\Commands\Triibo\SyncIdsMP::class,
        \App\Console\Commands\Triibo\UnsetUpdateDate::class,
        \App\Console\Commands\Triibo\UnsetVouchers::class,
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
            // // Trimestral
            // // $schedule->exec( "composer self-update" )->quarterly()->runInBackground();
            // // Mensal, 1º às 01h00
            // // $schedule->exec( "composer update"      )->monthlyOn( 1, "00:45" )->runInBackground();


            // Awin Triibo Integration
            // Lojas, promoções e imagens - Diariamente às 03h
            $schedule->command( "awin:process --option=all" )->dailyAt( "03:00" )->runInBackground();
            // Relatórios - Diariamente às 01h -- relatórios do dia anterior
            $schedule->command( "awin:process --option=reports" )->dailyAt( "01:00" )->runInBackground();


            // Cofry Triibo Integration
            // Lojas e promoções - Diariamente às 02 e às 14h
            $schedule->command( "cofry:process_sp --option=all" )->twiceDaily( 2, 14 )->runInBackground();
            // Imagens - Diariamente às 02h05
            $schedule->command( "cofry:delete_images"           )->dailyAt( "02:05" )->runInBackground();
            // Imagens - Diariamente às 02h10
            $schedule->command( "cofry:process_images"          )->dailyAt( "02:15" )->runInBackground();


            // // SocialSoul Triibo Integration
            // // Lojas, promoções e imagens - Diariamente às 02h30 e às 14h30
            // // $schedule->command( "socialsoul:process --option=all"     )->twiceDaily( 3, 15 )->runInBackground();
            // // Relatórios - Diariamente às 4h
            // // $schedule->command( "socialsoul:process --option=reports" )->dailyAt( "04:00" )->runInBackground();


            // // Mautic Pipedrive Integration
            // // Toda segunda às 05h00
            // // $schedule->command( "mkt:update_pipedrive_stages" )->weeklyOn( 1, "5:00" )->runInBackground();


            // Marketplace
            // Get Categories
            $schedule->command( "mkt:get_categories"              )->dailyAt( "23:59" )->runInBackground();
            // Get stock/price FS
            $schedule->command( "mkt:get_stock_price_fs"          )->dailyAt( "00:01" )->runInBackground();
            // Delete duplicate SKUs
            $schedule->command( "mkt:delete_duplicate_sku"        )->dailyAt( "01:03" )->runInBackground();
            // Change status of products without images
            $schedule->command( "mkt:product_private_without_img" )->dailyAt( "02:05" )->runInBackground();
            // Remove FS products without SKU
            $schedule->command( "mkt:delete_product_without_sku"  )->dailyAt( "05:00" )->runInBackground();
            // Process product informations FS
            $schedule->command( "mkt:get_product_infos_fs --action=create" )->dailyAt( "02:00" )->runInBackground();
            $schedule->command( "mkt:get_product_infos_fs --action=update" )->dailyAt( "03:00" )->runInBackground();


            // // Clear Logs
            // // $schedule->command( "log:clear" )->weeklyOn( 3, "01:00" )->runInBackground();
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
