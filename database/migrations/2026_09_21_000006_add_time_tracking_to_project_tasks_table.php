<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            // Tiempo invertido acumulado (segundos de sesiones ya cerradas)
            $table->unsignedBigInteger('time_spent_seconds')->default(0)->after('status');

            // Inicio de la sesión en curso (null = cronómetro detenido)
            $table->timestamp('timer_started_at')->nullable()->after('time_spent_seconds');

            // Notas opcionales que se capturan al finalizar la tarea (junto con la evidencia)
            $table->text('completion_notes')->nullable()->after('timer_started_at');
        });

        // Tareas que ya están "En proceso": el cronómetro arranca desde su última actualización
        DB::table('project_tasks')
            ->where('status', 'En proceso')
            ->whereNull('timer_started_at')
            ->update(['timer_started_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['time_spent_seconds', 'timer_started_at', 'completion_notes']);
        });
    }
};
