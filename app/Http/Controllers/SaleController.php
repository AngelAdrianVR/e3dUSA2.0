<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        // Determina si se deben mostrar todas las ventas o solo las del usuario.
        // El frontend enviará un query param `view=all` para ver todas.
        $showAll = $request->query('view') === 'all';

        // Aquí deberías agregar tu lógica de permisos.
        // Ejemplo: if ($showAll && !Auth::user()->can('view all sales')) {
        //     abort(403);
        // }

        $query = Sale::query();

        // Si no se solicita ver todo, filtra por el usuario autenticado.
        if (!$showAll) {
            $query->where('user_id', Auth::id());
        }

        // Carga ansiosa (Eager Loading) para evitar problemas N+1 en la vista.
        // Cargamos las relaciones 'user' (creador) y 'branch' (cliente).
        $sales = $query->with(['user:id,name', 'branch:id,name', 'saleProducts.product:id,name,cost'])
                    ->select('id', 'branch_id', 'quote_id', 'user_id', 'type', 'status', 'total_amount', 'created_at', 'is_high_priority', 'authorized_user_name', 'authorized_at', 'created_at')
                    ->latest() // Ordena por los más recientes primero
                    ->paginate(15) // Pagina los resultados
                    ->withQueryString(); // Mantiene los query params (ej. `view=all`) en la paginación
        
                    //    return $sales;
        // Retorna la vista de Inertia con los datos paginados.
        return Inertia::render('Sale/Index', [
            'sales' => $sales,
            'filters' => $request->only(['view']), // Pasa los filtros actuales a la vista
        ]);
    }

    public function create()
    {
        // Obtenemos todas las sucursales (clientes) activas.
        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // Obtenemos solo las cotizaciones que han sido autorizadas y no están en una OV.
        $quotes = Quote::where('authorized_at', '!=', null)
                    ->latest()
                    ->whereDoesntHave('sale')
                    ->select('id', 'branch_id', 'sale_id')
                    ->with('branch:id,name')
                    ->get();
        
        return Inertia::render('Sale/Create', [
            'branches' => $branches,
            'quotes' => $quotes,
            'catalog_products' => Product::where('product_type', 'Catálogo')->select('id', 'name')->get()
        ]);
    }

    public function store(Request $request)
    {
        // --- 1. VALIDACIÓN DE DATOS ---
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'required|exists:contacts,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'type' => ['required', Rule::in(['venta', 'stock'])],
            'oce_name' => 'nullable|string|max:255',
            'order_via' => 'required|string|max:255',
            'freight_option' => 'required|string|max:255',
            'freight_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_high_priority' => 'required|boolean',
            'shipping_option' => 'required|string',

            // Validación de productos de la venta
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.notes' => 'nullable|string',

            // Validación de parcialidades (envíos)
            'shipments' => 'required|array|min:1',
            'shipments.*.promise_date' => 'required|date',
            'shipments.*.shipping_company' => 'nullable|string|max:255',
            'shipments.*.tracking_guide' => 'nullable|string|max:255',
            'shipments.*.acknowledgement_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt|max:2048',

            // Validación de productos dentro de cada parcialidad
            'shipments.*.products' => 'required|array',
            'shipments.*.products.*.product_id' => 'required|exists:products,id',
            'shipments.*.products.*.quantity' => 'required|integer|min:0',

            // Validación de archivos
            'oce_media' => 'nullable|array|max:3',
            'oce_media.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml|max:2048',
            'anotherFiles' => 'nullable|array|max:3',
            'anotherFiles.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml|max:2048',
        ]);

        // --- 2. TRANSACCIÓN DE BASE DE DATOS ---
        DB::beginTransaction();

        try {
            // --- 3. CREAR LA ORDEN DE VENTA PRINCIPAL ---
            $sale = Sale::create([
                'branch_id' => $validated['branch_id'],
                'contact_id' => $validated['contact_id'],
                'quote_id' => $validated['quote_id'],
                'user_id' => auth()->id(),
                'type' => $validated['type'],
                'status' => 'Pendiente', // Estado inicial
                'oce_name' => $validated['oce_name'],
                'order_via' => $validated['order_via'],
                'notes' => $validated['notes'],
                'is_high_priority' => $validated['is_high_priority'],
                'freight_option' => $validated['freight_option'],
                'freight_cost' => $validated['freight_cost'],
                'total_amount' => array_reduce($validated['products'], function ($carry, $product) {
                    return $carry + ($product['quantity'] * $product['price']);
                }, 0),
            ]);
            
            // Si la venta está basada en una cotización, actualizar la cotización para vincularla a esta venta
            if (isset($validated['quote_id']) && $validated['quote_id']) {
                Quote::find($validated['quote_id'])->update(['sale_id' => $sale->id]);
            }

            // --- 4. GUARDAR PRODUCTOS DE LA VENTA (TABLA PIVOTE `sale_products`) ---
            $saleProductsMap = []; // Mapa para fácil acceso: [product_id => sale_product_id]
            foreach ($validated['products'] as $productData) {
                $saleProduct = $sale->saleProducts()->create([
                    'product_id' => $productData['id'],
                    'quantity' => $productData['quantity'],
                    'price' => $productData['price'],
                    'notes' => $productData['notes'] ?? null,
                    // Valores iniciales
                    'quantity_produced' => 0,
                    'quantity_shipped' => 0,
                ]);
                $saleProductsMap[$productData['id']] = $saleProduct->id;
            }

            // --- 5. GUARDAR ENVÍOS (PARCIALIDADES) Y SUS PRODUCTOS ---
            foreach ($validated['shipments'] as $index => $shipmentData) {
                // Crear el registro del envío
                $shipment = $sale->shipments()->create([
                    'status' => 'Pendiente', // Estado inicial
                    'promise_date' => $shipmentData['promise_date'],
                    'shipping_company' => $shipmentData['shipping_company'],
                    'tracking_guide' => $shipmentData['tracking_guide'],
                    'sent_by' => null, // Se llenará cuando se marque como enviado
                    'sent_at' => null,
                ]);

                // Manejar subida de archivo de acuse si existe
                if ($request->hasFile("shipments.{$index}.acknowledgement_file")) {
                    $file = $request->file("shipments.{$index}.acknowledgement_file");
                    // Aquí puedes usar Spatie/MediaLibrary o el Storage de Laravel
                    // Ejemplo con Spatie/MediaLibrary:
                    $shipment->addMedia($file)->toMediaCollection('acuse_files');
                }

                // Guardar los productos de este envío
                foreach ($shipmentData['products'] as $shipmentProductData) {
                    if ($shipmentProductData['quantity'] > 0) {
                        $shipment->shipmentProducts()->create([
                            'sale_product_id' => $saleProductsMap[$shipmentProductData['product_id']],
                            'quantity' => $shipmentProductData['quantity'],
                        ]);
                    }
                }
            }
            
            // --- 6. MANEJAR ARCHIVOS ADJUNTOS A LA VENTA ---
            // Ejemplo con Spatie/MediaLibrary:
            if ($request->hasFile('oce_media')) {
                $sale->addMultipleMediaFromRequest(['oce_media'])->each(function ($fileAdder) {
                    $fileAdder->toMediaCollection('oce_media');
                });
            }
            if ($request->hasFile('anotherFiles')) {
                $sale->addMultipleMediaFromRequest(['anotherFiles'])->each(function ($fileAdder) {
                    $fileAdder->toMediaCollection('other_files');
                });
            }

            // --- 7. CONFIRMAR TRANSACCIÓN ---
            DB::commit();

            // Log de éxito (opcional)
            Log::info("Órden de venta #{$sale->id} creada exitosamente por el usuario " . auth()->id());

            return redirect()->route('sales.index', $sale)->with('success', 'Órden de venta creada exitosamente.');

        } catch (\Exception $e) {
            // --- 8. REVERTIR TRANSACCIÓN EN CASO DE ERROR ---
            DB::rollBack();

            // Log del error para depuración
            Log::error("Error al crear la órden de venta: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            // Redirigir de vuelta con un mensaje de error
            return back()->withInput()->withErrors(['generic_error' => 'Ocurrió un error inesperado al guardar la órden de venta. Por favor, inténtalo de nuevo.']);
        }
    }

    public function show(Sale $sale)
    {
        //
    }

    public function edit(Sale $sale)
    {
        //
    }

    public function update(Request $request, Sale $sale)
    {
        //
    }

    public function destroy(Sale $sale)
    {
        //
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Sale::find($id);
            $bonus?->delete();
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $sales = Sale::with(['user:id,name', 'branch', 'saleProducts.product:id,name,cost'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhereHas('user', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->select('id', 'branch_id', 'quote_id', 'user_id', 'type', 'status', 'total_amount', 'created_at', 'is_high_priority', 'authorized_user_name', 'authorized_at', 'created_at')
            ->get();

        return response()->json(['items' => $sales], 200);
    }

    public function authorizeSale(Sale $sale)
    {
        $sale->update([
            'authorized_user_name' => auth()->user()->name,
            'authorized_at' => now(),
            'status' => 'Autorizada',
        ]);

        // notificar a creador de la orden si quien autoriza no es el mismo usuario
        // if (auth()->id() != $quote->user->id) {
        //     $quote_folio = 'OV-' . str_pad($quote->id, 4, "0", STR_PAD_LEFT);
        //     $quote->user->notify(new ApprovalQuoteNotification(
        //         'Orden de venta autorizada',
        //         $quote_folio,
        //         'quote',
        //         route('quotes.show', $quote->id)
        //     ));
        // }

        return response()->json(['message' => 'Orden de venta autorizada', 'item' => $sale]); //en caso de actualizar en la misma vista descomentar
    }

    /**
     * Muestra una vista de impresión para la orden de venta.
     *
     * @param  \App\Models\Sale  $sale
     * @return \Inertia\Response
     */
    public function print(Sale $sale)
    {
        $sale->load([
            'branch.contacts', // Sucursal del cliente y sus contactos
            'user',            // Usuario que creó la venta
            'saleProducts.product.media', // Productos de la venta y su info del catálogo
            'shipments'       // Envíos o parcialidades de la venta
        ]);

        // return $sale;
        return Inertia::render('Sale/Print', [
            'sale' => $sale
        ]);
    }
}
