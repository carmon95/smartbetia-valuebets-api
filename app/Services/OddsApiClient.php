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
        $sports  = config('odds.sports', []);   // lista de sport_keys
        $regions = config('odds.regions');      // ej: eu
        $markets = config('odds.markets');      // ej: h2h,totals
        $apiKey  = config('odds.key');

        if (empty($apiKey)) {
            throw new \RuntimeException('ODDS_API_KEY no está configurada.');
        }

        if (empty($sports) || !is_array($sports)) {
            throw new \RuntimeException('No hay deportes configurados en odds.sports.');
        }

        $allEvents = [];

        foreach ($sports as $sport) {
            try {
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
                    // Mergeamos todos los eventos de este deporte al total
                    $allEvents = array_merge($allEvents, $json);
                }
            } catch (\Throwable $e) {
                \Log::warning("Error consultando The Odds API para sport={$sport}: ".$e->getMessage());
                // seguimos con el siguiente deporte
            }
        }

        return $allEvents;
    }
}
