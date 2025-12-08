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
    Schema::create('value_bets', function (Blueprint $table) {
        $table->id();
        $table->string('sport', 50);            // football, basketball, etc.
        $table->string('league', 100);          // LaLiga, NBA, etc.
        $table->string('match', 200);           // Real Madrid vs Sevilla
        $table->string('market', 200);          // Over 2.5 goles, 1X2, etc.
        $table->string('bookmaker', 100);       // Casa de apuestas
        $table->decimal('odds', 6, 2);          // cuota decimal
        $table->decimal('implied_probability', 6, 4);   // 0..1
        $table->decimal('model_probability', 6, 4);     // 0..1
        $table->decimal('edge', 6, 4);                  // model - implied
        $table->string('risk_label', 50);       // Riesgo bajo/medio/alto/muy alto
        $table->dateTime('kickoff_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('value_bets');
    }
};
