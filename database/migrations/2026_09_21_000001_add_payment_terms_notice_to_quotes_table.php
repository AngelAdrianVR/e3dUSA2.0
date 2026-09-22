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
        Schema::table('quotes', function (Blueprint $table) {
            // Aviso de condiciones de pago que ve el cliente en la cotización.
            // Valores posibles: 'advance' (pago por anticipado), 'split_50_50' (50% anticipo / 50% contra entrega)
            // o NULL (no mostrar ningún aviso).
            $table->string('payment_terms_notice')
                  ->nullable()
                  ->after('validity')
                  ->comment('Aviso de condiciones de pago visible al cliente: advance, split_50_50 o null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('payment_terms_notice');
        });
    }
};
