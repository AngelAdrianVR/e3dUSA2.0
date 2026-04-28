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
        // 1. Lógica de KPIs actualizada a los nuevos requerimientos
        $kpis = [
            // Pendiente Pre-factura: No tiene folio de pre-factura
            'total_pending_pre_invoice' => Sale::whereNull('pre_invoice_folio')
                ->where('status', '!=', 'Cancelada') // Asumiendo que no facturamos canceladas
                ->count(),

            // Pendiente Timbrado: Tiene pre-factura, NO tiene timbrado y su estatus indica que ya se produjo/envió
            'total_pending_stamping'    => Sale::whereNotNull('pre_invoice_folio')
                ->whereNull('stamped_invoice_folio')
                ->whereIn('status', ['En Producción', 'Preparando Envío', 'Enviada'])
                ->count(),

            // Timbradas en el mes actual
            'total_stamped_month'       => Sale::whereNotNull('stamped_invoice_folio')
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        // 2. Query Principal para la tabla
        $query = Sale::with(['contact', 'user', 'branch.parent', 'saleProducts.product']);

        // Filtro: Estado de Facturación
        if ($request->filled('billing_status')) {
            $query->where('billing_status', $request->billing_status);
        }

        // Filtro: Búsqueda (ID, RFC, Nombre de Sucursal)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                // Buscar por ID de OV
                $q->where('id', 'like', "%{$search}%")
                  // O buscar dentro de la sucursal
                  ->orWhereHas('branch', function ($qBranch) use ($search) {
                      $qBranch->where('name', 'like', "%{$search}%")
                              ->orWhere('rfc', 'like', "%{$search}%");
                  })
                  // O buscar dentro de la sucursal padre (razón social)
                  ->orWhereHas('branch.parent', function ($qParent) use ($search) {
                      $qParent->where('name', 'like', "%{$search}%");
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
                    $query->whereNotNull('stamped_invoice_folio')
                          ->whereMonth('created_at', now()->month);
                    break;
            }
        }

        $salesForBilling = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Billing/Index', [
            'kpis' => $kpis,
            'salesForBilling' => $salesForBilling,
            'filtersProp' => $request->only('billing_status', 'search', 'kpi_filter'),
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