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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name');
            $table->string('contact_phone')->nullable();
            $table->string('company_name')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status')->default('Nueva')->comment('Ej: Nueva, Contactada, Propuesta, Cierre');
            $table->string('priority')->comment('Ej: Baja, Media, Alta');
            $table->text('description')->nullable();
            $table->text('lost_reason')->nullable();
            $table->unsignedTinyInteger('probability')->nullable()->comment('Probabilidad de cierre del 1 al 100');
            $table->timestamp('closed_at')->nullable()->comment('Fecha en que se ganó o perdió');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('estimated_close_date')->nullable();

            // Llaves foráneas
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->comment('Usuario que creó la oportunidad')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->comment('Vendedor asignado')->constrained('users')->cascadeOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
