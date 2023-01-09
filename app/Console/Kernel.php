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
        \App\Console\Commands\Affiliates\SocialSoul\ProcessDataSS::class,

        \App\Console\Commands\Cofry\DeleteImages::class,
        \App\Console\Commands\Cofry\ProcessImages::class,
        \App\Console\Commands\Cofry\ProcessStoresPromos::class,

        \App\Console\Commands\Log\ClearLogFile::class,

        \App\Console\Commands\Marketplace\DuplicateSKUs::class,
        \App\Console\Commands\Marketplace\GetMktProductCategories::class,
        \App\Console\Commands\Marketplace\GetMktProductSkus::class,
        \App\Console\Commands\Marketplace\ProcessFastShopProducts::class,
        \App\Console\Commands\Marketplace\RemoveSkuFastShop::class,
        \App\Console\Commands\Marketplace\SetPublishProduct::class,
        \App\Console\Commands\Marketplace\UpdatePipeStages::class,
        \App\Console\Commands\Marketplace\UpdateProductWithoutImage::class,
        \App\Console\Commands\Marketplace\UpdateStockFastShop::class,
        \App\Console\Commands\Marketplace\WithoutHubFastShop::class,

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
            // Trimestral
            $schedule->exec( "composer self-update" )->quarterly()->runInBackground();
            // Mensal, 1º às 01h00
            $schedule->exec( "composer update"      )->monthlyOn( 1, "00:45" )->runInBackground();


            // Cofry Triibo Integration
            // Lojas e promoções - Diariamente às 02 e às 14h
            $schedule->command( "process:storepromo" )->twiceDaily( 2, 14 )->runInBackground();
            // Imagens - Diariamente às 02h05
            $schedule->command( "delete:images"      )->dailyAt( "02:05" )->runInBackground();
            // Imagens - Diariamente às 02h10
            $schedule->command( "process:images"     )->dailyAt( "02:15" )->runInBackground();

            // SocialSoul Triibo Integration
            // Lojas, promoções e imagens - Diariamente às 02h30 e às 14h30
            $schedule->command( "socialsoul:process --option=all"     )->twiceDaily( 3, 15 )->runInBackground();
            // Relatórios - Diariamente às 4h
            $schedule->command( "socialsoul:process --option=reports" )->dailyAt( "04:00" )->runInBackground();


            // Mautic Pipedrive Integration
            // Diariamente às 05h00
            $schedule->command( "update:stages" )->weeklyOn( 1, "5:00" )->runInBackground();


            // // Triibo
            // // Diariamente às 03h
            // $schedule->command("unset:updateDate")->dailyAt("03:00")->runInBackground();
            // // Diariamente às 03h02
            // $schedule->command("unset:vouchers")->dailyAt("03:02")->runInBackground();


            // Marketplace
            // Process FS products
            $schedule->command( "sku:process_fs_products --action=update" )->hourly()->runInBackground();
            // Get SKUs
            $schedule->command( "mkt:get_skus"         )->dailyAt( "00:01" )->runInBackground();
            // Get Categories
            $schedule->command( "mkt:get_categories"   )->dailyAt( "23:59" )->runInBackground();
            // Delete duplicate SKUs
            $schedule->command( "sku:delete_duplicate" )->dailyAt( "01:03" )->runInBackground();
            // Change status of products without images
            $schedule->command( "sku:private"          )->dailyAt( "02:05" )->runInBackground();
            // Update FS products instock, sc 05/14/33
            $schedule->command( "sku:update"           )->dailyAt( "06:00" )->runInBackground();
            // // Remove FS products without SKU
            // $schedule->command( "sku:delete_fs"        )->twiceDaily( 5, 8 )->runInBackground();


            // Clear Logs
            $schedule->command( "log:clear" )->weeklyOn( 3, "01:00" )->runInBackground();
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
