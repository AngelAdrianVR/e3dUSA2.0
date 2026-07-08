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
        Schema::create('pms_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique(); // Ej: PMS-2026-001
            
            // Relación polimórfica opcional para vincular a ProductionTask o Task
            $table->nullableMorphs('sourceable'); 

            // URL directa al módulo que originó la tarea para ver sus detalles completos
            $table->string('reference_url')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            
            // Categorización según la imagen de tu cliente
            $table->enum('department', ['Producción', 'Ventas', 'Administración', 'Diseño', 'General'])->default('General');
            $table->string('origin')->nullable(); // Dirección, Junta, Cliente, etc.
            $table->enum('priority', ['Alta', 'Media', 'Baja'])->default('Media');
            
            // Estatus estricto del tablero Kanban ISO
            $table->enum('kanban_status', ['Pendiente', 'En proceso', 'Validación', 'Terminado'])->default('Pendiente');
            
            // Tiempos
            $table->dateTime('start_date');
            $table->dateTime('due_date'); // Fecha compromiso
            $table->timestamp('finished_at')->nullable(); // Fecha real de término
            
            // Responsable (Spatie Roles dictará qué puede hacer)
            // Nullable para permitir "Tareas por asignar"
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Para auditoría extra además de MediaLibrary
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_tasks');
    }
};
