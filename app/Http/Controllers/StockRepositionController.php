<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;

class StockRepositionController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        // 1. Obtener productos candidatos (con filtro de búsqueda si existe)
        $candidates = Product::with(['storages', 'media', 'suppliers'])
            ->where(function ($query) {
                $query->where('product_type', 'Materia prima')
                      ->orWhere(function ($q) {
                          $q->where('product_type', 'Catálogo')
                            ->where('is_purchasable', true);
                      });
            })
            ->whereNull('archived_at')
            // --- NUEVO: Buscador por Nombre o Código ---
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->get();

        // 2. Filtrar y formatear para la vista (Lógica existente)
        $lowStockProducts = $candidates->filter(function ($product) {
            $currentStock = $product->storages->sum('quantity');
            return $currentStock < $product->min_quantity;
        })->map(function ($product) {
            $currentStock = $product->storages->sum('quantity');
            $missing = $product->min_quantity - $currentStock;
            
            $suggestedSupplier = $product->suppliers->first();

            $activePurchase = Purchase::whereHas('items', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            })
            ->whereIn('status', ['Pendiente', 'Autorizada', 'Compra realizada'])
            ->latest()
            ->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'image_url' => $product->getFirstMediaUrl() ?: null,
                'current_stock' => $currentStock,
                'min_quantity' => $product->min_quantity,
                'missing' => $missing > 0 ? $missing : 0,
                'suggested_supplier_id' => $suggestedSupplier?->id,
                'suggested_supplier_name' => $suggestedSupplier?->name,
                'active_purchase' => $activePurchase ? [
                    'id' => $activePurchase->id,
                    'status' => $activePurchase->status,
                    'created_at' => $activePurchase->created_at->isoFormat('DD MMM YYYY'),
                ] : null,
            ];
        })->values(); // Resetear índices para la colección

        // 3. --- NUEVO: Paginación Manual sobre la colección filtrada ---
        $perPage = 10; // Elementos por página
        $page = Paginator::resolveCurrentPage() ?: 1;
        
        $items = $lowStockProducts->forPage($page, $perPage);
        
        $paginatedItems = new LengthAwarePaginator(
            $items, // Items de la página actual
            $lowStockProducts->count(), // Total de items filtrados
            $perPage, // Items por página
            $page, // Página actual
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => $request->query(), // Mantiene los parámetros de búsqueda en los links
            ]
        );

        return Inertia::render('StockReposition/Index', [
            'products' => $paginatedItems, // Ahora enviamos el objeto paginador
            'filters' => $request->only(['search']), // Para mantener el estado del input
        ]);
    }
}