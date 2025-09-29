<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        // Cargamos la relación 'media' para poder mostrar las imágenes en el index.
        $reports = Report::with('user', 'media')->latest()->paginate(10);

        return Inertia::render('Report/Index', [
            'reports' => $reports,
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:Error,Sugerencia',
            // Validación para los archivos adjuntos (máximo 3, tipos de imagen, 2MB por archivo)
            'attachments' => 'nullable|array|max:3',
            // 'attachments.*' => 'image|mimes:jpeg,png,jpg,gif,txt,pdf|max:2048',
        ]);

        $report = Report::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'type' => $validated['type'],
            'status' => 'Pendiente', // Default status
        ]);

        // Si hay archivos, los asociamos al reporte creado.
        if ($request->hasFile('attachments')) {
            $report->addMultipleMediaFromRequest(['attachments'])
                   ->each(function ($fileAdder) {
                       $fileAdder->toMediaCollection('reports');
                   });
        }

        return back()->with('status', '¡Reporte enviado con éxito!');
    }

    public function show(Report $report)
    {
        //
    }

    public function edit(Report $report)
    {
        //
    }

    public function update(Request $request, Report $report)
    {
        $request->validate(['status' => 'required|in:Pendiente,Atendido']);
        
        $report->update(['status' => $request->status]);

        return back()->with('status', 'Estatus actualizado correctamente.');
    }

    public function destroy(Report $report)
    {
        //
    }
}
