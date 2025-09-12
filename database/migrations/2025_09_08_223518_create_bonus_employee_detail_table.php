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
        // Tabla pivote para la relación muchos a muchos entre EmployeeDetail y Bonus.
        // El nombre sigue la convención de Laravel (modelos en orden alfabético y singular).
        Schema::create('bonus_employee_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_detail_id')->constrained()->onDelete('cascade');
            $table->foreignId('bonus_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_employee_detail');
    }
};
