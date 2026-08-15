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
        Schema::create('salary_increases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_detail_id')->constrained()->onDelete('cascade');
            $table->decimal('previous_amount', 10, 2); // cantidad anterior
            $table->decimal('new_amount', 10, 2); // cantidad después del aumento
            $table->text('notes')->nullable(); // notas adicionales opcionales
            $table->date('increase_date'); // fecha del aumento
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_increases');
    }
};
