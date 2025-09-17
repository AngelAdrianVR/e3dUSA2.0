<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\NewProductProposal;
use App\Models\Product;
use App\Models\SampleTracking;
use App\Notifications\SampleAuthorizedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Notifications\NewSampleTrackingNotification;
use Illuminate\Support\Facades\Notification as FacadesNotification;

class SampleTrackingController extends Controller
{
    public function index()
    {
        $sampleTrackings = SampleTracking::with(['branch:id,name', 'contact:id,name', 'requester:id,name'])
            ->latest()
            ->paginate(20);

        return Inertia::render('SampleTracking/Index', compact('sampleTrackings'));
    }

    public function create()
    {
        // Pasa los datos necesarios para los selectores del formulario
        $branches = Branch::with(['contacts:id,name,branch_id'])->get(['id', 'name']);
        $products = Product::where('product_type', 'Catálogo')->whereNull('archived_at')->get(['id', 'name']); 

        // return $branches;
        return Inertia::render('SampleTracking/Create', compact('branches', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'required|exists:contacts,id',
            'will_be_returned' => 'required|boolean',
            'expected_devolution_date' => 'nullable|required_if:will_be_returned,true|date|after_or_equal:today',
            'comments' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            // --- VALIDACIONES DE ITEMS MODIFICADAS ---
            'items.*.type' => ['required', Rule::in(['catalog', 'new'])],
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.itemable_id' => 'nullable|required_if:items.*.type,catalog|exists:products,id',
            'items.*.name' => 'nullable|required_if:items.*.type,new|string|max:255',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.notes' => 'nullable|string|max:1000', // Agregado: validación para notas
            'items.*.media' => 'nullable|array|max:2', // Agregado: validación para array de imágenes
            'items.*.media.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Agregado: validación para cada imagen
        ]);
        
        try {
            DB::transaction(function () use ($validated, $request) {
                // Crear el registro principal de seguimiento
                $sampleTracking = SampleTracking::create([
                    'branch_id' => $validated['branch_id'],
                    'contact_id' => $validated['contact_id'],
                    'requester_user_id' => auth()->id(),
                    'will_be_returned' => $validated['will_be_returned'],
                    'expected_devolution_date' => $validated['expected_devolution_date'] ?? null,
                    'comments' => $validated['comments'] ?? null,
                    'status' => 'Pendiente', // Estatus inicial
                ]);
    
                // --- LÓGICA DE ITEMS MODIFICADA ---
                // Crear los items asociados
                foreach ($validated['items'] as $index => $itemData) {
                    $itemable = null;

                    if ($itemData['type'] === 'new') {
                        // Si es un nuevo producto, creamos primero la propuesta
                        $itemable = NewProductProposal::create([
                            'name' => $itemData['name'],
                            'description' => $itemData['description'] ?? null,
                            'status' => 'Propuesta',
                        ]);

                        // --- Agregado: Manejo de imágenes para nuevos productos ---
                        if ($request->hasFile("items.{$index}.media")) {
                            foreach ($request->file("items.{$index}.media") as $file) {
                                $itemable->addMedia($file)->toMediaCollection('images');
                            }
                        }
                        // --- Fin del bloque de imágenes ---

                    } else if ($itemData['type'] === 'catalog') {
                        // Si es de catálogo, buscamos el modelo del producto
                        $itemable = Product::find($itemData['itemable_id']);
                    }

                    if ($itemable) {
                        // Creamos el 'SampleTrackingItem' usando la relación polimórfica
                        $sampleTracking->items()->create([
                            'itemable_id' => $itemable->id,
                            'itemable_type' => get_class($itemable),
                            'quantity' => $itemData['quantity'],
                            'notes' => $itemData['notes'] ?? null, // Agregado: guardar notas
                        ]);
                    }
                }

                // --- INICIO DE LÓGICA DE NOTIFICACIÓN ---
                // Si la transacción fue exitosa y el registro se creó
                if ($sampleTracking) {
                    // Buscamos a todos los usuarios con el rol 'Super Administrador'
                    $usersToNotify = User::role('Super Administrador')->get();
                    
                    // Si encontramos usuarios, les enviamos la notificación
                    if ($usersToNotify->isNotEmpty()) {
                        FacadesNotification::send($usersToNotify, new NewSampleTrackingNotification($sampleTracking));
                    }
                }
                // --- FIN DE LÓGICA DE NOTIFICACIÓN ---
            });

        } catch (\Exception $e) {
            // Manejo de errores
            return back()->withErrors('Ocurrió un error inesperado al guardar la solicitud: ' . $e->getMessage());
        }

        return to_route('sample-trackings.index')->with('success.flash', 'Solicitud de muestra creada correctamente');
    }

    public function show(SampleTracking $sampleTracking)
    {
        // Cargar relaciones necesarias
        $sampleTracking->load(['branch', 'contact.details', 'requester', 'items.itemable']);

        // Adjuntar la URL de la primera imagen a cada item para fácil acceso en el frontend
        $sampleTracking->items->each(function ($item) {
            $imageUrl = null;
            if ($item->itemable) {
                 // El nombre de la colección puede variar según tu configuración
                $collectionName = $item->itemable instanceof NewProductProposal ? 'images' : 'images';
                $media = $item->itemable->getFirstMedia($collectionName);
                if ($media) {
                    $imageUrl = $media->getUrl();
                }
            }
            $item->image_url = $imageUrl;
        });

        return Inertia::render('SampleTracking/Show', [
            'sampleTracking' => $sampleTracking,
        ]);
    }

    public function edit(SampleTracking $sampleTracking)
    {
        // No se puede editar si ya fue autorizada
        if ($sampleTracking->authorized_at) {
            return redirect()->route('sample-trackings.show', $sampleTracking)
                ->with('error.flash', 'No se puede editar una solicitud que ya ha sido autorizada.');
        }

        $sampleTracking->load('items.itemable');

        // Datos para los selectores del formulario
        $branches = Branch::with(['contacts:id,name,branch_id'])->get(['id', 'name']);
        $products = Product::where('product_type', 'Catálogo')->get(['id', 'name']);

        return Inertia::render('SampleTracking/Edit', compact('sampleTracking', 'branches', 'products'));
    }

    public function update(Request $request, SampleTracking $sampleTracking)
    {
        // No se puede editar si ya fue autorizada
        if ($sampleTracking->authorized_at) {
             return redirect()->route('sample-trackings.show', $sampleTracking)
                ->with('error.flash', 'No se puede editar una solicitud que ya ha sido autorizada.');
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'required|exists:contacts,id',
            'will_be_returned' => 'required|boolean',
            'expected_devolution_date' => 'nullable|required_if:will_be_returned,true|date|after_or_equal:today',
            'comments' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.type' => ['required', Rule::in(['catalog', 'new'])],
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.itemable_id' => 'nullable|required_if:items.*.type,catalog|exists:products,id',
            'items.*.name' => 'nullable|required_if:items.*.type,new|string|max:255',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.notes' => 'nullable|string|max:1000',
            'items.*.media' => 'nullable|array|max:2',
            'items.*.media.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $sampleTracking) {
                // Actualizar el registro principal
                $sampleTracking->update([
                    'branch_id' => $validated['branch_id'],
                    'contact_id' => $validated['contact_id'],
                    'will_be_returned' => $validated['will_be_returned'],
                    'expected_devolution_date' => $validated['expected_devolution_date'] ?? null,
                    'comments' => $validated['comments'] ?? null,
                ]);

                // Eliminar items antiguos para reemplazarlos con los nuevos
                $sampleTracking->items()->delete();
    
                // Crear los items actualizados
                foreach ($validated['items'] as $index => $itemData) {
                    $itemable = null;

                    if ($itemData['type'] === 'new') {
                        $itemable = NewProductProposal::create([
                            'name' => $itemData['name'],
                            'description' => $itemData['description'] ?? null,
                            'status' => 'Propuesta',
                        ]);

                        if ($request->hasFile("items.{$index}.media")) {
                            foreach ($request->file("items.{$index}.media") as $file) {
                                $itemable->addMedia($file)->toMediaCollection('images');
                            }
                        }

                    } else if ($itemData['type'] === 'catalog') {
                        $itemable = Product::find($itemData['itemable_id']);
                    }

                    if ($itemable) {
                        $sampleTracking->items()->create([
                            'itemable_id' => $itemable->id,
                            'itemable_type' => get_class($itemable),
                            'quantity' => $itemData['quantity'],
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors('Ocurrió un error inesperado al actualizar la solicitud: ' . $e->getMessage());
        }

        return to_route('sample-trackings.show', $sampleTracking)->with('success.flash', 'Solicitud de muestra actualizada correctamente');
    }

    public function destroy(SampleTracking $sampleTracking)
    {
        $sampleTracking->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $sampleTracking = SampleTracking::find($id);
            $sampleTracking?->delete();
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $sampleTracking = SampleTracking::with(['branch:id,name', 'contact:id,name', 'requester:id,name'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhereHas('requester', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $sampleTracking], 200);
    }

    public function authorizeSample(SampleTracking $sampleTracking)
    {
        $sampleTracking->update([
            'authorized_by_user_id' => auth()->id(),
            'authorized_at' => now(),
            'status' => 'Autorizado',
        ]);

        $sampleTracking->load('requester');

        // Generamos un folio legible para la notificación
        $sampleTracking_folio = 'MUE-' . str_pad($sampleTracking->id, 4, "0", STR_PAD_LEFT);
        
        if ($sampleTracking->requester) {
            $sampleTracking->requester->notify(new \App\Notifications\SampleAuthorizedNotification(
                'Seguimiento autorizado', // Título
                $sampleTracking_folio,      // Folio
                route('sample-trackings.show', $sampleTracking->id) // URL
            ));
        }

        return response()->json(['message' => 'Seguimiento de muestra autorizado', 'item' => $sampleTracking]);
    }

    public function updateStatus(Request $request, SampleTracking $sampleTracking)
    {
        // --- MODIFICADO: Se agrega 'comments' a la validación ---
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Aprobado', 'Enviado', 'Devuelto', 'Completado', 'Rechazado', 'Modificación'])],
            'comments' => 'nullable|string|max:1000',
        ]);

        $status = $validated['status'];
        
        // --- MODIFICADO: Se agrega 'approved_at' al mapeo ---
        $columnMap = [
            'Enviado' => 'sent_at',
            'Devuelto' => 'returned_at',
            'Completado' => 'completed_at',
            'Rechazado' => 'denied_at',
            'Aprobado' => 'approved_at',
        ];

        $sampleTracking->status = $status;
        
        // --- AGREGADO: Sobreescribir comentarios si se envían ---
        if (isset($validated['comments'])) {
            $sampleTracking->comments = $validated['comments'];
        }
        
        // Si el estatus tiene una columna de fecha asociada, la actualizamos
        if (array_key_exists($status, $columnMap)) {
            $column = $columnMap[$status];
            if ($column) { // Verificamos que la columna no sea un string vacío
                $sampleTracking->$column = now();
            }
        }

        $sampleTracking->save();
        
        return back()->with('success.flash', 'Estatus actualizado correctamente.');
    }
}
