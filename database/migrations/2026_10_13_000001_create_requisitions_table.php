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
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();

            // Tipo de compra y vía de entrada: Local/Importación desde Vendedor o Almacén.
            $table->string('type')->index()->comment('Local, Importación');
            $table->string('origin')->comment('Vendedor, Almacén');

            // Etapa del proceso: ver catálogo en el modelo Requisition.
            $table->string('status')->index();
            $table->string('priority')->nullable()->comment('Baja, Media, Urgente');
            $table->string('arrival_status')->default('Pendiente')->comment('Pendiente, En tránsito, Incidencia, Arribado');

            $table->foreignId('user_id')->comment('Usuario que levantó la requisición')->constrained('users')->cascadeOnDelete();

            // Vía vendedor: cliente (sucursal) y snapshot de la razón social.
            $table->foreignId('branch_id')->nullable()->comment('Cliente que solicita')->constrained()->nullOnDelete();
            $table->string('business_name')->nullable()->comment('Razón social del cliente al momento de la requisición');

            // Vía almacén / producción.
            $table->foreignId('product_id')->nullable()->comment('Producto padre solicitado')->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->text('reason')->nullable()->comment('Motivo de la requisición');
            $table->text('sale_specification')->nullable()->comment('Especificación de venta');

            $table->text('notes')->nullable();

            // Cotización y facturación (compras locales).
            $table->decimal('quoted_cost', 12, 2)->nullable();
            $table->string('quoted_currency', 3)->nullable();
            $table->decimal('invoice_amount', 12, 2)->nullable();
            $table->string('invoice_currency', 3)->nullable();
            $table->timestamp('paid_at')->nullable()->comment('Fecha en que tesorería realizó el pago');

            // Validación (Pass/Fail) de la gerencia de compras.
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->text('rejection_reason')->nullable()->comment('Motivo del rechazo; se re-trabaja sobre el mismo folio');
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            // Logística / entrega.
            $table->date('expected_delivery_date')->nullable()->comment('Fecha estimada de entrega o arribo');
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
