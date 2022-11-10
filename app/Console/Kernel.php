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
        \App\Console\Commands\Cofry\DeleteImages::class,
        \App\Console\Commands\Cofry\ProcessImages::class,
        \App\Console\Commands\Cofry\ProcessStoresPromos::class,
        \App\Console\Commands\Log\ClearLogFile::class,
        \App\Console\Commands\Marketplace\DuplicateSKUs::class,
        \App\Console\Commands\Marketplace\RemoveSkuFastShop::class,
        \App\Console\Commands\Marketplace\SetPublishProduct::class,
        \App\Console\Commands\Marketplace\UpdatePipeStages::class,
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
    protected function schedule(Schedule $schedule)
    {
        if ( config( "app.env" ) == "production" )
        {
            // // Domingo às 00h30
            // $schedule->exec("composer self-update")->weeklyOn(0, "0:30")->timezone( "America/Sao_Paulo" )->runInBackground();
            // // Domingo às 01h00
            // $schedule->exec("composer update")->weeklyOn(0, "0:45")->timezone( "America/Sao_Paulo" )->runInBackground();


            // Cofry Triibo Integration
            // Diariamente às 02 e às 14h
            $schedule->command( "process:storepromo" )->twiceDaily( 2, 14 )->timezone( "America/Sao_Paulo" )->runInBackground();
            // Diariamente às 02h05
            $schedule->command( "delete:images"      )->dailyAt( "02:05" )->timezone( "America/Sao_Paulo" )->runInBackground();
            // Diariamente às 02h10
            $schedule->command( "process:images"     )->dailyAt( "02:10" )->timezone( "America/Sao_Paulo" )->runInBackground();


            // Mautic Pipedrive Integration
            // Diariamente às 05h00
            $schedule->command( "update:stages" )->weeklyOn( 1, "5:00" )->timezone( "America/Sao_Paulo" )->runInBackground();


            // // Triibo
            // // Diariamente às 03h
            // $schedule->command("unset:updateDate")->dailyAt("03:00")->timezone( "America/Sao_Paulo" )->runInBackground();
            // // Diariamente às 03h02
            // $schedule->command("unset:vouchers")->dailyAt("03:02")->timezone( "America/Sao_Paulo" )->runInBackground();


            // Marketplace
            // Delete duplicate SKUs
            $schedule->command( "sku:delete_duplicate" )->dailyAt( "01:03" )->timezone( "America/Sao_Paulo" )->runInBackground();
            // Change status of products without images
            $schedule->command( "sku:private"          )->dailyAt( "02:05" )->timezone( "America/Sao_Paulo" )->runInBackground();
            // Update FS products instock, sc 05/14/33
            $schedule->command( "sku:update"           )->dailyAt( "06:00" )->timezone( "America/Sao_Paulo" )->runInBackground();
            // // Remove FS products without SKU
            // $schedule->command( "sku:delete_fs"        )->twiceDaily( 5, 8 )->timezone( "America/Sao_Paulo" )->runInBackground();


            // Clear Logs
            $schedule->command( "log:clear" )->weeklyOn( 3, "16:00" )->timezone( "America/Sao_Paulo" )->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__."/Commands");

        require base_path("routes/console.php");
    }
}
