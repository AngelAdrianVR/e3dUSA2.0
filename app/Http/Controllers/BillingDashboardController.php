<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BillingDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Definir el ID mínimo a mostrar para filtrar las OV viejas
        $minSaleId = 4000;

        // Extraer el mes y año del request (formato YYYY-MM) o usar el actual por defecto
        $monthYear = $request->input('month_year');
        if ($monthYear) {
            $parts = explode('-', $monthYear);
            $year = $parts[0] ?? now()->year;
            $month = $parts[1] ?? now()->month;
        } else {
            $year = now()->year;
            $month = now()->month;
            // Formateamos para devolverlo a la vista
            $monthYear = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        }

        // 1. Lógica de KPIs actualizada a los nuevos requerimientos y filtrada por el mes/año seleccionado
        $kpis = [
            // Pendiente Pre-factura: No tiene folio de pre-factura
            'total_pending_pre_invoice' => Sale::where('id', '>=', $minSaleId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereNull('pre_invoice_folio')
                ->where('status', '!=', 'Cancelada') // Asumiendo que no facturamos canceladas
                ->count(),

            // Pendiente Timbrado: Tiene pre-factura, NO tiene timbrado y su estatus indica que ya se produjo/envió
            'total_pending_stamping'    => Sale::where('id', '>=', $minSaleId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereNotNull('pre_invoice_folio')
                ->whereNull('stamped_invoice_folio')
                ->whereIn('status', ['En Producción', 'Preparando Envío', 'Enviada'])
                ->count(),

            // Timbradas en el mes seleccionado
            'total_stamped_month'       => Sale::where('id', '>=', $minSaleId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereNotNull('stamped_invoice_folio')
                ->count(),
        ];

        // 2. Query Principal para la tabla (también filtrada por mes/año)
        $query = Sale::where('id', '>=', $minSaleId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with(['contact', 'user', 'branch.parent', 'saleProducts.product.media', 'quote']);

        // Filtro: Estado de Facturación
        if ($request->filled('billing_status')) {
            $query->where('billing_status', $request->billing_status);
        }

        // Filtro: Búsqueda (ID, RFC, Nombre de Sucursal, Razon Social)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                // Buscar por ID de OV
                $q->where('id', 'like', "%{$search}%")
                  // O buscar dentro de la sucursal (Nombre, RFC o Razón Social)
                  ->orWhereHas('branch', function ($qBranch) use ($search) {
                      $qBranch->where('name', 'like', "%{$search}%")
                              ->orWhere('rfc', 'like', "%{$search}%")
                              ->orWhere('business_name', 'like', "%{$search}%");
                  })
                  // O buscar dentro de la sucursal matriz (Nombre, RFC o Razón Social)
                  ->orWhereHas('branch.parent', function ($qParent) use ($search) {
                      $qParent->where('name', 'like', "%{$search}%")
                              ->orWhere('rfc', 'like', "%{$search}%")
                              ->orWhere('business_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro: Clic desde los KPIs
        if ($request->filled('kpi_filter')) {
            switch ($request->kpi_filter) {
                case 'pending_pre_invoice':
                    $query->whereNull('pre_invoice_folio')->where('status', '!=', 'Cancelada');
                    break;
                case 'pending_stamping':
                    $query->whereNotNull('pre_invoice_folio')
                          ->whereNull('stamped_invoice_folio')
                          ->whereIn('status', ['En Producción', 'Preparando Envío', 'Enviada']);
                    break;
                case 'stamped_month':
                    $query->whereNotNull('stamped_invoice_folio');
                    break;
            }
        }

        $salesForBilling = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Billing/Index', [
            'kpis' => $kpis,
            'salesForBilling' => $salesForBilling,
            // Agregamos el mes_año a las propiedades que regresan a Vue para mantener el estado
            'filtersProp' => array_merge(
                $request->only('billing_status', 'search', 'kpi_filter'),
                ['month_year' => $monthYear]
            ),
        ]);
    }

    public function updateFolios(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'pre_invoice_folio' => 'nullable|string|max:255',
            'stamped_invoice_folio' => 'nullable|string|max:255',
        ]);

        // AUTOMATIZACIÓN DE ESTATUS
        if ($request->filled('stamped_invoice_folio')) {
            $validated['billing_status'] = 'Timbrada';
        } elseif ($request->filled('pre_invoice_folio')) {
            $validated['billing_status'] = 'Pre-facturada';
        }

        $sale->update($validated);

        return back()->with('success', 'Folios actualizados correctamente.');
    }

    // Nuevo método para manejar la exportación
    public function report(Request $request)
    {
        $type = $request->type; // 'sin_prefactura', 'prefacturadas', 'timbradas', 'todas'
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = Sale::with(['branch.parent']);

        // Filtrar por fechas si existen
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(), 
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        // Filtrar por el tipo de reporte solicitado
        switch ($type) {
            case 'sin_prefactura':
                $query->whereNull('pre_invoice_folio');
                break;
            case 'prefacturadas':
                $query->whereNotNull('pre_invoice_folio')->whereNull('stamped_invoice_folio');
                break;
            case 'timbradas':
                $query->whereNotNull('stamped_invoice_folio');
                break;
        }

        // Aseguramos cargar las relaciones que utiliza el componente (branch, invoices, payments, etc.)
        // Si tu modelo Sale tiene la relación 'invoices', asegúrate de agregarla aquí:
        // $query->with(['branch', 'invoices.payments']);
        
        $data = $query->get();

        // Renderizamos el componente de Vue enviando las propiedades que espera
        return Inertia::render('Billing/Report', [
            'sales' => $data,
            'report_dates' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }
}