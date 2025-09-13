<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DesignOrder;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;

class AppLayoutController extends Controller
{
    public function globalSearch(Request $request){
        $term = $request->input('term');

        // Validar que el término de búsqueda no esté vacío
        if (empty($term)) {
            return response()->json([]);
        }

        $results = [];
        $limit = 5; // Límite de resultados por modelo

        // Búsqueda en Usuarios
        $users = User::where('name', 'LIKE', "%{$term}%")
            ->orWhere('email', 'LIKE', "%{$term}%")
            ->select('id', 'name')
            ->take($limit)
            ->get()
            ->map(function ($user) {
                // Asumimos que tienes una ruta 'users.show'
                $user->url = route('users.show', $user->id);
                return $user;
            });

        if ($users->isNotEmpty()) {
            $results['Usuarios'] = $users;
        }

        // Búsqueda en Productos
        $products = Product::where('name', 'LIKE', "%{$term}%")
            ->orWhere('code', 'LIKE', "%{$term}%")
            ->select('id', 'name', 'code')
            ->take($limit)
            ->get()
            ->map(function ($product) {
                // Asumimos que tienes una ruta 'products.show'
                $product->url = route('products.show', $product->id);
                return $product;
            });

        if ($products->isNotEmpty()) {
            $results['Productos'] = $products;
        }

        // Búsqueda en Sucursales (Clientes/Prospectos)
        $branches = Branch::where('name', 'LIKE', "%{$term}%")
            ->select('id', 'name', 'status')
            ->take($limit)
            ->get()
            ->map(function ($branch) {
                // Asumimos que tienes una ruta 'branches.show'
                $branch->url = route('branches.show', $branch->id);
                return $branch;
            });

        if ($branches->isNotEmpty()) {
            $results['Sucursales'] = $branches;
        }

        // Búsqueda en Ordenes de diseño
        $designOrders = DesignOrder::where('orden_title', 'LIKE', "%{$term}%")
            ->select('id', 'orden_title', 'status')
            ->take($limit)
            ->get()
            ->map(function ($design_order) {
                // Asumimos que tienes una ruta 'designOrders.show'
                $design_order->url = route('design-orders.show', $design_order->id);
                return $design_order;
            });

        if ($designOrders->isNotEmpty()) {
            $results['Sucursales'] = $designOrders;
        }

        // Búsqueda en Ventas (por ID)
        if (is_numeric($term)) {
            $sales = Sale::where('id', $term)
                ->select('id', 'status', 'total_amount')
                ->take($limit)
                ->get()
                ->map(function ($sale) {
                    // Asumimos que tienes una ruta 'sales.show'
                    $sale->url = route('sales.show', $sale->id);
                    return $sale;
                });
            
            if ($sales->isNotEmpty()) {
                $results['Ventas'] = $sales;
            }
        }

        // Búsqueda en Máquinas
        $machines = Machine::where('name', 'LIKE', "%{$term}%")
            ->orWhere('serial_number', 'LIKE', "%{$term}%")
            ->select('id', 'name', 'serial_number')
            ->take($limit)
            ->get()
            ->map(function ($machine) {
                // Asumimos que tienes una ruta 'machines.show'
                $machine->url = route('machines.show', $machine->id);
                return $machine;
            });

        if ($machines->isNotEmpty()) {
            $results['Máquinas'] = $machines;
        }

        return response()->json($results);
    }
}
