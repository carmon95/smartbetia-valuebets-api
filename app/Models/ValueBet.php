<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValueBet extends Model
{
    protected $fillable = [
        'sport',
        'league',
        'match',
        'market',
        'total_line',   // 👈 AÑADIR ESTO
        'bookmaker',
        'odds',
        'implied_probability',
        'model_probability',
        'edge',
        'risk_label',
        'kickoff_at',
        'is_active',
    ];

    protected $casts = [
        'kickoff_at' => 'datetime',
        'is_active' => 'boolean',
        'odds' => 'float',
        'implied_probability' => 'float',
        'model_probability' => 'float',
        'edge' => 'float',
    ];
}
