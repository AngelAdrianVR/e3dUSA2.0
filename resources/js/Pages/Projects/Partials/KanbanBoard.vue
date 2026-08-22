<template>
    <div class="flex gap-4 overflow-x-auto custom-scrollbar pb-4">
        <div
            v-for="col in columns"
            :key="col.status"
            class="min-w-[270px] w-1/4 bg-gray-50 dark:bg-slate-800/60 rounded-xl p-3 flex flex-col border border-gray-200 dark:border-slate-700 shadow-sm"
            @dragover.prevent
            @drop="onDrop(col.status)"
        >
            <div class="flex justify-between items-center mb-3 pb-2 border-b-2" :class="col.borderColor">
                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-xs tracking-wide uppercase flex items-center gap-2">
                    <span class="size-2.5 rounded-full" :class="col.dotColor"></span>
                    {{ col.label }}
                </h3>
                <span class="bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-slate-600 py-0.5 px-2.5 rounded-full text-xs font-bold shadow-sm">
                    {{ getTasksByStatus(col.status).length }}
                </span>
            </div>

            <div class="flex-1 overflow-y-auto space-y-2.5 pr-1 custom-scrollbar min-h-[200px]">
                <TaskCard
                    v-for="task in getTasksByStatus(col.status)"
                    :key="task.id"
                    :task="task"
                    :can-drag="canDragTask(task)"
                    @drag-start="setDraggedTask"
                    @card-click="$emit('task-click', $event)"
                />
                <div
                    v-if="!getTasksByStatus(col.status).length"
                    class="text-center text-gray-400 text-xs py-8 border border-dashed border-gray-200 dark:border-slate-700 rounded-lg"
                >
                    <i class="fa-solid fa-arrow-right-to-bracket mb-1 block"></i>
                    Suelta tareas aquí
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import TaskCard from './TaskCard.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    memberRole: { type: String, default: null },
    currentUserId: { type: Number, default: null },
});

const emit = defineEmits(['update-status', 'task-click']);

const draggedTask = ref(null);

// ¿Puede arrastrar esta tarea?
// - Lectura y escritura: puede mover cualquier tarea.
// - Solo lectura: únicamente sus propias tareas.
const canDragTask = (task) => {
    if (props.canEdit) return true;
    return !!props.currentUserId && Number(task.assigned_to) === Number(props.currentUserId);
};

const columns = [
    { label: 'Pendiente', status: 'Pendiente', borderColor: 'border-blue-300 dark:border-blue-600', dotColor: 'bg-blue-400' },
    { label: 'En proceso', status: 'En proceso', borderColor: 'border-yellow-400 dark:border-yellow-600', dotColor: 'bg-yellow-400' },
    { label: 'Pausada', status: 'Pausada', borderColor: 'border-orange-400 dark:border-orange-600', dotColor: 'bg-orange-400' },
    { label: 'Terminada', status: 'Terminada', borderColor: 'border-green-400 dark:border-green-600', dotColor: 'bg-green-500' },
];

const getTasksByStatus = (status) => props.tasks.filter(t => t.status === status);

const setDraggedTask = (task) => {
    draggedTask.value = task;
};

const onDrop = (targetStatus) => {
    if (!draggedTask.value) return;

    const task = draggedTask.value;

    if (!canDragTask(task)) {
        ElMessage.warning('Solo puedes mover tus propias tareas.');
        draggedTask.value = null;
        return;
    }

    // Si lo suelta en la misma columna, no hace nada
    if (task.status === targetStatus) {
        draggedTask.value = null;
        return;
    }

    // Nueva posición = final de la columna destino
    const position = getTasksByStatus(targetStatus).filter(t => t.id !== task.id).length;

    emit('update-status', { task, newStatus: targetStatus, position });
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
