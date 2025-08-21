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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('Esperando respuesta'); // Esperando respuesta, Aceptada, Rechazada
            $table->string('receiver')->nullable();
            $table->string('department')->nullable();
            
            // Costos y Moneda
            $table->char('currency', 3)->default('MXN');
            $table->decimal('tooling_cost', 12, 2)->default(0);
            $table->boolean('is_tooling_cost_stroked')->default(false); // costo de herramental condonado (tacha la cantidad)
            
            // Lógica de Flete
            $table->string('freight_option')->default('Por cuenta del cliente'); // Por cuenta del cliente, Cargo de flete prorrateado en productos, La empresa absorbe el costo de flete, Cliente manda guia
            $table->decimal('freight_cost', 12, 2)->default(0);
            $table->boolean('is_freight_cost_stroked')->default(false); // costo de envío condonado (tacha la cantidad)

            // Detalles y Notas
            $table->string('first_production_days');
            $table->text('notes')->nullable();

            // Aceptación / Rechazo
            $table->text('rejection_reason')->nullable();
            $table->timestamp('customer_responded_at')->nullable();
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('authorized_at')->nullable();

            // Metadatos y Configuración
            $table->boolean('is_spanish_template')->default(true);
            $table->boolean('show_breakdown')->default(false);
            $table->boolean('created_by_customer')->default(false);

            // Descuento por Pago Anticipado
            $table->boolean('has_early_payment_discount')->default(false);
            $table->decimal('early_payment_discount_amount', 12, 2)->default(0);
            $table->timestamp('early_paid_at')->nullable();

            // Relaciones
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // usuario que creó la cotización
            $table->foreignId('sale_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
