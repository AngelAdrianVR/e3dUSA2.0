<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductionTask;
use App\Models\ProductionLog;
use App\Models\StockMovement;
use App\Models\Product; // Asegúrate de importar el modelo Product
use App\Models\Storage; // Asegúrate de importar el modelo Storage
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'partial_units' => 'nullable|integer|min:0',
            'scrap_items' => 'nullable|array',
            'scrap_items.*.product_id' => 'required|exists:products,id',
            'scrap_items.*.quantity' => 'required|integer|min:1',
        ]);

        // Usamos una transacción para asegurar que la tarea y el inventario se actualicen juntos
        DB::beginTransaction();

        try {
            $newStatus = $request->input('status');
            $oldStatus = $production_task->status;

            // --- INICIO: VALIDACIÓN DE TAREA ÚNICA EN PROCESO ---
            if ($newStatus === 'En Proceso' && !Auth::user()->hasRole('Jefe de producción') && !Auth::user()->hasRole('Samuel')) {
                $hasTaskInProgress = ProductionTask::where('operator_id', Auth::id())
                                                   ->where('status', 'En Proceso')
                                                   ->exists();

                if ($hasTaskInProgress) {
                    DB::rollBack();
                    return back()->withErrors(['error' => 'Ya tienes una tarea "En Proceso". No puedes iniciar o reanudar otra.']);
                }
            }
            // --- FIN: VALIDACIÓN ---

            // Prevenir transiciones de estado inválidas
            if ($oldStatus === 'Terminada' || $oldStatus === 'Cancelada') {
                DB::rollBack();
                return back()->withErrors('Esta tarea ya ha sido finalizada o cancelada y no puede cambiar de estado.');
            }

            // --- Actualizar Estado y Timestamps ---
            $production_task->status = $newStatus;

            if ($newStatus === 'En Proceso' && is_null($production_task->started_at)) {
                $production_task->started_at = Carbon::now();
            }

            // --- MANEJO DE UNIDADES PARCIALES AL PAUSAR O SIN MATERIAL ---
            if (in_array($newStatus, ['Pausada', 'Sin material'])) {
                $partialUnits = (int) $request->input('partial_units', 0);
                if ($partialUnits > 0) {
                    $production = $production_task->production;
                    $production->registerProductionProgress($partialUnits, 0, null);
                }
            }

            if ($newStatus === 'Terminada') {
                // --- VALIDACIÓN DE TIEMPO (BACKEND) ---
                if (!Auth::user()->hasRole('Super Administrador') && !Auth::user()->hasRole('Jefe de producción') && !Auth::user()->hasRole('Samuel')) {
                    if ($production_task->started_at) {
                        $startTime = new Carbon($production_task->started_at);
                        $elapsedMinutes = $startTime->diffInMinutes(Carbon::now());
                        $requiredMinutes = $production_task->estimated_time_minutes / 2;

                        if ($elapsedMinutes < $requiredMinutes) {
                            $remainingMinutes = ceil($requiredMinutes - $elapsedMinutes);
                            DB::rollBack();
                            return back()->withErrors(['error' => "No se puede finalizar. Debes esperar al menos {$remainingMinutes} minuto(s) más."]);
                        }
                    } else {
                        DB::rollBack();
                        return back()->withErrors(['error' => 'No se puede finalizar una tarea que no ha sido iniciada.']);
                    }
                }

                $production_task->finished_at = Carbon::now();
                
                // Actualizar cantidades de progreso
                $production = $production_task->production;
                $production->registerProductionProgress(
                    (int) $request->input('good_units', 0),
                    (int) $request->input('scrap', 0),
                    $request->input('scrap_reason')
                );

                // --- NUEVO: DESCONTAR MERMA DE INVENTARIO Y REGISTRAR MOVIMIENTO ---
                if ($request->has('scrap_items') && !empty($request->scrap_items)) {
                    $productionFolio = 'OV/OS-' . str_pad($production->saleProduct->sale->id, 4, "0", STR_PAD_LEFT);
                    $notes = "Salida por merma en producción de {$productionFolio}. " . $request->input('scrap_reason', 'Reportado al finalizar tarea.');

                    foreach ($request->scrap_items as $scrapItem) {
                        $component = Product::find($scrapItem['product_id']);
                        $discountQuantity = $scrapItem['quantity'];

                        if ($component) {
                            // Buscar el almacén principal del componente (o crear uno genérico si no existe)
                            $componentStorage = $component->storages()->first();

                            if (!$componentStorage) {
                                // Si por alguna razón el producto no tiene un registro en la tabla storages, se crea uno
                                $componentStorage = $component->storages()->create([
                                    'quantity' => 0,
                                    'location' => 'Almacén Principal'
                                ]);
                            }

                            // Descontar la cantidad
                            $componentStorage->decrement('quantity', $discountQuantity);

                            // Registrar el movimiento de stock
                            StockMovement::create([
                                'product_id' => $component->id,
                                'storage_id' => $componentStorage->id,
                                'quantity_change' => $discountQuantity, // Usar negativo para salidas si así lo manejas, ajusta según tu lógica
                                'type' => 'Salida',
                                'notes' => $notes,
                                'user_id' => Auth::id() // Añadir el usuario si tu tabla StockMovement lo requiere
                            ]);
                        }
                    }
                }
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
                $partialUnits = (int) $request->input('partial_units', 0);
                $extraNote = $partialUnits > 0 ? " Además, se enviaron {$partialUnits} unidades terminadas al almacén." : "";
                $notes = "El operador ha pausado la tarea '" . $production_task->name . "'. Razón: {$reason}.{$extraNote}";
            } elseif ($newStatus === 'En Proceso' && $oldStatus === 'Pausada') {
                $logType = 'reanudacion';
                $notes = 'El operador ha reanudado la tarea "' . $production_task->name . '".';
            } elseif ($newStatus === 'Terminada') {
                $logType = 'progreso';
                $notes = "Tarea '" . $production_task->name . "' finalizada. Unidades buenas: {$request->input('good_units', 0)}, Merma total: {$request->input('scrap', 0)}.";
                if ($request->input('scrap', 0) > 0 && $request->filled('scrap_reason')) {
                     $notes .= " Razón: {$request->input('scrap_reason')}";
                }
            } elseif ($newStatus === 'Sin material') {
                $logType = 'alerta';
                $partialUnits = (int) $request->input('partial_units', 0);
                $extraNote = $partialUnits > 0 ? " Además, se enviaron {$partialUnits} unidades terminadas al almacén." : "";
                $notes = "El operador reportó falta de material.{$extraNote}";
            }

            if ($logType) {
                ProductionLog::create([
                    'production_id' => $production_task->production_id,
                    'user_id' => $production_task->operator_id,
                    'type' => $logType,
                    'notes' => $notes,
                ]);
            }

            DB::commit();
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            // Loguear el error si es necesario: \Log::error($e->getMessage());
            return back()->withErrors(['error' => 'Ocurrió un error al actualizar la tarea: ' . $e->getMessage()]);
        }
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