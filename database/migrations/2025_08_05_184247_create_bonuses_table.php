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
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('calculation_type')->default('all_or_nothing_weekly');
            $table->float('full_time')->unsigned()->nullable();
            $table->float('half_time')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Crear la nueva tabla para las reglas de los bonos
        Schema::create('bonus_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_id')->constrained()->onDelete('cascade');
            $table->string('metric'); // ej: 'daily_late_minutes', 'weekly_unjustified_absences'
            $table->string('operator'); // ej: '<=', '==', '>='
            $table->string('value'); // ej: 15, 0
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonuses');
    }
};
