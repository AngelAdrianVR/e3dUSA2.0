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
        Schema::create('branch_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('company_branch_id')->constrained('company_branches')->onDelete('cascade');
            $table->float('price')->unsigned();
            $table->timestamps();

            // Opcional: Para asegurar que no haya duplicados de producto-sucursal
            $table->unique(['product_id', 'company_branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_pricings');
    }
};
