<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        // Usamos with() para cargar las relaciones y evitar el problema N+1.
        // Esto hace que la consulta sea mucho más eficiente.
        $branches = Branch::with(['accountManager:id,name', 'parent:id,name'])
            ->latest() // Ordena por los más recientes primero
            ->paginate(30); // Pagina los resultados

        return Inertia::render('Branch/Index', [
            'branches' => $branches,
        ]);
    }

    public function create()
    {
        // Pasamos los datos necesarios para los selects del formulario
        return Inertia::render('Branch/Create', [
            'users' => User::where('is_active', true)->role(['Vendedor', 'Super Administrador'])->select('id', 'name')->get(),
            'branches' => Branch::select('id', 'name')->whereNull('parent_branch_id')->get(), // Solo matrices
            'catalog_products' => Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:branches',
            'rfc' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'status' => 'required|in:Prospecto,Cliente',
            'parent_branch_id' => 'nullable|exists:branches,id',
            'account_manager_id' => 'nullable|exists:users,id',
            'meet_way' => 'nullable|string|max:255',

            // Validación para los contactos
            'contacts' => 'present|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.phone' => 'required|string|max:10',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.birth_month' => 'nullable|integer|between:1,12',
            'contacts.*.birth_day' => 'nullable|integer|between:1,31',

            // Validación para productos asignados
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.currency' => 'nullable|string|in:MXN,USD',

            // Validación para productos sugeridos
            'suggested_products' => 'nullable|array',
            'suggested_products.*' => 'exists:products,id',
        ]);

        DB::transaction(function () use ($validated) {
            // 1. Crear la sucursal (Branch)
            $branch = Branch::create([
                'name' => $validated['name'],
                'rfc' => $validated['rfc'],
                'address' => $validated['address'],
                'post_code' => $validated['post_code'],
                'status' => $validated['status'],
                'parent_branch_id' => $validated['parent_branch_id'],
                'account_manager_id' => $validated['account_manager_id'],
                'meet_way' => $validated['meet_way'],
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
                        'name' => $contactData['name'],
                        'charge' => $contactData['charge'],
                        'birthdate' => $birthdate,
                        'is_primary' => $index === 0,
                    ]);

                    $contact->details()->create([
                        'type' => 'Teléfono',
                        'value' => $contactData['phone'],
                        'is_primary' => true,
                    ]);

                    $contact->details()->create([
                        'type' => 'Correo',
                        'value' => $contactData['email'],
                        'is_primary' => true,
                    ]);
                }
            }

            // 3. Relacionar productos y guardar precios especiales (MODIFICADO)
            if (!empty($validated['products'])) {
                // Obtenemos la sucursal matriz para asignarle los productos.
                // Si es una nueva sucursal hija, necesitamos cargar la relación 'parent'.
                $branch->load('parent'); 
                $productTargetBranch = $this->getProductTargetBranch($branch);

                // Extraemos solo los IDs de los productos para sincronizar la relación principal.
                $productIds = collect($validated['products'])->pluck('product_id')->toArray();
                
                // Sincronizamos la tabla pivote 'branch_product' con la sucursal matriz.
                $productTargetBranch->products()->sync($productIds);

                // Ahora, recorremos los productos para guardar los precios especiales en su tabla de historial.
                foreach ($validated['products'] as $productData) {
                    if (isset($productData['price']) && $productData['price'] !== null) {
                        // Creamos el registro en la tabla de historial de precios asociado a la matriz.
                        $productTargetBranch->priceHistory()->create([
                            'product_id' => $productData['product_id'],
                            'price' => $productData['price'],
                            'valid_from' => now(),
                            'currency' => $productData['currency'],
                        ]);
                    }
                }
            }

            // 4. Guardar productos sugeridos (se mantiene por sucursal individual)
            if (!empty($validated['suggested_products'])) {
                $branch->suggestedProducts()->sync($validated['suggested_products']);
            }

        });
        
        return to_route('branches.index');
    }

    public function show(Branch $branch)
    {
        // Cargamos las relaciones directas de la sucursal
        $branch->load([
            'children', 
            'accountManager:id,name', 
            'parent:id,name', 
            'contacts.details',
            'suggestedProducts.media',
        ]);

        // MODIFICADO: Obtenemos la sucursal desde donde se leerán los productos (la matriz o ella misma)
        $productSourceBranch = $this->getProductTargetBranch($branch);

        // Cargamos los productos desde la sucursal matriz
        $products = $productSourceBranch->products()->with([
            'storages',
            'media',
            // El historial de precios también se consulta con el ID de la matriz
            'priceHistory' => function ($query) use ($productSourceBranch) {
                $query->where('branch_id', $productSourceBranch->id)->orderBy('valid_from', 'desc');
            }
        ])->get();

        // Asignamos manualmente la relación de productos a la sucursal que se está mostrando
        $branch->setRelation('products', $products);

        $allBranches = Branch::select('id', 'name')->get();

        return Inertia::render('Branch/Show', [
            'branch' => $branch,
            'branches' => $allBranches,
            'catalog_products' => Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name')->get()
        ]);
    }

    public function edit(Branch $branch)
    {
        // Cargar las relaciones que no dependen de la matriz
        $branch->load(['contacts.details', 'suggestedProducts', 'parent']);
        $suggestedProductIds = $branch->suggestedProducts()->pluck('products.id')->toArray();

        // MODIFICADO: Obtenemos la sucursal desde donde se leerán los productos
        $productSourceBranch = $this->getProductTargetBranch($branch);
        $products = $productSourceBranch->products;

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
                'name' => $contact->name,
                'charge' => $contact->charge,
                'phone' => $contact->details->firstWhere('type', 'Teléfono')->value ?? null,
                'email' => $contact->details->firstWhere('type', 'Correo')->value ?? null,
                'birth_month' => $birth_month,
                'birth_day' => $birth_day,
            ];
        });

        // MODIFICADO: Formatear productos basados en la sucursal matriz
        $formattedProducts = $products->map(function ($product) use ($productSourceBranch) {
            $specialPrice = DB::table('branch_price_history')
                ->where('branch_id', $productSourceBranch->id) // Usar el ID de la matriz
                ->where('product_id', $product->id)
                ->whereNull('valid_to')
                ->orderBy('valid_from', 'desc')
                ->first();

            return [
                'product_id' => $product->id,
                'price' => $specialPrice->price ?? null,
                'currency' => $specialPrice->currency ?? 'MXN',
            ];
        });

        return Inertia::render('Branch/Edit', [
            'branch' => $branch,
            'formattedContacts' => $formattedContacts,
            'formattedProducts' => $formattedProducts,
            'users' => User::where('is_active', true)->role(['Vendedor', 'Super Administrador'])->select('id', 'name')->get(),
            'branches' => Branch::where('id', '!=', $branch->id)->whereNull('parent_branch_id')->select('id', 'name')->get(),
            'catalog_products' => Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name')->get(),
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
            'rfc' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'status' => 'required|in:Prospecto,Cliente',
            'parent_branch_id' => 'nullable|exists:branches,id',
            'account_manager_id' => 'nullable|exists:users,id',
            'meet_way' => 'nullable|string|max:255',

            // Validación para contactos
            'contacts' => 'present|array',
            'contacts.*.id' => 'nullable|exists:contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.phone' => 'required|string|max:10',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.birth_month' => 'nullable|integer|between:1,12',
            'contacts.*.birth_day' => 'nullable|integer|between:1,31',

            // Validación para productos asignados
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.currency' => 'nullable|string|in:MXN,USD',

            // Validación para productos sugeridos
            'suggested_products' => 'nullable|array',
            'suggested_products.*' => 'exists:products,id',
        ]);

        DB::transaction(function () use ($validated, $branch) {
            // 1. Actualizar datos de la sucursal
            $branch->update(collect($validated)->except(['contacts', 'products', 'suggested_products'])->all());

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
                        'name' => $contactData['name'],
                        'charge' => $contactData['charge'],
                        'birthdate' => $birthdate,
                        'is_primary' => $index === 0,
                    ]
                );
                $contact->details()->updateOrCreate(['type' => 'Teléfono'], ['value' => $contactData['phone'], 'is_primary' => true]);
                $contact->details()->updateOrCreate(['type' => 'Correo'], ['value' => $contactData['email'], 'is_primary' => true]);
                $contactIdsToKeep[] = $contact->id;
            }
            $branch->contacts()->whereNotIn('id', $contactIdsToKeep)->delete();
            
            // MODIFICADO: Determinar la sucursal matriz para la gestión de productos
            $branch->load('parent');
            $productTargetBranch = $this->getProductTargetBranch($branch);

            // 3. Sincronizar productos y precios especiales en la sucursal matriz
            $productDataFromRequest = collect($validated['products'] ?? [])->keyBy('product_id');
            $productIdsFromRequest = $productDataFromRequest->keys();

            $productTargetBranch->products()->sync($productIdsFromRequest);

            $currentActivePrices = $productTargetBranch->priceHistory()->whereNull('valid_to')->get()->keyBy('product_id');

            foreach ($productDataFromRequest as $productId => $data) {
                $newPrice = $data['price'] ?? null;
                $newCurrency = $data['currency'] ?? 'MXN';
                $currentPriceRecord = $currentActivePrices->get($productId);

                $hasPriceChanged = false;
                if (!$currentPriceRecord && $newPrice !== null) {
                    $hasPriceChanged = true;
                } elseif ($currentPriceRecord) {
                    if ($newPrice === null || (float)$newPrice !== (float)$currentPriceRecord->price || $newCurrency !== $currentPriceRecord->currency) {
                        $hasPriceChanged = true;
                    }
                }
                
                if ($hasPriceChanged) {
                    if ($currentPriceRecord) {
                        $currentPriceRecord->update(['valid_to' => now()]);
                    }
                    if ($newPrice !== null) {
                        $productTargetBranch->priceHistory()->create([
                            'product_id' => $productId,
                            'price' => $newPrice,
                            'currency' => $newCurrency,
                            'valid_from' => now(),
                        ]);
                    }
                }
            }

            $productIdsToRemovePrice = $currentActivePrices->keys()->diff($productIdsFromRequest);
            if ($productIdsToRemovePrice->isNotEmpty()) {
                $productTargetBranch->priceHistory()->whereIn('product_id', $productIdsToRemovePrice)->whereNull('valid_to')->update(['valid_to' => now()]);
            }

            // 4. Sincronizar productos sugeridos (se mantiene individual)
            $branch->suggestedProducts()->sync($validated['suggested_products'] ?? []);
        });

        return to_route('branches.show', $branch->id);
    }

    public function destroy(Branch $branch)
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
            // (Opcional) Si algo sale mal, redirige con un mensaje de error.
            // return back()->withErrors(['error' => 'Ocurrió un error al eliminar la sucursal: ' . $e->getMessage()]);
        }
        
        // Si no usas los retornos con mensajes, puedes simplemente redirigir.
        return to_route('branches.index');
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

                // 2. Eliminar la relación en la tabla pivote (de la matriz)
                $productTargetBranch->products()->detach($product->id);
            });

            return response()->json(['message' => 'Producto removido exitosamente.']);

        } catch (\Exception $e) {
            Log::error('Error al remover producto de cliente: ' . $e->getMessage());
            return response()->json(['message' => 'Ocurrió un error en el servidor al intentar remover el producto.'], 500);
        }
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

        // Realiza la búsqueda
        $branches = Branch::with(['accountManager:id,name', 'parent:id,name'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                // Busca dentro de la relación de la matriz (parent)
                ->orWhereHas('parent', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                // Correcto uso de whereHas para buscar en la relación
                ->orWhereHas('accountManager', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
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
        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.currency' => 'nullable|string',
        ]);

        // MODIFICADO: Obtenemos la sucursal matriz para asignarle los productos.
        $productTargetBranch = $this->getProductTargetBranch($branch);

        DB::transaction(function () use ($validated, $productTargetBranch) {
            $now = now();
            $priceHistoryData = [];
            $productIdsToSync = [];

            foreach ($validated['products'] as $productData) {
                $productIdsToSync[] = $productData['product_id'];

                if (isset($productData['price']) && !is_null($productData['price'])) {
                    // Invalidar precios anteriores para este producto y cliente (en la matriz)
                    DB::table('branch_price_history')
                        ->where('branch_id', $productTargetBranch->id)
                        ->where('product_id', $productData['product_id'])
                        ->whereNull('valid_to')
                        ->update(['valid_to' => $now]);

                    // Agregar el nuevo precio especial a la matriz
                    $priceHistoryData[] = [
                        'branch_id' => $productTargetBranch->id,
                        'product_id' => $productData['product_id'],
                        'price' => $productData['price'],
                        'valid_from' => $now,
                        'valid_to' => null,
                        'currency' => $productData['currency'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
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
        // MODIFICADO: Obtenemos la sucursal desde donde se leerán los productos
        $productSourceBranch = $this->getProductTargetBranch($branch);

        $products = $productSourceBranch->products()
            ->with([
                'media',
                // El historial de precios también se consulta con el ID de la matriz
                'priceHistory' => function ($query) use ($productSourceBranch) {
                    $query->where('branch_id', $productSourceBranch->id)
                        ->orderBy('valid_from', 'desc');
                }
            ])
            ->get();

        return response()->json($products);
    }

    // --- MÉTODOS NUEVOS PARA CREACIÓN RÁPIDA ---

    public function quickStoreBranch(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:branches,name',
            'rfc' => 'nullable|string|max:13',
        ]);

        $branch = Branch::create($validated + ['password' => bcrypt('e3d')]);
        $branch->load('contacts'); // Cargar relación para que coincida con la data inicial

        return response()->json($branch);
    }

    public function quickStoreContact(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'charge' => 'nullable|string|max:255',
        ]);

        $contact = $branch->contacts()->create($validated);

        return response()->json($contact);
    }

    /**
     * Obtiene la sucursal matriz (o la misma si no tiene padre).
     *
     * @param Branch $branch
     * @return Branch
     */
    private function getProductTargetBranch(Branch $branch): Branch
    {
        // Si la sucursal tiene un padre, esa es la matriz. De lo contrario, es ella misma.
        return $branch->parent_branch_id ? $branch->parent : $branch;
    }
}
