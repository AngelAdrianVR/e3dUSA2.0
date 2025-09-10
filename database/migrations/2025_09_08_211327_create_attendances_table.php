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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_detail_id')->constrained()->onDelete('cascade');
            $table->timestamp('timestamp');
            $table->enum('type', ['entry', 'exit', 'start_break', 'end_break']);
            $table->integer('late_minutes')->nullable();
            $table->boolean('ignore_late')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
