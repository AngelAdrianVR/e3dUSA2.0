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
        Schema::create('favored_stock_requests', function (Blueprint $table) {
            $table->id();
            
            // Origen del stock
            $table->foreignId('favored_product_id')->constrained('favored_products')->onDelete('cascade');
            
            // Quién solicitó
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Detalles de la solicitud
            $table->decimal('quantity_requested', 15, 2);
            $table->string('shipping_method'); // 'plane', 'ship', 'factory'
            
            // Snapshot de cantidades en 'favored_products'
            $table->decimal('quantity_before_request', 15, 2);
            $table->decimal('quantity_after_request', 15, 2);
            
            // Estado
            $table->string('status')->default('Solicitado'); // Solicitado, Recibido
            $table->timestamp('received_at')->nullable(); // Fecha en que se marcó como recibido

            $table->timestamps(); // 'created_at' es la fecha de solicitud
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favored_stock_requests');
    }
};
