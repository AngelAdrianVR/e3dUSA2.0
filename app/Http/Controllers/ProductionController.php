<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\ProductionCost;
use App\Models\ProductionTask;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // --- Vista para el Jefe de Producción (Optimizada con filtro por Estatus) ---
        if ($user->hasRole('Jefe de Producción') || $user->hasRole('Super Administrador')) {
            $selectedStatus = $request->input('status');

            // --- Consulta base: Obtenemos las Órdenes de Venta que tienen producción ---
            $query = Sale::query()
                ->whereHas('productions')
                ->select(['id', 'branch_id', 'authorized_at', 'type', 'status', 'is_high_priority', 'user_id', 'created_at', 'promise_date'])
                ->with([
                    'user:id,name',
                    'branch:id,name',
                    'productions.tasks:id,production_id,status,operator_id,started_at,finished_at,name',
                    'productions.tasks.operator:id,name',
                    'saleProducts:id,sale_id,product_id,quantity_to_produce',
                    'saleProducts.product:id,name,code,measure_unit',
                    'saleProducts.product.media',
                ]);
            // --- Aplicar filtro por estatus general de producción ---
            if ($selectedStatus) {
                $query->where(function ($query) use ($selectedStatus) {
                    if ($selectedStatus === 'Sin material') {
                        // Ventas que tienen al menos una producción 'Sin material'
                        $query->whereHas('productions', fn($q) => $q->where('status', 'Sin material'));
                    } elseif ($selectedStatus === 'Terminada') {
                        // Ventas donde TODAS sus producciones están 'Terminada'
                        $query->whereDoesntHave('productions', fn($q) => $q->where('status', '!=', 'Terminada'))
                              ->whereHas('productions');
                    } elseif ($selectedStatus === 'En Proceso') {
                        // Tienen al menos una 'En Proceso' y NINGUNA 'Sin material'
                        $query->whereHas('productions', fn($q) => $q->where('status', 'En Proceso'))
                              ->whereDoesntHave('productions', fn($q) => $q->where('status', 'Sin material'));
                    } elseif ($selectedStatus === 'Pausada') {
                         // Tienen al menos una 'Pausada' y NINGUNA 'Sin material' o 'En Proceso'
                         $query->whereHas('productions', fn($q) => $q->where('status', 'Pausada'))
                               ->whereDoesntHave('productions', fn($q) => $q->whereIn('status', ['Sin material', 'En Proceso']));
                    } elseif ($selectedStatus === 'Pendiente') {
                         // No tienen producciones en estados de mayor prioridad y no están todas terminadas
                         $query->whereDoesntHave('productions', fn($q) => $q->whereIn('status', ['Sin material', 'En Proceso', 'Pausada']))
                               ->whereHas('productions', fn($q) => $q->where('status', '!=', 'Terminada'));
                    }
                });
            }

            // --- Paginación ---
            $sales = $query->latest()->paginate(20)->withQueryString();

            // return $sales;
            return Inertia::render('Production/Index', [
                'viewType' => 'manager',
                'sales' => $sales,
                'filters' => $request->only(['status']), // Ahora el filtro es por status
            ]);
        }

        // --- Vista para el Operador ---
        if ($user->hasRole('Operador')) {
            // --- CARGA OPTIMIZADA ---
            // Solo se carga la información esencial para la vista de tarjetas.
            $myTasks = ProductionTask::where('operator_id', $user->id)
                ->with([
                    'production:id,sale_product_id,status,quantity_to_produce',
                    'production.saleProduct:id,sale_id,product_id,quantity_to_produce,quantity',
                    'production.saleProduct.product:id,name',
                    'production.saleProduct.product.media',
                    'production.saleProduct.sale:id,branch_id,type,is_high_priority,promise_date',
                    'production.saleProduct.sale.branch:id,name',
                    'production.saleProduct.sale.saleProducts.product:id,name,measure_unit',
                    'production.saleProduct.sale.saleProducts.product.media',
                ])
                ->whereHas('production', function ($query) {
                    $query->whereNotIn('status', ['Terminada', 'Cancelada']);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(15); 
                
                // return $myTasks;
            return Inertia::render('Production/Index', [
                'viewType' => 'operator',
                'tasks' => $myTasks,
            ]);
        }

        // Redirección por defecto
        return Inertia::render('404Error');
    }

    public function create()
    {
        // Obtener la lista de usuarios que son operadores
        $operators = User::where('is_active', true)->orderBy('name')->get();

        // Obtener la lista de procesos de producción predefinidos
        $productionCosts = ProductionCost::where('is_active', true)->orderBy('name')->get();

        // Obtener ventas autorizadas con paginación para carga progresiva.
        // Eager-load de todas las relaciones para optimizar al máximo.
        $sales = Sale::whereIn('status', ['Autorizada', 'En Proceso'])
            ->with([
                'branch:id,name',
                'saleProducts' => function ($query) {
                    $query->whereDoesntHave('production')->with([
                        'product:id,name,code,archived_at,measure_unit', // Solo lo necesario para la tabla
                        'product.storages', // Stock del producto terminado
                        'product.media',
                        'product.components.media',
                        'product.components:id,name,measure_unit',
                        'product.components.storages' // Stock de los componentes
                    ]);
                }
            ])
            ->whereHas('saleProducts', function ($query) {
                $query->whereDoesntHave('production');
            })
            ->select(['id', 'branch_id', 'authorized_at', 'type', 'status', 'is_high_priority', ])
            ->latest()
            ->paginate(10); // Paginación

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
                    // Create the production task
                    $productionTask = ProductionTask::create([
                        'production_id' => $production->id,
                        'operator_id' => $taskData['operator_id'],
                        'name' => $taskData['name'],
                        'estimated_time_minutes' => $taskData['estimated_time_minutes'],
                    ]);

                    // --- NOTIFICATION LOGIC ---
                    // Find the assigned operator
                    $operator = User::find($taskData['operator_id']);

                    // Check if the operator exists and is not the person creating the task
                    // if ($operator && $operator->id !== auth()->id()) {
                        $production_folio = 'PROD-' . str_pad($production->id, 4, "0", STR_PAD_LEFT);

                        // Send the notification
                        $operator->notify(new TaskAssignedNotification(
                            'Nueva Tarea Asignada',
                            $production_folio,
                            'production_task',
                            route('productions.index'),
                            $productionTask->name
                        ));
                    // }
                }

                $production->saleProduct->sale->updateStatus(); // <--- método para actualizar estatus de venta (agrega el estatus "En Producción))
            }
        });

        return redirect()->route('productions.index');
    }

    public function show(Sale $sale)
    {
        // Cargar la venta con todas las relaciones necesarias para la vista de producción.
        // Esto evita problemas de N+1 queries y optimiza el rendimiento.
        $sale->load([
            // Datos del cliente y la venta
            'branch:id,name,address,post_code,status',
            'contact:id,name',
            'user:id,name', // Usuario que creó la venta

            // Productos de la venta
            'saleProducts.product:id,name,code,measure_unit,archived_at,currency,large,height,width,diameter,caracteristics', 
            'saleProducts.product.media', 

            // Producciones relacionadas a través de los productos de la venta
            'productions' => function ($query) {
                $query->with([
                    'tasks:id,production_id,operator_id,name,status,started_at,finished_at,estimated_time_minutes',
                    // Tareas de cada producción y el operador asignado
                    'tasks.operator:id,name',
                    // Logs de cada producción y el usuario que los generó
                    'logs.user:id,name',
                    // Producto asociado a la producción para tener referencia
                    'saleProduct.product:id,name,code,measure_unit,archived_at,currency,large,height,width,diameter,caracteristics'
                ])->orderBy('created_at'); // Ordenar producciones
            }
        ]);

        // return $sale;
        // Retornar la vista de Inertia, pasando el objeto 'sale' con todos los datos.
        return Inertia::render('Production/Show', [
            'sale' => $sale
        ]);
    }

    public function edit(Production $production)
    {
        //
    }

    public function update(Request $request, Production $production)
    {
        //
    }

    /*
     HICE UNA MARIGUANADA: EN LA VISTA DE PRODUCCION INDEX SE AGRUPAN LAS PRODUCCIONES POR VENTA, POR LO TANTO SE MANDA EL ID DE LA VENTA
     AL METODO DESTROY, POR ESO CAMBIE EL NOMBRE DEL PARÁMETRO, PORQUE CON $SALE NO AGARRABA LA INYECCION DEL MODELO.

     ¡¡¡¡TODO LO QUE DIGA $production en realidad es $sale!!!!
    */ 
    public function destroy(Sale $production)
    {
        // 1. Obtenemos los IDs de todos los 'sale_products' que pertenecen a esa venta.
        //    (Asegúrate que la relación en tu modelo Sale se llame 'saleProducts')
        $saleProductIds = $production->saleProducts()->pluck('id');

        // 2. Borramos todos los registros de la tabla 'productions' que coincidan con esos IDs.
        //    Esto eliminará la producción original y todas sus "hermanas" de la misma venta.
        if ($saleProductIds->isNotEmpty()) {
            Production::whereIn('sale_product_id', $saleProductIds)->delete();
        }

        // 3. Finalmente, actualizamos el estado general de la venta.
        $production->updateStatus();
    }

    public function updateStatus(Request $request, Production $production)
    {
        $request->validate([
            'status' => 'required|string|in:Pendiente,En Proceso,Pausada,Terminada',
        ]);

        $production->update(['status' => $request->status]);
        $production->saleProduct->sale->updateStatus(); // <--- método para actualizar estatus de venta

        return back();
    }
}
