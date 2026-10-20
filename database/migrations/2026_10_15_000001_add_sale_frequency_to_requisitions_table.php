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
        Schema::table('requisitions', function (Blueprint $table) {
            // Frecuencia de venta / consumo de la mercancía solicitada.
            $table->string('sale_frequency')->nullable()->after('sale_specification')
                ->comment('Mensual, Bimestral, Trimestral, Semestral, Anual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('sale_frequency');
        });
    }
};
