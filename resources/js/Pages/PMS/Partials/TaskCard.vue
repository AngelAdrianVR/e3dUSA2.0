<template>
    <div
        draggable="true"
        @dragstart="onDragStart"
        @click="$emit('card-click', task)"
        class="rounded-lg shadow-sm cursor-grab active:cursor-grabbing hover:shadow-md transition-all duration-200 relative overflow-hidden group"
        :class="highPriorityStyles"
    >
        <!-- Borde izquierdo de prioridad -->
        <div :class="priorityColor" class="absolute left-0 top-0 bottom-0 w-1"></div>

        <div class="p-3 pl-4">
            <div class="flex justify-between items-start mb-2">
                <span :class="deptColor" class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-sm text-white tracking-wide shadow-sm">
                    {{ task.department }}
                </span>
                <span class="text-xs font-semibold" :class="task.priority === 'Alta' ? 'text-red-700 dark:text-red-300' : 'text-gray-500 dark:text-gray-400'">
                    {{ task.folio }}
                </span>
            </div>

            <h4 class="text-sm font-bold text-gray-800 dark:text-gray-100 leading-tight mb-1 line-clamp-2">
                {{ task.title }}
            </h4>
            
            <!-- Descripción truncada a 2 líneas -->
            <p v-if="task.description" class="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-2 leading-tight">
                {{ task.description }}
            </p>

            <div class="flex items-center justify-between mt-3">
                <div class="flex items-center space-x-2">
                    <el-tooltip v-if="task.responsible" :content="task.responsible.name" placement="top">
                        <img v-if="task.responsible.profile_photo_url" 
                             class="size-6 rounded-full object-cover border border-blue-200 dark:border-blue-700 shadow-sm"
                             :src="task.responsible.profile_photo_url" 
                             :alt="task.responsible.name">
                        <div v-else class="size-6 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300 text-[10px] font-bold border border-blue-200 dark:border-blue-700">
                            {{ task.responsible.name.substring(0, 2).toUpperCase() }}
                        </div>
                    </el-tooltip>
                    <el-tooltip v-else content="Sin asignar" placement="top">
                        <div class="size-6 rounded-full border border-dashed border-gray-400 dark:border-gray-500 flex items-center justify-center text-gray-400 dark:text-gray-500 text-xs bg-gray-50 dark:bg-slate-600">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                    </el-tooltip>
                </div>

                <div class="flex items-center space-x-2 text-xs">
                    <a v-if="task.reference_url" :href="task.reference_url" target="_blank" @click.stop title="Ver origen" class="text-blue-500 hover:text-blue-700 transition">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                    
                    <span v-if="task.media && task.media.length" class="text-gray-400" title="Contiene evidencia">
                        <i class="fa-solid fa-paperclip"></i> <span class="text-[10px] font-bold ml-0.5">{{ task.media.length }}</span>
                    </span>

                    <span :class="isExpired ? 'text-red-500 font-semibold' : (task.priority === 'Alta' ? 'font-semibold' : 'text-gray-500 dark:text-gray-400')" class="text-xs">
                        Fecha límite:
                        {{ formattedDueDate }}
                    </span>
                </div>
            </div>

            <!-- Tiempos Operativos (Inicio / Finalizado) -->
            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-slate-600/50 flex flex-col space-y-1 text-[10px] text-gray-500 dark:text-gray-400">
                <span v-if="task.start_date">
                    <span class="font-bold text-gray-600 dark:text-gray-300">Inicio:</span> {{ formatFullDate(task.start_date) }}
                </span>
                <span v-if="task.kanban_status === 'Terminado' && task.finished_at" class="text-green-600 dark:text-green-400">
                    <span class="font-bold">Finalizado:</span> {{ formatFullDate(task.finished_at) }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { format, isPast, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    task: Object
});

const emit = defineEmits(['dragstart', 'card-click']);

const onDragStart = (event) => {
    emit('dragstart', props.task);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', props.task.id);
};

const formattedDueDate = computed(() => {
    if (!props.task.due_date) return 'Sin fecha';
    return format(parseISO(props.task.due_date), "d MMM YYY", { locale: es });
});

const formatFullDate = (dateStr) => {
    if (!dateStr) return '';
    return format(parseISO(dateStr), "d MMM yyyy, HH:mm", { locale: es });
};

const isExpired = computed(() => {
    if (!props.task.due_date || ['Terminado', 'Validación'].includes(props.task.kanban_status)) return false;
    return isPast(parseISO(props.task.due_date));
});

// Resaltar de manera visual y muy llamativa la prioridad Alta
const highPriorityStyles = computed(() => {
    if (props.task.priority === 'Alta') {
        return 'bg-red-50 dark:bg-red-900/10 border border-red-300 ring-1 ring-red-400 dark:border-red-600';
    }
    return 'bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600';
});

const priorityColor = computed(() => {
    const map = {
        'Alta': 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.6)]', // Animación / Sombra distintiva
        'Media': 'bg-yellow-400',
        'Baja': 'bg-blue-400'
    };
    return map[props.task.priority] || 'bg-gray-400';
});

const deptColor = computed(() => {
    const map = {
        'Producción': 'bg-blue-600',
        'Ventas': 'bg-green-600',
        'Administración': 'bg-yellow-500',
        'Diseño': 'bg-red-600',
        'General': 'bg-gray-600'
    };
    return map[props.task.department] || 'bg-gray-600';
});
</script>