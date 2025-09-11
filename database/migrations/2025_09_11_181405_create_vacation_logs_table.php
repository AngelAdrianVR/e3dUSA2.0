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
        Schema::create('vacation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_detail_id')->constrained()->onDelete('cascade');
            $table->foreignId('incident_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['initial_balance', 'manual_adjustment', 'taken', 'earned']);
            $table->decimal('days', 8, 2); // Permite días fraccionados. Positivo para añadir, negativo para quitar.
            $table->text('description')->nullable();
            $table->date('date');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_logs');
    }
};