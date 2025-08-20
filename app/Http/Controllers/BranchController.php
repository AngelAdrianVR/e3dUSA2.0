<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Contact;
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
                // Asigna un password temporal si es necesario
                'password' => bcrypt(str()->random(10)),
            ]);

            // 2. Crear los contactos y sus detalles
            foreach ($validated['contacts'] as $index => $contactData) {
                $contact = $branch->contacts()->create([
                    'name' => $contactData['name'],
                    'charge' => $contactData['charge'],
                    // El primer contacto se marca como principal
                    'is_primary' => $index === 0,
                ]);

                // Crear el detalle del teléfono
                $contact->details()->create([
                    'type' => 'Teléfono',
                    'value' => $contactData['phone'],
                    'is_primary' => true,
                ]);

                // Crear el detalle del email
                $contact->details()->create([
                    'type' => 'Correo',
                    'value' => $contactData['email'],
                    'is_primary' => true,
                ]);
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
            'contacts.details'
        ]);

        return Inertia::render('Branch/Show', [
            'branch' => $branch,
        ]);
    }

    public function edit(Branch $branch)
    {
        // Cargamos la sucursal con sus contactos y los detalles de cada contacto
        $branch->load('contacts.details');

        return Inertia::render('Branch/Edit', [
            'branch' => $branch,
            'users' => User::select('id', 'name')->get(),
            'branches' => Branch::select('id', 'name')
                ->whereNull('parent_branch_id')
                ->where('id', '!=', $branch->id) // No puede ser su propia matriz
                ->get(),
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

            // Validación para los contactos
            'contacts' => 'present|array',
            'contacts.*.id' => 'nullable|exists:contacts,id', // Puede ser un contacto existente
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.charge' => 'nullable|string|max:255',
            'contacts.*.phone' => 'required|string|max:20',
            'contacts.*.email' => 'required|email|max:255',
        ]);

        DB::transaction(function () use ($validated, $branch) {
            // 1. Actualizar la sucursal (Branch)
            $branch->update($validated);

            $incomingContactIds = collect($validated['contacts'])->pluck('id')->filter();

            // 2. Eliminar contactos que ya no vienen en la petición
            $branch->contacts()->whereNotIn('id', $incomingContactIds)->delete();

            // 3. Actualizar o Crear contactos
            foreach ($validated['contacts'] as $contactData) {
                $contact = Contact::updateOrCreate(
                    ['id' => $contactData['id'] ?? null, 'branch_id' => $branch->id],
                    ['name' => $contactData['name'], 'charge' => $contactData['charge']]
                );

                // Simplificamos la lógica: eliminamos detalles viejos y creamos los nuevos
                $contact->details()->delete();

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
        });

        return to_route('branches.index');
    }

    public function destroy(Branch $branch)
    {
        //
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
