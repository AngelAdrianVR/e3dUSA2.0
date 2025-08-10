<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\Maintenance;
use App\Models\User;
use App\Notifications\ValidationRequiredNotification;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{

    public function index()
    {
        //
    }

    public function create($selectedMachine)
    {
        $machine = Machine::find($selectedMachine);

        return inertia('Maintenance/Create', compact('machine'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'maintenance_type' => 'required',
            'problems' => $request->maintenance_type == 'Correctivo' ? 'required' : 'nullable' . '|string',
            'actions' => 'required|string',
            'cost' => 'required|numeric|min:0',
            'responsible' => 'required|string',
            'machine_id' => 'required|numeric',
            'maintenance_date' => 'required|date',
        ]);

            // notificar a maribel para que valide limpieza
            // $machine = Machine::find($request->machine_id);
            // $maribel = User::find(3);
            // $maribel->notify(
            //     new ValidationRequiredNotification(
            //         'trabajo de limpieza de máquina',
            //         route('machines.show', $request->machine_id),
            //         $machine->name
            //     )
            // );

        $maintenance = Maintenance::create($request->all());
        $maintenance->addAllMediaFromRequest()->each(fn($file) => $file->toMediaCollection());

        return redirect()->route('machines.show', ['machine' => $request->machine_id]);
    }

    public function show(Maintenance $maintenance)
    {
        //
    }


    public function edit(Maintenance $maintenance)
    {
        $maintenance->load('machine', 'media');

        return inertia('Maintenance/Edit', compact('maintenance'));
    }


    public function update(Request $request, Maintenance $maintenance)
    {
        $request->validate([
            'maintenance_type' => 'required',
            'problems' => $request->maintenance_type == 'Correctivo' ? 'required' : 'nullable' . '|string',
            'actions' => 'required|string',
            'cost' => 'required|numeric|min:0',
            'responsible' => 'required|string',
            'machine_id' => 'required|numeric',
            'maintenance_date' => 'required|date',
        ]);

        $maintenance->update($request->all());
        $maintenance->addAllMediaFromRequest()->each(fn($file) => $file->toMediaCollection());

        return redirect()->route('machines.show', ['machine' => $request->machine_id]);
    }
    
    public function destroy(Maintenance $maintenance)
    {
        $maintenance->delete();
    }
   
    public function validateWork(Maintenance $maintenance)
    {
        $maintenance->update([
            'validated_by' => auth()->user()->name,
            'validated_at' => now(),
        ]);
    }
}
