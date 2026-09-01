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
        // Revertir la penalización de 30 min por no checar break:
        // se elimina la bandera por nómina ya que el descanso de jornada
        // ahora siempre se descuenta (máximo entre configurado y registrado).
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('break_penalty_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->boolean('break_penalty_enabled')->default(true)->after('status');
        });
    }
};
