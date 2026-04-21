<template>
    <div class="flex h-[calc(100vh-280px)] gap-6 overflow-x-auto custom-scrollbar pb-4">
        <!-- COLUMNA: BACKLOG (Sin Asignar) -->
        <div 
            class="min-w-[280px] w-72 bg-gray-100 dark:bg-slate-800 rounded-xl p-4 flex flex-col border border-dashed border-gray-300 dark:border-slate-600"
            @dragover.prevent 
            @drop="onDrop('Backlog')"
        >
            <div class="flex justify-between items-center mb-4 pb-2 border-b-2 border-gray-200 dark:border-slate-700">
                <h3 class="font-bold text-gray-600 dark:text-gray-400 text-sm tracking-wide flex items-center">
                    <i class="fa-solid fa-inbox mr-2"></i> POR ASIGNAR
                </h3>
                <span class="bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-gray-400 py-0.5 px-2.5 rounded-full text-xs font-bold shadow-inner">
                    {{ backlogTasks.length }}
                </span>
            </div>
            <div class="flex-1 overflow-y-auto space-y-3 pr-1 custom-scrollbar">
                <TaskCard v-for="task in backlogTasks" :key="task.id" :task="task" 
                          @dragstart="setDraggedTask" @card-click="$emit('task-click', $event)" />
                <div v-if="!backlogTasks.length" class="text-center text-gray-400 text-sm mt-10">
                    No hay tareas pendientes por asignar
                </div>
            </div>
        </div>

        <!-- COLUMNAS DEL PROCESO -->
        <div v-for="col in kanbanColumns" :key="col.status" 
             class="min-w-[300px] w-1/4 bg-gray-50 dark:bg-slate-800/60 rounded-xl p-4 flex flex-col border border-gray-200 dark:border-slate-700 shadow-sm"
             @dragover.prevent 
             @drop="onDrop(col.status)">
            <div class="flex justify-between items-center mb-4 pb-2 border-b-2" :class="col.borderColor">
                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-sm tracking-wide">
                    {{ col.label }}
                </h3>
                <span class="bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-slate-600 py-0.5 px-2.5 rounded-full text-xs font-bold shadow-sm">
                    {{ getTasksByStatus(col.status).length }}
                </span>
            </div>
            <div class="flex-1 overflow-y-auto space-y-3 pr-1 custom-scrollbar">
                <TaskCard v-for="task in getTasksByStatus(col.status)" :key="task.id" :task="task" 
                          @dragstart="setDraggedTask" @card-click="$emit('task-click', $event)" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import TaskCard from './TaskCard.vue';

const props = defineProps({
    tasks: Array,
});

// AÑADIDO: 'require-assignment' para interceptar la acción del Backlog
const emit = defineEmits(['update-status', 'task-click', 'require-assignment']);

const draggedTask = ref(null);

const kanbanColumns = [
    { label: 'PENDIENTES', status: 'Pendiente', borderColor: 'border-blue-300 dark:border-blue-600' },
    { label: 'EN PROCESO', status: 'En proceso', borderColor: 'border-yellow-400 dark:border-yellow-600' },
    { label: 'VALIDACIÓN', status: 'Validación', borderColor: 'border-orange-400 dark:border-orange-600' },
    { label: 'TERMINADO', status: 'Terminado', borderColor: 'border-green-400 dark:border-green-600' }
];

// Backlog = tareas sin responsable_id
const backlogTasks = computed(() => {
    return props.tasks.filter(t => !t.responsible_id);
});

// Kanban Normal = tareas con responsable, filtradas por estatus
const getTasksByStatus = (status) => {
    return props.tasks.filter(t => t.responsible_id && t.kanban_status === status);
};

const setDraggedTask = (task) => {
    draggedTask.value = task;
};

const onDrop = (targetStatus) => {
    if (!draggedTask.value) return;

    const task = draggedTask.value;
    
    // Ignorar si lo soltó en la misma columna donde estaba (y no es movimiento de backlog)
    if (task.kanban_status === targetStatus && task.responsible_id !== null && targetStatus !== 'Backlog') return;
    
    // Ignorar si intenta mover del Backlog al Backlog
    if (!task.responsible_id && targetStatus === 'Backlog') return;

    // NUEVO: Si mueve del Backlog a un proceso normal, solicitar asignación
    if (!task.responsible_id && targetStatus !== 'Backlog') {
        emit('require-assignment', { task, newStatus: targetStatus });
        draggedTask.value = null;
        return; // Interrumpimos el drop normal
    }

    emit('update-status', { task, newStatus: targetStatus });
    draggedTask.value = null;
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #475569;
}
</style>