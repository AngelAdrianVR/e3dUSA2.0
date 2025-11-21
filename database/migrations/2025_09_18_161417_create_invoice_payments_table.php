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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();

            // --- Relaciones ---
            // Vincula el pago con la factura correspondiente.
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // Guarda el usuario que registró el pago.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // --- Información del Pago ---
            // El monto de este pago parcial.
            $table->decimal('amount', 12, 2);
            // La fecha en que se realizó el pago.
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
