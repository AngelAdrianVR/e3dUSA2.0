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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('meet_way')
                ->comment('Método de pago SAT: PPD (Parcialidades o Diferido) o PUE (Una sola exhibición)');

            $table->string('payment_submethod', 50)->nullable()->after('payment_method')
                ->comment('Sub-método de pago: 99 X DEFINIR, TRANSFERENCIA o CHEQUES');

            $table->string('cfdi_use', 100)->nullable()->after('payment_submethod')
                ->comment('Uso del CFDI: GASTOS EN GENERAL o ADQUISICION DE MERCANCIAS');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_submethod', 'cfdi_use']);
        });
    }
};
