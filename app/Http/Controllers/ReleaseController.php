<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\ReleaseItem; // Asegúrate de importar esto
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReleaseController extends Controller
{
    public function index()
    {
        $releases = Release::withCount('items') 
            ->orderByDesc('created_at')
            ->paginate(10);
            
        return Inertia::render('Releases/Index', [
            'releases' => $releases
        ]);
    }

    public function create()
    {
        return Inertia::render('Releases/Create');
    }

    public function store(Request $request)
    {
        // CAMBIO: Validación extendida para videos y gifs
        $validated = $request->validate([
            'version' => 'required|string|max:20',
            'title' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.module_name' => 'required|string',
            'items.*.description' => 'required|string',
            // Aceptamos imágenes y videos (mp4, webm) hasta 10MB
            'items.*.image' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,webm|max:10240', 
        ]);

        DB::transaction(function () use ($validated, $request) {
            $release = Release::create([
                'version' => $validated['version'],
                'title'   => $validated['title'],
                'is_published' => false 
            ]);

            foreach ($validated['items'] as $index => $itemData) {
                $item = $release->items()->create([
                    'module_name' => $itemData['module_name'],
                    'description' => $itemData['description'],
                    'order'       => $index,
                ]);

                if ($request->hasFile("items.{$index}.image")) {
                    $item->addMediaFromRequest("items.{$index}.image")
                         ->toMediaCollection('release-images');
                }
            }
        });

        return redirect()->route('admin.releases.index')
            ->with('success', 'Release creada correctamente.');
    }

    // NUEVO: Método Edit
    public function edit(Release $release)
    {
        // Si está publicada, no permitimos editar para mantener integridad histórica
        if ($release->is_published) {
            return back()->with('error', 'No puedes editar una actualización que ya ha sido publicada.');
        }

        // Cargamos la release con sus items y medios para el formulario
        $release->load(['items' => function($q) {
            $q->orderBy('order');
        }, 'items.media']);

        return Inertia::render('Releases/Edit', [
            'release' => $release
        ]);
    }

    // NUEVO: Método Update
    public function update(Request $request, Release $release)
    {
        if ($release->is_published) {
            return back()->with('error', 'No puedes editar una actualización publicada.');
        }

        $validated = $request->validate([
            'version' => 'required|string|max:20',
            'title' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer', // ID para identificar si existe
            'items.*.module_name' => 'required|string',
            'items.*.description' => 'required|string',
            'items.*.image' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,webm|max:10240',
        ]);

        DB::transaction(function () use ($validated, $request, $release) {
            // 1. Actualizar datos básicos
            $release->update([
                'version' => $validated['version'],
                'title'   => $validated['title'],
            ]);

            // 2. Sincronizar Items
            $submittedIds = [];
            
            foreach ($validated['items'] as $index => $itemData) {
                $itemId = $itemData['id'] ?? null;
                $item = null;

                if ($itemId) {
                    // Actualizar existente
                    $item = ReleaseItem::find($itemId);
                    $item->update([
                        'module_name' => $itemData['module_name'],
                        'description' => $itemData['description'],
                        'order'       => $index,
                    ]);
                } else {
                    // Crear nuevo
                    $item = $release->items()->create([
                        'module_name' => $itemData['module_name'],
                        'description' => $itemData['description'],
                        'order'       => $index,
                    ]);
                }
                
                $submittedIds[] = $item->id;

                // Actualizar imagen/video SOLO si se subió uno nuevo
                if ($request->hasFile("items.{$index}.image")) {
                    $item->clearMediaCollection('release-images'); // Borrar anterior
                    $item->addMediaFromRequest("items.{$index}.image")
                         ->toMediaCollection('release-images');
                }
            }

            // 3. Eliminar items que se quitaron del formulario
            $release->items()->whereNotIn('id', $submittedIds)->delete();
        });

        return redirect()->route('admin.releases.index')
            ->with('success', 'Release actualizada correctamente.');
    }

    public function publish(Release $release)
    {
        $release->publish();
        return back()->with('success', 'Actualización publicada y visible para usuarios.');
    }

    public function destroy(Release $release)
    {
        $release->delete();
        return back()->with('success', 'Actualización eliminada correctamente.');
    }

    public function markAsRead(Request $request, Release $release)
    {
        $user = $request->user();

        if (!$release->users()->where('user_id', $user->id)->exists()) {
            $release->users()->attach($user->id, [
                'read_at' => now()
            ]);
        }
        
        return back();
    }
}