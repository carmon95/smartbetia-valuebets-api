<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('value_bets', function (Blueprint $table) {
        $table->decimal('total_line', 6, 2)->nullable()->after('market');
    });
}

public function down()
{
    Schema::table('value_bets', function (Blueprint $table) {
        $table->dropColumn('total_line');
    });
}

};
