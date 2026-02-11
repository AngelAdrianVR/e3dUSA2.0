<?php

namespace App\Exports;

use App\Models\PurchaseItem;
use App\Models\Purchase; // Necesario para el orderBy
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $startDate;
    protected $endDate;

    /**
     * @param string $startDate (Y-m-d)
     * @param string $endDate (Y-m-d, inclusivo)
     */
    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
    * @return \Illuminate\Database\Query\Builder
    */
    public function query()
    {
        // Consultamos los items de compra, ya que queremos el detalle
        // y los agruparemos por proveedor en la consulta.
        return PurchaseItem::with([
                'purchase.supplier:id,name', // Eager load de la relación
                'product:id,name'            // Eager load del producto
            ])
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->whereBetween('purchases.created_at', [$this->startDate, $this->endDate])
            ->select('purchase_items.*') // Seleccionar todo de purchase_items para evitar colisión de 'id'
            ->orderBy('suppliers.name', 'asc') // Ordenar por nombre de proveedor (agrupar)
            ->orderBy('purchases.id', 'asc'); // Luego por ID de compra
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Se agregaron las columnas "Requiere Molde" y "Costo Molde"
        return [
            'Proveedor',
            'Folio Compra',
            'Fecha Compra',
            'Estatus Compra',
            'Producto (Descripción)',
            'Cantidad',
            'Precio Unitario',
            'Precio Total (Item)',
            'Requiere Molde',       // Nueva columna
            'Costo Molde',          // Nueva columna
            'Fecha Recepción Esperada',
            'Notas del Producto'
        ];
    }

    /**
     * Mapea los datos de cada fila.
     *
     * @param \App\Models\PurchaseItem $item
     * @return array
     */
    public function map($item): array
    {
        // Mapeamos los datos incluyendo las nuevas columnas
        return [
            $item->purchase->supplier->name ?? 'N/A',
            'OC-' . str_pad($item->purchase->id, 4, '0', STR_PAD_LEFT),
            $item->purchase->created_at->format('Y-m-d'),
            $item->purchase->status,
            $item->product->name ?? $item->description, // Fallback a la descripción guardada
            $item->quantity,
            number_format($item->unit_price, 2),
            number_format($item->total_price, 2),
            // Lógica para las nuevas columnas:
            $item->needs_mold ? 'Sí' : 'No',            // Convertimos el booleano a texto legible
            number_format($item->mold_price ?? 0, 2),   // Formateamos el precio del molde
            $item->purchase->expected_delivery_date ? (new \DateTime($item->purchase->expected_delivery_date))->format('Y-m-d') : 'N/A',
            $item->notes,
        ];
    }

    /**
     * Aplica estilos a la hoja de cálculo.
     */
    public function styles(Worksheet $sheet)
    {
        // Hace que la fila de encabezados (fila 1) esté en negrita
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}