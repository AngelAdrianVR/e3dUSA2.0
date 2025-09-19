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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // --- Relaciones Fundamentales ---
            // Se vincula con la Orden de Venta. Esencial.
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            // Guarda el usuario que creó la factura para auditoría.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Opcional: Si manejas sucursales.
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            // --- Información de la Factura ---
            $table->string('folio')->unique(); // Folio único para identificar la factura.
            // Usamos decimal para montos monetarios, es más preciso que float.
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('MXN'); // Moneda (ej. MXN, USD).

            // --- Control de Parcialidades ---
            // Número de esta parcialidad (ej. 1, 2, 3).
            $table->unsignedTinyInteger('installment_number')->default(1);
            // Total de parcialidades para esta venta (ej. 4). Ayuda en la UI a mostrar "Factura 2 de 4".
            $table->unsignedTinyInteger('total_installments')->default(1);

            // --- Fechas Clave ---
            $table->date('issue_date'); // Fecha de emisión de la factura.
            // ¡La columna clave! Indica cuándo se debe pagar. Reemplaza la tabla 'programed_invoices'.
            $table->date('due_date');
            // Se actualiza cuando la factura es pagada completamente.
            $table->timestamp('paid_at')->nullable();

            // --- Estado y Detalles Adicionales ---
            // Controla el ciclo de vida de la factura.
            $table->enum('status', ['Pendiente', 'Pagada', 'Vencida', 'Cancelada'])->default('Pendiente');
            // PUE (Pago en una sola exhibición), PPD (Pago en parcialidades o diferido).
            $table->string('payment_option')->nullable();
            $table->string('payment_method')->nullable(); // Método de pago (transferencia, etc.).
            $table->text('notes')->nullable(); // Notas adicionales.

            // --- Tiempos de creación y actualización ---
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
