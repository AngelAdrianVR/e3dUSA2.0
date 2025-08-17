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
        Schema::create('product_production_cost', function (Blueprint $table) {
        $table->id();

        // Clave foránea para el producto
        $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

        // Clave foránea para el proceso de producción
        $table->foreignId('production_cost_id')->constrained('production_costs')->onDelete('cascade');

        // Campos adicionales específicos de esta relación
        // $table->decimal('cost', 8, 2)->comment('Costo específico de este proceso para este producto');
        // $table->unsignedInteger('estimated_time_minutes')->comment('Tiempo estimado en minutos para completar el proceso');
        $table->unsignedSmallInteger('order')->default(0)->comment('Orden de ejecución del proceso');

        $table->timestamps();

        // Aseguramos que no se pueda repetir el mismo proceso para el mismo producto
        $table->unique(['product_id', 'production_cost_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_production_cost');
    }
};
