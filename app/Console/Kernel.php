<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Refresh de value bets CADA 4 HORAS (plan free – seguro)
        $schedule
            ->command('odds:refresh-value-bets')
            ->everyFourHours()
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
