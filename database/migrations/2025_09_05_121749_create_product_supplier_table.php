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
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            
            // Campos adicionales para la relación
            $table->decimal('last_price', 10, 2)->nullable(); // Último precio de compra para referencia
            $table->string('supplier_sku')->nullable(); // SKU o código del producto para ese proveedor específico
            $table->string('min_quantity')->nullable(); // cantidad minima que se le puede comprar al proveedor

            $table->unique(['product_id', 'supplier_id']); // Evita duplicados
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
