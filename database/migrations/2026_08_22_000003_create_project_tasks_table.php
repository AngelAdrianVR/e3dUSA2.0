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
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Fechas de la tarea
            $table->date('start_date');
            $table->date('due_date')->nullable();      // Fecha límite de finalización
            $table->date('finished_at')->nullable();   // Fecha real de finalización

            // Estatus del tablero Kanban
            $table->enum('status', ['Pendiente', 'En proceso', 'Pausada', 'Terminada'])->default('Pendiente');

            // Responsable (solo puede ser miembro del proyecto)
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Auditoría
            $table->foreignId('created_by')->constrained('users');

            // Orden dentro de las columnas del Kanban (drag & drop)
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
