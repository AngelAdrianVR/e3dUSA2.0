<?php

namespace App\Observers;

use App\Models\ProductionTask;
use App\Models\PmsTask;

class ProductionTaskObserver
{
    /**
     * Handle the ProductionTask "created" event.
     */
    public function created(ProductionTask $productionTask): void
    {
        $this->createPmsTask($productionTask);
    }

    /**
     * Handle the ProductionTask "deleted" event.
     */
    public function deleted(ProductionTask $productionTask): void
    {
        // Buscamos y eliminamos la tarea del PMS asociada a esta tarea de producción
        PmsTask::where('sourceable_type', ProductionTask::class)
            ->where('sourceable_id', $productionTask->id)
            ->delete();
    }

    /**
     * Lógica para crear el reflejo de la tarea en el PMS
     */
    private function createPmsTask(ProductionTask $productionTask): void
    {
        $exists = PmsTask::where('sourceable_type', ProductionTask::class)
            ->where('sourceable_id', $productionTask->id)
            ->exists();

        if (!$exists) {
            // Obtenemos el sale_id a través de las relaciones (ProductionTask -> Production -> SaleProduct)
            // Usamos null safe operator y coalescencia por si alguna producción no estuviera ligada
            $saleId = $productionTask->production->saleProduct->sale_id ?? 'N/A';
            $productionFolio = 'OV-' . $saleId;

            PmsTask::create([
                'sourceable_type' => ProductionTask::class,
                'sourceable_id'   => $productionTask->id,
                'reference_url'   => route('productions.index'), // <- Te mandará al index como lo hace tu notificación
                'title'           => $productionTask->name . ' (' . $productionFolio . ')',
                'description'     => 'Tarea de producción asignada a operador.',
                'department'      => 'Producción',
                'origin'          => 'Módulo de Producción',
                'priority'        => 'Media', // Podrías derivarla de otro campo si lo necesitas
                'kanban_status'   => 'Pendiente',
                'start_date'      => now(),
                // CORRECCIÓN AQUÍ: Se convierte a entero (int) para evitar el error de Carbon
                'due_date'        => now()->addMinutes((int) $productionTask->estimated_time_minutes), 
                'responsible_id'  => $productionTask->operator_id,
                'created_by'      => auth()->id() ?? 1, // Quien haya hecho el store()
            ]);
        }
    }
}