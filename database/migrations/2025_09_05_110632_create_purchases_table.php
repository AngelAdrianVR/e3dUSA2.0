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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            // $table->string('type', 20)->default('Venta'); // si es para Muestra o Venta
            $table->boolean('is_spanish_template')->default(true);

            $table->enum('status', ['Pendiente', 'Autorizada', 'Compra realizada', 'Compra recibida', 'Cancelada'])->default('Pendiente');
            
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->comment('Usuario que creó la orden')->constrained('users')->onDelete('cascade');
            
            // Se normaliza el usuario que autoriza y el contacto de la compra
            $table->foreignId('authorizer_id')->nullable()->comment('Usuario que autorizó')->constrained('users')->onDelete('set null');
            $table->foreignId('supplier_contact_id')->nullable()->comment('Contacto del proveedor para esta compra')->constrained('supplier_contacts')->onDelete('set null');
            $table->foreignId('supplier_bank_account_id')->nullable()->constrained()->onDelete('set null');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0); // Impuestos (IVA, etc.)
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('MXN');

            $table->text('notes')->nullable();
            $table->text('shipping_details')->nullable(); // Detalles del envío, paquetería, etc.
            
            $table->timestamp('emited_at')->nullable(); // Fecha de emisión
            $table->timestamp('authorized_at')->nullable(); // Fecha de autorización
            $table->date('expected_delivery_date')->nullable(); // Fecha esperada de entrega
            $table->timestamp('recieved_at')->nullable(); // Fecha de recepción final
            
            $table->string('invoice_folio')->nullable(); // Folio de la factura del proveedor
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
