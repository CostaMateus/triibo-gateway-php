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
        // Domingo às 00h30
        $schedule->exec('composer self-update')->weeklyOn(0, '0:30')->timezone('America/Sao_Paulo');
        // Domingo às 01h00
        $schedule->exec('composer update')->weeklyOn(0, '1:00')->timezone('America/Sao_Paulo');


        // Cofry Triibo Integration
        // Diariamente às 01h10
        $schedule->command('delete:images')->dailyAt('01:10')->timezone('America/Sao_Paulo');
        // Diariamente às 02h00
        $schedule->command('process:storepromo')->dailyAt('02:00')->timezone('America/Sao_Paulo');
        // Diariamente às 03h00
        $schedule->command('process:images')->dailyAt('03:00')->timezone('America/Sao_Paulo');


        // Mautic Pipedrive Integration
        // Segunda às 05h00
        $schedule->command('import:stages')->weeklyOn(1, '5:00')->timezone('America/Sao_Paulo');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
