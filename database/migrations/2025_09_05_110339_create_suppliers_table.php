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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('rfc', 17)->unique()->nullable(); // RFC o identificador fiscal
            $table->string('nickname')->nullable(); // Apodo o nombre corto
            $table->string('address')->nullable();
            $table->string('post_code', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable(); // Notas generales sobre el proveedor
            $table->timestamps();
            $table->softDeletes(); // Para borrado lógico
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
