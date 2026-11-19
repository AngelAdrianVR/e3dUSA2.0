<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca las órdenes cuya autorización se retiró únicamente para poder editarlas.
 *
 * Al guardar la edición la orden se autoriza de nuevo automáticamente (solo si el
 * usuario tiene el permiso "Autorizar edición de ordenes de venta"), por lo que la
 * bandera se limpia en ese momento.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('authorization_withdrawn_for_edit')
                ->default(false)
                ->after('authorized_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('authorization_withdrawn_for_edit');
        });
    }
};
