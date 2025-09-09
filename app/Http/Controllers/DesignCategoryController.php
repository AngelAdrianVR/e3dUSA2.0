<?php

namespace App\Http\Controllers;

use App\Models\DesignCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignCategoryController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:design_categories,name',
            'complexity' => ['required', Rule::in(['Simple', 'Medio', 'Complejo'])],
        ]);

        DesignCategory::create($validated);

        // Al ser una petición de Inertia desde un modal, redirigir atrás
        // actualizará las props de la página en la que te encuentras.
        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function show(DesignCategory $designCategory)
    {
        //
    }

    public function edit(DesignCategory $designCategory)
    {
        //
    }

    public function update(Request $request, DesignCategory $designCategory)
    {
        //
    }

    public function destroy(DesignCategory $designCategory)
    {
        //
    }
}
