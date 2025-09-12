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
        Schema::create('new_product_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('specifications')->nullable(); // For technical details, etc.
            
            $table->enum('status', ['Propuesta', 'Aprobado', 'Rechazado'])->default('Propuesta');
            
            // This will be filled when the product is approved and created in the main catalog
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('new_product_proposals');
    }
};
