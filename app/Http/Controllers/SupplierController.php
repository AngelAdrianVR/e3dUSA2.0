<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('search');
        $suppliers = Supplier::query()
            ->when($query, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%")
                  ->orWhere('rfc', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Supplier/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        $purchasableProducts = Product::where('is_purchasable', true)
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('Supplier/Create', [
            'products' => $purchasableProducts,
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validación de los datos de entrada
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
            'rfc' => 'nullable|string|max:13|unique:suppliers,rfc',
            'nickname' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',

            'contacts' => 'present|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',

            'bankAccounts' => 'nullable|array',
            'bankAccounts.*.bank_name' => 'required|string|max:255',
            'bankAccounts.*.account_holder' => 'required|string|max:255',
            'bankAccounts.*.account_number' => 'required|string|max:255',
            'bankAccounts.*.clabe' => 'nullable|string|max:18',
            'bankAccounts.*.currency' => 'required|string|max:3',

            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.last_price' => 'nullable|numeric|min:0',
            'products.*.supplier_sku' => 'nullable|string|max:255',
            'products.*.min_quantity' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validatedData) {
            // Crear el proveedor con los datos principales
            $supplier = Supplier::create(collect($validatedData)->except(['contacts', 'bankAccounts', 'products'])->all());

            // 2. Lógica para crear y asociar los contactos
            if (!empty($validatedData['contacts'])) {
                foreach ($validatedData['contacts'] as $index => $contactData) {
                    $contact = $supplier->contacts()->create([
                        'name' => $contactData['name'],
                        'charge' => $contactData['charge'],
                        'is_primary' => $index === 0, // El primero es el principal
                    ]);
                    
                    if (!empty($contactData['phone'])) {
                        $contact->details()->create([
                            'type' => 'Teléfono',
                            'value' => $contactData['phone'],
                            'is_primary' => true,
                        ]);
                    }

                    if (!empty($contactData['email'])) {
                        $contact->details()->create([
                            'type' => 'Correo',
                            'value' => $contactData['email'],
                            'is_primary' => true,
                        ]);
                    }
                }
            }

            // Crear y asociar las cuentas bancarias
            if (!empty($validatedData['bankAccounts'])) {
                $supplier->bankAccounts()->createMany($validatedData['bankAccounts']);
            }

            // --- INICIO: LÓGICA MODIFICADA PARA PRODUCTOS ---
            if (!empty($validatedData['products'])) {
                // Usamos una colección para iterar sobre los productos enviados
                $productsToSync = collect($validatedData['products'])
                    ->each(function ($productData) {
                        // Si se proporciona 'last_price' y es un número, actualizamos el costo del producto
                        if (isset($productData['last_price']) && is_numeric($productData['last_price'])) {
                            $product = Product::find($productData['product_id']);
                            if ($product) {
                                // Se actualiza el campo 'cost' en la tabla de productos
                                $product->update(['cost' => $productData['last_price']]);
                            }
                        }
                    })
                    ->keyBy('product_id') // Agrupamos por ID de producto para la sincronización
                    ->map(fn ($p) => Arr::except($p, 'product_id')) // Preparamos los datos para la tabla pivote
                    ->toArray();
                
                // Asociamos los productos al proveedor en la tabla pivote
                $supplier->products()->attach($productsToSync);
            }
            // --- FIN: LÓGICA MODIFICADA PARA PRODUCTOS ---
        });

        return to_route('suppliers.index')->with('success', 'Proveedor creado exitosamente.');
    }

    public function show(Supplier $supplier)
    {
        // Cargar las relaciones necesarias del proveedor
        $supplier->load([
            'contacts.details', 
            'bankAccounts', 
            'products.media',
            
            // Cargar solo las últimas 25 órdenes de compra con sus relaciones
            'purchases' => function ($query) {
                $query->latest('emited_at')->limit(25)->with(['user', 'items.product']);
            },
            
            // --- ¡NUEVO! ---
            // Cargar el historial de solicitudes de stock
            'favoredStockRequests' => function ($query) {
                $query->with([
                    'user:id,name', // Usuario que solicitó
                    'favoredProduct' => function($q) { // El item de 'favored_product'
                        $q->select('id', 'product_id'); // Seleccionar solo IDs
                    },
                    'favoredProduct.product:id,name,code' // El producto final (nombre, código)
                ])
                ->latest() // Más recientes primero
                ->limit(100); // Limitar a las últimas 100 solicitudes
            },
        ]);

        // Obtener la lista de todos los proveedores para el selector
        $allSuppliers = Supplier::select('id', 'name')->get();

        // Obtener productos que se pueden comprar para otros fines
        $purchasableProducts = Product::where('is_purchasable', true)
            ->select('id', 'name', 'code', 'measure_unit')
            ->get();

            // return $supplier;
        // Renderizar la vista de Inertia con los datos necesarios
        return Inertia::render('Supplier/Show', [
            'supplier' => $supplier,
            'suppliers' => $allSuppliers,
            'catalog_products' => $purchasableProducts,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        // Cargamos contacts.details para poder formatearlos
        $supplier->load('contacts.details', 'bankAccounts', 'products');

        // Formateamos los contactos para que el frontend los reciba de forma consistente
        $formattedContacts = $supplier->contacts->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'charge' => $contact->charge,
                'phone' => $contact->details->firstWhere('type', 'Teléfono')->value ?? null,
                'email' => $contact->details->firstWhere('type', 'Correo')->value ?? null,
            ];
        });

        $purchasableProducts = Product::where('is_purchasable', true)
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('Supplier/Edit', [
            'supplier' => $supplier,
            'formattedContacts' => $formattedContacts, // Enviamos los contactos formateados
            'products' => $purchasableProducts,
        ]);
    }


    public function update(Request $request, Supplier $supplier)
    {
        // Validación de los datos de entrada
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers')->ignore($supplier->id)],
            'rfc' => ['nullable', 'string', 'max:13', Rule::unique('suppliers')->ignore($supplier->id)],
            'nickname' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',

            'contacts' => 'present|array',
            'contacts.*.id' => 'nullable|exists:contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            
            'bankAccounts' => 'nullable|array',
            'bankAccounts.*.bank_name' => 'required|string|max:255',
            'bankAccounts.*.account_holder' => 'required|string|max:255',
            'bankAccounts.*.account_number' => 'required|string|max:255',
            'bankAccounts.*.clabe' => 'nullable|string|max:18',
            'bankAccounts.*.currency' => 'required|string|max:3',

            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.last_price' => 'nullable|numeric|min:0',
            'products.*.supplier_sku' => 'nullable|string|max:255',
            'products.*.min_quantity' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validatedData, $supplier) {
            // Actualizar datos principales del proveedor
            $supplier->update(collect($validatedData)->except(['contacts', 'bankAccounts', 'products'])->all());

            // Lógica de sincronización de contactos
            $contactIdsToKeep = [];
            if (!empty($validatedData['contacts'])) {
                foreach ($validatedData['contacts'] as $index => $contactData) {
                    $contact = $supplier->contacts()->updateOrCreate(
                        ['id' => $contactData['id'] ?? null],
                        [
                            'name' => $contactData['name'],
                            'charge' => $contactData['charge'],
                            'is_primary' => $index === 0,
                        ]
                    );

                    if (!empty($contactData['phone'])) {
                        $contact->details()->updateOrCreate(
                            ['type' => 'Teléfono'], 
                            ['value' => $contactData['phone']]
                        );
                    } else {
                        $contact->details()->where('type', 'Teléfono')->delete();
                    }

                    if (!empty($contactData['email'])) {
                        $contact->details()->updateOrCreate(
                            ['type' => 'Correo'], 
                            ['value' => $contactData['email']]
                        );
                    } else {
                        $contact->details()->where('type', 'Correo')->delete();
                    }
                    
                    $contactIdsToKeep[] = $contact->id;
                }
            }
            $supplier->contacts()->whereNotIn('id', $contactIdsToKeep)->delete();

            // Sincronizar cuentas bancarias
            $supplier->bankAccounts()->delete();
            if (!empty($validatedData['bankAccounts'])) {
                $supplier->bankAccounts()->createMany($validatedData['bankAccounts']);
            }

            // --- INICIO: LÓGICA MODIFICADA PARA PRODUCTOS EN UPDATE ---
            $productsToSync = [];
            if (!empty($validatedData['products'])) {
                $productsToSync = collect($validatedData['products'])
                    ->each(function ($productData) {
                        // Si se proporciona 'last_price' y es un número, actualizamos el costo del producto
                        if (isset($productData['last_price']) && is_numeric($productData['last_price'])) {
                            $product = Product::find($productData['product_id']);
                            if ($product) {
                                // Se actualiza el campo 'cost' en la tabla de productos
                                $product->update(['cost' => $productData['last_price']]);
                            }
                        }
                    })
                    ->keyBy('product_id')
                    ->map(fn ($p) => Arr::except($p, 'product_id'))
                    ->toArray();
            }
            // Sincronizamos los productos con el proveedor usando sync()
            $supplier->products()->sync($productsToSync);
            // --- FIN: LÓGICA MODIFICADA PARA PRODUCTOS EN UPDATE ---

        });

        return to_route('suppliers.show', $supplier)->with('success', 'Proveedor actualizado exitosamente.');
    }


    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            Supplier::find($id)?->delete();
        }
    }

    public function getDetails(Supplier $supplier)
    {
        // Cargar las relaciones necesarias
        $supplier->load(['contacts', 'products', 'bankAccounts']);

        return response()->json([
            'contacts' => $supplier->contacts,
            'products' => $supplier->products->where('is_purchasable', true)->values(), // Enviar solo productos comprables
            'bankAccounts' => $supplier->bankAccounts,
        ]);
    }
}
