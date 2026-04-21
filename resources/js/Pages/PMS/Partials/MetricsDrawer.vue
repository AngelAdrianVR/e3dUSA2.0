<template>
    <el-drawer :model-value="show" @update:model-value="$emit('update:show', $event)" title="Métricas y Carga de Trabajo" size="35%" direction="rtl">
        <div class="space-y-8 p-2">
            <!-- KPIs Generales -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-50 dark:bg-slate-800 p-4 rounded-xl border border-blue-100 dark:border-slate-700 flex flex-col justify-center items-center text-center shadow-sm">
                    <i class="fa-solid fa-list-check text-blue-400 text-2xl mb-2"></i>
                    <p class="text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">Tareas Activas</p>
                    <p class="text-3xl font-black text-gray-800 dark:text-gray-100">{{ activeTasks }}</p>
                </div>
                <div class="bg-red-50 dark:bg-slate-800 p-4 rounded-xl border border-red-100 dark:border-slate-700 flex flex-col justify-center items-center text-center shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-red-400 text-2xl mb-2"></i>
                    <p class="text-sm text-red-600 dark:text-red-400 font-semibold mb-1">Vencidas</p>
                    <p class="text-3xl font-black text-gray-800 dark:text-gray-100">{{ expiredTasksCount }}</p>
                </div>
            </div>

            <!-- Carga de Trabajo por Usuario -->
            <div>
                <h4 class="font-bold text-gray-700 dark:text-gray-200 mb-4 border-b-2 pb-2 dark:border-slate-600 flex justify-between items-end">
                    Carga por Colaborador
                    <span class="text-xs font-normal text-gray-500">Ordenado por saturación</span>
                </h4>
                
                <div class="space-y-3">
                    <div v-for="stat in workload" :key="stat.id" class="flex items-center justify-between bg-gray-50 dark:bg-slate-800/50 p-3 rounded-lg border border-gray-100 dark:border-slate-700">
                        <div class="flex items-center space-x-3">
                            <div class="size-9 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold">
                                {{ stat.name.substring(0, 2).toUpperCase() }}
                            </div>
                            <span class="text-sm text-gray-800 dark:text-gray-200 font-semibold">{{ stat.name }}</span>
                        </div>
                        <div class="flex space-x-2">
                            <el-tooltip content="Pendientes" placement="top">
                                <span class="px-2 py-1 bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-gray-300 text-xs rounded font-bold">{{ stat.pending }}</span>
                            </el-tooltip>
                            <el-tooltip content="En Proceso" placement="top">
                                <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-500 text-xs rounded font-bold">{{ stat.inProgress }}</span>
                            </el-tooltip>
                        </div>
                    </div>
                    
                    <div v-if="!workload.length" class="text-sm text-gray-500 py-6 text-center italic">
                        No hay tareas en proceso actualmente.
                    </div>
                </div>
            </div>
        </div>
    </el-drawer>
</template>

<script setup>
import { computed } from 'vue';
import { isPast, parseISO } from 'date-fns';

const props = defineProps({
    show: Boolean,
    tasks: Array
});

defineEmits(['update:show']);

const activeTasks = computed(() => {
    return props.tasks.filter(t => ['Pendiente', 'En proceso', 'Validación'].includes(t.kanban_status)).length;
});

const expiredTasksCount = computed(() => {
    return props.tasks.filter(t => {
        if (!t.due_date || ['Terminado', 'Validación'].includes(t.kanban_status)) return false;
        return isPast(parseISO(t.due_date));
    }).length;
});

// Calcula la carga de trabajo sumando pendientes y en proceso por usuario
const workload = computed(() => {
    const users = {};
    props.tasks.forEach(task => {
        if (task.responsible && ['Pendiente', 'En proceso'].includes(task.kanban_status)) {
            if (!users[task.responsible.id]) {
                users[task.responsible.id] = { id: task.responsible.id, name: task.responsible.name, pending: 0, inProgress: 0 };
            }
            if (task.kanban_status === 'Pendiente') users[task.responsible.id].pending++;
            if (task.kanban_status === 'En proceso') users[task.responsible.id].inProgress++;
        }
    });
    // Ordenar de mayor a menor carga
    return Object.values(users).sort((a, b) => (b.pending + b.inProgress) - (a.pending + a.inProgress));
});
</script>