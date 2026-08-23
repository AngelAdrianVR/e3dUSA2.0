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
        Schema::table('project_task_comments', function (Blueprint $table) {
            // IDs de los usuarios mencionados en el comentario (para recibos de lectura)
            $table->json('mentioned_user_ids')->nullable()->after('body');
            // Recibos de lectura: [{ user_id, read_at }, ...]
            $table->json('read_by')->nullable()->after('mentioned_user_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_comments', function (Blueprint $table) {
            $table->dropColumn(['mentioned_user_ids', 'read_by']);
        });
    }
};
