<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parcialidades de entrega: cada producto puede viajar en varias guías y se
     * indica qué cantidad de la partida viaja en cada una.
     */
    public function up(): void
    {
        Schema::table('requisition_item_shipment', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->nullable()->after('requisition_id');
        });

        // Los enlaces existentes representaban la partida completa en una sola guía.
        DB::table('requisition_item_shipment')
            ->whereNull('quantity')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $quantities = DB::table('requisition_items')
                    ->whereIn('id', $rows->pluck('requisition_item_id')->unique()->all())
                    ->pluck('quantity', 'id');

                foreach ($rows as $row) {
                    DB::table('requisition_item_shipment')
                        ->where('id', $row->id)
                        ->update(['quantity' => $quantities[$row->requisition_item_id] ?? 0]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('requisition_item_shipment', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
