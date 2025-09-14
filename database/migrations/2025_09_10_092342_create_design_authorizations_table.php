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
            $table->string('material')->nullable(); // Mantenemos como texto simple
            $table->string('color')->nullable();

            // --- LA SOLUCIÓN SENCILLA ---
            // Reemplazamos 'engrave_method' por una columna JSON.
            // Aquí puedes guardar un array de strings con los métodos.
            // Ej: ['Serigrafía', 'Emblema Pegado']
            $table->json('production_methods')->nullable();
            
            $table->text('specifications')->nullable();
            $table->string('logistic_details')->nullable();
            // $table->unsignedInteger('quantity');

            // Campos para el seguimiento de la autorización
            $table->timestamp('responded_at')->nullable();
            $table->boolean('is_accepted')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('authorizer_name')->nullable();

            // Relaciones
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('contact_id')->constrained();
            
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
