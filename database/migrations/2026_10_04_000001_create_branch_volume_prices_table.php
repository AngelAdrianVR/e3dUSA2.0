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
        Schema::create('branch_volume_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            // Usuario que registró los rangos (puede ser nulo)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Rango de cantidad: min_quantity inclusivo, max_quantity nulo = sin límite superior
            $table->decimal('min_quantity', 12, 2);
            $table->decimal('max_quantity', 12, 2)->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('MXN'); // moneda MXN, USD

            $table->timestamps();

            $table->index(['branch_id', 'product_id'], 'branch_volume_prices_branch_product_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_volume_prices');
    }
};
