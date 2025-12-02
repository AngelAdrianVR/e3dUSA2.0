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
use App\Models\Contact; // Importar el modelo Contact
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
        $branches = Branch::with(['contacts:id,name,contactable_id,contactable_type'])->get(['id', 'name']);
        $products = Product::where('product_type', 'Catálogo')->whereNull('archived_at')->get(['id', 'name']); 

        return Inertia::render('SampleTracking/Create', compact('branches', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => [
                'required',
                'exists:contacts,id',
                function ($attribute, $value, $fail) use ($request) {
                    $contact = Contact::find($value);
                    if (!$contact || $contact->contactable_id != $request->branch_id || $contact->contactable_type !== Branch::class) {
                        $fail('El contacto seleccionado no pertenece al cliente/prospecto seleccionado.');
                    }
                },
            ],
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
            DB::transaction(function () use ($validated, $request) {
                $sampleTracking = SampleTracking::create([
                    'name' => $validated['name'],
                    'branch_id' => $validated['branch_id'],
                    'contact_id' => $validated['contact_id'],
                    'requester_user_id' => auth()->id(),
                    'will_be_returned' => $validated['will_be_returned'],
                    'expected_devolution_date' => $validated['expected_devolution_date'] ?? null,
                    'comments' => $validated['comments'] ?? null,
                    'status' => 'Pendiente',
                ]);
    
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

                if ($sampleTracking) {
                    $usersToNotify = User::role('Super Administrador')->get();
                    
                    if ($usersToNotify->isNotEmpty()) {
                        FacadesNotification::send($usersToNotify, new NewSampleTrackingNotification($sampleTracking));
                    }
                }
            });

        } catch (\Exception $e) {
            return back()->withErrors('Ocurrió un error inesperado al guardar la solicitud: ' . $e->getMessage());
        }

        return to_route('sample-trackings.index')->with('success.flash', 'Solicitud de muestra creada correctamente');
    }

    public function show(SampleTracking $sampleTracking)
    {
        // 1. Cargamos 'items.itemable.media' para optimizar la consulta y traer las imagenes
        $sampleTracking->load(['branch', 'contact.details', 'requester', 'items.itemable.media']);

        $sampleTracking->items->each(function ($item) {
            $imageUrl = null;
            
            // Verificamos si existe el itemable y si tiene el trait de media library o método getFirstMediaUrl
            if ($item->itemable && method_exists($item->itemable, 'getFirstMediaUrl')) {
                // Obtenemos la URL directamente. Si no hay imagen, devolverá string vacío o null.
                // Usamos 'images' ya que vimos en el store que ahí se guardan.
                $imageUrl = $item->itemable->getFirstMediaUrl('images');
            }

            $item->image_url = $imageUrl;
        });

        return Inertia::render('SampleTracking/Show', [
            'sampleTracking' => $sampleTracking,
        ]);
    }

    public function edit(SampleTracking $sampleTracking)
    {
        if ($sampleTracking->authorized_at) {
            return redirect()->route('sample-trackings.show', $sampleTracking)
                ->with('error.flash', 'No se puede editar una solicitud que ya ha sido autorizada.');
        }

        $sampleTracking->load('items.itemable');

        // --- CORREGIDO: Cargar contactos con la relación polimórfica ---
        $branches = Branch::with(['contacts:id,name,contactable_id,contactable_type'])->get(['id', 'name']);
        $products = Product::where('product_type', 'Catálogo')->get(['id', 'name']);

        return Inertia::render('SampleTracking/Edit', compact('sampleTracking', 'branches', 'products'));
    }

    public function update(Request $request, SampleTracking $sampleTracking)
    {
        if ($sampleTracking->authorized_at) {
             return redirect()->route('sample-trackings.show', $sampleTracking)
                ->with('error.flash', 'No se puede editar una solicitud que ya ha sido autorizada.');
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => [
                'required',
                'exists:contacts,id',
                function ($attribute, $value, $fail) use ($request) {
                    $contact = Contact::find($value);
                    if (!$contact || $contact->contactable_id != $request->branch_id || $contact->contactable_type !== Branch::class) {
                        $fail('El contacto seleccionado no pertenece al cliente/prospecto seleccionado.');
                    }
                },
            ],
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
                $sampleTracking->update([
                    'name' => $validated['name'],
                    'branch_id' => $validated['branch_id'],
                    'contact_id' => $validated['contact_id'],
                    'will_be_returned' => $validated['will_be_returned'],
                    'expected_devolution_date' => $validated['expected_devolution_date'] ?? null,
                    'comments' => $validated['comments'] ?? null,
                ]);

                $sampleTracking->items()->delete();
    
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

        $sampleTracking = SampleTracking::with(['branch:id,name', 'contact:id,name', 'requester:id,name'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
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

        $sampleTracking_folio = 'MUE-' . str_pad($sampleTracking->id, 4, "0", STR_PAD_LEFT);
        
        if ($sampleTracking->requester) {
            $sampleTracking->requester->notify(new \App\Notifications\SampleAuthorizedNotification(
                'Seguimiento autorizado',
                $sampleTracking_folio,
                route('sample-trackings.show', $sampleTracking->id)
            ));
        }

        return response()->json(['message' => 'Seguimiento de muestra autorizado', 'item' => $sampleTracking]);
    }

    public function updateStatus(Request $request, SampleTracking $sampleTracking)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Aprobado', 'Enviado', 'Devuelto', 'Completado', 'Rechazado', 'Modificación'])],
            'comments' => 'nullable|string|max:1000',
        ]);

        $status = $validated['status'];
        
        $columnMap = [
            'Enviado' => 'sent_at',
            'Devuelto' => 'returned_at',
            'Completado' => 'completed_at',
            'Rechazado' => 'denied_at',
            'Aprobado' => 'approved_at',
        ];

        $sampleTracking->status = $status;
        
        if (isset($validated['comments'])) {
            $sampleTracking->comments = $validated['comments'];
        }
        
        if (array_key_exists($status, $columnMap)) {
            $column = $columnMap[$status];
            if ($column) {
                $sampleTracking->$column = now();
            }
        }

        $sampleTracking->save();
        
        return back()->with('success.flash', 'Estatus actualizado correctamente.');
    }

}
