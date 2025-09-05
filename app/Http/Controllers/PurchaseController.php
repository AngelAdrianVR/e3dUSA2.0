<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = auth()->user();

        // Inicializar la consulta de compras
        $query = Purchase::with(['supplier', 'user', 'authorizer', 'purchaseItems.product'])
                         ->latest();

        // Filtrar por "mis compras" o "todas las compras"
        $view = $request->input('view', 'my'); // 'my' es el valor por defecto

        if ($view === 'my' && !$user->hasPermissionTo('Ver todas las compras')) {
            $query->where('user_id', $user->id);
        }

        // Paginar los resultados
        $purchases = $query->paginate(15)->withQueryString();

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
        // Validación de los datos
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_contact_id' => 'nullable|exists:supplier_contacts,id',
            'expected_delivery_date' => 'required|date',
            'currency' => 'required|string|max:3',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // 1. Crear la orden de compra principal
            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'user_id' => auth()->id(),
                'supplier_contact_id' => $request->supplier_contact_id,
                'expected_delivery_date' => $request->expected_delivery_date,
                'currency' => $request->currency,
                'notes' => $request->notes,
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
                    'description' => $itemData['product_name'], // o busca el nombre del producto
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                ]);
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
        //
    }

    public function edit(Purchase $purchase)
    {
        //
    }

    public function update(Request $request, Purchase $purchase)
    {
        //
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
}
