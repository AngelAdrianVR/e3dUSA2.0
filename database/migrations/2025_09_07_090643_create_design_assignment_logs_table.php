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
        Schema::create('design_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_designer_id')->nullable()->constrained('users');
            $table->foreignId('new_designer_id')->constrained('users');
            $table->foreignId('changed_by_user_id')->comment('Admin o manager que hizo el cambio')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_assignment_logs');
    }
};
