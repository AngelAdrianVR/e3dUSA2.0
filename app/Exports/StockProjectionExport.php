<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockProjectionExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        // $data recibe la colección o arreglo 'table' del controlador
        $this->data = collect($data);
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Código',
            'Nombre del Producto',
            'Stock Actual (unidades)',
            'Mínimo Establecido',
            'Total Vendido',
            'Promedio Mensual',
            'Proyección (3 Meses)',
            'Sugerencia (A Pedir)',
            'Estado',
        ];
    }

    public function map($row): array
    {
        // Mapeamos los campos del arreglo de la tabla a las columnas de Excel
        return [
            $row['code'],
            $row['name'],
            $row['current_stock'],
            $row['min_quantity'],
            $row['total_sold'],
            $row['monthly_average'],
            $row['projection_3_months'],
            $row['to_order'],
            $row['status'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Dale estilo a la fila 1 (Cabeceras)
            1 => ['font' => ['bold' => true]],
        ];
    }
}