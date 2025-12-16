<?php

namespace App\Console\Commands;

use App\Models\ValueBet;
use App\Services\OddsApiClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RefreshValueBets extends Command
{
    protected $signature = 'odds:refresh-value-bets';
    protected $description = 'Consulta The Odds API y actualiza value_bets con edge dinámico por deporte';

    public function __construct(protected OddsApiClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        \Log::info('RefreshValueBets ejecutado', ['time' => now()]);

        try {
            $events = $this->client->fetchOdds();
        } catch (\Throwable $e) {
            \Log::error('Error Odds API', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        if (empty($events)) {
            $this->warn('No se recibieron eventos.');
            return Command::SUCCESS;
        }

        // 🔹 Edge dinámico por deporte (CLAVE)
        $minEdgeBySport = [
            'basketball_nba'        => 0.01,
            'americanfootball_nfl'  => 0.03,
            'soccer_spain_la_liga'  => 0.05,
        ];

        $allowedSports = array_keys($minEdgeBySport);

        // ⚠️ Mantienes truncate como ya lo usabas
        ValueBet::truncate();
        $insertCount = 0;

        foreach ($events as $event) {

            $sportKey   = $event['sport_key'] ?? null;
            if (!$sportKey || !in_array($sportKey, $allowedSports, true)) {
                continue;
            }

            $kickoffAt = isset($event['commence_time'])
            ? Carbon::parse($event['commence_time'], 'UTC')
            ->setTimezone('America/Managua')
            : null;

            // 🔹 Excluir solo partidos MUY viejos
            if ($kickoffAt && $kickoffAt->lt(now()->subHours(10))) {
                continue;
            }

            if (empty($event['bookmakers'])) {
                continue;
            }

            $league   = $event['sport_title'] ?? 'Desconocido';
            $match    = ($event['home_team'] ?? 'Local') . ' vs ' . ($event['away_team'] ?? 'Visitante');
            $minEdge  = $minEdgeBySport[$sportKey];

            foreach ($event['bookmakers'] as $bookmaker) {
                $bookmakerName = $bookmaker['title'] ?? 'Bookmaker';

                foreach ($bookmaker['markets'] ?? [] as $market) {
                    $marketKey = $market['key'] ?? null;

                    if (!in_array($marketKey, ['h2h', 'totals', 'spreads'], true)) {
                        continue;
                    }

                    foreach ($market['outcomes'] ?? [] as $outcome) {

                        $odds = isset($outcome['price']) ? (float) $outcome['price'] : null;
                        if (!$odds || $odds <= 1.0 || $odds > 50) {
                            continue;
                        }

                        $line = isset($outcome['point']) ? (float) $outcome['point'] : null;
                        $implied = 1 / $odds;

                        // 🔹 Modelo solo para NBA Totals Over
                        $isNbaOver =
                            $sportKey === 'basketball_nba' &&
                            $marketKey === 'totals' &&
                            stripos($outcome['name'] ?? '', 'over') !== false;

                        if ($isNbaOver && $line !== null) {
                            $modelProb = $this->predictNbaOver($line, $odds)
                                ?? $this->estimateModelProbability($odds);
                        } else {
                            $modelProb = $this->estimateModelProbability($odds);
                        }

                        $edge = $modelProb - $implied;

                        if ($edge < $minEdge) {
                            continue;
                        }

                        ValueBet::create([
                            'sport'               => $sportKey,
                            'league'              => $league,
                            'match'               => $match,
                            'market'              => $this->formatMarketLabel(
                                $marketKey,
                                $outcome['name'] ?? 'Outcome',
                                $line,
                                $sportKey
                            ),
                            'bookmaker'           => $bookmakerName,
                            'odds'                => $odds,
                            'implied_probability' => $implied,
                            'model_probability'   => $modelProb,
                            'edge'                => $edge,
                            'risk_label'          => $this->riskFromProbability($modelProb),
                            'kickoff_at'          => $kickoffAt,
                            'is_active'           => true,
                            'total_line'          => $line,
                        ]);

                        $insertCount++;
                    }
                }
            }
        }

        $this->info("Value bets insertadas: {$insertCount}");
        return Command::SUCCESS;
    }

    protected function predictNbaOver(float $line, float $odds): ?float
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 2]);
            $res = $client->post(config('services.model_api.url') . '/predict-nba-over', [
                'json' => [
                    'closing_total_line' => $line,
                    'over_odds' => $odds,
                ]
            ]);

            $data = json_decode($res->getBody(), true);
            return $data['prob_over'] ?? null;
        } catch (\Throwable $e) {
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

    protected function formatMarketLabel(string $marketKey, string $name, ?float $line, string $sport): string
    {
        $suffix = str_starts_with($sport, 'soccer_') ? ' goles' : ' pts';
        $line   = $line !== null ? rtrim(rtrim((string)$line, '0'), '.') : null;

        return match ($marketKey) {
            'h2h'     => "Ganador del partido - {$name}",
            'totals'  => $line ? "Total {$name} {$line}{$suffix}" : "Total {$name}",
            'spreads' => $line ? "Hándicap {$name} {$line}" : "Hándicap {$name}",
            default   => "{$marketKey} - {$name}",
        };
    }
}
