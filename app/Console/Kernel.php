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
        // $schedule->command('inspire')->hourly();

        // $schedule->command('delete:images')->weekly()->sundays()->at('01:30')->timezone('America/Sao_Paulo');
        // $schedule->command('delete:images')->weekly()->tuesdays()->at('01:30')->timezone('America/Sao_Paulo');
        // $schedule->command('delete:images')->weekly()->fridays()->at('01:30')->timezone('America/Sao_Paulo');
        // o mesmo que os acima - domingo, terca e sexta
        $schedule->command('delete:images')->cron('30 1 * * 0,2,5')->timezone('America/Sao_Paulo');

        $schedule->command('process:storepromo')->dailyAt('02:00')->timezone('America/Sao_Paulo');
        $schedule->command('process:images')->dailyAt('03:00')->timezone('America/Sao_Paulo');
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
