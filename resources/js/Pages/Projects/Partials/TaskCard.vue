<template>
    <div
        draggable="true"
        class="group bg-white dark:bg-slate-800 rounded-lg p-3 shadow-sm border border-gray-200 dark:border-slate-700 border-l-4 cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
        :class="[meta.border, canDrag ? '' : 'cursor-default']"
        :title="canDrag ? 'Arrastra para cambiar de estatus' : 'Solo lectura'"
        @dragstart="onDragStart"
        @click="$emit('card-click', task)"
    >
        <div class="flex items-start justify-between gap-2">
            <h4 class="font-semibold text-sm text-gray-800 dark:text-gray-100 leading-snug line-clamp-2">{{ task.title }}</h4>
            <span class="text-[10px] font-bold text-white px-2 py-0.5 rounded-full whitespace-nowrap shrink-0" :class="meta.badge">
                {{ task.status }}
            </span>
        </div>

        <p v-if="task.description" class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ task.description }}</p>

        <div class="flex items-center justify-between mt-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1" title="Fechas de la tarea">
                <i class="fa-regular fa-calendar"></i>
                {{ formatDate(task.start_date) }}
                <template v-if="task.due_date">→ {{ formatDate(task.due_date) }}</template>
            </span>
            <div class="flex items-center gap-3">
                <span v-if="task.media?.length" class="flex items-center gap-1" :title="`${task.media.length} archivo(s)`">
                    <i class="fa-solid fa-paperclip"></i>{{ task.media.length }}
                </span>
                <span v-if="task.comments?.length" class="flex items-center gap-1" :title="`${task.comments.length} comentario(s)`">
                    <i class="fa-regular fa-comment"></i>{{ task.comments.length }}
                </span>
            </div>
        </div>

        <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-2 min-w-0">
                <img v-if="task.assignee" :src="task.assignee.profile_photo_url" class="size-6 rounded-full object-cover shrink-0" :title="task.assignee.name" />
                <span v-else class="text-[10px] text-orange-500 font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Sin asignar</span>
                <span v-if="task.assignee" class="text-[11px] font-medium text-gray-600 dark:text-gray-300 truncate">{{ task.assignee.name }}</span>
            </div>
            <span
                v-if="task.due_date && task.status !== 'Terminada'"
                class="text-[10px]"
                :class="isOverdue ? 'text-red-500 font-semibold' : 'text-gray-400'"
            >
                {{ isOverdue ? 'Vencida' : 'A tiempo' }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    canDrag: { type: Boolean, default: true },
});

const emit = defineEmits(['card-click', 'drag-start']);

const STATUS_META = {
    'Pendiente': { border: 'border-l-blue-400', badge: 'bg-blue-500' },
    'En proceso': { border: 'border-l-yellow-400', badge: 'bg-yellow-500' },
    'Pausada': { border: 'border-l-orange-400', badge: 'bg-orange-500' },
    'Terminada': { border: 'border-l-green-500', badge: 'bg-green-500' },
};

const meta = computed(() => STATUS_META[props.task.status] || STATUS_META['Pendiente']);

const isOverdue = computed(() => {
    if (!props.task.due_date || props.task.status === 'Terminada') return false;
    return new Date(props.task.due_date) < new Date();
});

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
};

const onDragStart = (e) => {
    if (!props.canDrag) {
        e.preventDefault();
        return;
    }
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(props.task.id));
    emit('drag-start', props.task);
};
</script>
