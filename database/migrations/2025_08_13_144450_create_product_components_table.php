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
        Schema::create('product_components', function (Blueprint $table) {
             // El producto terminado o ensamblado.
            $table->foreignId('catalog_product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();

            // La materia prima o componente.
            $table->foreignId('component_product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            
            // Cantidad del componente para UNA unidad del ensamble.
            $table->decimal('quantity', 8, 2);
            $table->decimal('cost', 8, 2);

            $table->timestamps();

            // Restricción para no agregar el mismo componente dos veces al mismo ensamble.
            // $table->unique(['catalog_product_id', 'component_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
