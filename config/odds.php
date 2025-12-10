<?php

return [
    'base_url' => 'https://api.the-odds-api.com/v4/',
    'key'      => env('ODDS_API_KEY'),

    // 👇 estos puedes quitarlos si ya dejamos defaults en el servicio,
    // pero es buena práctica tenerlos igual:
    'regions'  => 'us',
    'markets'  => 'h2h,totals,spreads',

    // 👇 lista de deportes (si la dejas vacía, el servicio usa el fallback)
    'sports'   => [
        'basketball_nba',
        'americanfootball_nfl',
        'soccer_spain_la_liga',
        // 'soccer_england_premier_league',
        // 'soccer_italy_serie_a',
    ],
];
