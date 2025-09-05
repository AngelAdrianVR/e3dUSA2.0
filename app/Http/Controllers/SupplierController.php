<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        // Obtiene el término de búsqueda de la solicitud
        $query = $request->input('search');

        $suppliers = Supplier::query()
            // Aplica el filtro de búsqueda si existe
            ->when($query, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%")
                  ->orWhere('rfc', 'like', "%{$search}%");
            })
            ->latest() // Ordena por los más recientes
            ->paginate(10) // Pagina los resultados
            ->withQueryString(); // Mantiene los parámetros de búsqueda en la paginación

        // Renderiza el componente de Vue y le pasa los proveedores
        return Inertia::render('Supplier/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search']), // Pasa los filtros actuales a la vista
        ]);
    }

    public function create()
    {
        // Obtenemos solo los productos que se pueden comprar (is_purchasable = true)
        $purchasableProducts = Product::where('is_purchasable', true)
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('Supplier/Create', [
            'products' => $purchasableProducts,
        ]);
    }

    /**
     * Almacena un nuevo proveedor en la base de datos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validación de los datos principales del proveedor y los arrays anidados
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
            'rfc' => 'nullable|string|max:13|unique:suppliers,rfc',
            'nickname' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',

            // Validación para los contactos
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.position' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.is_primary' => 'boolean',

            // Validación para las cuentas bancarias
            'bankAccounts' => 'nullable|array',
            'bankAccounts.*.bank_name' => 'required|string|max:255',
            'bankAccounts.*.account_holder' => 'required|string|max:255',
            'bankAccounts.*.account_number' => 'required|string|max:255',
            'bankAccounts.*.clabe' => 'nullable|string|max:18',
            'bankAccounts.*.currency' => 'required|string|max:3',

            // Validación para los productos
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.last_price' => 'required|numeric|min:0',
            'products.*.supplier_sku' => 'nullable|string|max:255',
            'products.*.min_quantity' => 'nullable|numeric|min:0',
        ]);

        try {
            // Usamos una transacción para asegurar la integridad de los datos
            DB::transaction(function () use ($validatedData) {
                // 1. Crear el proveedor
                $supplier = Supplier::create([
                    'name' => $validatedData['name'],
                    'rfc' => $validatedData['rfc'],
                    'nickname' => $validatedData['nickname'],
                    'address' => $validatedData['address'],
                    'post_code' => $validatedData['post_code'],
                    'phone' => $validatedData['phone'],
                    'email' => $validatedData['email'],
                    'notes' => $validatedData['notes'],
                ]);

                // 2. Crear y asociar los contactos (si existen)
                if (!empty($validatedData['contacts'])) {
                    // Asegurarse de que solo un contacto sea el principal
                    $hasPrimary = false;
                    foreach (array_reverse($validatedData['contacts']) as $contactData) {
                        if ($contactData['is_primary'] && !$hasPrimary) {
                            $hasPrimary = true;
                        } elseif ($contactData['is_primary']) {
                            $contactData['is_primary'] = false;
                        }
                        $supplier->contacts()->create($contactData);
                    }
                }

                // 3. Crear y asociar las cuentas bancarias (si existen)
                if (!empty($validatedData['bankAccounts'])) {
                    foreach ($validatedData['bankAccounts'] as $bankAccountData) {
                        $supplier->bankAccounts()->create($bankAccountData);
                    }
                }

                // 4. Asociar los productos (si existen)
                if (!empty($validatedData['products'])) {
                    $productsToSync = [];
                    foreach ($validatedData['products'] as $productData) {
                        // Preparamos el array para el método attach/sync
                        $productsToSync[$productData['product_id']] = [
                            'last_price' => $productData['last_price'],
                            'supplier_sku' => $productData['supplier_sku'],
                            'min_quantity' => $productData['min_quantity'],
                        ];
                    }
                    $supplier->products()->attach($productsToSync);
                }
            });
        } catch (\Exception $e) {
            // En caso de error, puedes registrarlo y devolver un mensaje
            // \Log::error('Error creating supplier: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Ocurrió un error al crear el proveedor. Inténtalo de nuevo.']);
        }

        // Redirigir a la lista de proveedores con un mensaje de éxito
        return to_route('suppliers.index')->with('success', 'Proveedor creado exitosamente.');
    }

    public function show(Supplier $supplier): Response
    {
        // Carga las relaciones para que estén disponibles en el componente de Vue
        // Carga los productos relacionados a través de la tabla pivote
        $supplier->load('contacts', 'bankAccounts', 'products');

        // Obtiene todos los proveedores para el selector de búsqueda rápida en la vista
        $allSuppliers = Supplier::select('id', 'name')->get();

        // Obtenemos todos los productos que se pueden comprar para los modales de la vista
        $purchasableProducts = Product::where('is_purchasable', true)
            ->select('id', 'name', 'code', 'measure_unit')
            ->get();

        // Renderiza el componente de Vue 'Supplier/Show' y le pasa los datos necesarios como props
        return Inertia::render('Supplier/Show', [
            'supplier' => $supplier,
            'suppliers' => $allSuppliers,
            'catalog_products' => $purchasableProducts,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        // Carga las relaciones del proveedor para que estén disponibles en la vista
        $supplier->load('contacts', 'bankAccounts', 'products');

        // Obtenemos todos los productos que se pueden comprar para el selector
        $purchasableProducts = Product::where('is_purchasable', true)
            ->select('id', 'name', 'code')
            ->get();

            // return $supplier;
        // Renderiza el componente de Vue y le pasa los datos necesarios
        return Inertia::render('Supplier/Edit', [
            'supplier' => $supplier,
            'products' => $purchasableProducts,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        // Validación de los datos. Usamos Rule::unique para ignorar el registro actual
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers')->ignore($supplier->id)],
            'rfc' => ['nullable', 'string', 'max:13', Rule::unique('suppliers')->ignore($supplier->id)],
            'nickname' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.position' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.is_primary' => 'boolean',
            'bankAccounts' => 'nullable|array',
            'bankAccounts.*.bank_name' => 'required|string|max:255',
            'bankAccounts.*.account_holder' => 'required|string|max:255',
            'bankAccounts.*.account_number' => 'required|string|max:255',
            'bankAccounts.*.clabe' => 'nullable|string|max:18',
            'bankAccounts.*.currency' => 'required|string|max:3',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.last_price' => 'required|numeric|min:0',
            'products.*.supplier_sku' => 'nullable|string|max:255',
            'products.*.min_quantity' => 'nullable|numeric|min:0',
        ]);

        try {
            // Usamos una transacción para asegurar la integridad de los datos
            DB::transaction(function () use ($validatedData, $supplier) {
                // 1. Actualizar los datos principales del proveedor
                $supplier->update([
                    'name' => $validatedData['name'],
                    'rfc' => $validatedData['rfc'],
                    'nickname' => $validatedData['nickname'],
                    'address' => $validatedData['address'],
                    'post_code' => $validatedData['post_code'],
                    'phone' => $validatedData['phone'],
                    'email' => $validatedData['email'],
                    'notes' => $validatedData['notes'],
                ]);

                // 2. Sincronizar contactos: Eliminamos los anteriores y creamos los nuevos
                $supplier->contacts()->delete();
                if (!empty($validatedData['contacts'])) {
                    $hasPrimary = false;
                    foreach (array_reverse($validatedData['contacts']) as $contactData) {
                        if ($contactData['is_primary'] && !$hasPrimary) {
                            $hasPrimary = true;
                        } elseif ($contactData['is_primary']) {
                            $contactData['is_primary'] = false;
                        }
                        $supplier->contacts()->create($contactData);
                    }
                }

                // 3. Sincronizar cuentas bancarias
                $supplier->bankAccounts()->delete();
                if (!empty($validatedData['bankAccounts'])) {
                    $supplier->bankAccounts()->createMany($validatedData['bankAccounts']);
                }

                // 4. Sincronizar productos con el método sync()
                $productsToSync = [];
                if (!empty($validatedData['products'])) {
                    foreach ($validatedData['products'] as $productData) {
                        $productsToSync[$productData['product_id']] = [
                            'last_price' => $productData['last_price'],
                            'supplier_sku' => $productData['supplier_sku'],
                            'min_quantity' => $productData['min_quantity'],
                        ];
                    }
                }
                $supplier->products()->sync($productsToSync);
            });
        } catch (\Exception $e) {
            // \Log::error('Error updating supplier: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Ocurrió un error al actualizar el proveedor.']);
        }

        return to_route('suppliers.index')->with('success', 'Proveedor actualizado exitosamente.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $suppliers= Supplier::find($id);
            $suppliers->delete();
        }
    }
}
