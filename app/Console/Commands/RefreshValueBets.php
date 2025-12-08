<?php

namespace App\Console\Commands;

use App\Models\ValueBet;
use App\Services\OddsApiClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RefreshValueBets extends Command
{
    protected $signature = 'odds:refresh-value-bets';

    protected $description = 'Consulta The Odds API, calcula edge y actualiza la tabla value_bets';

    public function __construct(protected OddsApiClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        \Log::info('RefreshValueBets ejecutado a las ' . now());

        $this->info('Obteniendo cuotas desde The Odds API...');

        try {
            $events = $this->client->fetchOdds();
        } catch (\Throwable $e) {
            $this->error('Error llamando a The Odds API: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (empty($events)) {
            $this->warn('No se recibieron eventos.');
            return Command::SUCCESS;
        }

        $minEdge = config('odds.min_edge', 0.05);

        // Por simplicidad, borramos y volvemos a poblar
        ValueBet::truncate();

        $insertCount = 0;

        foreach ($events as $event) {
            $league     = $event['sport_title'] ?? 'Desconocido';   // ej: La Liga - Spain / NBA
            $homeTeam   = $event['home_team'] ?? 'Local';
            $awayTeam   = $event['away_team'] ?? 'Visitante';
            $match      = "{$homeTeam} vs {$awayTeam}";
            $kickoffIso = $event['commence_time'] ?? null;          // ISO string
            $kickoffAt  = $kickoffIso ? Carbon::parse($kickoffIso) : null;
            $sportKey   = $event['sport_key'] ?? 'unknown';         // ej: soccer_spain_la_liga, basketball_nba

            if (empty($event['bookmakers']) || !is_array($event['bookmakers'])) {
                continue;
            }

            foreach ($event['bookmakers'] as $bookmaker) {
                $bookmakerName = $bookmaker['title'] ?? 'Bookmaker';

                if (empty($bookmaker['markets']) || !is_array($bookmaker['markets'])) {
                    continue;
                }

                foreach ($bookmaker['markets'] as $market) {
                    $marketKey = $market['key'] ?? 'unknown';  // h2h, totals, spreads, h2h_lay...

                    // 1) Solo queremos mercados clásicos, NO lay ni cosas raras
                    if (!in_array($marketKey, ['h2h', 'totals', 'spreads'])) {
                        continue;
                    }

                    if (empty($market['outcomes']) || !is_array($market['outcomes'])) {
                        continue;
                    }

                    foreach ($market['outcomes'] as $outcome) {
                        $outcomeName = $outcome['name'] ?? 'Outcome';
                        $odds        = isset($outcome['price']) ? (float) $outcome['price'] : null;

                        if ($odds === null || $odds <= 1.0) {
                            continue;
                        }

                        // 👉 NUEVO: obtener la línea si existe (totals / spreads)
                        $line = isset($outcome['point']) ? (float) $outcome['point'] : null; // 👈

                        // 2) Evitar cuotas absurdamente altas (ej. > 50.0)
                        if ($odds > 50.0) {
                            continue;
                        }

                        // Probabilidad implícita
                        $implied = 1.0 / $odds;

                        // ============================
                        // ¿Es un TOTAL de NBA (Over)?
                        // ============================
                        $isNbaTotalsOver =
                            $sportKey === 'basketball_nba' &&           // solo NBA
                            $marketKey === 'totals' &&                  // mercado totals
                            stripos($outcomeName, 'Over') !== false;    // lado Over

                        if ($isNbaTotalsOver) {
                            if ($line === null) {
                                // Sin línea no podemos usar el modelo → fallback a heurística
                                $modelProb = $this->estimateModelProbability($odds);
                            } else {
                                // Llamamos al modelo de Python
                                $prob = $this->predictNbaOver($line, $odds);
                                // Si algo falla, usamos la heurística
                                $modelProb = $prob ?? $this->estimateModelProbability($odds);
                            }
                        } else {
                            // Para todo lo que NO sea totals de NBA Over → heurística vieja
                            $modelProb = $this->estimateModelProbability($odds);
                        }

                        $edge = $modelProb - $implied;

                        if ($edge < $minEdge) {
                            continue; // no es value bet
                        }

                        $riskLabel   = $this->riskFromProbability($modelProb);

                        // 👉 NUEVO: label que incluye la línea cuando aplique
                        $marketLabel = $this->formatMarketLabel($marketKey, $outcomeName, $line, $sportKey); // 👈

                        ValueBet::create([
                            'sport'               => $sportKey,
                            'league'              => $league,
                            'match'               => $match,
                            'market'              => $marketLabel,
                            'bookmaker'           => $bookmakerName,
                            'odds'                => $odds,
                            'implied_probability' => $implied,
                            'model_probability'   => $modelProb,
                            'edge'                => $edge,
                            'risk_label'          => $riskLabel,
                            'kickoff_at'          => $kickoffAt,
                            'is_active'           => true,
                            'total_line'          => $line, // 👈 GUARDAMOS LA LÍNEA EN LA TABLA
                        ]);

                        $insertCount++;
                    }
                }
            }
        }

        $this->info("Value bets insertadas: {$insertCount}.");

        return Command::SUCCESS;
    }

    /**
     * Llama al servicio de IA en Python para estimar la probabilidad del Over en NBA.
     * Devuelve un float 0.0 - 1.0 o null si algo falla.
     */
    protected function predictNbaOver(float $line, float $overOdds): ?float
    {
        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => 'http://127.0.0.1:8001',
                'timeout'  => 2.0,
            ]);

            $response = $client->post('/predict-nba-over', [
                'json' => [
                    'closing_total_line' => $line,
                    'over_odds'          => $overOdds,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!is_array($data) || !isset($data['prob_over'])) {
                \Log::warning('Respuesta inesperada del modelo NBA', ['data' => $data]);
                return null;
            }

            return (float) $data['prob_over'];
        } catch (\Throwable $e) {
            \Log::warning('Error llamando al modelo NBA: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Modelo heurístico simple solo para empezar.
     * Luego aquí podemos meter algo mucho más pro.
     */
    protected function estimateModelProbability(float $odds): float
    {
        return match (true) {
            $odds <= 1.40 => 0.75,
            $odds <= 1.70 => 0.65,
            $odds <= 2.10 => 0.55,
            $odds <= 3.00 => 0.40,
            default       => 0.12,
        };
    }

    protected function riskFromProbability(float $prob): string
    {
        return match (true) {
            $prob >= 0.70 => 'Riesgo bajo',
            $prob >= 0.50 => 'Riesgo medio',
            $prob >= 0.30 => 'Riesgo alto',
            default       => 'Riesgo muy alto',
        };
    }

    // 👇 AHORA RECIBE TAMBIÉN $line y $sportKey
    protected function formatMarketLabel(string $marketKey, string $outcomeName, ?float $line = null, ?string $sportKey = null): string
    {
        // Normalizamos algunos textos
        $sportKey = $sportKey ?? '';

        // Si hay línea, la formateamos bonito (sin ceros de más)
        $lineFormatted = null;
        if ($line !== null) {
            $lineFormatted = rtrim(rtrim((string)$line, '0'), '.');
        }

        // Sufijo para diferenciar puntos / goles
        $suffix = '';
        if (str_starts_with($sportKey, 'basketball_') || str_starts_with($sportKey, 'americanfootball_')) {
            $suffix = ' pts';
        } elseif (str_starts_with($sportKey, 'soccer_')) {
            $suffix = ' goles';
        }

        return match ($marketKey) {
            'h2h'    => "Ganador del partido - {$outcomeName}",

            'totals' => $lineFormatted !== null
                ? "Total {$outcomeName} {$lineFormatted}{$suffix}"   // 👈 Ej: Total Over 2.5 goles / Total Over 224.5 pts
                : "Línea de goles/puntos - {$outcomeName}",          // fallback,

            'spreads'=> $lineFormatted !== null
                ? "Hándicap {$outcomeName} {$lineFormatted}"         // Ej: Hándicap Elche CF +1.5
                : "Hándicap - {$outcomeName}",

            default  => "{$marketKey} - {$outcomeName}",
        };
    }
}
