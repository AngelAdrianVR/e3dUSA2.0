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
        Schema::create('product_exchanges', function (Blueprint $table) {
            $table->id();
            
            // Relación con la venta original para trazabilidad
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            
            // Usuario que realiza/autoriza el cambio (Auditoría)
            $table->foreignId('user_id')->constrained('users');
            
            // PRODUCTO QUE EL CLIENTE DEVUELVE (Entrada al almacén)
            $table->foreignId('returned_product_id')->constrained('products');
            $table->integer('returned_quantity');
            
            // PRODUCTO QUE SE ENTREGA AL CLIENTE (Salida del almacén)
            $table->foreignId('new_product_id')->constrained('products');
            $table->integer('new_quantity');
            
            // Diferencia monetaria (positivo = cliente paga más, negativo = saldo a favor)
            // Esto es útil si los productos tienen precios distintos.
            $table->decimal('price_difference', 10, 2)->default(0);
            
            // Motivo del cambio (Defecto, Error de pedido, etc.)
            $table->text('reason')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_exchanges');
    }
};
