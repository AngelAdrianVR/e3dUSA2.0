<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar flag target_all a releases
        Schema::table('releases', function (Blueprint $table) {
            $table->boolean('target_all')->default(true)->after('is_published');
        });

        // 2. Tabla pivote para público objetivo específico
        Schema::create('release_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['release_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_targets');
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn('target_all');
        });
    }
};
