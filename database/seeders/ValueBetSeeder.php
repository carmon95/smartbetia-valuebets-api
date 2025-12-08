<?php

namespace Database\Seeders;

use App\Models\ValueBet;
use Illuminate\Database\Seeder;

class ValueBetSeeder extends Seeder
{
    public function run(): void
    {
        $bets = [
            [
                'sport' => 'football',
                'league' => 'LaLiga',
                'match' => 'Real Madrid vs Sevilla',
                'market' => 'Over 2.5 goles',
                'bookmaker' => 'DemoBook',
                'odds' => 1.80,
                'implied_probability' => 1 / 1.80,
                'model_probability' => 0.65,
                'edge' => 0.65 - (1 / 1.80),
                'risk_label' => 'Riesgo medio',
                'kickoff_at' => now()->addDays(1),
                'is_active' => true,
            ],
            [
                'sport' => 'basketball',
                'league' => 'NBA',
                'match' => 'Lakers vs Warriors',
                'market' => 'Over 220.5 puntos',
                'bookmaker' => 'DemoBook',
                'odds' => 1.90,
                'implied_probability' => 1 / 1.90,
                'model_probability' => 0.60,
                'edge' => 0.60 - (1 / 1.90),
                'risk_label' => 'Riesgo medio',
                'kickoff_at' => now()->addDays(1),
                'is_active' => true,
            ],
        ];

        foreach ($bets as $bet) {
            ValueBet::create($bet);
        }
    }
}
