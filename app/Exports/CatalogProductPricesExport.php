<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CatalogProductPricesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Se obtienen solo los productos de tipo 'Catálogo' con sus sucursales y precios especiales.
        return Product::where('product_type', 'Catálogo')
            ->with(['branches' => function ($query) {
                // Asegúrate de que la relación 'branches' en tu modelo Product
                // cargue los datos de la tabla pivote que necesitas (precio, moneda, etc.)
                // Ejemplo en Product.php: return $this->belongsToMany(Branch::class)->withPivot('new_price', 'new_currency', 'new_date');
                $query->orderBy('name');
            }])
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Código de Producto',
            'Nombre de Producto',
            'Costo Base',
            'Sucursal / Cliente',
            'Precio Especial',
            'Moneda',
            'Fecha de Precio',
        ];
    }

    /**
     * @param mixed $product
     * @return array
     */
    public function map($product): array
    {
        $rows = [];

        if ($product->branches->isEmpty()) {
            // Si el producto no tiene precios especiales en ninguna sucursal,
            // se agrega una fila con la información base del producto.
            $rows[] = [
                $product->code,
                $product->name,
                $product->cost,
                'N/A',
                'N/A',
                'N/A',
                'N/A',
            ];
        } else {
            // Si tiene precios especiales, se crea una fila por cada sucursal.
            foreach ($product->branches as $branch) {
                $rows[] = [
                    $product->code,
                    $product->name,
                    $product->cost,
                    $branch->name, // Usando 'name' del modelo Branch
                    $branch->pivot->new_price ?? 'N/A',
                    $branch->pivot->new_currency ?? 'N/A',
                    isset($branch->pivot->new_date) ? \Carbon\Carbon::parse($branch->pivot->new_date)->format('d-m-Y') : 'N/A',
                ];
            }
        }
        
        return $rows;
    }
    
    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para la primera fila (encabezados).
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
