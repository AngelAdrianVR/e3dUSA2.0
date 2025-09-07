<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = auth()->user();

        // Inicializar la consulta de compras
        $query = Purchase::with(['supplier:id,name', 'user:id,name', 'authorizer:id,name', 'items.product:id,name,measure_unit', 'items.product.media'])
                         ->latest();

        // Filtrar por "mis compras" o "todas las compras"
        $view = $request->input('view', 'my'); // 'my' es el valor por defecto

        if ($view === 'my') {
            $query->where('user_id', $user->id);
        }

        // Paginar los resultados
        $purchases = $query->paginate(15)->withQueryString();

        // return $purchases;
        // Devolver la vista de Inertia con los datos
        return Inertia::render('Purchase/Index', [
            'purchases' => $purchases,
            'filters' => ['view' => $view],
        ]);
    }

    public function create()
    {
        // Se envían solo los proveedores activos.
        $suppliers = Supplier::select('id', 'name')->get();
        
        // Se podrían enviar también los productos, pero es mejor cargarlos dinámicamente
        // al seleccionar un proveedor para mejorar el rendimiento.
        return Inertia::render('Purchase/Create', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        // Validación de los datos del formulario, incluyendo los nuevos campos
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_contact_id' => 'nullable|exists:supplier_contacts,id',
            'supplier_bank_account_id' => 'nullable|exists:supplier_bank_accounts,id',
            'expected_delivery_date' => 'required|date',
            'currency' => 'required|string|max:3',
            'notes' => 'nullable|string|max:1000',
            'is_spanish_template' => 'required|boolean',
            // 'type' => 'required|string|in:Venta,Muestra',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.additional_stock' => 'nullable|numeric|min:0',
            'items.*.plane_stock' => 'nullable|numeric|min:0',
            'items.*.ship_stock' => 'nullable|numeric|min:0',
            'items.*.type' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'subtotal' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // 1. Crear la orden de compra principal con todos los datos
            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'user_id' => auth()->id(),
                'supplier_contact_id' => $request->supplier_contact_id,
                'supplier_bank_account_id' => $request->supplier_bank_account_id,
                'expected_delivery_date' => $request->expected_delivery_date,
                'currency' => $request->currency,
                'notes' => $request->notes,
                'is_spanish_template' => $request->is_spanish_template,
                // 'type' => $request->type,
                'subtotal' => $request->subtotal,
                'tax' => $request->tax,
                'total' => $request->total,
                'status' => 'Pendiente', // Estatus inicial
                'emited_at' => now(),
            ]);

            // 2. Crear los items de la orden de compra
            foreach ($request->items as $itemData) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'description' => $itemData['product_name'], // Se toma del form
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                    'additional_stock' => $itemData['additional_stock'] ?? 0,
                    'plane_stock' => $itemData['plane_stock'] ?? 0,
                    'ship_stock' => $itemData['ship_stock'] ?? 0,
                    'type' => $itemData['type'] ?? 'Venta',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            // manejo de media (archivos extra)
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $purchase->addMedia($file)->toMediaCollection();
                }
            }
            
            DB::commit();
            
            // Redirigir al index con un mensaje de éxito
            return redirect()->route('purchases.index')->with('success', 'Órden de compra creada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear la orden de compra: " . $e->getMessage());
            // Redirigir de vuelta con un mensaje de error
            return back()->withErrors(['general' => 'Ocurrió un error inesperado. Por favor, inténtelo de nuevo.'])->withInput();
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load([
            'user:id,name', // Usuario que creó la orden
            'authorizer:id,name', // Usuario que autorizó
            'supplier:id,name,address,phone', // Información del proveedor
            'contact:id,name', // Contacto del proveedor
            'items.product.media', // Items de la compra, con su producto y la imagen del producto
            'bankAccount'
        ]);

        // return $purchase;
        // Renderizamos el componente de Vue y le pasamos la orden de compra con sus relaciones.
        return Inertia::render('Purchase/Show', [
            'purchase' => $purchase,
        ]);
    }

    public function edit(Purchase $purchase)
    {
        // Cargar las relaciones necesarias para la vista de edición
        $purchase->load(['items.product', 'supplier', 'media']);

        // Se envían todos los proveedores para el selector
        $suppliers = Supplier::select('id', 'name')->get();
        
        return Inertia::render('Purchase/Edit', [
            'purchase' => $purchase,
            'suppliers' => $suppliers,
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        // Validación de los datos del formulario
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_contact_id' => 'nullable|exists:supplier_contacts,id',
            'supplier_bank_account_id' => 'nullable|exists:supplier_bank_accounts,id',
            'expected_delivery_date' => 'required|date',
            'currency' => 'required|string|max:3',
            'notes' => 'nullable|string|max:1000',
            'is_spanish_template' => 'required|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
            'media.*' => 'nullable|file|max:10240', // max 10MB
            'current_media_ids' => 'nullable|array', // IDs de los archivos existentes a conservar
        ]);

        try {
            DB::beginTransaction();

            // 1. Actualizar la orden de compra principal
            $purchase->update($request->except(['items', 'media', 'current_media_ids']));

            // 2. Sincronizar los items: Eliminar los antiguos e insertar los nuevos
            $purchase->items()->delete();
            foreach ($request->items as $itemData) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'description' => $itemData['product_name'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                    'additional_stock' => $itemData['additional_stock'] ?? 0,
                    'plane_stock' => $itemData['plane_stock'] ?? 0,
                    'ship_stock' => $itemData['ship_stock'] ?? 0,
                    'type' => $itemData['type'] ?? 'Venta',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            // 3. Manejo de media
            // Agregar nuevos archivos
            if ($request->hasFile('media')) {
                $purchase->addMediaFromRequest('media')->toMediaCollection();
            }

            DB::commit();

            return redirect()->route('purchases.index')->with('success', 'Órden de compra actualizada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar la orden de compra: " . $e->getMessage());
            return back()->withErrors(['general' => 'Ocurrió un error inesperado. Por favor, inténtelo de nuevo.'])->withInput();
        }
    }

    public function destroy(Purchase $purchase)
    {
        $purchase->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $purchase = Purchase::find($id);
            $purchase->delete();
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $purchases = Purchase::with(['supplier:id,name', 'user:id,name', 'authorizer:id,name', 'items.product:id,name,measure_unit', 'items.product.media'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhereHas('user', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('supplier', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $purchases], 200);
    }

    public function authorizePurchase(Purchase $purchase)
    {
        $purchase->update([
            'authorizer_id' => auth()->id(),
            'authorized_at' => now(),
            'status' => 'Autorizada',
        ]);

        $purchase->load(['user', 'authorizer']);

        // return response()->json(['message' => 'Orden autorizada', 'item' => $purchase]);
    }

    public function updateStatus(Request $request, Purchase $purchase)
    {
        // Validamos que el estatus enviado sea uno de los permitidos
        $request->validate([
            'status' => ['required', 'string', Rule::in(['Compra realizada', 'Compra recibida'])],
        ]);

        $newStatus = $request->input('status');
        $currentStatus = $purchase->status;

        try {
            DB::transaction(function () use ($newStatus, $currentStatus, $purchase) {
                switch ($newStatus) {
                    case 'Compra realizada':
                        if ($currentStatus !== 'Autorizada') {
                            throw new \Exception('La compra debe estar "Autorizada" para marcarla como realizada.');
                        }
                        $purchase->update(['status' => $newStatus]);
                        break;

                    case 'Compra recibida':
                        if ($currentStatus !== 'Compra realizada') {
                            throw new \Exception('La compra debe estar marcada como "Realizada" para poder recibirla.');
                        }

                        // 1. Actualizar el estado y la fecha de recepción de la compra
                        $purchase->update([
                            'status' => $newStatus,
                            'recieved_at' => now(),
                        ]);

                        // 2. Cargar la relación items con sus productos para evitar N+1 queries
                        $purchase->load('items.product');

                        // 3. Iterar sobre los productos de la compra para actualizar el stock
                        foreach ($purchase->items as $item) {
                            if ($item->product) {

                                $storage = $item->product->storages()->firstOrCreate([], ['quantity' => 0]);

                                // Incrementar el stock con la cantidad del item de la compra
                                $storage->increment('quantity', $item->quantity);

                                StockMovement::create([
                                    'product_id' => $item->product->id,
                                    'storage_id' => $storage->id,
                                    'quantity_change' => $item->quantity,
                                    'type' => 'Entrada',
                                    'notes' => 'Entrada por orden de compra recibida OC- ' . $purchase->id
                                ]);
                            }
                        }
                        break;

                    default:
                        throw new \Exception('Estatus no válido o transición no permitida.');
                }
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "El estatus de la compra se actualizó a \"{$newStatus}\".");
    }

    public function print(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'bankAccount',
            'contact',
            'authorizer',
            'items.product',
            'items.product.media',
            'user',
        ]);

        // return $purchase;
        return Inertia::render('Purchase/Print', [
            'purchase' => $purchase
        ]);
    }
}
