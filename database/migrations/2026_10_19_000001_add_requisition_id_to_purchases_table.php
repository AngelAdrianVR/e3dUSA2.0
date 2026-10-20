<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula la orden de compra con la requisición que la originó.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('requisition_id')
                ->nullable()
                ->after('id')
                ->comment('Requisición que originó la orden de compra')
                ->constrained('requisitions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requisition_id');
        });
    }
};
