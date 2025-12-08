<?php

namespace App\Console\Commands;

use App\Models\NbaGame;
use Illuminate\Console\Command;

class ExportNbaGames extends Command
{
    /**
     * El nombre y firma del comando.
     */
    protected $signature = 'nba:export-games {--path= : Ruta opcional del CSV relativa a storage/}';

    /**
     * Descripción.
     */
    protected $description = 'Exporta los partidos de nba_games a un archivo CSV para entrenar el modelo.';

    public function handle(): int
    {
        $this->info('Exportando partidos de nba_games...');

        $games = NbaGame::all();

        if ($games->isEmpty()) {
            $this->warn('No hay registros en nba_games.');
            return Command::SUCCESS;
        }

        // Ruta relativa dentro de /storage
        // Esto dará: storage/app/exports/nba_games.csv
        $relativePath = $this->option('path') ?: 'app/exports/nba_games.csv';

        // Ruta absoluta completa en el disco
        $fullPath = storage_path($relativePath);

        // Asegurar que la carpeta exista (storage/app/exports)
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            // recursive = true para crear subcarpetas
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                $this->error("No se pudo crear el directorio: {$dir}");
                return Command::FAILURE;
            }
        }

        // Abrir el archivo CSV para escritura
        $handle = fopen($fullPath, 'w');
        if ($handle === false) {
            $this->error("No se pudo abrir el archivo para escritura: {$fullPath}");
            return Command::FAILURE;
        }

        // Encabezados del CSV
        $headers = [
            'id',
            'season',
            'tipoff_at',
            'home_team',
            'away_team',
            'home_score',
            'away_score',
            'total_points',
            'closing_total_line',
            'over_odds',
            'under_odds',
            'over_hit',
        ];

        fputcsv($handle, $headers);

        // Filas
        foreach ($games as $g) {
            fputcsv($handle, [
                $g->id,
                $g->season,
                $g->tipoff_at,
                $g->home_team,
                $g->away_team,
                $g->home_score,
                $g->away_score,
                $g->total_points,
                $g->closing_total_line,
                $g->over_odds,
                $g->under_odds,
                $g->over_hit ? 1 : 0,
            ]);
        }

        fclose($handle);

        $this->info("CSV generado correctamente en: {$fullPath}");

        return Command::SUCCESS;
    }
}
