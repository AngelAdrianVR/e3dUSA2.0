<?php

// ! Este archivo es similar a CatalogProductPricesExport.php pero con una estructura de datos diferente.
// ! Contiene los precios en formato ABC y ordena los datos alfabéticamente por Sucursal / Cliente. Ademas se eliminan unas columnas

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
        $products = Product::where('product_type', 'Catálogo')
            ->with([
                'branches', // Cargamos branches sin ordenar aquí, ordenaremos la colección final
                'priceHistory' => function ($query) {
                    // Cargar solo los precios especiales que están vigentes
                    $query->whereNull('valid_to');
                }
            ])
            ->whereNull('archived_at')
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
                $specialPrice = $product->priceHistory
                                        ->where('branch_id', $branch?->id)
                                        ->first();

                // Estructura Modificada:
                // Sucursal / Cliente | Nombre de producto | Moneda (Base) | Precio A (Especial) | Precio B | Precio C
                $data[] = [
                    'Sucursal / Cliente' => $branch?->name,
                    'Nombre de producto' => $product->name,
                    'Moneda'             => $product->currency ?? '-', // Se toma de Moneda Base
                    'Precio A'           => $specialPrice->price ?? '-', // Se toma del Precio Especial
                    'Precio B'           => '', // En blanco
                    'Precio C'           => '', // En blanco
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
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        // Anchos de columna ajustados a la nueva estructura (A-F)
        return [
            'A' => 50, // Sucursal / Cliente (Antes era D)
            'B' => 45, // Nombre de producto (Antes era A)
            'C' => 12, // Moneda
            'D' => 16, // Precio A
            'E' => 16, // Precio B
            'F' => 16, // Precio C
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