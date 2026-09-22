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
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_family_id')
                  ->constrained('product_families')
                  ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->comment('Cantidad de piezas que caben en la caja');

            // Dimensiones de la caja (en centímetros) y peso (en kilogramos)
            $table->decimal('length_cm', 8, 2)->comment('Largo de la caja en cm');
            $table->decimal('width_cm', 8, 2)->comment('Ancho de la caja en cm');
            $table->decimal('height_cm', 8, 2)->comment('Alto de la caja en cm');
            $table->decimal('weight_kg', 8, 2)->comment('Peso de la caja en kg');

            $table->timestamps();

            // Índice para consultar rápido las tarifas de una familia ordenadas por cantidad
            $table->index(['product_family_id', 'quantity'], 'shipping_rates_family_quantity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
