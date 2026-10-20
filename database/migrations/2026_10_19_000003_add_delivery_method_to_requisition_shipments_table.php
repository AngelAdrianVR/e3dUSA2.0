<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forma de entrega de la mercancía: guía de paquetería, entrega directa
     * del proveedor o transporte propio de la empresa (sin guía).
     */
    public function up(): void
    {
        Schema::table('requisition_shipments', function (Blueprint $table) {
            $table->string('delivery_method', 50)
                ->default('Guía')
                ->after('carrier')
                ->comment('Guía, Proveedor o Transporte propio');

            // Las entregas sin guía (proveedor / transporte propio) no tienen número.
            $table->string('tracking_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('requisition_shipments', function (Blueprint $table) {
            $table->string('tracking_number')->nullable(false)->change();
            $table->dropColumn('delivery_method');
        });
    }
};
