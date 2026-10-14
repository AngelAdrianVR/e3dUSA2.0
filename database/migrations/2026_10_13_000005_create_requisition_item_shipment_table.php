<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las guías de paquetería ahora se ligan a cada producto (requisition_items)
     * en lugar de a la requisición completa, de modo que cada producto puede
     * viajar en su propia guía.
     */
    public function up(): void
    {
        Schema::create('requisition_item_shipment', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requisition_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_shipment_id')->constrained()->cascadeOnDelete();

            // Requisición denormalizada para consultas y notificaciones.
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['requisition_item_id', 'requisition_shipment_id'], 'requisition_item_shipment_unique');
        });

        $now = now();

        // 1. Cada requisición existente recibe su partida (para las requisiciones
        //    creadas por vendedores la partida no tiene producto).
        $itemIdByRequisition = [];

        foreach (DB::table('requisitions')->get(['id', 'product_id', 'quantity']) as $requisition) {
            $itemIdByRequisition[$requisition->id] = DB::table('requisition_items')->insertGetId([
                'requisition_id' => $requisition->id,
                'product_id' => $requisition->product_id,
                'quantity' => $requisition->quantity,
                'arrival_status' => 'Pendiente',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Se migran las guías ligadas a requisiciones completas hacia sus partidas.
        if (Schema::hasTable('requisition_shipment_items')) {
            $statusByShipment = DB::table('requisition_shipments')->pluck('status', 'id');

            foreach (DB::table('requisition_shipment_items')->get() as $row) {
                $itemId = $itemIdByRequisition[$row->requisition_id] ?? null;

                if (! $itemId) {
                    continue;
                }

                DB::table('requisition_item_shipment')->insert([
                    'requisition_item_id' => $itemId,
                    'requisition_shipment_id' => $row->requisition_shipment_id,
                    'requisition_id' => $row->requisition_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('requisition_items')->where('id', $itemId)->update([
                    'arrival_status' => $statusByShipment[$row->requisition_shipment_id] ?? 'Pendiente',
                ]);
            }

            Schema::drop('requisition_shipment_items');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('requisition_shipment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requisition_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['requisition_shipment_id', 'requisition_id'], 'requisition_shipment_unique');
        });

        Schema::dropIfExists('requisition_item_shipment');
    }
};
