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
        // Se ajusta la consulta para que sea más eficiente y correcta.
        $salesWithPartialInvoices = Sale::with([
            'branch:id,name',
            // Cargar solo facturas no canceladas para obtener un conteo preciso.
            'invoices' => function ($query) {
                $query->where('status', '!=', 'Cancelada')->select('sale_id', 'amount', 'total_installments');
            }
        ])
        ->where('status', '!=', 'Cancelada')
        // Sumar solo el monto de las facturas que no están canceladas.
        ->withSum(['invoices as invoiced_amount' => function ($query) {
            $query->where('status', '!=', 'Cancelada');
        }], 'amount')
        ->latest()
        ->get();

        // Filtrar en PHP: solo ventas cuyo monto total sea mayor al monto ya facturado.
        $sales = $salesWithPartialInvoices->filter(function ($sale) {
            $invoicedAmount = $sale->invoiced_amount ?? 0;
            return (float)$sale->total_amount > (float)$invoicedAmount;
        })->map(function ($sale) {
            $lastInvoice = $sale->invoices->last();
            return [
                'id' => $sale->id,
                'total_amount' => $sale->total_amount,
                'branch_id' => $sale->branch_id,
                'currency' => $sale->currency,
                'branch' => $sale->branch,
                'invoiced_amount' => $sale->invoiced_amount ?? 0,
                'invoice_count' => $sale->invoices->count(), // Conteo correcto
                'last_total_installments' => $lastInvoice ? $lastInvoice->total_installments : 1,
            ];
        })->values();

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

        // --- MANEJAR ARCHIVOS ADJUNTOS ---
            if ($request->hasFile('media')) {
                $sale->addMultipleMediaFromRequest(['media'])->each(function ($fileAdder) {
                    $fileAdder->toMediaCollection('invoice_media');
                });
            }

        return to_route('invoices.index');
    }

    public function show(Invoice $invoice)
    {
        // Carga todas las relaciones necesarias, incluyendo las otras facturas de la misma venta.
        $invoice->load([
            'sale' => function ($query) {
                $query->select('id', 'branch_id', 'contact_id', 'currency', 'total_amount')
                    // Cargar las facturas relacionadas directamente desde la venta
                    ->with(['invoices' => function($q) {
                        $q->select('id', 'sale_id', 'folio', 'status', 'amount', 'installment_number', 'total_installments')
                          ->orderBy('installment_number', 'asc');
                    }]);
            },
            'sale.branch' => function ($query) {
                $query->select('id', 'name', 'rfc', 'address', 'post_code', 'status');
            },
            'sale.contact' => function ($query) {
                $query->select('id', 'name');
            },
            'payments' => function ($query) {
                // Ordena los pagos por fecha para mostrarlos cronológicamente.
                $query->select('id', 'invoice_id', 'amount', 'payment_date', 'payment_method', 'notes')->latest('payment_date');
            },
            'user:id,name', // El usuario que creó la factura.
            'media' // Archivos adjuntos a la factura.
        ]);

        return Inertia::render('Invoice/Show', [
            'invoice' => $invoice
        ]);
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
        // Se añade validación para no permitir eliminar facturas con pagos.
        if ($invoice->payments()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar una factura que ya tiene pagos registrados.']);
        }

        $invoice->delete();

        // Se retorna a la página anterior con un mensaje de éxito.
        return back()->with('success', 'Factura eliminada correctamente.');
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

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $invoices = Invoice::with(['branch:id,name', 'sale:id'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                // Busca dentro de la relación de la matriz (parent)
                ->orWhereHas('branch', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $invoices], 200);
    }
}
