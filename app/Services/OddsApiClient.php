<?php

namespace App\Services;

use GuzzleHttp\Client;

class OddsApiClient
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => rtrim(config('odds.base_url'), '/') . '/',
            'timeout'  => 10.0,
        ]);
    }

    /**
     * Trae cuotas de The Odds API para TODOS los deportes configurados.
     *
     * Devuelve un array de eventos (mezcla de La Liga, NBA, NFL, etc.).
     */
    public function fetchOdds(): array
    {
        // 🔹 1) Deportes desde config/odds.php, con fallback a una lista por defecto
        $sports = config('odds.sports');

        if (empty($sports) || !is_array($sports)) {
            // Fallback por si aún no configuraste nada en odds.sports
            $sports = [
                'basketball_nba',
                'americanfootball_nfl',
                'soccer_spain_la_liga',
                // aquí puedes seguir agregando más ligas soportadas por The Odds API
                // 'soccer_england_premier_league',
                // 'soccer_italy_serie_a',
            ];
        }

        // 🔹 2) Resto de parámetros con valores por defecto sensatos
        $regions = config('odds.regions', 'us');                // ej: 'us', 'eu'
        $markets = config('odds.markets', 'h2h,totals,spreads'); // mercados clásicos
        $apiKey  = config('odds.key');

        if (empty($apiKey)) {
            throw new \RuntimeException('ODDS_API_KEY no está configurada (odds.key).');
        }

        $allEvents = [];

        foreach ($sports as $sport) {
            try {
                \Log::info("Consultando The Odds API para sport={$sport}");

                $response = $this->client->get("sports/{$sport}/odds", [
                    'query' => [
                        'apiKey'      => $apiKey,
                        'regions'     => $regions,
                        'markets'     => $markets,
                        'oddsFormat'  => 'decimal',
                        'dateFormat'  => 'iso',
                    ],
                ]);

                $json = json_decode($response->getBody()->getContents(), true);

                if (is_array($json)) {
                    \Log::info("Eventos recibidos para {$sport}: " . count($json));
                    // Mergeamos todos los eventos de este deporte al total
                    $allEvents = array_merge($allEvents, $json);
                } else {
                    \Log::warning("Respuesta inesperada de The Odds API para sport={$sport}", [
                        'body' => $response->getBody()->getContents(),
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning("Error consultando The Odds API para sport={$sport}: " . $e->getMessage());
                // seguimos con el siguiente deporte
            }
        }

        \Log::info('Total de eventos combinados desde The Odds API: ' . count($allEvents));

        return $allEvents;
    }
}
