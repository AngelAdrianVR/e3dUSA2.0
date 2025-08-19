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
        Schema::create('calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Columnas para la relación polimórfica
            $table->string('entryable_type');
            $table->unsignedBigInteger('entryable_id');
            
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->boolean('is_full_day')->default(false);
            
            // Llave foránea para el usuario creador
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_entries');
    }
};
