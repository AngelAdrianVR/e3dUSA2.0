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
        Schema::create('stock_shortages', function (Blueprint $table) {
            $table->id();

            // Orden de venta y línea de la orden que generó el faltante.
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_product_id')->constrained('sale_products')->cascadeOnDelete();

            // Producto (terminado o componente) que no alcanzó a cubrirse con stock.
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('storage_id')->nullable()->constrained('storages')->nullOnDelete();

            // Cantidad que faltó al crear/actualizar la orden y cantidad que sigue pendiente
            // de rebajar cuando el usuario confirma la rebaja al registrar una entrada.
            $table->decimal('quantity', 10, 2);
            $table->decimal('quantity_pending', 10, 2);

            // Indica si el faltante es de un componente del producto de la orden.
            $table->boolean('is_component')->default(false);

            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'quantity_pending']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_shortages');
    }
};
