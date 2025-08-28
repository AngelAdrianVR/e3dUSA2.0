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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('Empacado'); // 'Empacado', 'Enviado', 'Entregado'
            $table->string('shipping_company')->nullable();
            $table->date('promise_date')->nullable();
            $table->string('tracking_guide')->nullable();
            $table->integer('number_of_packages')->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
