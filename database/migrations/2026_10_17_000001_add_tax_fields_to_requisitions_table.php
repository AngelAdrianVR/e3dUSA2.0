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
            // IVA de la cotización: se captura al adjuntarla y se desglosa en la validación gerencial.
            $table->boolean('quoted_tax_enabled')->default(false)->after('quoted_currency');
            $table->decimal('quoted_tax_rate', 5, 2)->default(16)->after('quoted_tax_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['quoted_tax_enabled', 'quoted_tax_rate']);
        });
    }
};
