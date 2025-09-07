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
        Schema::create('employee_details', function (Blueprint $table) {
            $table->id();
            $table->float('week_salary')->unsigned(); // salario semanal
            $table->date('birthdate')->nullable(); // Cumpleaños
            $table->timestamp('join_date')->default(now()); // fecha de ingreso
            $table->string('job_position')->nullable(); // puesto de trabajo
            $table->string('department')->nullable(); // departamento
            $table->float('hours_per_week')->unsigned(); // horas por semana
            $table->json('department_details')->nullable(); // informacion adicional con respecto al departamento de trabajo
            $table->json('work_days')->nullable(); // dias de trabajo
            $table->json('vacations')->nullable(); // vacaciones

            // falta bonuses y discounts pero eso se pondra un una tabla pivote
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_details');
    }
};
