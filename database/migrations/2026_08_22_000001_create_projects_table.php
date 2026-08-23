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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Información general
            $table->string('name');
            $table->text('description')->nullable();

            // Presupuesto de inversión (moneda seleccionable MXN/USD)
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency')->default('MXN');

            // Fechas del proyecto
            $table->date('start_date');
            $table->date('tentative_end_date')->nullable(); // Fecha tentativa de finalización
            $table->date('actual_end_date')->nullable();    // Fecha real de finalización

            // Usuario que creó el proyecto
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
