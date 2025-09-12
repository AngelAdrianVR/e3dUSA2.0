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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            // Claves foráneas principales
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Columnas de la tabla
            $table->char('currency', 3)->default('MXN');
            $table->enum('type', ['venta', 'stock'])->default('venta');
            $table->string('status')->default('Pendiente'); // Pendiente, Autorizada, En Proceso, Completada, Enviada
            $table->string('oce_name')->nullable();
            $table->string('order_via')->nullable();
            $table->string('shipping_option')->nullable();
            $table->date('promise_date')->nullable(); // util para indicar fecha en producción
            $table->text('notes')->nullable();
            $table->boolean('is_high_priority')->default(false);
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('freight_option')->nullable();
            $table->decimal('freight_cost', 10, 2)->nullable();
            $table->string('authorized_user_name')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
