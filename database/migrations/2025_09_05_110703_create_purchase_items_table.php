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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            $table->string('type', 20)->default('Venta'); // si es para Muestra o Venta
            $table->string('description'); // Descripción del producto al momento de la compra (puede variar del nombre actual del producto)
            $table->decimal('quantity', 10, 2);
            $table->decimal('quantity_received', 10, 2)->default(0);
            $table->decimal('additional_stock', 10, 2)->nullable(); // cantidad a favor
            $table->decimal('plane_stock', 10, 2)->nullable(); // cantidad en avion
            $table->decimal('ship_stock', 10, 2)->nullable(); // cantidad en barco
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_price', 12, 2);
            $table->decimal('mold_price', 12, 2)->nullable(); // Precio del molde (si aplica)
            $table->boolean('needs_mold')->default(false); // si necesita molde
            $table->string('notes')->nullable(); // notas
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
