<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el tipo 'muestra' a las órdenes.
 *
 * Las órdenes de tipo 'muestra' representan Órdenes de Venta creadas a partir de un
 * seguimiento de muestra (muestras que el cliente no devuelve o productos regalados)
 * y que se generan únicamente para poder facturarlas.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('type', ['venta', 'stock', 'muestra'])->default('venta')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // IMPORTANTE: si existen órdenes con type = 'muestra' esta migración fallará.
        // Cambiarlas antes a 'venta' manualmente si se desea revertir.
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('type', ['venta', 'stock'])->default('venta')->change();
        });
    }
};
