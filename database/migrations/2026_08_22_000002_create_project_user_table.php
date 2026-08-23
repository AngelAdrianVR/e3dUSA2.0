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
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Rol del miembro dentro del proyecto:
            // 'viewer' = solo lectura (ver y comentar) | 'editor' = lectura y escritura (editar detalles y tareas)
            $table->enum('role', ['viewer', 'editor'])->default('viewer');

            $table->timestamps();

            // Un usuario solo puede tener un rol por proyecto
            $table->unique(['project_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};
