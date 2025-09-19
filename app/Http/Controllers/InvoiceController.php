<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InvoiceController extends Controller
{
   public function index(Request $request)
    {
        // Pestaña 1: Todas las facturas registradas.
        $invoices = Invoice::with(['sale:id,branch_id', 'sale.branch:id,name'])
            ->orderBy('sale_id', 'desc')
            ->orderBy('installment_number', 'asc')
            ->paginate(10, ['*'], 'invoices_page');

        // Pestaña 2: Órdenes de venta que requieren facturación.
        // Se cambió `havingRaw` por `whereRaw` con una subconsulta para evitar
        // errores de SQL con el modo ONLY_FULL_GROUP_BY en MySQL. La lógica sigue siendo la misma:
        // mostrar OVs cuyo total es mayor que la suma de sus facturas no canceladas.
        $salesWithoutInvoice = Sale::with(['branch:id,name'])
            ->withSum(['invoices as total_invoiced' => function($query) {
                $query->where('status', '!=', 'Cancelada');
            }], 'amount')
            ->withCount('invoices as invoices_count')
            ->where('status', '!=', 'Cancelada')
            ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE invoices.sale_id = sales.id AND status != ?)', ['Cancelada'])
            ->latest('id')
            ->paginate(10, ['*'], 'sales_page');

        // Pestaña 3: Facturas por cobrar.
        $pendingInvoices = Invoice::with(['sale:id,branch_id', 'sale.branch:id,name', 'payments'])
            ->whereIn('status', ['Pendiente', 'Vencida'])
            ->orderBy('due_date', 'asc')
            ->paginate(10, ['*'], 'pending_page');

        return Inertia::render('Invoice/Index', [
            'invoices' => $invoices,
            'salesWithoutInvoice' => $salesWithoutInvoice,
            'pendingInvoices' => $pendingInvoices,
            'active_tab_prop' => $request->get('tab', 'all_invoices'),
        ]);
    }
    
    public function create(Request $request)
    {
        // --- MODIFICACIÓN ---
        // La consulta ahora busca OVs que no estén totalmente facturadas.
        // 1. Carga relaciones y suma el monto de las facturas existentes (`invoiced_amount`).
        // 2. Filtra en PHP para mantener solo las OVs cuyo `total_amount` es mayor
        //    que el monto ya facturado (`invoiced_amount`).
        // 3. Mapea el resultado para enviar al frontend datos útiles como el conteo
        //    de facturas existentes y el total de parcialidades de la última factura.
        $salesWithPartialInvoices = Sale::with(['branch:id,name', 'invoices:sale_id,amount,total_installments'])
            ->where('status', '!=', 'Cancelada')
            ->withSum('invoices as invoiced_amount', 'amount')
            ->latest()
            ->get();

        $sales = $salesWithPartialInvoices->filter(function ($sale) {
            return is_null($sale->invoiced_amount) || $sale->total_amount > $sale->invoiced_amount;
        })->map(function ($sale) {
            $lastInvoice = $sale->invoices->last();
            return [
                'id' => $sale->id,
                'total_amount' => $sale->total_amount,
                'branch_id' => $sale->branch_id,
                'currency' => $sale->currency,
                'branch' => $sale->branch,
                'invoiced_amount' => $sale->invoiced_amount ?? 0,
                'invoice_count' => $sale->invoices->count(),
                'last_total_installments' => $lastInvoice ? $lastInvoice->total_installments : 1,
            ];
        })->values(); // Resetea las llaves para que sea un array JSON válido.

        return Inertia::render('Invoice/Create', [
            'sales' => $sales,
            'sale_id_prop' => intval($request->get('sale_id'))
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $validatedData = $request->validated();

        $invoice = Invoice::create(array_merge($validatedData, [
            'user_id' => Auth::id(),
            // No es necesario agregar 'branch_id' aquí, ya viene en $validatedData
        ]));

        $sale = Sale::find($validatedData['sale_id']);
        $sale->invoice_id = $invoice->id;
        $sale->save();

        return to_route('invoices.index');
    }

    public function show(Invoice $invoice)
    {
        //
    }

    public function edit(Invoice $invoice)
    {
        //
    }

    public function update(Request $request, Invoice $invoice)
    {
        //
    }

    public function destroy(Invoice $invoice)
    {
        //
    }

    public function cancel(Invoice $invoice)
    {
        // Se carga la relación de pagos para poder verificar si existen.
        $invoice->load('payments');

        // Se valida que la factura no esté 'Pagada' y que no tenga ningún pago registrado.
        if ($invoice->status === 'Pagada' || $invoice->payments->isNotEmpty()) {
            // Si no cumple la condición, se regresa con un mensaje de error.
            return back()->withErrors(['error' => 'No se puede cancelar una factura que ya tiene pagos registrados.']);
        }

        // Si pasa la validación, se actualiza el estatus.
        $invoice->status = 'Cancelada';
        $invoice->save();

        return back()->with('success', 'Factura cancelada correctamente.');
    }
}
