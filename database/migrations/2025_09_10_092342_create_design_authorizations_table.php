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
        Schema::create('design_authorizations', function (Blueprint $table) {
            $table->id();

            // Llave foránea para vincular con la orden de diseño.
            // Recomiendo mantener esto para la trazabilidad del flujo.
            $table->foreignId('design_order_id')->constrained()->cascadeOnDelete();

            $table->string('version')->default('1.0');
            $table->string('product_name');
            $table->string('product_type')->nullable(); // NUEVO: Tipo de producto
            $table->string('material')->nullable(); // Se mantiene como string, pero en frontend será select
            $table->string('color')->nullable();
            $table->json('pantone')->nullable(); // NUEVO: Pantone (Array de pantones)
            
            // Reemplazamos 'engrave_method' por una columna JSON.
            // Aquí puedes guardar un array de strings con los métodos.
            // Ej: ['Serigrafía', 'Emblema Pegado']
            $table->json('production_methods')->nullable();
            
            $table->text('specifications')->nullable();
            $table->string('logistic_details')->nullable();

            // Campos para el seguimiento de la autorización
            $table->timestamp('responded_at')->nullable();
            $table->boolean('is_accepted')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('authorizer_name')->nullable();
            $table->timestamp('authorized_at')->nullable();

            // Relaciones
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('contact_id')->constrained();

            // Datos técnicos adicionales
            $table->string('dimensions')->nullable()->comment('Medidas del producto');

            // Datos comerciales
            $table->string('delivery_time')->nullable()->comment('Tiempo de entrega estimado');
            $table->unsignedInteger('minimum_volume')->nullable()->comment('Volumen mínimo requerido');
            
            // Costos y Precios (Usamos decimal para manejar centavos correctamente)
            $table->decimal('printing_tooling_cost', 10, 2)->nullable()->comment('Costo del herramental de impresión');
            $table->decimal('injection_tooling_cost', 10, 2)->nullable()->comment('Costo del herramental de inyección');
            $table->decimal('unit_price', 10, 2)->nullable()->comment('Precio por unidad');
            $table->decimal('freight_cost', 10, 2)->nullable()->comment('Costo de flete');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_authorizations');
    }
};
