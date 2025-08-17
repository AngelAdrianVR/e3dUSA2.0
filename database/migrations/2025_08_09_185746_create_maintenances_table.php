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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->text('problems')->nullable();
            $table->text('actions');
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('maintenance_type'); // Preventive, Corrective, Limpieza
            $table->string('responsible'); // quien hizo el mantenimiento
            $table->date('maintenance_date')->nullable(); // fecha de mantenimiento 
            $table->string('validated_by')->nullable();
            $table->json('spare_parts_used')->nullable(); // refacciones usadas para el mantenimiento
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
