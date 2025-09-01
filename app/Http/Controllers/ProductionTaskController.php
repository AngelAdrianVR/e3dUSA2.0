<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductionTask;
use App\Models\ProductionLog;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ProductionTaskController extends Controller
{
    /**
     * Actualiza el estado de una tarea de producción.
     */
    public function updateStatus(Request $request, ProductionTask $production_task)
    {
        // Verificación de autorización: asegurar que el operador es dueño de la tarea o es un Admin.
        if ($production_task->operator_id !== Auth::id() && !Auth::user()->hasRole('Admin')) {
            return back()->withErrors('No tienes permiso para modificar esta tarea.');
        }

        $request->validate([
            'status' => 'required|string|in:En Proceso,Pausada,Terminada,Sin material',
            'good_units' => 'nullable|integer|min:0',
            'scrap' => 'nullable|integer|min:0',
            'scrap_reason' => 'nullable|string|max:255',
        ]);

        $newStatus = $request->input('status');
        $oldStatus = $production_task->status;

        // Prevenir transiciones de estado inválidas
        if ($oldStatus === 'Terminada' || $oldStatus === 'Cancelada') {
            return back()->withErrors('Esta tarea ya ha sido finalizada o cancelada y no puede cambiar de estado.');
        }

        // --- Actualizar Estado y Timestamps ---
        $production_task->status = $newStatus;

        if ($newStatus === 'En Proceso' && is_null($production_task->started_at)) {
            $production_task->started_at = Carbon::now();
        }

        if ($newStatus === 'Terminada') {
            $production_task->finished_at = Carbon::now();
            // También actualizar cantidades en la producción si se proporcionan
            $production = $production_task->production;
            if ($request->has('good_units')) {
                $production->good_units = $request->input('good_units');
            }
            if ($request->has('scrap')) {
                $production->scrap = $request->input('scrap');
                $production->scrap_reason = $request->input('scrap_reason');
            }
            $production->save();
        }

        $production_task->save();

        // actualiza el estatus de la produccion padre
        $production_task->production->updateStatusFromTasks();

        // --- Crear Log de Producción ---
        $logType = null;
        $notes = null;

        if ($newStatus === 'Pausada') {
            $logType = 'pausa';
            $notes = 'El operador ha pausado la tarea.';
        } elseif ($newStatus === 'En Proceso' && $oldStatus === 'Pausada') {
            $logType = 'reanudacion';
            $notes = 'El operador ha reanudado la tarea.';
        } elseif ($newStatus === 'Terminada') {
            $logType = 'progreso';
            $notes = "Tarea finalizada. Unidades buenas: {$request->input('good_units', 0)}, Merma: {$request->input('scrap', 0)}.";
            if ($request->input('scrap', 0) > 0) {
                 $notes .= " Razón: {$request->input('scrap_reason', 'N/A')}";
            }
        } elseif ($newStatus === 'Sin material') {
            $logType = 'alerta';
            $notes = 'El operador reportó falta de material.';
        }

        if ($logType) {
            ProductionLog::create([
                'production_id' => $production_task->production_id,
                'user_id' => Auth::id(),
                'type' => $logType,
                'notes' => $notes,
            ]);
        }

        return back();
    }
}
