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
        // Actualizaciones / eventos de una guía: cada registro se pinta en el
        // calendario de arribos con una fecha propia, distinta de la fecha
        // tentativa de arribo de la guía.
        Schema::create('requisition_shipment_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requisition_shipment_id')->constrained('requisition_shipments')->cascadeOnDelete();
            $table->date('event_date')->index()->comment('Día en que ocurrió la actualización o evento');
            $table->text('description')->comment('Descripción de la actualización o evento');

            $table->foreignId('created_by')->nullable()->comment('Usuario que registró el evento')->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_shipment_events');
    }
};
