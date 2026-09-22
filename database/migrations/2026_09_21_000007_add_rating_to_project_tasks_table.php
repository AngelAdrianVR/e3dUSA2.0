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
        Schema::table('project_tasks', function (Blueprint $table) {
            // Calificación del desempeño (tipo Uber): 1 a 5 estrellas.
            // La otorga únicamente quien creó el proyecto y puede editarla.
            $table->unsignedTinyInteger('rating')->nullable()->after('completion_notes');
            $table->text('rating_note')->nullable()->after('rating');
            $table->foreignId('rated_by')->nullable()->after('rating_note')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('rated_at')->nullable()->after('rated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rated_by');
            $table->dropColumn(['rating', 'rating_note', 'rated_at']);
        });
    }
};
