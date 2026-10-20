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
        Schema::create('requisition_shipments', function (Blueprint $table) {
            $table->id();

            $table->string('tracking_number')->unique()->comment('Número de guía de paquetería');
            $table->string('carrier')->nullable()->comment('Paquetería o medio de transporte');

            // Estatus de arribo (define el color en el calendario).
            $table->string('status')->default('Pendiente')->comment('Pendiente, En tránsito, Incidencia, Arribado');

            $table->date('estimated_arrival_date')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisition_shipments');
    }
};
