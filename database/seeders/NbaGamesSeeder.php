<?php

namespace Database\Seeders;

use App\Models\NbaGame;
use Illuminate\Database\Seeder;

class NbaGamesSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'season'             => '2024-25',
                'tipoff_at'          => '2024-10-25 19:30:00',
                'home_team'          => 'Los Angeles Lakers',
                'away_team'          => 'Golden State Warriors',
                'home_score'         => 118,
                'away_score'         => 115,
                'total_points'       => 233,
                'closing_total_line' => 224.5,
                'over_odds'          => 1.90,
                'under_odds'         => 1.90,
                'over_hit'           => true,
            ],
            [
                'season'             => '2024-25',
                'tipoff_at'          => '2024-10-26 20:00:00',
                'home_team'          => 'Boston Celtics',
                'away_team'          => 'Miami Heat',
                'home_score'         => 104,
                'away_score'         => 99,
                'total_points'       => 203,
                'closing_total_line' => 214.5,
                'over_odds'          => 1.95,
                'under_odds'         => 1.85,
                'over_hit'           => false,
            ],
            [
                'season'             => '2024-25',
                'tipoff_at'          => '2024-10-27 19:00:00',
                'home_team'          => 'Dallas Mavericks',
                'away_team'          => 'Phoenix Suns',
                'home_score'         => 120,
                'away_score'         => 112,
                'total_points'       => 232,
                'closing_total_line' => 228.5,
                'over_odds'          => 1.88,
                'under_odds'         => 1.92,
                'over_hit'           => true,
            ],
        ];

        foreach ($games as $game) {
            NbaGame::create($game);
        }
    }
}
