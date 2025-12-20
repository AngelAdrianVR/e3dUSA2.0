<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla Principal: Releases (Cabeceras)
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('version')->nullable(); // Ej: v1.2.0
            $table->string('title'); // Ej: Actualización de Módulo de Ventas
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // 2. Tabla de Detalle: Items (Pasos del Wizard)
        Schema::create('release_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->string('module_name')->nullable(); // Ej: Contabilidad
            $table->text('description'); // Explicación detallada
            $table->integer('order')->default(0); // Para ordenar los pasos
            $table->timestamps();
        });

        // 3. Tabla Pivote: Control de Lectura por Usuario
        Schema::create('release_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            
            // Evitar duplicados: Un usuario solo puede marcar como leída una release una vez
            $table->unique(['user_id', 'release_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_user');
        Schema::dropIfExists('release_items');
        Schema::dropIfExists('releases');
    }
};
