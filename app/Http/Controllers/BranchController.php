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

            // 3. Relacionar productos y guardar precios especiales (CORREGIDO)
            if (!empty($validated['products'])) {
                // Primero, extraemos solo los IDs de los productos para sincronizar la relación principal.
                $productIds = collect($validated['products'])->pluck('product_id')->toArray();
                
                // Sincronizamos la tabla pivote 'branch_product' solo con las IDs.
                $branch->products()->sync($productIds);

                // Ahora, recorremos los productos para guardar los precios especiales en su tabla de historial.
                foreach ($validated['products'] as $productData) {
                    // Verificamos si se proporcionó un precio para este producto.
                    if (isset($productData['price']) && $productData['price'] !== null) {
                        // Creamos el registro en la tabla de historial de precios.
                        $branch->priceHistory()->create([
                            'product_id' => $productData['product_id'],
                            'price' => $productData['price'],
                            'valid_from' => now(), // Se establece la fecha de inicio como el día de hoy.
                            'currency' => $productData['currency'], // Puedes añadir la moneda si es necesario.
                        ]);
                    }
                }
            }

            // 4. Guardar productos sugeridos
            if (!empty($validated['suggested_products'])) {
                $branch->suggestedProducts()->sync($validated['suggested_products']);
            }

        });
        
        return to_route('branches.index');
    }

    public function show(Branch $branch)
    {
        // Cargamos la sucursal con todas sus relaciones importantes
        $branch->load([
            'accountManager:id,name', 
            'parent:id,name', 
            'contacts.details',
            'suggestedProducts.media',
            'products.storages',
            'products.media', // Carga los productos y sus imágenes
            'products.priceHistory' => function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)->orderBy('valid_from', 'desc');
            } // Carga solo el historial de precios de este cliente para cada producto
        ]);

        $allBranches = Branch::select('id', 'name')->get();

        return Inertia::render('Branch/Show', [
            'branch' => $branch,
            'branches' => $allBranches,
            'catalog_products' => Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name')->get()
        ]);
    }

    public function edit(Branch $branch)
    {
        // Cargar las relaciones necesarias para la edición
        $branch->load(['contacts.details', 'products', 'suggestedProducts']);
        // Carga los IDs de los productos sugeridos y pásalos como prop
        $suggestedProductIds = $branch->suggestedProducts()->pluck('products.id')->toArray();

        // Formatear los datos para que coincidan con la estructura del formulario de Vue
        $formattedContacts = $branch->contacts->map(function ($contact) {
            // --- LÓGICA PARA EXTRAER MES Y DÍA DEL CUMPLEAÑOS ---
            $birth_month = null;
            $birth_day = null;

            if ($contact->birthdate) {
                // Usamos Carbon para parsear la fecha de forma segura
                $date = Carbon::parse($contact->birthdate);
                $birth_month = $date->month; // Extrae el número del mes (1-12)
                $birth_day = $date->day;     // Extrae el número del día (1-31)
            }
            // --- FIN DE LA LÓGICA ---
            
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'charge' => $contact->charge,
                'phone' => $contact->details->firstWhere('type', 'Teléfono')->value ?? null,
                'email' => $contact->details->firstWhere('type', 'Correo')->value ?? null,
                'birth_month' => $birth_month, // Nuevo campo
                'birth_day' => $birth_day,       // Nuevo campo
            ];
        });

        $formattedProducts = $branch->products->map(function ($product) use ($branch) {
            $specialPrice = DB::table('branch_price_history')
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->whereNull('valid_to')
                ->orderBy('valid_from', 'desc')
                ->first();

            return [
                'product_id' => $product->id,
                'price' => $specialPrice->price ?? null,
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
            // Eliminar contactos que ya no vienen en la petición
            $branch->contacts()->whereNotIn('id', $contactIdsToKeep)->delete();

            // 3. Sincronizar productos y precios especiales (LÓGICA MEJORADA)
            $productDataFromRequest = collect($validated['products'] ?? [])->keyBy('product_id');
            $productIdsFromRequest = $productDataFromRequest->keys();

            // Sincroniza la tabla pivote 'branch_product'
            $branch->products()->sync($productIdsFromRequest);

            $currentActivePrices = $branch->priceHistory()->whereNull('valid_to')->get()->keyBy('product_id');

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
                        $branch->priceHistory()->create([
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
                $branch->priceHistory()->whereIn('product_id', $productIdsToRemovePrice)->whereNull('valid_to')->update(['valid_to' => now()]);
            }

            // 4. Sincronizar productos sugeridos
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
        try {
            DB::transaction(function () use ($branch, $product) {
                // 1. Eliminar el historial de precios para esta relación específica
                DB::table('branch_price_history')
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->delete();

                // 2. Eliminar la relación en la tabla pivote (branch_product)
                $branch->products()->detach($product->id);
            });

            // Retornar una respuesta JSON exitosa. Inertia la recibirá.
            return response()->json(['message' => 'Producto removido exitosamente.']);

        } catch (\Exception $e) {
            // Registrar el error para facilitar la depuración
            Log::error('Error al remover producto de cliente: ' . $e->getMessage());
            
            // Retornar una respuesta de error
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
                $q->where('name', 'like', "%{$query}%")
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
        ]);

        DB::transaction(function () use ($validated, $branch) {
            $now = now();
            $priceHistoryData = [];
            $productIdsToSync = [];

            foreach ($validated['products'] as $productData) {
                $productIdsToSync[] = $productData['product_id'];

                if (isset($productData['price']) && !is_null($productData['price'])) {
                    // Opcional: Invalidar precios anteriores para este producto y cliente
                    DB::table('branch_price_history')
                        ->where('branch_id', $branch->id)
                        ->where('product_id', $productData['product_id'])
                        ->whereNull('valid_to')
                        ->update(['valid_to' => $now]);

                    // Agregar el nuevo precio especial
                    $priceHistoryData[] = [
                        'branch_id' => $branch->id,
                        'product_id' => $productData['product_id'],
                        'price' => $productData['price'],
                        'valid_from' => $now,
                        'valid_to' => null, // El nuevo precio es el vigente
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Usamos syncWithoutDetaching para añadir los nuevos productos
            // sin eliminar los que ya estaban asignados.
            $branch->products()->syncWithoutDetaching($productIdsToSync);

            if (!empty($priceHistoryData)) {
                DB::table('branch_price_history')->insert($priceHistoryData);
            }
        });

        // No es necesario retornar a una ruta, Inertia se encargará de recargar los props.
        // Simplemente puedes retornar un redirect o un JSON.
        return back()->with('success', 'Productos agregados correctamente.');
    }

    public function fetchBranchProducts(Branch $branch)
    {
        $products = $branch->products()
            ->with([
                'media',
                'priceHistory' => function ($query) use ($branch) {
                    $query->where('branch_id', $branch->id)
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
}
