use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Ejecutar cada 10 minutos (puedes cambiarlo a cada 5, 15, etc.)
        $schedule->command('odds:refresh-value-bets')->everyTenMinutes();
    }
}
