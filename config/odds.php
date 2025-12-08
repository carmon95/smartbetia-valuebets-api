<?php

return [

    'base_url' => env('ODDS_API_BASE_URL', 'https://api.the-odds-api.com/v4'),

    'key' => env('ODDS_API_KEY', '44d915cf1e45a34c9ce04d4d1c49e4ed'),

    // 👇 AHORA ES UNA LISTA (array) DE MÚLTIPLES SPORTS
    'sports' => explode(',', env('ODDS_API_SPORTS', 'soccer_spain_la_liga')),

    'regions'  => env('ODDS_API_REGIONS', 'eu'),

    // puedes usar: h2h, totals, spreads
    'markets'  => env('ODDS_API_MARKETS', 'h2h,totals,spreads'),

    'min_edge' => (float) env('ODDS_MIN_EDGE', 0.05),
];
