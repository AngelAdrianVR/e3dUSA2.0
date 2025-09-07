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
        Schema::create('design_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Definir qué tan complejo es un tipo de trabajo.
            $table->enum('complexity', ['Simple', 'Medio', 'Complejo']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_categories');
    }
};
