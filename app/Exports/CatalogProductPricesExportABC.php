<?php
/*
    * Exportar reporte de productos con precios A, B y C (Precios únicos encontrados en historial).
*/ 
namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CatalogProductPricesExportABC implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths
{
    /**
     * Reporte de productos con precios A, B y C (Precios únicos encontrados en historial).
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // 1. Obtenemos productos de catálogo activos
        $products = Product::where('product_type', 'Catálogo')
            ->with([
                'priceHistory' => function ($query) {
                    // Solo precios vigentes
                    $query->whereNull('valid_to');
                }
            ])
            ->whereNull('archived_at')
            ->orderBy('name') // Ordenamos alfabéticamente por nombre de producto
            ->get();

        $data = [];

        foreach ($products as $product) {
            // 2. Obtener precios únicos del historial
            // Pluck saca solo el valor 'price', unique elimina duplicados, values reindexa el array
            $uniquePrices = $product->priceHistory
                ->pluck('price')
                ->unique()
                ->sort() // Ordenar de menor a mayor
                ->values();

            // Concatenamos todos los precios separados por coma
            $allPrices = $uniquePrices->implode(', ');

            // 3. Mapear a columnas A, B, C
            // Precio A contiene todos los precios, B y C vacíos.
            $data[] = [
                'Nombre de producto' => $product->name,
                'Precio A'           => $allPrices, 
                'Precio B'           => '',
                'Precio C'           => '',
            ];
        }

        return new Collection($data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Nombre de producto',
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
        return [
            'A' => 45, // Nombre de producto
            'B' => 30, // Precio A (Aumenté el ancho para que quepan varios precios)
            'C' => 15, // Precio B
            'D' => 15, // Precio C
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0D6EFD'], // Azul
                ],
            ],
        ];
    }
}