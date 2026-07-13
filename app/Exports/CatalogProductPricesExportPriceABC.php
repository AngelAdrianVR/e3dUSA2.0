<?php

// ! Este archivo es similar a CatalogProductPricesExport.php pero con una estructura de datos diferente.
// ! Contiene los precios en formato ABC y ordena los datos alfabéticamente por Sucursal / Cliente. Ademas se eliminan unas columnas

namespace App\Exports;

use App\Models\Product;
use App\Models\SaleProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CatalogProductPricesExportPriceABC implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths
{
    /**
     * Este escribe los productos con formato personalizado.
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Se obtienen los productos con sus sucursales y el historial de precios vigentes.
        $products = Product::where('product_type', 'Producto')
            ->where('is_sellable', true)
            ->with([
                'branches', // Cargamos branches sin ordenar aquí, ordenaremos la colección final
                'storages',
                'priceHistory' => function ($query) {
                    // Cargar solo los precios especiales que están vigentes
                    $query->whereNull('valid_to');
                }
            ])
            ->whereNull('archived_at')
            ->get();

        // Construir mapa de última compra: (branch_id, product_id) => fecha
        $productIds = $products->pluck('id')->toArray();
        $branchIds = $products->pluck('branches.*.id')->flatten()->unique()->toArray();

        $lastPurchases = SaleProduct::query()
            ->join('sales', 'sales.id', '=', 'sale_products.sale_id')
            ->whereIn('sale_products.product_id', $productIds)
            ->whereIn('sales.branch_id', $branchIds)
            ->selectRaw('sales.branch_id, sale_products.product_id, MAX(sales.created_at) as last_purchase_date')
            ->groupBy('sales.branch_id', 'sale_products.product_id')
            ->get()
            ->keyBy(fn ($item) => $item->branch_id . '-' . $item->product_id);

        $data = [];

        // Se itera sobre cada producto
        foreach ($products as $product) {
            if ($product->branches->isEmpty()) {
                continue;
            }

            $stock = $product->storages->first()?->quantity ?? 0;

            // Luego se itera sobre cada sucursal/cliente asociado al producto
            foreach ($product->branches as $branch) {
                // Se busca el precio especial para esta combinación de producto y sucursal
                $specialPrice = $product->priceHistory
                                        ->where('branch_id', $branch?->id)
                                        ->first();

                // Última compra de esta sucursal para este producto
                $purchaseKey = $branch->id . '-' . $product->id;
                $lastPurchase = $lastPurchases->get($purchaseKey);
                $lastPurchaseDate = $lastPurchase
                    ? Carbon::parse($lastPurchase->last_purchase_date)->format('d/m/Y')
                    : 'Sin compras';

                // Estructura:
                // Sucursal / Cliente | Nombre de producto | Moneda | Precio A | Precio B | Precio C | Stock Disponible | Última compra
                $data[] = [
                    'Sucursal / Cliente' => $branch?->name,
                    'Nombre de producto' => $product->name,
                    'Moneda'             => $product->currency ?? '-',
                    'Precio A'           => $specialPrice->price ?? '-',
                    'Precio B'           => '',
                    'Precio C'           => '',
                    'Stock Disponible'   => $stock,
                    'Última compra'      => $lastPurchaseDate,
                ];
            }
        }

        // Convertimos el array a colección y ordenamos alfabéticamente por 'Sucursal / Cliente'
        // values() reindexa las claves numéricas para evitar problemas en el excel
        return collect($data)->sortBy('Sucursal / Cliente')->values();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Cabeceras actualizadas al nuevo orden
        return [
            'Sucursal / Cliente',
            'Nombre de producto',
            'Moneda',
            'Precio A',
            'Precio B',
            'Precio C',
            'Stock Disponible',
            'Última compra',
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        // Anchos de columna ajustados a la nueva estructura (A-F)
        return [
            'A' => 50, // Sucursal / Cliente
            'B' => 45, // Nombre de producto
            'C' => 12, // Moneda
            'D' => 16, // Precio A
            'E' => 16, // Precio B
            'F' => 16, // Precio C
            'G' => 18, // Stock Disponible
            'H' => 16, // Última compra
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Se aplican estilos a la fila de encabezados
        return [
            1    => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0D6EFD'],
                ],
            ],
        ];
    }


}