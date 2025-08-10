<?php

namespace App\Http\Controllers;

use App\Http\Resources\MachineResource;
use App\Models\Machine;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    
    public function index(Request $request)
    {
        // Iniciamos la consulta con las relaciones necesarias
        $query = Machine::latest();

        // Aplicamos el filtro de búsqueda si existe
        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('supplier', 'like', "%{$search}%")
              ->orWhere('serial_number', 'like', "%{$search}%");
        });

        // Paginamos los resultados, obteniendo x por página
        // withQueryString() asegura que los links de paginación mantengan el parámetro de búsqueda
        $machines = $query->paginate(15)->withQueryString();
        
        return inertia('Machine/Index', [
            'machines' => $machines,
            'filters' => $request->only('search'), // Pasamos el filtro actual a la vista para mantenerlo en el input
        ]);
    }

    public function create()
    {
        return inertia('Machine/Create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'serial_number' => 'nullable',
            'wight' => 'nullable|numeric|min:1',
            'width' => 'nullable|numeric|min:1',
            'large' => 'nullable|numeric|min:1',
            'height' => 'nullable|numeric|min:1',
            'cost' => 'nullable|numeric|min:1',
            'supplier' => 'nullable|string',
            'adquisition_date' => 'nullable|date|before:tomorrow',
            'days_next_maintenance' => 'required|numeric|min:7',
        ]);

        $machine = Machine::create($request->all());

        $machine->addAllMediaFromRequest('images')
        ->each(function ($file) {
            $file->toMediaCollection('images');
        });

        return to_route('machines.index');
    }

    
    public function show(Machine $machine)
    {
        $machines = Machine::all(['id', 'name']);
        $machine->load('media', 'maintenances.media', 'spareParts.media');

        // return $machine;
        return inertia('Machine/Show', compact('machine', 'machines'));
    }

    public function edit(Machine $machine)
    {
        $media = $machine->getFirstMedia();

        // return $machine;
        return inertia('Machine/Edit', compact('machine', 'media'));
    }

    
    public function update(Request $request, Machine $machine)
    {
        $request->validate([
            'name' => 'required',
            'serial_number' => 'nullable',
            'wight' => 'nullable|numeric|min:1',
            'width' => 'nullable|numeric|min:1',
            'large' => 'nullable|numeric|min:1',
            'height' => 'nullable|numeric|min:1',
            'cost' => 'nullable|numeric|min:1',
            'supplier' => 'nullable|string',
            'adquisition_date' => 'nullable|date|before:tomorrow',
            'days_next_maintenance' => 'required|numeric|min:7',
        ]);

        $machine->update($request->all());
        
        $machine->addAllMediaFromRequest('images')
            ->each(function ($file) {
                $file->toMediaCollection('images');
        });

        return to_route('machines.index');
    }

    
    public function destroy(Machine $machine)
    {
        $machine->delete();

    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Machine::find($id);
            $bonus?->delete();
        }
    }

    public function uploadFiles(Request $request, Machine $machine)
    {
        $request->validate([
            'media' => 'required'
        ]);

        $machine->addAllMediaFromRequest()->each(fn ($file) => $file->toMediaCollection('files'));

       return to_route('machines.show', ['machine'=> $machine]);
    }

    public function QRSearchMachine(Request $request)
{
    $request->validate([ 
        'barCode' => 'required|string'
    ]);

    // Obtén el número de serie
    $serial_number = $request->barCode;

    // Verifica si el formato es incorrecto (contiene "'") y realiza el reemplazo solo en ese caso
    if (strpos($serial_number, "'") !== false) {
        $serial_number = str_replace("'", "-", $serial_number);
    }

    if (strpos($serial_number, "´") !== false) {
        $serial_number = str_replace("´", "-", $serial_number);
    }

    // Realiza la búsqueda en la base de datos
    $machine = Machine::with('maintenances', 'spareParts', 'media')->where('serial_number', $serial_number)->first();

    return response()->json(['item' => $machine]);
}

}
