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
        Schema::create('design_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_title');
            $table->text('specifications');
            $table->enum('status', ['Pendiente', 'Autorizada', 'En proceso', 'Terminada', 'Cancelada'])->default('Pendiente');
            $table->boolean('is_hight_priority')->default(false);
            $table->foreignId('requester_id')->comment('ID del vendedor que solicita')->constrained('users');
            $table->foreignId('designer_id')->nullable()->comment('ID del diseñador asignado')->constrained('users');
            $table->foreignId('design_category_id')->constrained('design_categories');
            $table->foreignId('design_id')->nullable()->constrained('designs');
            // Este campo guardará el ID del diseño que se está modificando.
            // Es nullable porque las órdenes nuevas no modifican nada.
            // Lo ponemos después de 'design_id' para mantener el orden lógico.
            $table->foreignId('modifies_design_id')->nullable()->constrained('designs')->onDelete('set null'); // Si se borra el diseño original, no borramos la orden.
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('contact_id')->nullable()->constrained('contacts');
            $table->string('authorized_user_name')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->text('reuse_justification')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_orders');
    }
};
