use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
{
    $schedule->command('odds:refresh-value-bets')->everyFiveMinutes();
}

}
