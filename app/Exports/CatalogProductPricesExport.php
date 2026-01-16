<?php

/*
 ! Este archivo escribe los productos con una estructura de datos diferente a CatalogProductPricesExportPriceABC.php
 ! Contiene los precios en formato estándar y ordena los datos por Nombre de Producto.
 ! Ademas icluye columnas como precio base, moneda base, moneda especial.
*/

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CatalogProductPricesExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths
{
    /**
     * Este escribe los productos aunque se repitan, el otro archivo que termina en Agrupado los agrupa
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Se obtienen los productos con sus sucursales y el historial de precios vigentes.
        // Cargar 'priceHistory' donde 'valid_to' es nulo evita problemas de N+1 consultas.
        $products = Product::where('product_type', 'Catálogo')
            ->with([
                'branches' => function ($query) {
                    $query->orderBy('name');
                },
                'priceHistory' => function ($query) {
                    // Cargar solo los precios especiales que están vigentes
                    $query->whereNull('valid_to');
                }
            ])
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        $data = [];

        // Se itera sobre cada producto
        foreach ($products as $product) {
            if ($product->branches->isEmpty()) {
                continue;
            }

            // Luego se itera sobre cada sucursal/cliente asociado al producto
            foreach ($product->branches as $branch) {
                // Se busca el precio especial para esta combinación de producto y sucursal
                // de la colección que ya cargamos previamente (priceHistory)
                $specialPrice = $product->priceHistory
                                        ->where('branch_id', $branch?->id)
                                        ->first();

                // Se construye el arreglo de datos con la nueva estructura
                $data[] = [
                    'Producto' => $product->name,
                    'Precio Base' => $product->base_price ?? '-',
                    'Moneda Base' => $product->currency ?? '-',
                    'Cliente' => $branch?->name,
                    'Precio Especial' => $specialPrice->price ?? '-',
                    'Moneda Especial' => $specialPrice->currency ?? '-',
                ];
            }
        }

        return new Collection($data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Se definen las nuevas cabeceras del archivo
        return [
            'Nombre de producto',
            'Precio Base',
            'Moneda Base',
            'Sucursal / Cliente',
            'Precio Especial',
            'Moneda Especial',
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        // Se definen anchos de columna para la nueva estructura
        return [
            'A' => 45, // Nombre de producto
            'B' => 16, // Precio Base
            'C' => 12, // Moneda Base
            'D' => 50, // Sucursal / Cliente
            'E' => 16, // Precio Especial
            'F' => 12, // Moneda Especial
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0D6EFD'],
                ],
            ],
        ];
    }
}

