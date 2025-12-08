<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nba_games', function (Blueprint $table) {
            $table->id();

            // Temporada del partido (ej: "2024-25")
            $table->string('season', 9);

            // Fecha y hora oficial del partido
            $table->dateTime('tipoff_at');

            // Equipos
            $table->string('home_team');
            $table->string('away_team');

            // Marcador final
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();

            // Total de puntos (home + away)
            $table->unsignedSmallInteger('total_points')->nullable();

            // Línea (closing line) del mercado Over/Under
            $table->decimal('closing_total_line', 6, 1)->nullable(); // ej: 224.5

            // Cuotas del mercado
            $table->decimal('over_odds', 5, 2)->nullable();  // ej. 1.91
            $table->decimal('under_odds', 5, 2)->nullable();

            // Etiqueta para el modelo: 1 si se cumplió el Over
            $table->boolean('over_hit')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nba_games');
    }
};
