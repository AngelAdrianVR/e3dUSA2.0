<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\ProductionCost;
use App\Models\ProductionTask;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // --- Vista para el Jefe de Producción ---
        if ($user->hasRole('Jefe de Producción') || $user->hasRole('Super Administrador')) {
            $productions = Production::with([
                'saleProduct.product.media', // Imagen del producto
                'saleProduct.sale:id,branch_id', // Solo lo necesario para la OV
                'saleProduct.sale.branch:id,name', // Solo nombre del cliente
                'tasks:id,production_id,operator_id,status', // Para el progreso
                'tasks.operator:id,name' // Para las fotos de perfil
            ])
            ->latest()
            ->get(); // Traemos todo para el Kanban, luego paginaremos en el frontend si es necesario

            // Agrupar por estatus para el Kanban
            $groupedProductions = $productions->groupBy('status');

            // return $productions;
            return inertia('Production/Index', [
                'viewType' => 'manager',
                'productions' => $productions, // Para la tabla
                'kanbanData' => $groupedProductions, // Para el Kanban
            ]);
        }

        // --- Vista para el Operador ---
        if ($user->hasRole('Operador')) {
            $myTasks = ProductionTask::where('operator_id', $user->id)
                ->with([
                    'production.saleProduct.product.media',
                    'production.saleProduct.sale.branch:id,name',
                ])
                ->whereHas('production', function ($query) {
                    $query->whereNotIn('status', ['Terminada', 'Cancelada']);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return inertia('Production/Index', [
                'viewType' => 'operator',
                'tasks' => $myTasks,
            ]);
        }

        // --- Redirección o vista por defecto si no es ninguno ---
        // Puedes cambiar esto a una página de error o al dashboard.
        return inertia('404Error');
    }


    public function create()
    {
        // Obtener la lista de usuarios que son operadores
        $operators = User::where('is_active', true)->orderBy('name')->get();

        // Obtener la lista de procesos de producción predefinidos
        $productionCosts = ProductionCost::where('is_active', true)->orderBy('name')->get();

        // Obtener ventas autorizadas con paginación para carga progresiva.
        // Eager-load de todas las relaciones para optimizar al máximo.
        $sales = Sale::where('status', 'Autorizada')
            ->with([
                'branch',
                'saleProducts' => function ($query) {
                    $query->whereDoesntHave('production')->with([
                        'product.media', // Para la imagen
                        'product.storages', // Stock del producto terminado
                        'product.components.storages' // Stock de los componentes
                    ]);
                }
            ])
            ->whereHas('saleProducts', function ($query) {
                $query->whereDoesntHave('production');
            })
            ->latest()
            ->paginate(15); // Paginación

        return inertia('Production/Create', [
            'sales' => $sales,
            'operators' => $operators,
            'productionCosts' => $productionCosts, // Enviar los procesos a la vista
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'products_with_tasks' => 'required|array',
            'products_with_tasks.*.sale_product_id' => 'required|exists:sale_products,id',
            'products_with_tasks.*.tasks' => 'required|array|min:1',
            'products_with_tasks.*.tasks.*.operator_id' => 'required|exists:users,id',
            'products_with_tasks.*.tasks.*.name' => 'required|string|max:255',
            'products_with_tasks.*.tasks.*.estimated_time_minutes' => 'required|integer|min:1',
            'products_with_tasks.*.tasks.*.production_cost_id' => 'nullable|exists:production_costs,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->products_with_tasks as $productData) {
                $saleProduct = \App\Models\SaleProduct::find($productData['sale_product_id']);

                $production = Production::create([
                    'sale_product_id' => $saleProduct->id,
                    'quantity_to_produce' => $saleProduct->quantity_to_produce,
                    'status' => 'Pendiente',
                    'created_by_user_id' => auth()->id(),
                ]);

                foreach ($productData['tasks'] as $taskData) {
                    ProductionTask::create([
                        'production_id' => $production->id,
                        'operator_id' => $taskData['operator_id'],
                        'name' => $taskData['name'],
                        'estimated_time_minutes' => $taskData['estimated_time_minutes'],
                    ]);
                }
            }
        });

        return redirect()->route('productions.index');
    }

    public function show(Production $production)
    {
        //
    }

    public function edit(Production $production)
    {
        //
    }

    public function update(Request $request, Production $production)
    {
        //
    }

    public function destroy(Production $production)
    {
        //
    }

    public function updateStatus(Request $request, Production $production)
    {
        $request->validate([
            'status' => 'required|string|in:Pendiente,En Proceso,Pausada,Terminada', // Asegúrate que los estados sean válidos
        ]);

        $production->update(['status' => $request->status]);

        return back();
    }
}
