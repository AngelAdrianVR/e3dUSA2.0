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
        Schema::create('sale_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->integer('quantity');
            $table->integer('quantity_to_produce')->default(0);
            $table->decimal('price', 10, 2);
            $table->boolean('is_new_design')->default(false);
            $table->json('customization_details')->nullable();
            $table->text('notes')->nullable();
            $table->integer('quantity_produced')->default(0);
            $table->integer('quantity_shipped')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_products');
    }
};
