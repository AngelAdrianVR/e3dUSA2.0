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
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            $table->float('quantity')->unsigned();
            $table->decimal('unit_price', 12, 2);
            $table->text('notes')->nullable();
            $table->json('customization_details')->nullable(); // Se guardan los detalles de personalizacion como info de grabado de medallon, etc.
            
            $table->string('customer_approval_status')->default('Pendiente'); // Pendiente, Aprovado, Rechazado
            
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
