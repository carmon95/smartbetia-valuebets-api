<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NbaGame extends Model
{
    use HasFactory;

    protected $table = 'nba_games';

    protected $fillable = [
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

    protected $casts = [
        'tipoff_at' => 'datetime',
        'over_hit'  => 'boolean',
    ];
}
