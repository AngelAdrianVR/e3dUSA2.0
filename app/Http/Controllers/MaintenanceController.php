<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\Maintenance;
use App\Models\SparePart;
use Illuminate\Support\Facades\DB; // Importar para usar transacciones
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Notifications\NewMaintenanceNotification;
use App\Notifications\ValidationRequiredNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class MaintenanceController extends Controller
{

    public function create($selectedMachine)
    {
        $machine = Machine::find($selectedMachine);
        $spare_parts = SparePart::where('machine_id', $machine->id)->get(['id', 'name', 'quantity']);

        return inertia('Maintenance/Create', compact('machine', 'spare_parts'));
    }

    public function store(Request $request)
    {
        // 1. VALIDACIÓN
        $validatedData = $request->validate([
            'maintenance_type' => 'required|string|in:Preventivo,Correctivo,Limpieza',
            'problems'         => $request->maintenance_type == 'Correctivo' ? 'required|string' : 'nullable|string',
            'actions'          => 'required|string',
            'cost'             => 'required|numeric|min:0',
            'responsible'      => 'required|string',
            'machine_id'       => 'required|integer|exists:machines,id',
            'maintenance_date' => 'required|date',
            'media'            => 'nullable|array',
            'media.*'          => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:4096',

            // Validación para las refacciones utilizadas
            'spare_parts_used'           => 'nullable|array',
            'spare_parts_used.*.id'      => 'required|integer|exists:spare_parts,id',
            'spare_parts_used.*.quantity'=> 'required|integer|min:1',
        ]);

            // Notificacion
            // 1. Buscamos a todos los usuarios con los roles deseados.
            //    Asegúrate de que los nombres de los roles coincidan exactamente.
            $usersToNotify = User::role(['Super Administrador', 'Mantenimiento'])->get();
            $machine = Machine::find($request->machine_id);

            // 2. Verificamos si encontramos usuarios antes de enviar.
            if ($usersToNotify->isNotEmpty()) {
                // 3. Usamos el Facade 'Notification' para enviar a la colección de usuarios.
                Notification::send($usersToNotify, new NewMaintenanceNotification(
                    $machine->name, // Ya tenemos la máquina de la notificación anterior
                    $validatedData['maintenance_type'],
                    route('machines.show', ['machine' => $machine->id])
                ));
            }

        // 2. LÓGICA DE BASE DE DATOS CON TRANSACCIÓN
        // Usamos una transacción para asegurar que todas las operaciones (crear mantenimiento y actualizar stock)
        // se completen con éxito. Si algo falla, todo se revierte.
        try {
            DB::beginTransaction();

            // Crear el mantenimiento
            $maintenance = Maintenance::create($validatedData);

            // Procesar las refacciones utilizadas para descontar el stock
            if (!empty($validatedData['spare_parts_used'])) {
                foreach ($validatedData['spare_parts_used'] as $part_used) {
                    $spare_part = SparePart::find($part_used['id']);
                    
                    // Validar si hay suficiente stock antes de descontar
                    if ($spare_part && $spare_part->quantity >= $part_used['quantity']) {
                        // El método decrement es atómico y seguro para operaciones concurrentes
                        $spare_part->decrement('quantity', $part_used['quantity']);
                    } else {
                        // Si no hay stock, se lanza una excepción para revertir la transacción
                        throw new \Exception('No hay suficiente stock para la refacción: ' . $spare_part->name);
                    }
                }
            }
            
            // Guardar los archivos si se subieron
            if ($request->hasFile('media')) {
                $maintenance->addAllMediaFromRequest('media')->each(fn($file) => $file->toMediaCollection());
            }

            // Si todo salió bien, confirmamos los cambios en la base de datos
            DB::commit();

        } catch (\Exception $e) {
            // Si algo falló, revertimos todos los cambios
            DB::rollBack();

            // Redirigir hacia atrás con un mensaje de error
            return back()->withErrors(['error' => 'Ocurrió un error al registrar el mantenimiento: ' . $e->getMessage()]);
        }

        // 3. REDIRECCIÓN
        return redirect()->route('machines.show', ['machine' => $request->machine_id])
                         ->with('success', 'Mantenimiento registrado con éxito.');
    }

    public function show(Maintenance $maintenance)
    {
        //
    }


    public function edit(Maintenance $maintenance)
    {
        $maintenance->load('machine', 'media');
        $spare_parts = SparePart::where('machine_id', $maintenance->machine_id)->get(['id', 'name', 'quantity']);

        return inertia('Maintenance/Edit', compact('maintenance', 'spare_parts'));
    }

    public function update(Request $request, Maintenance $maintenance)
    {
        // 1. VALIDACIÓN
        $validatedData = $request->validate([
            'maintenance_type' => 'required|string|in:Preventivo,Correctivo,Limpieza',
            'problems'         => $request->maintenance_type == 'Correctivo' ? 'required|string' : 'nullable|string',
            'actions'          => 'required|string',
            'cost'             => 'required|numeric|min:0',
            'responsible'      => 'required|string',
            'machine_id'       => 'required|integer|exists:machines,id',
            'maintenance_date' => 'required|date',
            'media'            => 'nullable|array',
            'media.*'          => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:4096',
            'spare_parts_used' => 'nullable|array',
            'spare_parts_used.*.id' => 'required_with:spare_parts_used|integer|exists:spare_parts,id',
            'spare_parts_used.*.quantity' => 'required_with:spare_parts_used|integer|min:1',
        ]);

        // 2. LÓGICA DE ACTUALIZACIÓN DE STOCK CON TRANSACCIÓN
        try {
            DB::beginTransaction();

            // --- A. Revertir el stock del mantenimiento anterior ---
            $old_parts = $maintenance->spare_parts_used ?? [];
            if (!empty($old_parts)) {
                foreach ($old_parts as $part_data) {
                    SparePart::find($part_data['id'])->increment('quantity', $part_data['quantity']);
                }
            }

            // --- B. Descontar el stock del nuevo registro ---
            $new_parts = $validatedData['spare_parts_used'] ?? [];
            if (!empty($new_parts)) {
                foreach ($new_parts as $part_data) {
                    $spare_part = SparePart::find($part_data['id']);
                    if ($spare_part->quantity < $part_data['quantity']) {
                        // Si no hay stock suficiente, lanza una excepción para detener y revertir todo.
                        throw ValidationException::withMessages([
                            'spare_parts_used' => 'No hay suficiente stock para la refacción: ' . $spare_part->name . 
                                                  '. Disponibles: ' . $spare_part->quantity . 
                                                  ', Requeridas: ' . $part_data['quantity'],
                        ]);
                    }
                    $spare_part->decrement('quantity', $part_data['quantity']);
                }
            }

            // --- C. Actualizar el registro de mantenimiento ---
            $maintenance->update($validatedData);
            
            // --- D. Añadir nuevos archivos si existen ---
            if ($request->hasFile('media')) {
                $maintenance->addAllMediaFromRequest('media')->each(fn($file) => $file->toMediaCollection());
            }

            // Si todo fue exitoso, confirma los cambios.
            DB::commit();

        } catch (\Exception $e) {
            // Si algo falló, revierte todos los cambios.
            DB::rollBack();

            // Redirige con el error. Si fue un error de validación, lo mostrará en el campo correcto.
            return back()->withErrors(['error' => 'Ocurrió un error al actualizar: ' . $e->getMessage()]);
        }

        // 3. REDIRECCIÓN
        return redirect()->route('machines.show', ['machine' => $request->machine_id])
                         ->with('success', 'Mantenimiento actualizado con éxito.');
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
