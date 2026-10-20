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
        Schema::table('requisition_items', function (Blueprint $table) {
            // Costo unitario capturado al adjuntar la cotización del proveedor.
            $table->decimal('unit_cost', 12, 2)->nullable()->after('quantity');
            // Descripción / nombre del producto cotizado cuando no existe en el catálogo.
            $table->text('description')->nullable()->after('unit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'description']);
        });
    }
};
