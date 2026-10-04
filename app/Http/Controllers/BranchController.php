<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchPriceHistory;
use App\Models\BranchVolumePrice;
use App\Models\Contact;
use App\Models\Product;
use App\Models\User;
use App\Services\BranchGroupService;
use App\Services\BranchVolumePriceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    public function __construct(
        private BranchGroupService $branchGroups,
        private BranchVolumePriceService $volumePrices
    ) {
    }

    public function index(Request $request)
    {
        $group = $request->input('group');
        $status = $request->input('status');
        $accountManagerId = $request->input('account_manager_id');

        // Cargamos solo las matrices (parent_branch_id = null) y sus sucursales hijas
        $branches = Branch::whereNull('parent_branch_id')
            ->when($group, function ($query) use ($group) {
                // Se incluyen las matrices del grupo y también aquellas que tengan
                // sucursales hijas asignadas al grupo.
                $query->where(function ($q) use ($group) {
                    $q->where('group_name', $group)
                      ->orWhereHas('children', function ($childQuery) use ($group) {
                          $childQuery->where('group_name', $group);
                      });
                });
            })
            ->when($status, function ($query) use ($status) {
                // Coincide la matriz o alguna de sus sucursales hijas.
                $query->where(function ($q) use ($status) {
                    $q->where('status', $status)
                      ->orWhereHas('children', function ($childQuery) use ($status) {
                          $childQuery->where('status', $status);
                      });
                });
            })
            ->when($accountManagerId, function ($query) use ($accountManagerId) {
                // Coincide la matriz o alguna de sus sucursales hijas.
                $query->where(function ($q) use ($accountManagerId) {
                    $q->where('account_manager_id', $accountManagerId)
                      ->orWhereHas('children', function ($childQuery) use ($accountManagerId) {
                          $childQuery->where('account_manager_id', $accountManagerId);
                      });
                });
            })
            ->with(['accountManager:id,name', 'children.accountManager:id,name'])
            ->latest() // Ordena por los más recientes primero
            ->paginate(30) // Pagina los resultados
            ->withQueryString();

        // Listado de grupos existentes para el filtro del index
        $groups = Branch::whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name')
            ->values();

        // Vendedores disponibles para el filtro
        $sellers = User::where('is_active', true)
            ->where('id', '!=', 1)
            ->role(['Vendedor', 'Super Administrador'])
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Branch/Index', [
            'branches' => $branches,
            'groups' => $groups,
            'sellers' => $sellers,
            'filters' => [
                'group' => $group,
                'status' => $status,
                'account_manager_id' => $accountManagerId,
            ],
        ]);
    }

    // --- MÉTODO PARA EL REPORTE ---
    public function report()
    {
        // Consultamos todas las matrices y sus hijas sin paginación para el reporte completo
        $matrices = Branch::whereNull('parent_branch_id')
            ->with(['accountManager:id,name', 'children.accountManager:id,name'])
            ->orderBy('name', 'asc') // Orden alfabético para el reporte
            ->get();

        return Inertia::render('Branch/Report', [
            'matrices' => $matrices,
        ]);
    }

    // --- MÉTODO PARA EXPORTAR A EXCEL (CSV) ---
    public function export()
    {
        $fileName = 'Directorio_Clientes_' . date('Y-m-d_H-i-s') . '.csv';

        $matrices = Branch::whereNull('parent_branch_id')
            ->with(['accountManager:id,name', 'children.accountManager:id,name'])
            ->orderBy('name', 'asc')
            ->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Nombre', 'RFC', 'Direccion', 'Razon Social', 'Vendedor Asignado', 'Tipo (Matriz/Sucursal)'];

        $callback = function() use($matrices, $columns) {
            $file = fopen('php://output', 'w');
            
            // Agregar BOM para que Excel lea correctamente los caracteres especiales (UTF-8, Acentos, etc.)
            fputs($file, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
            
            // Escribir cabeceras
            fputcsv($file, $columns);

            foreach ($matrices as $matriz) {
                // Escribir fila de la matriz
                fputcsv($file, [
                    $matriz->id,
                    $matriz->name,
                    $matriz->rfc,
                    $matriz->address,
                    $matriz->business_name,
                    $matriz->accountManager->name ?? 'No asignado',
                    'Matriz'
                ]);

                foreach ($matriz->children as $hija) {
                    // Escribir fila de la hija
                    fputcsv($file, [
                        $hija->id,
                        '   -> ' . $hija->name, // Se indenta el nombre para fácil lectura
                        $hija->rfc,
                        $hija->address,
                        $hija->business_name,
                        $hija->accountManager->name ?? 'No asignado',
                        'Sucursal'
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        // Pasamos los datos necesarios para los selects del formulario
        return Inertia::render('Branch/Create', [
            // Se excluye al usuario Soporte DTW (id: 1) de la selección de vendedores
            'users' => User::where('is_active', true)->where('id', '!=', 1)->role(['Vendedor', 'Super Administrador'])->select('id', 'name')->get(),
            'branches' => Branch::select('id', 'name')->whereNull('parent_branch_id')->get(), // Solo matrices
            'catalog_products' => Product::where('is_sellable', true)->whereNull('archived_at')->select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:branches',
            'rfc' => 'required_without:parent_branch_id|nullable|string|min:10|max:20',
            
            // Nuevas columnas validadas
            'group_name' => 'nullable|string|max:255',
            'business_name' => 'required_without:parent_branch_id|nullable|string|min:3|max:255',
            'bank_account' => 'nullable|string|max:255',
            'client_number' => 'nullable|string|max:255',

            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'status' => 'required|in:Prospecto,Cliente',
            'parent_branch_id' => 'nullable|exists:branches,id',
            'account_manager_id' => 'nullable|exists:users,id',
            'meet_way' => 'nullable|string|max:255',

            // Método de pago y uso de CFDI (obligatorios solo para sucursales matriz nuevas)
            'payment_method' => 'required_without:parent_branch_id|nullable|string|in:PPD,PUE',
            'payment_submethod' => 'nullable|string|in:99 X DEFINIR,TRANSFERENCIA,CHEQUES',
            'cfdi_use' => 'required_without:parent_branch_id|nullable|string|in:GASTOS EN GENERAL,ADQUISICION DE MERCANCIAS',

            // Documento CSF (obligatorio solo para sucursales matriz nuevas)
            'csf' => 'required_without:parent_branch_id|nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',

            // Validación para los contactos
            'contacts' => 'present|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.area' => 'nullable|string|in:Comercial,Finanzas,Pagos',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.phone' => 'required|string|max:20',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.birth_month' => 'nullable|integer|between:1,12',
            'contacts.*.birth_day' => 'nullable|integer|between:1,31',
            'contacts.*.prefix' => 'nullable|string|max:50',

            // Validación para productos asignados
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.currency' => 'nullable|string|in:MXN,USD',

            // Validación para productos sugeridos
            'suggested_products' => 'nullable|array',
            'suggested_products.*' => 'exists:products,id',
        ]);

        // Reglas adicionales para los contactos (al menos un medio de contacto y sin duplicados)
        $this->validateContactRules($validated['contacts'] ?? []);

        DB::transaction(function () use ($validated, $request) {
            // 1. Crear la sucursal (Branch)
            $branch = Branch::create([
                'name' => $validated['name'],
                'rfc' => $validated['rfc'],
                
                // Nuevas columnas mapeadas para inserción
                'group_name' => !empty($validated['group_name']) ? trim($validated['group_name']) : null,
                'business_name' => $validated['business_name'] ?? null,
                'bank_account' => $validated['bank_account'] ?? null,
                'client_number' => $validated['client_number'] ?? null,

                'address' => $validated['address'],
                'post_code' => $validated['post_code'],
                'status' => $validated['status'],
                'parent_branch_id' => $validated['parent_branch_id'],
                'account_manager_id' => $validated['account_manager_id'],
                'meet_way' => $validated['meet_way'],

                // Datos fiscales (CFDI)
                'payment_method' => $validated['payment_method'] ?? null,
                'payment_submethod' => $validated['payment_submethod'] ?? null,
                'cfdi_use' => $validated['cfdi_use'] ?? null,

                'password' => bcrypt('e3d'),
            ]);

            // 2. Crear los contactos y sus detalles
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $index => $contactData) {
                    
                    $birthdate = null;
                    if (!empty($contactData['birth_month']) && !empty($contactData['birth_day'])) {
                        if (checkdate($contactData['birth_month'], $contactData['birth_day'], 2000)) {
                            $birthdate = "2000-{$contactData['birth_month']}-{$contactData['birth_day']}";
                        }
                    }

                    $contact = $branch->contacts()->create([
                        'prefix' => $contactData['prefix'] ?? 'Ing.', // Toma el valor o por defecto Ing.
                        'area' => $contactData['area'] ?? null,
                        'name' => $contactData['name'],
                        'charge' => $contactData['charge'],
                        'birthdate' => $birthdate,
                        'is_primary' => $index === 0,
                    ]);

                    // Teléfono opcional: basta con tener al menos un medio de contacto
                    if (!empty($contactData['phone'])) {
                        $contact->details()->create([
                            'type' => 'Teléfono',
                            'value' => $contactData['phone'],
                            'is_primary' => true,
                        ]);
                    }

                    // Correo opcional: basta con tener al menos un medio de contacto
                    if (!empty($contactData['email'])) {
                        $contact->details()->create([
                            'type' => 'Correo',
                            'value' => $contactData['email'],
                            'is_primary' => true,
                        ]);
                    }
                }
            }

            // 3. Relacionar productos y guardar precios especiales (MODIFICADO)
            if (!empty($validated['products'])) {
                // Obtenemos la sucursal matriz / líder de grupo desde donde se leerán los productos.
                // Si es una nueva sucursal hija, necesitamos cargar la relación 'parent'.
                $branch->load('parent'); 
                $productTargetBranch = $this->getProductTargetBranch($branch);

                // Extraemos solo los IDs de los productos.
                $productIds = collect($validated['products'])->pluck('product_id')->toArray();

                // El cliente conserva sus propios productos (por si se separa de la matriz).
                $branch->products()->sync($productIds);

                // Guardamos los precios especiales en el propio cliente.
                foreach ($validated['products'] as $productData) {
                    if (isset($productData['price']) && $productData['price'] !== null) {
                        $branch->priceHistory()->create([
                            'product_id' => $productData['product_id'],
                            'price' => $productData['price'],
                            'valid_from' => now(),
                            'currency' => $productData['currency'],
                        ]);
                    }
                }

                // Si pertenece a una matriz o grupo, se COMBINAN (unión, sin duplicar) sus
                // productos con los del destino, sin reemplazar los que ya tenía.
                if ($productTargetBranch->id !== $branch->id) {
                    $this->branchGroups->mergeProductsInto($branch, $productTargetBranch);
                }
            }

            // 4. Guardar productos sugeridos (se mantiene por sucursal individual)
            if (!empty($validated['suggested_products'])) {
                $branch->suggestedProducts()->sync($validated['suggested_products']);
            }

            // 5. Guardar el documento CSF (Constancia de Situación Fiscal)
            if ($request->hasFile('csf')) {
                $branch->addMediaFromRequest('csf')->toMediaCollection('csf');
            }

            // 6. Consolida los productos del grupo al que pertenece la nueva sucursal.
            $this->branchGroups->rebalance($branch->group_name);

        });

        return to_route('branches.index');
    }

    public function show(Branch $branch)
    {
        // Cargamos las relaciones directas de la sucursal
        $branch->load([
            'children', 
            'accountManager:id,name', 
            'parent:id,name,group_name,business_name,rfc', 
            'contacts.details',
            'media',
            'suggestedProducts.media',
        ]);

        // MODIFICADO: Obtenemos la sucursal desde donde se leerán los productos (la matriz o ella misma)
        $productSourceBranch = $this->getProductTargetBranch($branch);

        // Cargamos los productos desde la sucursal matriz
        $products = $productSourceBranch->products()->with([
            'parent:id,name', // <--- NUEVO: Cargar producto padre para identificar si es variante
            'storages',
            'media',
            // Los precios por volumen también se consultan con el ID de la matriz
            'volumePrices' => function ($query) use ($productSourceBranch) {
                $query->where('branch_id', $productSourceBranch->id)
                      ->with('user:id,name')
                      ->orderBy('min_quantity');
            },
            // El historial de precios también se consulta con el ID de la matriz
            'priceHistory' => function ($query) use ($productSourceBranch) {
                $query->where('branch_id', $productSourceBranch->id)
                      ->with('user:id,name')
                      ->orderBy('valid_from', 'desc');
            }
        ])->get();

        // Asignamos manualmente la relación de productos a la sucursal que se está mostrando
        $branch->setRelation('products', $products);

        $allBranches = Branch::select('id', 'name')->get();

        // Mandamos llamar a nuestro nuevo método refactorizado
        $consumptionData = $this->calculateSalesAnalytics($branch);

        // --- Datos de grupo (la membresía se gestiona a nivel de cliente raíz) ---
        $groupBranch = $branch->parent_branch_id ? $branch->parent : $branch;

        $groups = Branch::whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name')
            ->values();

        $groupMembers = $groupBranch->group_name
            ? Branch::whereNull('parent_branch_id')
                ->where('group_name', $groupBranch->group_name)
                ->with(['accountManager:id,name', 'children.accountManager:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'rfc', 'business_name', 'group_name', 'parent_branch_id', 'account_manager_id'])
            : collect();

        return Inertia::render('Branch/Show', [
            'branch' => $branch,
            'branches' => $allBranches,
            'catalog_products' => Product::where('is_sellable', true)->whereNull('archived_at')->select('id', 'name')->get(),
            'consumptionData' => $consumptionData, 
            'groups' => $groups,
            'groupName' => $groupBranch->group_name,
            'groupBranchId' => $groupBranch->id,
            'groupBranchName' => $groupBranch->name,
            'groupBranchIsChild' => (bool) $branch->parent_branch_id,
            'groupMembers' => $groupMembers,
        ]);
    }

    /**
     * NUEVO MÉTODO: Endpoint para obtener analíticas vía petición (Axios)
     */
    public function getSalesAnalytics(Branch $branch)
    {
        // Retornamos la data en formato JSON para peticiones frontend
        return response()->json($this->calculateSalesAnalytics($branch));
    }

    /**
     * NUEVO MÉTODO PRIVADO: Centraliza la lógica de cálculos de consumo
     * para usarla tanto en el show como en el endpoint JSON.
     */
    private function calculateSalesAnalytics(Branch $branch)
    {
        $oneYearAgo = now()->subYear();
        
        $annualConsumption = $branch->sales()->where('created_at', '>=', $oneYearAgo)->where('type', 'venta')->where('status', '!=', 'Cancelada')->sum('total_amount');
        $monthlyConsumption = $annualConsumption / 12;

        // Cargar productos de ventas del último año
        $salesProductsLastYear = \App\Models\SaleProduct::with('product.media')
            ->whereHas('sale', function($query) use ($branch, $oneYearAgo) {
                $query->where('branch_id', $branch->id)
                      ->where('created_at', '>=', $oneYearAgo)
                      ->where('type', 'venta')
                      ->where('status', '!=', 'Cancelada');
            })
            ->get();
            
        $productConsumption = $salesProductsLastYear->groupBy('product_id')->map(function ($items) {
            $first = $items->first();
            $annualQuantity = $items->sum('quantity');
            $annualTotal = $items->sum(function($item) { return $item->quantity * $item->price; });
            
            $imageUrl = null;
            if ($first->product && $first->product->media && $first->product->media->isNotEmpty()) {
                $imageUrl = $first->product->media->first()->original_url;
            }

            return [
                'product_id' => $first->product_id,
                'name' => $first->product->name ?? 'Producto temporal',
                'code' => $first->product->code ?? 'N/A',
                'image_url' => $imageUrl,
                'annual_quantity' => $annualQuantity,
                'monthly_quantity' => round($annualQuantity / 12, 2),
                'annual_total' => $annualTotal,
                'monthly_total' => $annualTotal / 12,
            ];
        })->values()->sortByDesc('annual_quantity')->toArray();

        // Histórico de Ventas (Con desglose de productos)
        $allSales = $branch->sales()
            ->with('saleProducts.product') 
            ->where('type', 'venta')
            ->where('status', '!=', 'Cancelada')
            ->get();

        $salesByYearMonth = [];
        foreach ($allSales as $sale) {
            $year = \Carbon\Carbon::parse($sale->created_at)->format('Y');
            $month = (int)\Carbon\Carbon::parse($sale->created_at)->format('m');

            if (!isset($salesByYearMonth[$year])) {
                $salesByYearMonth[$year] = array_fill(1, 12, ['total' => 0, 'products' => []]);
            }

            $salesByYearMonth[$year][$month]['total'] += (float)$sale->total_amount;

            if ($sale->saleProducts) {
                foreach ($sale->saleProducts as $sp) {
                    $prodId = $sp->product_id;
                    $prodName = $sp->product->name ?? 'Producto Eliminado';
                    
                    if (!isset($salesByYearMonth[$year][$month]['products'][$prodId])) {
                        $salesByYearMonth[$year][$month]['products'][$prodId] = [
                            'name' => $prodName,
                            'quantity' => 0,
                            'total' => 0,
                        ];
                    }
                    
                    $salesByYearMonth[$year][$month]['products'][$prodId]['quantity'] += $sp->quantity;
                    $salesByYearMonth[$year][$month]['products'][$prodId]['total'] += ($sp->quantity * $sp->price);
                }
            }
        }

        foreach ($salesByYearMonth as $year => &$months) {
            foreach ($months as $month => &$data) {
                usort($data['products'], function($a, $b) {
                    return $b['total'] <=> $a['total']; 
                });
                $data['products'] = array_values($data['products']);
            }
        }

        return [
            'annual_consumption' => $annualConsumption,
            'monthly_consumption' => $monthlyConsumption,
            'product_breakdown' => $productConsumption,
            'sales_by_year_month' => $salesByYearMonth,
        ];
    }

    public function edit(Branch $branch)
    {
        // Cargar las relaciones que no dependen de la matriz
        $branch->load(['contacts.details', 'suggestedProducts', 'parent', 'media']);

        if ($branch->parent_branch_id) {
            $branch->business_name = $branch->business_name ?? $branch->parent?->business_name;
            $branch->rfc = $branch->rfc ?? $branch->parent?->rfc;
        }

        $suggestedProductIds = $branch->suggestedProducts()->pluck('products.id')->toArray();

        // MODIFICADO: Obtenemos los productos (con imagen y stock) desde la sucursal matriz
        $productSourceBranch = $this->getProductTargetBranch($branch);
        $products = $this->formatBranchProducts($productSourceBranch);

        // Formatear los datos de contactos para que coincidan con la estructura del formulario
        $formattedContacts = $branch->contacts->map(function ($contact) {
            $birth_month = null;
            $birth_day = null;

            if ($contact->birthdate) {
                $date = Carbon::parse($contact->birthdate);
                $birth_month = $date->month;
                $birth_day = $date->day;
            }
            
            return [
                'id' => $contact->id,
                'prefix' => $contact->prefix,
                'name' => $contact->name,
                'charge' => $contact->charge,
                'area' => $contact->area,
                'phone' => $contact->details->firstWhere('type', 'Teléfono')->value ?? null,
                'email' => $contact->details->firstWhere('type', 'Correo')->value ?? null,
                'birth_month' => $birth_month,
                'birth_day' => $birth_day,
            ];
        });

        // Los productos ya vienen formateados con precio especial, imagen, stock y ubicación
        $formattedProducts = $products;

        return Inertia::render('Branch/Edit', [
            'branch' => $branch,
            'formattedContacts' => $formattedContacts,
            'formattedProducts' => $formattedProducts,
            // Se excluye al usuario Soporte DTW (id: 1) de la selección de vendedores
            'users' => User::where('is_active', true)->where('id', '!=', 1)->role(['Vendedor', 'Super Administrador'])->select('id', 'name')->get(),
            'branches' => Branch::where('id', '!=', $branch->id)->whereNull('parent_branch_id')->select('id', 'name', 'business_name', 'rfc')->get(),
            'catalog_products' => Product::where('is_sellable', true)->whereNull('archived_at')->select('id', 'name')->get(),
            'suggestedProductIds' => $suggestedProductIds,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')->ignore($branch->id),
            ],
            'rfc' => 'required_without:parent_branch_id|nullable|string|min:10|max:20',
            
            // Nuevas columnas también agregadas al update por seguridad y consistencia
            'group_name' => 'nullable|string|max:255',
            'business_name' => 'required_without:parent_branch_id|nullable|string|min:3|max:255',
            'bank_account' => 'nullable|string|max:255',
            'client_number' => 'nullable|string|max:255',

            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'status' => 'required|in:Prospecto,Cliente',
            'parent_branch_id' => 'nullable|exists:branches,id',
            'account_manager_id' => 'nullable|exists:users,id',
            'meet_way' => 'nullable|string|max:255',

            // Método de pago y uso de CFDI (opcionales en edición)
            'payment_method' => 'nullable|string|in:PPD,PUE',
            'payment_submethod' => 'nullable|string|in:99 X DEFINIR,TRANSFERENCIA,CHEQUES',
            'cfdi_use' => 'nullable|string|in:GASTOS EN GENERAL,ADQUISICION DE MERCANCIAS',

            // Documento CSF (opcional: reemplaza el existente si se sube)
            'csf' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',

            // Validación para contactos
            'contacts' => 'present|array',
            'contacts.*.id' => 'nullable|exists:contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.area' => 'nullable|string|in:Comercial,Finanzas,Pagos',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.phone' => 'required|string|max:20',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.birth_month' => 'nullable|integer|between:1,12',
            'contacts.*.birth_day' => 'nullable|integer|between:1,31',
            'contacts.*.prefix' => 'nullable|string|max:50',

            // Validación para productos asignados
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.currency' => 'nullable|string|in:MXN,USD',

            // Validación para productos sugeridos
            'suggested_products' => 'nullable|array',
            'suggested_products.*' => 'exists:products,id',
        ]);

        // Reglas adicionales para los contactos (al menos un medio de contacto y sin duplicados)
        $this->validateContactRules($validated['contacts'] ?? []);

        // Matriz/grupo del que dependían los productos ANTES de la edición, para detectar
        // si el cliente cambia de destino y, en ese caso, combinar en lugar de reemplazar.
        $branch->loadMissing('parent');
        $oldProductTargetBranch = $this->getProductTargetBranch($branch);

        DB::transaction(function () use ($validated, $branch, $request, $oldProductTargetBranch) {
            // Guardamos el grupo anterior para reconsolidarlo si la sucursal cambia de grupo.
            $oldGroup = $branch->group_name;

            // Normalizamos el nombre del grupo (se manejan cadenas vacías como null).
            if (array_key_exists('group_name', $validated)) {
                $validated['group_name'] = !empty($validated['group_name']) ? trim($validated['group_name']) : null;
            }

            // 1. Actualizar datos de la sucursal (excluimos relaciones y el archivo CSF)
            $branch->update(collect($validated)->except(['contacts', 'products', 'suggested_products', 'csf'])->all());

            // 2. Sincronizar contactos
            $contactIdsToKeep = [];
            foreach ($validated['contacts'] as $index => $contactData) {
                $birthdate = null;
                if (!empty($contactData['birth_month']) && !empty($contactData['birth_day'])) {
                    if (checkdate($contactData['birth_month'], $contactData['birth_day'], 2000)) {
                        $birthdate = "2000-{$contactData['birth_month']}-{$contactData['birth_day']}";
                    }
                }

                $contact = $branch->contacts()->updateOrCreate(
                    ['id' => $contactData['id'] ?? null],
                    [
                        'prefix' => $contactData['prefix'] ?? 'Ing.', // <--- NUEVO
                        'area' => $contactData['area'] ?? null,
                        'name' => $contactData['name'],
                        'charge' => $contactData['charge'],
                        'birthdate' => $birthdate,
                        'is_primary' => $index === 0,
                    ]
                );

                // Teléfono opcional (basta con tener al menos un medio de contacto)
                if (!empty($contactData['phone'])) {
                    $contact->details()->updateOrCreate(['type' => 'Teléfono'], ['value' => $contactData['phone'], 'is_primary' => true]);
                } else {
                    $contact->details()->where('type', 'Teléfono')->delete();
                }

                // Correo opcional (basta con tener al menos un medio de contacto)
                if (!empty($contactData['email'])) {
                    $contact->details()->updateOrCreate(['type' => 'Correo'], ['value' => $contactData['email'], 'is_primary' => true]);
                } else {
                    $contact->details()->where('type', 'Correo')->delete();
                }
                $contactIdsToKeep[] = $contact->id;
            }
            $branch->contacts()->whereNotIn('id', $contactIdsToKeep)->delete();
            
            // MODIFICADO: Determinar la sucursal matriz / líder de grupo para la gestión de productos
            $branch->load('parent');
            $productTargetBranch = $this->getProductTargetBranch($branch);

            // 3. Sincronizar productos y precios especiales.
            $productDataFromRequest = collect($validated['products'] ?? [])->keyBy('product_id');

            // Si el cliente cambió de matriz o de grupo, sus productos se COMBINAN con
            // los del nuevo destino (unión, sin duplicar) y conserva los suyos propios.
            $targetChanged = $oldProductTargetBranch && $oldProductTargetBranch->id !== $productTargetBranch->id;

            if ($targetChanged) {
                // El cliente conserva su propio catálogo sincronizado con el formulario.
                $this->applyProductsToBranch($branch, $productDataFromRequest, true);

                // Unión de productos con el nuevo destino (no se elimina nada del destino).
                if ($productTargetBranch->id !== $branch->id) {
                    $this->applyProductsToBranch($productTargetBranch, $productDataFromRequest, false);
                }
            } else {
                // Mismo destino: se permite agregar, editar y quitar productos como antes.
                $this->applyProductsToBranch($productTargetBranch, $productDataFromRequest, true);
            }

            // 4. Sincronizar productos sugeridos (se mantiene individual)
            $branch->suggestedProducts()->sync($validated['suggested_products'] ?? []);

            // 5. Reemplazar el documento CSF si se subió uno nuevo
            if ($request->hasFile('csf')) {
                $branch->clearMediaCollection('csf');
                $branch->addMediaFromRequest('csf')->toMediaCollection('csf');
            }

            // 6. Reconsolidar los grupos afectados por el cambio de grupo
            if ($branch->group_name) {
                $this->branchGroups->rebalance($branch->group_name);
            }

            if ($oldGroup && $oldGroup !== $branch->group_name) {
                $this->branchGroups->rebalance($oldGroup);
            }
        });

        if ($request->has('redirect_to')) {
            $redirectRoute = $request->query('redirect_to');

            // Al volver a la creación de la OV, conservamos la cotización que se estaba convirtiendo
            $redirectParams = [];
            $redirectQuoteId = $request->input('redirect_quote_id');
            if ($redirectQuoteId) {
                $redirectParams['quote_id'] = $redirectQuoteId;
            }

            return to_route($redirectRoute, $redirectParams);
        }

        return to_route('branches.show', $branch->id);
    }

    
    /**
     * Reglas adicionales para los contactos:
     * - Cada contacto debe tener al menos un medio de contacto (teléfono o correo).
     * - No se permite repetir el mismo teléfono ni el mismo correo entre contactos.
     */
    private function validateContactRules(array $contacts): void
    {
        $errors = [];
        $phones = [];
        $emails = [];

        foreach ($contacts as $index => $contact) {
            $phone = trim((string) ($contact['phone'] ?? ''));
            $email = trim((string) ($contact['email'] ?? ''));

            if ($phone === '' && $email === '') {
                $errors["contacts.$index.phone"] = 'Cada contacto debe tener al menos un medio de contacto (teléfono o correo).';
            }

            if ($phone !== '') {
                if (isset($phones[$phone])) {
                    $errors["contacts.$index.phone"] = 'Este teléfono ya está registrado en otro contacto.';
                }
                $phones[$phone] = true;
            }

            if ($email !== '') {
                $key = strtolower($email);
                if (isset($emails[$key])) {
                    $errors["contacts.$index.email"] = 'Este correo ya está registrado en otro contacto.';
                }
                $emails[$key] = true;
            }
        }

        // El contacto Comercial y el de Pagos son obligatorios; el de Finanzas es opcional.
        $areas = collect($contacts)->pluck('area')->filter()->all();

        if (!in_array('Comercial', $areas, true)) {
            $errors['contacts.comercial'] = 'Debes registrar al menos un contacto del área Comercial.';
        }

        if (!in_array('Pagos', $areas, true)) {
            $errors['contacts.pagos'] = 'Debes registrar al menos un contacto del área Pagos.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function destroy(Request $request, Branch $branch)
    {
        try {
            // Usamos una transacción para garantizar la integridad de los datos.
            // Si algo falla, se revierte toda la operación.
            DB::transaction(function () use ($branch) {
                
                // 1. Buscamos todas las sucursales "hijas" que apuntan a esta matriz.
                // 2. Actualizamos su `parent_branch_id` a null para "desvincularlas".
                Branch::where('parent_branch_id', $branch->id)
                    ->update(['parent_branch_id' => null]);

                // 3. Ahora que no hay hijos apuntando a esta sucursal, podemos eliminarla de forma segura.
                $branch->delete();
            });

        } catch (\Exception $e) {
            Log::error('Error al eliminar cliente: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Ocurrió un error al eliminar el cliente.'], 500);
            }

            return back()->withErrors(['error' => 'Ocurrió un error al eliminar el cliente.']);
        }

        // Respuesta JSON para peticiones AJAX (vista Show) y redirección para el resto.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cliente eliminado con éxito.']);
        }

        return to_route('branches.index');
    }

    /**
     * Sube (o reemplaza) la CSF del cliente desde la vista Show.
     */
    public function uploadCsf(Request $request, Branch $branch)
    {
        $request->validate([
            'csf' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $branch->clearMediaCollection('csf');
        $branch->addMediaFromRequest('csf')->toMediaCollection('csf');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'CSF actualizada correctamente.']);
        }

        return back()->with('success', 'CSF actualizada correctamente.');
    }

    /**
     * Actualiza únicamente los datos fiscales propios del cliente (razón social y RFC).
     * Si se envían vacíos, el cliente heredará los de su sucursal matriz.
     */
    public function updateFiscalData(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'business_name' => 'nullable|string|min:3|max:255',
            'rfc' => 'nullable|string|min:10|max:20',
        ]);

        $branch->update([
            'business_name' => !empty($validated['business_name']) ? trim($validated['business_name']) : null,
            'rfc' => !empty($validated['rfc']) ? trim($validated['rfc']) : null,
        ]);

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['message' => 'Datos fiscales actualizados correctamente.']);
    }

    public function removeProduct(Branch $branch, Product $product)
    {
        // MODIFICADO: Obtenemos la sucursal matriz para eliminar la relación del producto.
        $productTargetBranch = $this->getProductTargetBranch($branch);

        try {
            DB::transaction(function () use ($productTargetBranch, $product) {
                // 1. Eliminar el historial de precios para esta relación (de la matriz)
                DB::table('branch_price_history')
                    ->where('branch_id', $productTargetBranch->id)
                    ->where('product_id', $product->id)
                    ->delete();

                // 2. Eliminar los precios por volumen para esta relación
                BranchVolumePriceService::deleteFor($productTargetBranch, $product->id);

                // 3. Eliminar la relación en la tabla pivote (de la matriz)
                $productTargetBranch->products()->detach($product->id);
            });

            return response()->json(['message' => 'Producto removido exitosamente.']);

        } catch (\Exception $e) {
            Log::error('Error al remover producto de cliente: ' . $e->getMessage());
            return response()->json(['message' => 'Ocurrió un error en el servidor al intentar remover el producto.'], 500);
        }
    }

    /**
     * Busca clientes/matrices candidatos para agregarlos como sucursales hijas de $branch.
     * Solo se ofrecen clientes que NO pertenezcan ya a otra matriz.
     */
    public function searchChildCandidates(Request $request, Branch $branch)
    {
        $query = trim((string) $request->input('query', ''));

        // Excluimos la propia matriz y a sus sucursales actuales.
        $excludedIds = $branch->children()->pluck('id')->push($branch->id)->all();

        $candidates = Branch::whereNull('parent_branch_id')
            ->whereNotIn('id', $excludedIds)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('name', 'like', "%{$query}%")
                      ->orWhere('rfc', 'like', "%{$query}%")
                      ->orWhere('business_name', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            })
            ->withCount('children')
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'rfc', 'business_name', 'parent_branch_id']);

        return response()->json(['items' => $candidates]);
    }

    /**
     * Agrega uno o varios clientes como sucursales hijas de una matriz.
     *
     * Reglas:
     * - El destino debe ser una matriz (sin padre).
     * - No se pueden agregar clientes que ya pertenezcan a otra matriz.
     * - Si el cliente agregado es una matriz con sucursales, todas sus sucursales
     *   pasan también a formar parte de la nueva matriz.
     * - Los productos se combinan (unión, sin duplicar) y cada cliente conserva los suyos.
     */
    public function addChildren(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'child_ids' => 'required|array|min:1',
            'child_ids.*' => 'exists:branches,id',
        ]);

        if ($branch->parent_branch_id) {
            return response()->json(['message' => 'Solo una sucursal matriz puede recibir sucursales hijas.'], 422);
        }

        $moved = 0;

        DB::transaction(function () use ($validated, $branch, &$moved) {
            foreach ($validated['child_ids'] as $childId) {
                if ((int) $childId === (int) $branch->id) {
                    continue;
                }

                $child = Branch::with('children')->find($childId);

                // No existe o ya pertenece a otra matriz.
                if (!$child || $child->parent_branch_id) {
                    continue;
                }

                // El cliente seleccionado y todas sus sucursales hijas pasan a la nueva matriz.
                $idsToMove = collect([$child->id])->merge($child->children->pluck('id'));

                foreach ($idsToMove as $id) {
                    $moving = Branch::find($id);
                    if (!$moving || (int) $moving->id === (int) $branch->id) {
                        continue;
                    }

                    $moving->update(['parent_branch_id' => $branch->id]);
                    $moved++;

                    // Combinar (unión, sin duplicar) los productos del cliente con los del destino.
                    $target = $this->getProductTargetBranch($moving);
                    $this->branchGroups->mergeProductsInto($moving, $target);
                }
            }
        });

        if ($moved === 0) {
            return response()->json(['message' => 'No se agregó ninguna sucursal. Verifica que no pertenezcan ya a otra matriz.'], 422);
        }

        return response()->json(['message' => 'Sucursal(es) agregada(s) correctamente.', 'moved' => $moved]);
    }

    public function massiveDelete(Request $request)
    {
        // 1. Validar que los IDs enviados son un array y que no está vacío.
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:branches,id', // Opcional: valida que cada id exista en la tabla.
        ]);

        $idsToDelete = $request->input('ids');

        try {
            DB::transaction(function () use ($idsToDelete) {
                
                // 2. Desvincular todas las sucursales hijas que apunten a CUALQUIERA
                //    de las sucursales que vamos a eliminar.
                //    Esto se hace en una sola consulta.
                Branch::whereIn('parent_branch_id', $idsToDelete)
                    ->update(['parent_branch_id' => null]);

                // 3. Eliminar todas las sucursales seleccionadas.
                //    Esto también se hace en una sola consulta.
                Branch::whereIn('id', $idsToDelete)->delete();
            });
        } catch (\Exception $e) {
            // En caso de un error inesperado, retornamos un error 500.
            return response()->json(['message' => 'Ocurrió un error al eliminar los clientes.'], 500);
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda mostrando solo matrices y filtrando si hay coincidencias en las hijas
        $branches = Branch::whereNull('parent_branch_id')
            ->with(['accountManager:id,name', 'children.accountManager:id,name'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orWhere('business_name', 'like', "%{$query}%") // Búsqueda por Razón Social
                ->orWhere('group_name', 'like', "%{$query}%") // Búsqueda por Grupo
                ->orWhere('status', 'like', "%{$query}%")
                // Busca dentro de su propio account manager
                ->orWhereHas('accountManager', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                })
                // Busca si alguna sucursal hija coincide
                ->orWhereHas('children', function ($childQuery) use ($query) {
                    $childQuery->where('name', 'like', "%{$query}%")
                               ->orWhere('business_name', 'like', "%{$query}%") // Búsqueda en hijas
                               ->orWhere('status', 'like', "%{$query}%")
                               ->orWhereHas('accountManager', function($uq) use ($query){
                                   $uq->where('name', 'like', "%{$query}%");
                               });
                });
            })
            ->get();

        return response()->json(['items' => $branches], 200);
    }

    /*
      * Asigna productos al cliente. Lo uso en el show de clientes.
    */ 
    public function addProducts(Request $request, Branch $branch)
    {
        $validated = $request->validate(array_merge([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.currency' => 'nullable|string',
        ], BranchVolumePriceService::rules('products.*.volume_prices')));

        // MODIFICADO: Obtenemos la sucursal matriz para asignarle los productos.
        $productTargetBranch = $this->getProductTargetBranch($branch);

        // Validamos los rangos de volumen antes de abrir la transacción.
        foreach ($validated['products'] as $productData) {
            if (array_key_exists('volume_prices', $productData)) {
                BranchVolumePriceService::validateRanges($productData['volume_prices'] ?? []);
            }
        }

        DB::transaction(function () use ($validated, $productTargetBranch) {
            $now = now();
            $priceHistoryData = [];
            $productIdsToSync = [];

            foreach ($validated['products'] as $productData) {
                $productIdsToSync[] = $productData['product_id'];

                if (isset($productData['price']) && !is_null($productData['price'])) {
                    // Invalidar precios anteriores...
                    DB::table('branch_price_history')
                        ->where('branch_id', $productTargetBranch->id)
                        ->where('product_id', $productData['product_id'])
                        ->whereNull('valid_to')
                        ->update(['valid_to' => $now]);

                    // Agregar el nuevo precio especial a la matriz (modificado)
                    $priceHistoryData[] = [
                        'branch_id' => $productTargetBranch->id,
                        'product_id' => $productData['product_id'],
                        // Se asegura de guardar el ID de usuario autenticado
                        'user_id' => auth()->id(), 
                        'price' => $productData['price'],
                        'valid_from' => $now,
                        'valid_to' => null,
                        // Usamos Null Coalescing por si algún día falla la petición
                        'currency' => $productData['currency'] ?? 'MXN', 
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Sincronizamos los rangos de precio por volumen cuando vienen en la petición.
                if (array_key_exists('volume_prices', $productData)) {
                    $this->volumePrices->sync(
                        $productTargetBranch,
                        $productData['product_id'],
                        $productData['volume_prices'] ?? [],
                        auth()->id()
                    );
                }
            }

            // Usamos syncWithoutDetaching para añadir los nuevos productos a la matriz
            $productTargetBranch->products()->syncWithoutDetaching($productIdsToSync);

            if (!empty($priceHistoryData)) {
                DB::table('branch_price_history')->insert($priceHistoryData);
            }
        });

        return back()->with('success', 'Productos agregados correctamente.');
    }

    public function fetchBranchProducts(Branch $branch)
    {
        // Sucursal destino (matriz o líder del grupo) donde se consolidan los productos.
        $productSourceBranch = $this->getProductTargetBranch($branch);

        // Sucursales cuyos productos forman parte del catálogo visible: el destino,
        // todos los miembros del grupo (si pertenece a uno) y las sucursales hijas.
        $branchIds = collect([$productSourceBranch->id]);

        $groupName = $this->branchGroups->resolveGroupName($branch);

        if ($groupName) {
            $groupBranchIds = Branch::where('group_name', $groupName)->pluck('id');

            $branchIds = $branchIds
                ->merge($groupBranchIds)
                ->merge(Branch::whereIn('parent_branch_id', $groupBranchIds)->pluck('id'));
        }

        // Incluimos también las sucursales hijas del destino (por si conservan productos propios).
        $branchIds = $branchIds
            ->merge(Branch::where('parent_branch_id', $productSourceBranch->id)->pluck('id'))
            ->unique()
            ->values();

        // Unión de los IDs de producto de todas esas sucursales, sin duplicar.
        $productIds = DB::table('branch_product')
            ->whereIn('branch_id', $branchIds)
            ->distinct()
            ->pluck('product_id');

        $products = Product::whereIn('id', $productIds)
            ->whereNull('archived_at')
            ->with('media')
            ->get();

        // Historial de precios: preferimos el del destino (matriz/líder) y, si no tiene
        // registros para el producto, mostramos el del miembro que sí los tenga.
        $allHistory = BranchPriceHistory::whereIn('product_id', $productIds)
            ->whereIn('branch_id', $branchIds)
            ->with('user:id,name')
            ->orderByDesc('valid_from')
            ->get()
            ->groupBy('product_id');

        // Precios por volumen: mismo criterio de lectura que el historial.
        $allVolumePrices = BranchVolumePrice::whereIn('product_id', $productIds)
            ->whereIn('branch_id', $branchIds)
            ->with('user:id,name')
            ->orderBy('min_quantity')
            ->get()
            ->groupBy('product_id');

        $products->each(function ($product) use ($allHistory, $allVolumePrices, $productSourceBranch) {
            $rows = $allHistory->get($product->id, collect());
            $leaderRows = $rows->where('branch_id', $productSourceBranch->id)->values();

            if ($leaderRows->isNotEmpty()) {
                $history = $leaderRows;
            } else {
                $firstBranchId = optional($rows->first())->branch_id;
                $history = $firstBranchId
                    ? $rows->where('branch_id', $firstBranchId)->values()
                    : collect();
            }

            $product->setRelation('priceHistory', $history);

            $volumeRows = $allVolumePrices->get($product->id, collect());
            $leaderVolumeRows = $volumeRows->where('branch_id', $productSourceBranch->id)->values();

            if ($leaderVolumeRows->isNotEmpty()) {
                $volumePrices = $leaderVolumeRows;
            } else {
                $firstVolumeBranchId = optional($volumeRows->first())->branch_id;
                $volumePrices = $firstVolumeBranchId
                    ? $volumeRows->where('branch_id', $firstVolumeBranchId)->values()
                    : collect();
            }

            $product->setRelation('volumePrices', $volumePrices);
        });

        return response()->json($products);
    }

    /**
     * Devuelve los datos de una sucursal matriz (razón social, RFC, grupo)
     * junto con sus productos, para autorrellenar el formulario de sucursales hijas.
     */
    public function getMatrixData(Branch $branch)
    {
        $sourceBranch = $branch->parent_branch_id ? $branch->parent : $branch;

        return response()->json([
            'branch' => [
                'id' => $sourceBranch->id,
                'name' => $sourceBranch->name,
                'business_name' => $sourceBranch->business_name,
                'rfc' => $sourceBranch->rfc,
                'group_name' => $sourceBranch->group_name,
            ],
            // Los productos se leen desde la matriz o, si pertenece a un grupo,
            // desde el líder del grupo (donde se consolidan).
            'products' => $this->formatBranchProducts($this->getProductTargetBranch($sourceBranch)),
        ]);
    }

    // --- MÉTODOS NUEVOS PARA CREACIÓN RÁPIDA ---

    public function quickStoreBranch(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:branches,name',
            'rfc' => 'nullable|string|max:13',
            // Agregado por si decides enviarlos desde la forma rápida también
            'group_name' => 'nullable|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'client_number' => 'nullable|string|max:255',
        ]);

        $branch = Branch::create($validated + ['password' => bcrypt('e3d')]);
        $branch->load('contacts'); // Cargar relación para que coincida con la data inicial

        // Si se agregó a un grupo, consolidamos los productos del grupo.
        $this->branchGroups->rebalance($branch->group_name);

        return response()->json($branch);
    }

    public function quickStoreContact(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'charge' => 'nullable|string|max:255',
            'area' => 'nullable|string|in:Comercial,Finanzas,Pagos',
        ]);

        $contact = $branch->contacts()->create($validated);

        return response()->json($contact);
    }

    /**
     * Sincroniza los productos de un formulario en una sucursal destino.
     *
     * @param bool $allowRemovals Si es true, el catálogo del destino se reemplaza con el del
     *                            formulario (permite quitar productos). Si es false, solo se
     *                            agregan/complementan productos (unión sin duplicar)
     *                            conservando los que ya tenía el destino.
     */
    private function applyProductsToBranch(Branch $target, $productDataFromRequest, bool $allowRemovals): void
    {
        if ($allowRemovals) {
            $target->products()->sync($productDataFromRequest->keys());
        } else {
            $target->products()->syncWithoutDetaching($productDataFromRequest->keys());
        }

        $currentActivePrices = $target->priceHistory()->whereNull('valid_to')->get()->keyBy('product_id');

        foreach ($productDataFromRequest as $productId => $data) {
            $newPrice = $data['price'] ?? null;
            $newCurrency = $data['currency'] ?? 'MXN';
            $currentPriceRecord = $currentActivePrices->get($productId);

            if (!$currentPriceRecord) {
                // Sin precio vigente en el destino: se registra el del formulario.
                if ($newPrice !== null) {
                    $target->priceHistory()->create([
                        'product_id' => $productId,
                        'price' => $newPrice,
                        'currency' => $newCurrency,
                        'valid_from' => now(),
                    ]);
                }
                continue;
            }

            // En modo unión conservamos el precio vigente que ya tenía el destino.
            if (!$allowRemovals) {
                continue;
            }

            if ($newPrice === null || (float)$newPrice !== (float)$currentPriceRecord->price || $newCurrency !== $currentPriceRecord->currency) {
                $currentPriceRecord->update(['valid_to' => now()]);
                if ($newPrice !== null) {
                    $target->priceHistory()->create([
                        'product_id' => $productId,
                        'price' => $newPrice,
                        'currency' => $newCurrency,
                        'valid_from' => now(),
                    ]);
                }
            }
        }

        if ($allowRemovals) {
            $productIdsToRemovePrice = $currentActivePrices->keys()->diff($productDataFromRequest->keys());
            if ($productIdsToRemovePrice->isNotEmpty()) {
                $target->priceHistory()->whereIn('product_id', $productIdsToRemovePrice)->whereNull('valid_to')->update(['valid_to' => now()]);

                // Los precios por volumen no tienen vigencia: se eliminan al quitar el producto.
                DB::table('branch_volume_prices')
                    ->where('branch_id', $target->id)
                    ->whereIn('product_id', $productIdsToRemovePrice)
                    ->delete();
            }
        }
    }

    /**
     * Formatea los productos de una sucursal con la información necesaria
     * para los formularios (precio especial vigente, imagen, stock y ubicación).
     */
    private function formatBranchProducts(Branch $sourceBranch)
    {
        return $sourceBranch->products()
            ->with(['media', 'storages'])
            ->whereNull('archived_at')
            ->get()
            ->map(function ($product) use ($sourceBranch) {
                $specialPrice = DB::table('branch_price_history')
                    ->where('branch_id', $sourceBranch->id)
                    ->where('product_id', $product->id)
                    ->whereNull('valid_to')
                    ->orderBy('valid_from', 'desc')
                    ->first();

                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'price' => $specialPrice->price ?? null,
                    'currency' => $specialPrice->currency ?? 'MXN',
                    'base_price' => $product->base_price,
                    'image_url' => $product->media->first()?->original_url,
                    'current_stock' => $product->storages->sum('quantity'),
                    'location' => $product->storages->first()?->location,
                ];
            })
            ->values();
    }

    /**
     * Obtiene la sucursal matriz (o la misma si no tiene padre).
     *
     * @param Branch $branch
     * @return Branch
     */
    private function getProductTargetBranch(Branch $branch): Branch
    {
        // Si la sucursal pertenece a un grupo, los productos se consolidan en el
        // líder del grupo. De lo contrario, si tiene padre, esa es la matriz;
        // caso contrario, es ella misma.
        return $this->branchGroups->getProductTargetBranch($branch);
    }

    /* ============================================================
     *  GESTIÓN DE GRUPOS DE CLIENTES
     * ============================================================ */

    /**
     * Devuelve todos los grupos existentes con sus sucursales miembros.
     * Usado por el modal de gestión de grupos.
     */
    public function groupsIndex()
    {
        // La pertenencia a un grupo se gestiona a nivel de clientes raíz (matrices o
        // clientes independientes). Las sucursales hijas heredan el grupo de su matriz.
        $branches = Branch::whereNull('parent_branch_id')
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->with('accountManager:id,name')
            ->orderBy('group_name')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'rfc',
                'business_name',
                'group_name',
                'parent_branch_id',
                'account_manager_id',
            ]);

        $groups = $branches
            ->groupBy('group_name')
            ->map(function ($members, $name) {
                return [
                    'name' => $name,
                    'members' => $members->values(),
                ];
            })
            ->values();

        return response()->json(['groups' => $groups]);
    }

    /**
     * Busca clientes/sucursales candidatos para agregar a un grupo.
     * Excluye a los que ya pertenecen al grupo indicado.
     */
    public function searchForGroup(Request $request)
    {
        $query = trim((string) $request->input('query', ''));
        $group = $request->input('group');

        $branches = Branch::query()
            ->whereNull('parent_branch_id')
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('name', 'like', "%{$query}%")
                      ->orWhere('rfc', 'like', "%{$query}%")
                      ->orWhere('business_name', 'like', "%{$query}%")
                      ->orWhere('client_number', 'like', "%{$query}%")
                      ->orWhere('id', 'like', "%{$query}%");
                });
            })
            ->when($group, function ($q) use ($group) {
                $q->where(function ($w) use ($group) {
                    $w->whereNull('group_name')
                      ->orWhere('group_name', '!=', $group);
                });
            })
            ->with('accountManager:id,name')
            ->orderBy('name')
            ->limit(25)
            ->get([
                'id',
                'name',
                'rfc',
                'business_name',
                'group_name',
                'parent_branch_id',
                'account_manager_id',
            ]);

        return response()->json(['items' => $branches]);
    }

    /**
     * Agrega (o mueve) un cliente a un grupo y consolida sus productos.
     */
    public function addToGroup(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'group_name' => 'required|string|max:255',
        ]);

        $newGroup = trim($validated['group_name']);
        $oldGroup = $branch->group_name;

        DB::transaction(function () use ($branch, $newGroup, $oldGroup) {
            $branch->update(['group_name' => $newGroup]);

            // Las sucursales hijas siguen a su matriz, por lo que no llevan grupo propio.
            $branch->children()->update(['group_name' => null]);

            // Consolida el grupo destino y, si cambió de grupo, reconsolida el origen.
            $this->branchGroups->rebalance($newGroup);

            if ($oldGroup && $oldGroup !== $newGroup) {
                $this->branchGroups->rebalance($oldGroup);
            }
        });

        return response()->json(['message' => 'Cliente agregado al grupo correctamente.']);
    }

    /**
     * Saca a un cliente de su grupo (deja el campo group_name vacío).
     */
    public function removeFromGroup(Branch $branch)
    {
        $oldGroup = $branch->group_name;

        DB::transaction(function () use ($branch, $oldGroup) {
            $branch->update(['group_name' => null]);

            // Las sucursales hijas dejan de pertenecer al grupo junto con su matriz.
            $branch->children()->update(['group_name' => null]);

            if ($oldGroup) {
                $this->branchGroups->rebalance($oldGroup);
            }
        });

        return response()->json(['message' => 'Cliente removido del grupo correctamente.']);
    }

    // === AGREGAR ESTE NUEVO MÉTODO AL FINAL DE TU CONTROLADOR ===
    public function checkSaleValidity(Branch $branch)
    {
        $branch->load(['parent', 'contacts']);
        
        $missingData = [];

        // 1. Revisar si la propia sucursal tiene la información
        $hasOwnRfc = !empty($branch->rfc);
        $hasOwnBusinessName = !empty($branch->business_name);

        // 2. Si es hija y le falta algo, revisar el padre
        if ($branch->parent_branch_id) {
            if (!$hasOwnRfc && empty($branch->parent->rfc)) {
                $missingData[] = 'RFC';
            }
            if (!$hasOwnBusinessName && empty($branch->parent->business_name)) {
                $missingData[] = 'Razón Social';
            }
        } else {
            // Es matriz, revisar sus propios datos
            if (!$hasOwnRfc) {
                $missingData[] = 'RFC';
            }
            if (!$hasOwnBusinessName) {
                $missingData[] = 'Razón Social';
            }
        }

        if (count($missingData) > 0) {
            return response()->json([
                'valid' => false,
                'message' => implode(', ', $missingData)
            ]);
        }

        return response()->json(['valid' => true]);
    }
}