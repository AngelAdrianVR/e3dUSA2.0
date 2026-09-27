<template>
    <div
        draggable="true"
        class="group bg-white dark:bg-slate-800 rounded-lg p-3 shadow-sm border border-gray-200 dark:border-slate-700 border-l-4 cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
        :class="[
            meta.border,
            canDrag ? '' : 'cursor-default',
            isOverdue ? 'due-pulse-danger' : (isDueSoon ? 'due-pulse-warning' : '')
        ]"
        :title="canDrag ? 'Arrastra para cambiar de estatus' : 'Colaborador'"
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

        <!-- ALERTA DE VENCIMIENTO (vence pronto / vencida) -->
        <div v-if="isOverdue || isDueSoon"
            class="mt-2 flex items-center gap-1.5 text-[10px] font-bold px-2 py-1 rounded-md w-fit"
            :class="isOverdue
                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                    :class="isOverdue ? 'bg-red-400' : 'bg-amber-400'"></span>
                <span class="relative inline-flex rounded-full h-2 w-2"
                    :class="isOverdue ? 'bg-red-500' : 'bg-amber-500'"></span>
            </span>
            <i :class="isOverdue ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-hourglass-half'"></i>
            <template v-if="isOverdue">
                Vencida{{ overdueDays === 1 ? ' hace 1 día' : ` hace ${overdueDays} días` }}
            </template>
            <template v-else>
                {{ daysUntilDue === 0 ? 'Vence hoy' : (daysUntilDue === 1 ? 'Vence mañana' : `Vence en ${daysUntilDue} días`) }}
            </template>
        </div>

        <div class="flex items-center justify-between mt-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1" title="Fechas de la tarea">
                <i class="fa-regular fa-calendar"></i>
                {{ formatDate(task.start_date) }}
                <template v-if="task.due_date">→ {{ formatDate(task.due_date) }}</template>
            </span>
            <div class="flex items-center gap-3">
                <!-- Tiempo invertido: solo lo ven los Administradores del proyecto -->
                <span v-if="canSeeTime" class="flex items-center gap-1"
                    :class="task.is_timer_running ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''"
                    :title="task.is_timer_running ? 'Tiempo invertido (cronómetro en marcha)' : 'Tiempo invertido'">
                    <i :class="task.is_timer_running ? 'fa-solid fa-stopwatch' : 'fa-regular fa-clock'"></i>
                    {{ formattedTime }}
                </span>
                <span v-if="task.evidence_count" class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400" title="Tiene evidencia de finalización">
                    <i class="fa-solid fa-clipboard-check"></i>
                </span>
                <!-- Calificación del desempeño (visible para todos) -->
                <span v-if="task.rating" class="flex items-center gap-1 font-semibold text-amber-500"
                    :title="`Calificación del jefe de proyecto: ${task.rating}/5`">
                    <i class="fa-solid fa-star"></i>{{ task.rating }}
                </span>
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
                :class="isOverdue ? 'text-red-500 font-semibold' : (isDueSoon ? 'text-amber-500 font-semibold' : 'text-gray-400')"
            >
                {{ isOverdue ? 'Vencida' : (isDueSoon ? 'Por vencer' : 'A tiempo') }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    canDrag: { type: Boolean, default: true },
    // Solo los Administradores del proyecto ven el tiempo invertido
    canSeeTime: { type: Boolean, default: false },
    // Marca de tiempo del reloj compartido del tablero (para que el cronómetro avance en vivo)
    tick: { type: Number, default: 0 },
});

const mountedAt = Date.now();

// Tiempo invertido: acumulado del servidor + lo transcurrido desde que se pintó la tarjeta
const elapsedSeconds = computed(() => {
    const base = Number(props.task.total_time_seconds || 0);

    if (!props.task.is_timer_running) {
        return base;
    }

    const reference = props.tick || Date.now();
    return base + Math.max(0, Math.floor((reference - mountedAt) / 1000));
});

const formattedTime = computed(() => formatSeconds(elapsedSeconds.value));

// Formatea segundos como "2h 15m", "45m" o "<1m"
const formatSeconds = (totalSeconds) => {
    const seconds = Math.max(0, Number(totalSeconds) || 0);
    if (seconds < 60) return '<1m';

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m`;

    const hours = Math.floor(minutes / 60);
    const restMinutes = minutes % 60;
    if (hours < 24) return restMinutes ? `${hours}h ${restMinutes}m` : `${hours}h`;

    const days = Math.floor(hours / 24);
    return `${days}d ${hours % 24}h`;
};

const emit = defineEmits(['card-click', 'drag-start']);

const STATUS_META = {
    'Pendiente': { border: 'border-l-blue-400', badge: 'bg-blue-500' },
    'En proceso': { border: 'border-l-yellow-400', badge: 'bg-yellow-500' },
    'Pausada': { border: 'border-l-orange-400', badge: 'bg-orange-500' },
    'Terminada': { border: 'border-l-green-500', badge: 'bg-green-500' },
};

const meta = computed(() => STATUS_META[props.task.status] || STATUS_META['Pendiente']);

// Días restantes hasta el vencimiento: negativo = vencida, 0 = vence hoy, positivo = por vencer.
// Se calcula por día natural a partir de la parte de fecha para evitar el desfase de zona horaria.
const daysUntilDue = computed(() => {
    if (!props.task.due_date) return null;
    const [datePart] = String(props.task.due_date).split('T');
    const [y, m, d] = datePart.split('-').map(Number);
    if (!y || !m || !d) return null;
    const due = new Date(y, m - 1, d);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((due - today) / 86400000);
});

const isFinished = computed(() => props.task.status === 'Terminada');

const isOverdue = computed(() => !isFinished.value && daysUntilDue.value !== null && daysUntilDue.value < 0);

// Ventana de aviso: 3 días o menos para vencer (misma regla que la notificación diaria)
const isDueSoon = computed(() => !isFinished.value && daysUntilDue.value !== null && daysUntilDue.value >= 0 && daysUntilDue.value <= 3);

const overdueDays = computed(() => (isOverdue.value ? Math.abs(daysUntilDue.value) : 0));

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

<style scoped>
/* Pulso ámbar: tarea próxima a vencer | Pulso rojo: tarea vencida */
@keyframes due-pulse-warning {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.55); }
    50% { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0); }
}
@keyframes due-pulse-danger {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.55); }
    50% { box-shadow: 0 0 0 7px rgba(239, 68, 68, 0); }
}
.due-pulse-warning { animation: due-pulse-warning 1.8s ease-in-out infinite; }
.due-pulse-danger { animation: due-pulse-danger 1.8s ease-in-out infinite; }

@media (prefers-reduced-motion: reduce) {
    .due-pulse-warning,
    .due-pulse-danger { animation: none; }
}
</style>
