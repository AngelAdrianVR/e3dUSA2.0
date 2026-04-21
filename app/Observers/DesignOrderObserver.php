<?php

namespace App\Observers;

use App\Models\DesignOrder;
use App\Models\PmsTask;

class DesignOrderObserver
{
    /**
     * Handle the DesignOrder "created" event.
     */
    public function created(DesignOrder $designOrder): void
    {
        // Si al crear la orden ya viene con un diseñador asignado
        if ($designOrder->designer_id !== null) {
            $this->createPmsTask($designOrder);
        }
    }

    /**
     * Handle the DesignOrder "updated" event.
     */
    public function updated(DesignOrder $designOrder): void
    {
        // Si la orden se actualizó y el campo designer_id cambió a un ID válido (se acaba de asignar)
        if ($designOrder->wasChanged('designer_id') && $designOrder->designer_id !== null) {
            $this->createPmsTask($designOrder);
        }
    }

    /**
     * Lógica reutilizable para crear la tarea en el PMS
     */
    private function createPmsTask(DesignOrder $designOrder): void
    {
        // Verificamos si ya existe para evitar duplicar tareas si la orden se actualiza varias veces
        $exists = PmsTask::where('sourceable_type', DesignOrder::class)
            ->where('sourceable_id', $designOrder->id)
            ->exists();

        if (!$exists) {
            PmsTask::create([
                'sourceable_type' => DesignOrder::class,
                'sourceable_id'   => $designOrder->id,
                'reference_url'   => route('design-orders.show', $designOrder->id), // <- Redirección al detalle de la orden
                'title'           => 'Orden de Diseño: ' . $designOrder->order_title,
                'description'     => $designOrder->specifications,
                'department'      => 'Diseño',
                'origin'          => 'Órdenes de Diseño', // Se puede ajustar al solicitante
                'priority'        => $designOrder->is_hight_priority ? 'Alta' : 'Media',
                'kanban_status'   => 'Pendiente',
                'start_date'      => now(),
                'due_date'        => $designOrder->due_date ?? now()->addDays(3), // Fecha compromiso original o default
                'responsible_id'  => $designOrder->designer_id,
                'created_by'      => $designOrder->requester_id ?? (auth()->id() ?? 1),
            ]);
        }
    }
}