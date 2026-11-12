<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los productos compuestos ya no generan adeudo de producto terminado (solo sus
     * componentes). Este ajuste elimina los registros que se hayan creado antes con
     * esa lógica y que todavía no se habían rebajado.
     */
    public function up(): void
    {
        $compositeProductIds = DB::table('products')
            ->where(function ($query) {
                // Productos que tienen componentes propios...
                $query->whereIn('id', function ($sub) {
                    $sub->select('catalog_product_id')->from('product_components');
                })
                // ...o variantes que heredan los componentes de su producto padre.
                ->orWhere(function ($variantQuery) {
                    $variantQuery->whereNotNull('parent_id')
                        ->whereIn('parent_id', function ($sub) {
                            $sub->select('catalog_product_id')->from('product_components');
                        });
                });
            })
            ->pluck('id');

        if ($compositeProductIds->isEmpty()) {
            return;
        }

        // Solo los adeudos que siguen intactos (sin rebajas parciales) para no perder
        // el historial de los que ya se liquidaron total o parcialmente.
        DB::table('stock_shortages')
            ->where('is_component', false)
            ->whereColumn('quantity_pending', 'quantity')
            ->whereIn('product_id', $compositeProductIds)
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se recrean los registros eliminados.
    }
};
