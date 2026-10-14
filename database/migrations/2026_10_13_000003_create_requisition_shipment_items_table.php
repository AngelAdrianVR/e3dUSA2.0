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
        // Permite ligar varias requisiciones a una misma guía de paquetería
        // sin duplicar registros en el calendario de arribos.
        Schema::create('requisition_shipment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requisition_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['requisition_shipment_id', 'requisition_id'], 'requisition_shipment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_shipment_items');
    }
};
