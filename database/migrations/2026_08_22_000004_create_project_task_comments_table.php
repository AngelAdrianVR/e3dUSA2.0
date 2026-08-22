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
        Schema::create('project_task_comments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_task_id')->constrained()->cascadeOnDelete();

            // Autor del comentario (cualquier miembro del proyecto)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Texto del comentario (puede incluir menciones @)
            $table->text('body');

            $table->timestamps();

            $table->index('project_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_task_comments');
    }
};
