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
        Schema::create('production_costs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Nombre del proceso. Ej: "Grabado Láser", "Ensamblado de Medallón"
            $table->text('description')->nullable(); // Descripción de lo que implica el proceso

            // Define cómo se calcula el costo para dar más flexibilidad
            $table->enum('cost_type', ['Hora', 'Pieza'])->default('Pieza');

            // El valor del costo. Si es 'Hora', es costo/hr. Si es 'per_unit', es costo por pieza.
            $table->decimal('cost', 10, 2)->default(0);

            // Tiempo estándar en segundos para completar la operación para 1 unidad.
            // Útil para calcular el costo total si el tipo es 'Hora' y para estimar tiempos de producción.
            $table->unsignedInteger('estimated_time_seconds')->nullable();

            // Para poder desactivar un proceso sin borrarlo
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_costs');
    }
};
