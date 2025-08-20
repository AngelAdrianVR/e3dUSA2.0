<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

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
            'users' => User::select('id', 'name')->get(),
            'branches' => Branch::select('id', 'name')->whereNull('parent_branch_id')->get(), // Solo matrices
            'catalog_products' => Product::where('product_type', 'Catálogo')->select('id', 'name')->get(), // productos para relacionar a clientes
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
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

            // --- VALIDACIÓN PARA PRODUCTOS ---
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
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
            foreach ($validated['contacts'] as $index => $contactData) {
                $contact = $branch->contacts()->create([
                    'name' => $contactData['name'],
                    'charge' => $contactData['charge'],
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

            // 3. Relacionar productos y guardar precios especiales
            if (!empty($validated['products'])) {
                $now = now();
                $priceHistoryData = [];
                $productIdsToSync = [];

                foreach ($validated['products'] as $productData) {
                    // Añadimos el ID del producto para sincronizar la relación
                    $productIdsToSync[] = $productData['product_id'];

                    // Si se especificó un precio, lo preparamos para el historial
                    if (isset($productData['price']) && !is_null($productData['price'])) {
                        $priceHistoryData[] = [
                            'branch_id' => $branch->id,
                            'product_id' => $productData['product_id'],
                            'price' => $productData['price'],
                            'valid_from' => $now,
                            'valid_to' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Usamos sync() para crear las relaciones en la tabla pivote.
                // Es el método de Eloquent para relaciones Muchos a Muchos.
                $branch->products()->sync($productIdsToSync);

                // Insertamos los precios especiales si se definió alguno.
                if (!empty($priceHistoryData)) {
                    DB::table('branch_price_history')->insert($priceHistoryData);
                }
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
            'products.media', // Carga los productos y sus imágenes
            'products.priceHistory' => function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)->orderBy('valid_from', 'desc');
            } // Carga solo el historial de precios de este cliente para cada producto
        ]);

        $allBranches = Branch::select('id', 'name')->get();

        return Inertia::render('Branch/Show', [
            'branch' => $branch,
            'branches' => $allBranches,
        ]);
    }

    public function edit(Branch $branch)
    {
        // Cargar las relaciones necesarias para la edición
        $branch->load(['contacts.details', 'products']);

        // Formatear los datos para que coincidan con la estructura del formulario de Vue
        $formattedContacts = $branch->contacts->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'charge' => $contact->charge,
                'phone' => $contact->details->firstWhere('type', 'Teléfono')->value ?? null,
                'email' => $contact->details->firstWhere('type', 'Correo')->value ?? null,
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
                // Puedes cargar más datos del producto si es necesario para la vista
            ];
        });

        return Inertia::render('Branch/Edit', [
            'branch' => $branch,
            'formattedContacts' => $formattedContacts,
            'formattedProducts' => $formattedProducts,
            'users' => User::where('is_active', true)->select('id', 'name')->get(),
            'branches' => Branch::where('id', '!=', $branch->id)->select('id', 'name')->get(), // Excluir la actual de las matrices
            'catalog_products' => Product::select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rfc' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'status' => 'required|in:Prospecto,Cliente',
            'parent_branch_id' => 'nullable|exists:branches,id',
            'account_manager_id' => 'nullable|exists:users,id',
            'meet_way' => 'nullable|string|max:255',
            'contacts' => 'present|array',
            'contacts.*.id' => 'nullable|exists:contacts,id', // ID para identificar contactos existentes
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.phone' => 'required|string|max:10',
            'contacts.*.email' => 'required|email|max:255',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $branch) {
            // 1. Actualizar los datos de la sucursal (Branch)
            $branch->update([
                'name' => $validated['name'],
                'rfc' => $validated['rfc'],
                'address' => $validated['address'],
                'post_code' => $validated['post_code'],
                'status' => $validated['status'],
                'parent_branch_id' => $validated['parent_branch_id'],
                'account_manager_id' => $validated['account_manager_id'],
                'meet_way' => $validated['meet_way'],
            ]);

            // 2. Sincronizar los contactos
            $existingContactIds = [];
            foreach ($validated['contacts'] as $index => $contactData) {
                $contact = null;
                // Si el contacto tiene ID, se actualiza. Si no, se crea.
                if (!empty($contactData['id'])) {
                    $contact = $branch->contacts()->find($contactData['id']);
                    if ($contact) {
                        $contact->update([
                            'name' => $contactData['name'],
                            'charge' => $contactData['charge'],
                            'is_primary' => $index === 0,
                        ]);
                    }
                } else {
                    $contact = $branch->contacts()->create([
                        'name' => $contactData['name'],
                        'charge' => $contactData['charge'],
                        'is_primary' => $index === 0,
                    ]);
                }
                
                // Actualizar o crear detalles
                $contact->details()->updateOrCreate(
                    ['type' => 'Teléfono'],
                    ['value' => $contactData['phone'], 'is_primary' => true]
                );

                $contact->details()->updateOrCreate(
                    ['type' => 'Correo'],
                    ['value' => $contactData['email'], 'is_primary' => true]
                );
                
                $existingContactIds[] = $contact->id;
            }
            // Eliminar contactos que ya no están en el formulario
            $branch->contacts()->whereNotIn('id', $existingContactIds)->delete();


            // 3. Sincronizar productos y precios
            $now = now();
            $productIdsToSync = [];
            
            // Invalidar todos los precios especiales actuales para este cliente
            DB::table('branch_price_history')
                ->where('branch_id', $branch->id)
                ->whereNull('valid_to')
                ->update(['valid_to' => $now]);

            if (!empty($validated['products'])) {
                $newPriceHistory = [];
                foreach ($validated['products'] as $productData) {
                    $productIdsToSync[] = $productData['product_id'];

                    // Añadir nuevo registro de precio si se ha especificado
                    if (isset($productData['price']) && !is_null($productData['price'])) {
                        $newPriceHistory[] = [
                            'branch_id' => $branch->id,
                            'product_id' => $productData['product_id'],
                            'price' => $productData['price'],
                            'valid_from' => $now,
                            'valid_to' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                
                // Sincronizar la tabla pivote
                $branch->products()->sync($productIdsToSync);

                // Insertar los nuevos precios
                if (!empty($newPriceHistory)) {
                    DB::table('branch_price_history')->insert($newPriceHistory);
                }
            } else {
                // Si no hay productos, desvincular todos
                $branch->products()->sync([]);
            }
        });

        return to_route('branches.index');
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

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Branch::find($id);
            $bonus?->delete();
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
}
