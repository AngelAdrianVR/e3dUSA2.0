<?php

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
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Se obtienen los productos con sus relaciones
        $products = Product::where('product_type', 'Catálogo')
            ->with(['branches' => function ($query) {
                $query->orderBy('name');
            }])
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
        
        $data = [];

        // Se itera sobre cada producto y luego sobre cada sucursal/cliente asociado
        foreach ($products as $product) {
            if ($product->branches->isEmpty()) {
                // Si no hay sucursales, se puede omitir o agregar una fila por defecto
                // Para mantener la consistencia con el archivo antiguo, solo se procesan los que tienen relación
                continue;
            }

            foreach ($product->branches as $branch) {
                // Se construye el arreglo de datos con la estructura deseada
                $data[] = [
                    'Producto' => $product->name,
                    'Precio' => $branch->pivot->new_price ?? '-',
                    'Moneda' => $branch->pivot->new_currency ?? '-',
                    'Clientes' => $branch->name,
                ];
            }
        }

        // Se retorna una nueva colección con los datos procesados
        return new Collection($data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Se definen las cabeceras del archivo, al igual que en el archivo antiguo
        return [
            'Nombre de producto',
            'Precio registrado',
            'Moneda',
            'Sucursal / Cliente',
        ];
    }
    
    /**
     * @return array
     */
    public function columnWidths(): array
    {
        // Se definen anchos de columna fijos
        return [
            'A' => 45, // Nombre de producto
            'B' => 16, // Precio registrado
            'C' => 10, // Moneda
            'D' => 50, // Sucursal / Cliente
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
