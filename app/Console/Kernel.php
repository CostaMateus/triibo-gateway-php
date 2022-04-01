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
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // // Domingo às 00h30
        // $schedule->exec("composer self-update")->weeklyOn(0, "0:30")->timezone("America/Sao_Paulo")->runInBackground();
        // // Domingo às 01h00
        // $schedule->exec("composer update")->weeklyOn(0, "0:45")->timezone("America/Sao_Paulo")->runInBackground();


        // Cofry Triibo Integration
        // Diariamente às 02 e às 14h
        $schedule->command("process:storepromo")->twiceDaily(2, 14)->timezone("America/Sao_Paulo")->runInBackground();
        // Diariamente às 02h55
        $schedule->command("delete:images")->dailyAt("01:55")->timezone("America/Sao_Paulo")->runInBackground();
        // Diariamente às 03h00
        $schedule->command("process:images")->dailyAt("02:00")->timezone("America/Sao_Paulo")->runInBackground();


        // Mautic Pipedrive Integration
        // Diariamente às 05h00
        $schedule->command("update:stages")->dailyAt(1, "5:00")->timezone("America/Sao_Paulo")->runInBackground();


        // // Triibo
        // // Diariamente às 03h
        // $schedule->command("unset:updateDate")->dailyAt("03:00")->timezone("America/Sao_Paulo")->runInBackground();
        // // Diariamente às 03h02
        // $schedule->command("unset:vouchers")->dailyAt("03:02")->timezone("America/Sao_Paulo")->runInBackground();


        // Marketplace
        // Delete duplicate SKUs
        $schedule->command("sku:delete")->dailyAt("03:03")->timezone("America/Sao_Paulo")->runInBackground();
        // Update FS SKUs (domingo, terca, quinta)
        $schedule->command("sku:update")->weeklyOn(0, "03:13")->timezone("America/Sao_Paulo")->runInBackground();
        $schedule->command("sku:update")->weeklyOn(2, "03:13")->timezone("America/Sao_Paulo")->runInBackground();
        $schedule->command("sku:update")->weeklyOn(4, "03:13")->timezone("America/Sao_Paulo")->runInBackground();

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
