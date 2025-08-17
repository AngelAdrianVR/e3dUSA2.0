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
        Schema::create('storages', function (Blueprint $table) {
            $table->id();
            
            // --- Columnas Polimórficas ---
            // Guardarán el ID y el tipo de modelo (ej: App\Models\Product)
            $table->morphs('storable'); // Esto crea storable_id y storable_type

            $table->decimal('quantity', 10, 2); // Cantidad en existencia
            $table->string('location')->nullable(); // Ubicación física (ej: Estante A-1)
            
            // Opcional pero recomendado si manejas varios almacenes
            // $table->foreignId('warehouse_id')->constrained(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storages');
    }
};
