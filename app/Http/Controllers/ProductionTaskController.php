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
        // Verificación de autorización: asegurar que el operador es dueño de la tarea o es un Admin/rol permitido.
        if ($production_task->operator_id !== Auth::id() && !Auth::user()->hasRole('Super Administrador') && !Auth::user()->hasRole('Jefe de producción') && !Auth::user()->hasRole('Samuel')) {
            return back()->withErrors('No tienes permiso para modificar esta tarea.');
        }

        $request->validate([
            'status' => 'required|string|in:En Proceso,Pausada,Terminada,Sin material',
            'good_units' => 'nullable|integer|min:0',
            'scrap' => 'nullable|integer|min:0',
            'scrap_reason' => 'nullable|string|max:255',
            'pause_reason' => 'nullable|string|max:255',
        ]);

        $newStatus = $request->input('status');
        $oldStatus = $production_task->status;

        // --- INICIO: VALIDACIÓN DE TAREA ÚNICA EN PROCESO ---
        // Se aplica al intentar poner CUALQUIER tarea en "En Proceso".
        if ($newStatus === 'En Proceso' && !Auth::user()->hasRole('Jefe de producción') && !Auth::user()->hasRole('Samuel')) {
            $hasTaskInProgress = ProductionTask::where('operator_id', Auth::id())
                                               ->where('status', 'En Proceso')
                                               ->exists();

            if ($hasTaskInProgress) {
                return back()->withErrors(['error' => 'Ya tienes una tarea "En Proceso". No puedes iniciar o reanudar otra.']);
            }
        }
        // --- FIN: VALIDACIÓN ---

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
            // --- VALIDACIÓN DE TIEMPO (BACKEND) ---
            // Se omite la validación si el usuario tiene uno de los roles especificados.
            if (!Auth::user()->hasRole('Super Administrador') && !Auth::user()->hasRole('Jefe de producción') && !Auth::user()->hasRole('Samuel')) {
                if ($production_task->started_at) {
                    // Se convierte la fecha de inicio (que puede ser un string) a un objeto Carbon para asegurar un cálculo correcto.
                    $startTime = new Carbon($production_task->started_at);
                    
                    // Se calcula la diferencia de tiempo entre ahora y el inicio.
                    $elapsedMinutes = $startTime->diffInMinutes(Carbon::now());
                    $requiredMinutes = $production_task->estimated_time_minutes / 2;

                    if ($elapsedMinutes < $requiredMinutes) {
                        $remainingMinutes = ceil($requiredMinutes - $elapsedMinutes);
                        return back()->withErrors(['error' => "No se puede finalizar. Debes esperar al menos {$remainingMinutes} minuto(s) más."]);
                    }
                } else {
                    return back()->withErrors(['error' => 'No se puede finalizar una tarea que no ha sido iniciada.']);
                }
            }
            // --- FIN DE LA VALIDACIÓN ---

            $production_task->finished_at = Carbon::now();
            // También actualizar cantidades en la producción si se proporcionan
            $production = $production_task->production;
            if ($request->has('good_units')) {
                $production->good_units = $request->input('good_units');
            }
            if ($request->has('scrap')) {
                $production->scrap += $request->input('scrap');
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
            $reason = $request->input('pause_reason', 'No especificada');
            $notes = "El operador ha pausado la tarea '" . $production_task->name . "'. Razón: {$reason}";
        } elseif ($newStatus === 'En Proceso' && $oldStatus === 'Pausada') {
            $logType = 'reanudacion';
            $notes = 'El operador ha reanudado la tarea "' . $production_task->name . '".';
        } elseif ($newStatus === 'Terminada') {
            $logType = 'progreso';
            $notes = "Tarea '" . $production_task->name . "' finalizada. Unidades buenas: {$request->input('good_units', 0)}, Merma: {$request->input('scrap', 0)}.";
            if ($request->input('scrap', 0) > 0 && $request->filled('scrap_reason')) {
                 $notes .= " Razón: {$request->input('scrap_reason')}";
            }
        } elseif ($newStatus === 'Sin material') {
            $logType = 'alerta';
            $notes = 'El operador reportó falta de material.';
        }

        if ($logType) {
            ProductionLog::create([
                'production_id' => $production_task->production_id,
                'user_id' => $production_task->operator_id,
                'type' => $logType,
                'notes' => $notes,
            ]);
        }

        return back();
    }

    /**
     * MÉTODO NUEVO: Recupera los detalles completos de una tarea para la vista expandida.
     * ? Puede usarse en un futuro para mostrar tarifas de envios, empaques, etc
     */
    public function getTaskDetails(ProductionTask $task)
    {
        // Valida que el operador solo pueda ver sus propias tareas
        if ($task->operator_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $task->load([
            // Carga los componentes del producto asociado a la tarea
            'production.saleProduct.product.components' => function ($query) {
                $query->with(['storages', 'media']); // Carga el stock y media de cada componente
            },
            // Carga los avances de producción (asumiendo que tienes esta relación)
            // 'production.advancements',
        ]);

        return response()->json($task);
    }
}
