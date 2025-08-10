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
        Schema::create('audits', function (Blueprint $table) {
            $table->id();

            // Relación polimórfica para el usuario que realiza la acción (puede ser un usuario, un API token, etc.)
            // Crea las columnas `user_id` y `user_type`. Nullable por si la acción es del sistema.
            $table->nullableMorphs('user');

            // El evento que ocurrió (created, updated, deleted)
            $table->string('event');

            // Relación polimórfica para el modelo que está siendo auditado (User, Bonus, Product, etc.)
            // Crea las columnas `auditable_id` y `auditable_type`.
            $table->morphs('auditable');

            // Columnas para guardar los valores viejos y nuevos en formato JSON.
            // Se usa text para máxima compatibilidad con bases de datos.
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();

            // Información adicional del contexto de la acción
            $table->text('url')->nullable(); // La URL donde se ejecutó la acción
            $table->ipAddress('ip_address')->nullable(); // La dirección IP del usuario
            $table->string('user_agent', 1023)->nullable(); // El navegador o cliente del usuario
            $table->string('tags')->nullable(); // Para agrupar o categorizar auditorías

            $table->timestamps(); // Crea las columnas created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
