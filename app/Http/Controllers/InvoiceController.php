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
    // Capturamos el filtro de cliente desde la URL
    $clientId = $request->get('client_id');

    // Obtenemos la lista de clientes (sucursales) para el selector filtrable
    // Ajusta el nombre del modelo según tu proyecto (asumo Branch por el contexto previo)
    $branches = \App\Models\Branch::select('id', 'name')
        ->orderBy('name')
        ->get();

    // Pestaña 1: Todas las facturas registradas.
    $invoices = Invoice::with(['sale:id,branch_id', 'sale.branch:id,name'])
        // Filtramos por cliente si el ID está presente
        ->when($clientId, function ($query) use ($clientId) {
            $query->where('branch_id', $clientId);
        })
        ->orderBy('sale_id', 'desc')
        ->orderBy('installment_number', 'asc')
        ->paginate(10, ['*'], 'invoices_page')
        ->withQueryString(); // Mantiene tab y client_id en los links de paginación

    // Pestaña 2: Órdenes de venta que requieren facturación.
    $salesWithoutInvoice = Sale::with(['branch:id,name'])
        ->withSum(['invoices as total_invoiced' => function($query) {
            $query->where('status', '!=', 'Cancelada');
        }], 'amount')
        ->withCount('invoices as invoices_count')
        ->where('status', '!=', 'Cancelada')
        // Aplicamos el filtro de cliente aquí también
        ->when($clientId, function ($query) use ($clientId) {
            $query->where('branch_id', $clientId);
        })
        ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE invoices.sale_id = sales.id AND status != ?)', ['Cancelada'])
        ->latest('id')
        ->paginate(10, ['*'], 'sales_page')
        ->withQueryString();

    // Pestaña 3: Facturas por cobrar.
    $pendingInvoices = Invoice::with(['sale:id,branch_id', 'sale.branch:id,name', 'payments'])
        ->whereIn('status', ['Pendiente', 'Vencida', 'Parcialmente pagada'])
        // Aplicamos el filtro de cliente
        ->when($clientId, function ($query) use ($clientId) {
            $query->where('branch_id', $clientId);
        })
        ->orderBy('due_date', 'asc')
        ->paginate(10, ['*'], 'pending_page')
        ->withQueryString();
        
    return Inertia::render('Invoice/Index', [
        'invoices' => $invoices,
        'salesWithoutInvoice' => $salesWithoutInvoice,
        'pendingInvoices' => $pendingInvoices,
        'branches' => $branches, // Pasamos la lista para el el-select
        'active_tab_prop' => $request->get('tab', 'all_invoices'), // Persistencia de pestaña
        'client_id_prop' => $clientId, // Persistencia del filtro seleccionado
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
        ]));

        // Agrega el id de la factura a la venta si no la tiene
        $sale = Sale::find($validatedData['sale_id']);
        if ( !$sale->invoice_id ) {
            $sale->invoice_id = $invoice->id;
            $sale->save();
        }

        // --- MANEJAR ARCHIVOS ADJUNTOS ---
            if ($request->hasFile('media')) {
                $invoice->addMultipleMediaFromRequest(['media'])->each(function ($fileAdder) {
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
                $query->select('id', 'name', 'rfc', 'address', 'post_code', 'status', 'created_at');
            },
            'sale.contact' => function ($query) {
                $query->select('id', 'name');
            },
            // --- CORRECCIÓN AQUÍ ---
            // Se aplican las condiciones a la relación 'payments' y luego se carga 'media' para cada pago.
            'payments' => function ($query) {
                // Ordena los pagos por fecha para mostrarlos cronológicamente y carga sus archivos.
                $query->with('media')->latest('payment_date');
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
        // La lógica para obtener las órdenes de venta es similar a la del método 'create'.
        // Se podría refactorizar a un método privado para evitar duplicar código.
        $salesWithPartialInvoices = Sale::with([
            'branch:id,name',
            'invoices' => function ($query) {
                $query->where('status', '!=', 'Cancelada')->select('sale_id', 'amount', 'total_installments');
            }
        ])
        ->where('status', '!=', 'Cancelada')
        ->withSum(['invoices as invoiced_amount' => function ($query) {
            $query->where('status', '!=', 'Cancelada');
        }], 'amount')
        ->latest()
        ->get();

        $sales = $salesWithPartialInvoices->filter(function ($sale) use ($invoice) {
            $invoicedAmount = $sale->invoiced_amount ?? 0;
            // Permite la OV asociada a la factura actual, incluso si ya está completamente facturada.
            return ((float)$sale->total_amount > (float)$invoicedAmount) || $sale->id === $invoice->sale_id;
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
        })->values();

        return Inertia::render('Invoice/Edit', [
            'invoice' => $invoice,
            'sales' => $sales,
        ]);
    }

    public function update(StoreInvoiceRequest $request, Invoice $invoice)
    {
        $validatedData = $request->validated();
        $invoice->update($validatedData);

        // Manejo de archivos adjuntos. Solo añade los nuevos.
        if ($request->hasFile('media')) {
            // Opcional: si quisieras reemplazar los archivos, descomenta la siguiente línea.
            // $invoice->clearMediaCollection('invoice_media');
            
            $invoice->addMultipleMediaFromRequest(['media'])->each(function ($fileAdder) {
                $fileAdder->toMediaCollection('invoice_media');
            });
        }
        
        // Redirige a la vista de detalle con un mensaje de éxito.
        return to_route('invoices.show', $invoice)->with('success', 'Factura actualizada correctamente.');
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

    /**
     * Adjunta archivos a una factura existente.
     * Es necesario crear la ruta para este método en tu archivo de rutas (web.php).
     * Ejemplo: Route::post('/invoices/{invoice}/media', [InvoiceController::class, 'storeMedia'])->name('invoices.media.store');
     */
    public function storeMedia(Request $request, Invoice $invoice)
    {
        $request->validate([
            'media' => 'required|array',
            'media.*' => 'file|mimes:jpg,jpeg,png,pdf,xml|max:4096',
        ]);

        if ($request->hasFile('media')) {
            $invoice->addMultipleMediaFromRequest(['media'])->each(function ($fileAdder) {
                $fileAdder->toMediaCollection('invoice_media');
            });
        }

        return back()->with('success', 'Archivos adjuntados correctamente.');
    }

    /**
     * Genera un reporte de facturas pendientes en un rango de fechas.
     */
    public function pendingReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // Buscar OVs que tengan facturas con estatus pendiente/vencido/parcial
        // y cuya fecha de emisión esté en el rango seleccionado.
        $sales = Sale::whereHas('invoices', function ($query) use ($startDate, $endDate) {
            $query->whereIn('status', ['Pendiente', 'Parcialmente pagada', 'Vencida'])
                  ->whereBetween('issue_date', [$startDate, $endDate]);
        })
        ->with([
            // Cargar todas las facturas de esas OVs para dar contexto completo.
            'invoices' => function ($query) {
                $query->with('payments')->orderBy('installment_number', 'asc');
            }, 
            'branch:id,name'
        ])
        ->get();
        
        // Devuelve la vista del reporte con los datos.
        // Asegúrate de que no se use el layout principal para que la vista sea limpia para imprimir.
        return Inertia::render('Invoice/PendingInvoicesReport', [
            'sales' => $sales,
            'report_dates' => ['start' => $startDate, 'end' => $endDate],
        ]);
    }
}
