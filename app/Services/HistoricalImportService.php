<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Invoice;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HistoricalImportService
{
    /**
     * Procesa las colecciones de Excel
     */
    public function import(array $ovsRows, array $invoicesRows, int $defaultUserId)
    {
        // 1. Indexar facturas (evitar vacíos)
        // Filtramos filas que no tengan folio para evitar ensuciar el mapa
        $invoicesMap = collect($invoicesRows)
            ->filter(function($row) {
                $f = $row['factura'] ?? $row['folio'] ?? '';
                return !empty(trim((string)$f));
            })
            ->keyBy(function ($row) {
                return trim((string) ($row['factura'] ?? $row['folio'] ?? ''));
            });

        DB::beginTransaction();

        try {
            $importedCount = 0;
            $errors = [];

            foreach ($ovsRows as $ovRow) {
                // 2. Obtener Folio de Factura
                // Buscamos 'factura', 'folio_factura' o 'pactura' (por el error de dedo en el PDF)
                $ovInvoiceFolio = trim((string) (
                    $ovRow['factura'] ?? 
                    $ovRow['folio_factura'] ?? 
                    $ovRow['pactura'] ?? // Soporte para typos comunes
                    ''
                ));

                // --- CORRECCIÓN DEL ERROR SQL ---
                // Si la OV no tiene folio de factura asociado, LA SALTAMOS.
                // No podemos crear una factura con folio vacío.
                if (empty($ovInvoiceFolio)) {
                    continue; 
                }

                // Validación extra: Si la factura ya existe en BD, saltamos para no duplicar
                if (Invoice::where('folio', $ovInvoiceFolio)->exists()) {
                    // Opcional: podrías agregar esto a $errors si quieres saber cuáles se omitieron
                    continue; 
                }
                
                // Buscamos datos complementarios en el archivo de facturas
                $invoiceData = $invoicesMap->get($ovInvoiceFolio);

                // 3. Gestionar el Cliente (Branch)
                $clientName = $ovRow['cliente'] ?? $ovRow['razon_social'] ?? 'Cliente Desconocido';
                
                // Limpieza básica del nombre del cliente
                $clientName = trim($clientName) ?: 'Cliente Sin Nombre';

                $branch = Branch::firstOrCreate(
                    ['name' => $clientName],
                    [
                        'password' => bcrypt('temp1234'),
                        'status' => 'Cliente',
                        'days_to_reactive' => 60
                    ]
                );

                // 4. Crear la Orden de Venta (Sale)
                $totalAmount = $this->parseCurrency($ovRow['importe'] ?? 0);

                $sale = Sale::create([
                    'branch_id' => $branch->id,
                    'user_id' => $defaultUserId,
                    'status' => 'Completada',
                    'total_amount' => $totalAmount,
                    'currency' => 'MXN',
                    'type' => 'venta',
                    'notes' => 'Importación Histórica. OV Original: ' . ($ovRow['folio'] ?? 'S/N'),
                    'promise_date' => $this->parseDate($ovRow['fecha'] ?? null),
                ]);

                // 5. Crear la Factura (Invoice)
                // Si encontramos datos en el archivo de facturas, los usamos. 
                // Si no, usamos defaults basados en la OV.
                $invoiceAmount = $invoiceData ? $this->parseCurrency($invoiceData['importe'] ?? 0) : $totalAmount;
                $issueDate = $this->parseDate($invoiceData['fecha_de_emision'] ?? $invoiceData['emision'] ?? $ovRow['fecha'] ?? now());
                
                // Mapeo de status
                $statusRaw = $invoiceData['estatus'] ?? 'Pagada';
                $status = $this->mapInvoiceStatus($statusRaw);

                $invoice = Invoice::create([
                    'sale_id' => $sale->id,
                    'user_id' => $defaultUserId,
                    'branch_id' => $branch->id,
                    'folio' => $ovInvoiceFolio, // Aquí ya garantizamos que no es vacío
                    'amount' => $invoiceAmount,
                    'currency' => 'MXN',
                    'issue_date' => $issueDate,
                    'due_date' => $issueDate, // Asumimos vencimiento igual a emisión si no hay dato
                    'status' => $status,
                    'payment_method' => 'PPD',
                    'installment_number' => 1,
                    'total_installments' => 1,
                ]);

                // Actualizar relación inversa
                $sale->update(['invoice_id' => $invoice->id]);

                $importedCount++;
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $importedCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error importando historial: " . $e->getMessage());
            throw $e;
        }
    }

    private function parseCurrency($value)
    {
        return (float) preg_replace('/[^0-9.-]/', '', $value);
    }

    private function parseDate($value)
    {
        if (!$value) return now();
        try {
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            }
            // Intenta formatos comunes
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return now();
        }
    }

    private function mapInvoiceStatus($excelStatus)
    {
        $status = strtoupper(trim($excelStatus));
        return match (true) {
            str_contains($status, 'PAGADA') => 'Pagada',
            str_contains($status, 'CANCELADA') => 'Cancelada',
            str_contains($status, 'ACTIVA') => 'Pendiente',
            default => 'Pagada', // Default seguro para históricos
        };
    }
}