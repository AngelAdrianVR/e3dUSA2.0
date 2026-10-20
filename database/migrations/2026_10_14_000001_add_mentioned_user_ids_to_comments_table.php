<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite registrar a los usuarios mencionados (@) en un comentario.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->json('mentioned_user_ids')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('mentioned_user_ids');
        });
    }
};
