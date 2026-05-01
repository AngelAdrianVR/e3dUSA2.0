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
        Schema::create('quote_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->onDelete('cascade');
            
            // 1. PRODUCT_ID AHORA ES NULLABLE
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            
            // 2. CAMPOS PARA PRODUCTOS "CUSTOM" O TEMPORALES
            $table->string('custom_name')->nullable()->comment('Nombre del producto si no existe en el catálogo');
            $table->decimal('custom_cost', 12, 2)->nullable()->default(0)->comment('Costo estimado para calcular utilidades si no está en catálogo');
            $table->string('custom_measure_unit')->nullable()->comment('Unidad de medida temporal');

            // 3. CAMPOS ORIGINALES
            $table->float('quantity')->unsigned();
            $table->decimal('unit_price', 12, 2);
            $table->boolean('has_low_price')->default(false); // Nuevo campo para marcar si el precio está por debajo del establecido
            $table->text('low_price_reason')->nullable(); // Nuevo campo para que el usuario explique la razón del precio bajo
            $table->text('notes')->nullable();
            $table->boolean('show_image')->default(true);
            $table->json('customization_details')->nullable(); // Se guardan los detalles de personalizacion como info de grabado de medallon, etc.
            
            $table->string('customer_approval_status')->default('Pendiente'); // Pendiente, Aprobado, Rechazado
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_products');
    }
};
