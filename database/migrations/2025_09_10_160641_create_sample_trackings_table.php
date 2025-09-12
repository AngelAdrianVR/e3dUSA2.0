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
        Schema::create('sample_trackings', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['Pendiente', 'Autorizado', 'Enviado', 'Aprobado', 'Rechazado', 'Devuelto', 'Completado', 'Modificación'])->default('Pendiente');
            
            // Foreign Keys for relationships
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete(); // User who creates the request
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete(); // User who authorizes
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete(); // Link to a sale order if generated

            // Return logic
            $table->boolean('will_be_returned')->default(false);
            $table->date('expected_devolution_date')->nullable();
            
            // General feedback for the entire sample request
            $table->text('comments')->nullable();

            // Timestamps for tracking the process
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('completed_at')->nullable(); // When the sample process is fully closed
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sample_trackings');
    }
};
