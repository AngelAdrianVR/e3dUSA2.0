<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubicación / etapa del seguimiento (China, GDL, Aduanas u otra).
     */
    public function up(): void
    {
        Schema::table('requisition_shipment_events', function (Blueprint $table) {
            $table->string('location', 100)
                ->nullable()
                ->after('event_date')
                ->comment('Ubicación o etapa del seguimiento: China, GDL, Aduanas u otra');

            // La ubicación por sí sola ya describe el evento.
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('requisition_shipment_events', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
