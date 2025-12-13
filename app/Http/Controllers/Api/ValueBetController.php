<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ValueBet;
use Illuminate\Http\Request;

class ValueBetController extends Controller
{
    public function index(Request $request)
    {
        // Punto de corte de fechas:
        // solo mostrar partidos cuyo kickoff sea desde el inicio del día de hoy en adelante
        $cutoff = now()->subHours(12);

        $query = ValueBet::query()
    ->where('is_active', true)
    ->whereNotNull('kickoff_at')
    ->where('kickoff_at', '>=', $cutoff);


        // Filtro opcional por deporte
        if ($sport = $request->query('sport')) {
            $query->where('sport', $sport);
        }

        // Filtro opcional por liga
        if ($league = $request->query('league')) {
            $query->where('league', $league);
        }

        // --------- FILTRO POR EDGE ---------
        // Si viene por querystring, lo usamos; si no, aplicamos el mínimo global.
        $minEdgeParam = $request->query('min_edge');

        if ($minEdgeParam !== null) {
            $query->where('edge', '>=', (float) $minEdgeParam);
        } else {
            // valor por defecto desde config/odds.php (ODDS_MIN_EDGE en .env)
            $defaultMinEdge = config('odds.min_edge', 0.05);
            $query->where('edge', '>=', $defaultMinEdge);
        }

        // --------- FILTRO POR RIESGO MÁXIMO ---------
        if ($maxRisk = $request->query('max_risk')) {
            // ejemplo sencillito: hasta “riesgo medio”
            if ($maxRisk === 'medium') {
                $query->whereIn('risk_label', ['Riesgo bajo', 'Riesgo medio']);
            }
        }

        // Límite de registros (por defecto 20)
        $limit = (int) $request->query('limit', 20);

        // Orden: primero por kickoff, luego por mejor edge
        $bets = $query
            ->whereNotNull('kickoff_at')     // opcional, por seguridad
            ->orderBy('kickoff_at', 'asc')   // partidos más cercanos primero
            ->orderByDesc('edge')            // dentro del mismo partido, mejor edge
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $bets,
        ]);
    }
}
