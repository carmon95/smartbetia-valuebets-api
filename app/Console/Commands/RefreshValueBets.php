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

    // 👇 AGREGA ESTO AQUÍ
\Log::info('Sports keys recibidos', [
    'sports' => collect($events)->pluck('sport_key')->unique()->values()->all()
]);

    if (empty($events)) {
        $this->warn('No se recibieron eventos.');
        return Command::SUCCESS;
    }

    $minEdge = config('odds.min_edge', 0.05);

    $allowedSports = [
        'basketball_nba',
        'americanfootball_nfl',
        'soccer_spain_la_liga',
    ];

    // 🧹 LIMPIEZA INTELIGENTE (NO truncate)
    $deleted = ValueBet::whereNotNull('kickoff_at')
        ->where('kickoff_at', '<', now()->subHours(2))
        ->delete();

    $this->info("Partidos antiguos eliminados: {$deleted}");

    $upsertCount = 0;

    foreach ($events as $event) {
        $league     = $event['sport_title'] ?? 'Desconocido';
        $homeTeam   = $event['home_team'] ?? 'Local';
        $awayTeam   = $event['away_team'] ?? 'Visitante';
        $match      = "{$homeTeam} vs {$awayTeam}";
        $kickoffIso = $event['commence_time'] ?? null;
        $kickoffAt  = $kickoffIso ? Carbon::parse($kickoffIso) : null;
        $sportKey   = $event['sport_key'] ?? 'unknown';

        if (!in_array($sportKey, $allowedSports, true)) {
            continue;
        }
        // 🔹 Excluir solo partidos YA FINALIZADOS hace mucho
        if ($kickoffAt && $kickoffAt->lt(now()->subHours(10))) {
        continue;
        }

        foreach ($event['bookmakers'] ?? [] as $bookmaker) {
            $bookmakerName = $bookmaker['title'] ?? 'Bookmaker';

            foreach ($bookmaker['markets'] ?? [] as $market) {
                $marketKey = $market['key'] ?? 'unknown';

                if (!in_array($marketKey, ['h2h', 'totals', 'spreads'])) {
                    continue;
                }

                foreach ($market['outcomes'] ?? [] as $outcome) {
                    $outcomeName = $outcome['name'] ?? 'Outcome';
                    $odds = isset($outcome['price']) ? (float) $outcome['price'] : null;

                    if (!$odds || $odds <= 1.0 || $odds > 50.0) {
                        continue;
                    }

                    $line = isset($outcome['point']) ? (float) $outcome['point'] : null;
                    $implied = 1 / $odds;

                    $isNbaTotalsOver =
                        $sportKey === 'basketball_nba' &&
                        $marketKey === 'totals' &&
                        stripos($outcomeName, 'Over') !== false;

                    if ($isNbaTotalsOver) {
                        $modelProb = $line !== null
                            ? $this->predictNbaOver($line, $odds) ?? $this->estimateModelProbability($odds)
                            : $this->estimateModelProbability($odds);
                    } else {
                        $modelProb = $this->estimateModelProbability($odds);
                    }

                    $edge = $modelProb - $implied;
                    if ($edge < $minEdge) {
                        continue;
                    }

                    $riskLabel = $this->riskFromProbability($modelProb);
                    $marketLabel = $this->formatMarketLabel($marketKey, $outcomeName, $line, $sportKey);

                    // ✅ UPSERT en lugar de CREATE
                    ValueBet::updateOrCreate(
                        [
                            'sport'     => $sportKey,
                            'match'     => $match,
                            'market'    => $marketLabel,
                            'bookmaker' => $bookmakerName,
                        ],
                        [
                            'league'              => $league,
                            'odds'                => $odds,
                            'implied_probability' => $implied,
                            'model_probability'   => $modelProb,
                            'edge'                => $edge,
                            'risk_label'          => $riskLabel,
                            'kickoff_at'          => $kickoffAt,
                            'is_active'           => true,
                            'total_line'          => $line,
                        ]
                    );

                    $upsertCount++;
                }
            }
        }
    }

    $this->info("Value bets actualizadas/insertadas: {$upsertCount}");
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
                'base_uri' => 'http://127.0.0.1:8001', // cámbialo por tu URL pública cuando lo subas
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

    protected function formatMarketLabel(
        string $marketKey,
        string $outcomeName,
        ?float $line = null,
        ?string $sportKey = null
    ): string {
        $sportKey = $sportKey ?? '';

        $lineFormatted = null;
        if ($line !== null) {
            $lineFormatted = rtrim(rtrim((string)$line, '0'), '.');
        }

        $suffix = '';
        if (str_starts_with($sportKey, 'basketball_') || str_starts_with($sportKey, 'americanfootball_')) {
            $suffix = ' pts';
        } elseif (str_starts_with($sportKey, 'soccer_')) {
            $suffix = ' goles';
        }

        return match ($marketKey) {
            'h2h'    => "Ganador del partido - {$outcomeName}",

            'totals' => $lineFormatted !== null
                ? "Total {$outcomeName} {$lineFormatted}{$suffix}"
                : "Línea de goles/puntos - {$outcomeName}",

            'spreads'=> $lineFormatted !== null
                ? "Hándicap {$outcomeName} {$lineFormatted}"
                : "Hándicap - {$outcomeName}",

            default  => "{$marketKey} - {$outcomeName}",
        };
    }
}
