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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();

            // Datos de la empresa/sucursal
            $table->string('name');
            $table->string('password'); //Para poder ingresar al portal de clientes
            $table->string('rfc', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('post_code')->nullable();

            // Clasificación y Jerarquía
            $table->enum('status', ['Prospecto', 'Cliente'])->default('Prospecto');
            $table->foreignId('parent_branch_id')->nullable()->constrained('branches'); // indica a que sucursal matriz pertenece

            // Datos de Contacto y Seguimiento
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedSmallInteger('days_to_reactive')->default(60);
            // $table->date('last_purchase_date')->nullable(); // fecha de la última compra realizada en accesor
            $table->text('important_notes')->nullable();
            
             // Configuración fiscal (SAT)
            $table->string('sat_method', 50)->nullable();
            $table->string('sat_type', 50)->nullable();
            $table->string('meet_way')->nullable(); // forma en que conoció a e3dUSA

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
