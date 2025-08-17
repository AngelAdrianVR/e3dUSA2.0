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
        Schema::create('company_branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password');
            $table->string('address');
            $table->string('post_code');
            $table->foreignId('parent_branch_id')->nullable()->constrained('company_branches'); // indica a que sucursal matriz pertenece
            $table->string('meet_way')->nullable();
            $table->string('sat_method')->nullable();
            $table->string('sat_type')->nullable();
            $table->text('important_notes')->nullable();
            $table->unsignedTinyInteger('days_to_reactive')->default(0);
            $table->boolean('is_main')->default(false); // indica si la sucursal es la matriz

            // --- CAMPOS NUEVOS PARA PROSPECTOS/ClIENTE ---
            $table->enum('status', ['prospecto', 'cliente'])->default('cliente');
            $table->timestamp('converted_to_client_at')->nullable()->comment('Fecha y hora de conversión a cliente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_branches');
    }
};
