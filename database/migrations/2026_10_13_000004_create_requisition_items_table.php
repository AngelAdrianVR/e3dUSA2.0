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
        // Una requisición puede solicitar varios productos (una lista).
        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->comment('Producto padre solicitado')->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);

            // Estatus de arribo por producto: define el color en el calendario.
            $table->string('arrival_status')->default('Pendiente')->comment('Pendiente, En tránsito, Incidencia, Arribado');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
    }
};
