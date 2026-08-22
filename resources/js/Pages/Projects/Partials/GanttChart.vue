<template>
  <div>
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        <i class="fa-solid fa-chart-line mr-1"></i>
        Diagrama de Gantt — {{ tasks.length }} tarea{{ tasks.length === 1 ? '' : 's' }}
      </p>
      <div class="flex items-center gap-4 flex-wrap">
        <div class="hidden lg:flex items-center gap-3 text-xs text-gray-600 dark:text-gray-300">
          <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm gantt-legend pending"></span> Pendiente</span>
          <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm gantt-legend progress"></span> En proceso</span>
          <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm gantt-legend paused"></span> Pausada</span>
          <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm gantt-legend finished"></span> Terminada</span>
        </div>
        <el-radio-group v-model="viewMode" size="small" @change="render">
          <el-radio-button label="Day">Día</el-radio-button>
          <el-radio-button label="Week">Semana</el-radio-button>
          <el-radio-button label="Month">Mes</el-radio-button>
        </el-radio-group>
      </div>
    </div>

    <div ref="ganttContainer" class="gantt-chart-wrap overflow-x-auto pb-2"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import Gantt from 'frappe-gantt';
import '../../../../css/frappe-gantt.css';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
});

const ganttContainer = ref(null);
const viewMode = ref('Week');
let gantt = null;

const STATUS_CLASS = {
    'Pendiente': 'gantt-bar-pending',
    'En proceso': 'gantt-bar-progress',
    'Pausada': 'gantt-bar-paused',
    'Terminada': 'gantt-bar-finished',
};

const statusProgress = {
    'Pendiente': 0,
    'En proceso': 50,
    'Pausada': 30,
    'Terminada': 100,
};

const toDate = (v) => (v ? String(v).slice(0, 10) : '');

const buildTasks = () => {
    return [...props.tasks]
        .sort((a, b) => toDate(a.start_date).localeCompare(toDate(b.start_date)))
        .map(t => {
            const start = toDate(t.start_date) || new Date().toISOString().slice(0, 10);
            const end = toDate(t.finished_at) || toDate(t.due_date) || start;
            return {
                id: 'task-' + t.id,
                name: t.title,
                start,
                end: end < start ? start : end,
                progress: statusProgress[t.status] ?? 0,
                custom_class: STATUS_CLASS[t.status] || 'gantt-bar-pending',
                status: t.status,
                assignee: t.assignee?.name || 'Sin asignar',
            };
        });
};

const render = () => {
    if (!ganttContainer.value) return;

    ganttContainer.value.innerHTML = '';

    if (!props.tasks.length) {
        ganttContainer.value.innerHTML = '<div class="text-center text-gray-400 py-16 text-sm">No hay tareas para mostrar en el Gantt.</div>';
        return;
    }

    // Altura mínima del diagrama: 35% de la altura de la ventana (mínimo 220px)
    const minHeight = Math.max(Math.round((window.innerHeight || 800) * 0.35), 220);

    gantt = new Gantt(ganttContainer.value, buildTasks(), {
        view_mode: viewMode.value,
        date_format: 'YYYY-MM-DD',
        readonly: true,
        language: 'es',
        container_height: minHeight,
        custom_popup_html: (task) => `
            <div class="gantt-popup">
                <div class="gantt-popup-title">${task.name}</div>
                <div class="gantt-popup-row"><b>Inicio:</b> ${task.start}</div>
                <div class="gantt-popup-row"><b>Fin:</b> ${task.end}</div>
                <div class="gantt-popup-row"><b>Estatus:</b> ${task.status}</div>
                <div class="gantt-popup-row"><b>Responsable:</b> ${task.assignee}</div>
                <div class="gantt-popup-row"><b>Avance:</b> ${task.progress}%</div>
            </div>`,
    });

    // Traduce el botón "Today" que genera la librería
    const todayButton = ganttContainer.value.querySelector('.today-button');
    if (todayButton) todayButton.textContent = 'Hoy';
};

onMounted(render);
watch(() => props.tasks, render);
onBeforeUnmount(() => {
    gantt = null;
    if (ganttContainer.value) ganttContainer.value.innerHTML = '';
});
</script>

<style>
/* Colores de las barras según el estatus de la tarea (tonos oscuros para mejor contraste del texto) */
.gantt-bar-pending .bar,
.gantt-bar-pending .bar-progress { fill: #64748b; }
.gantt-bar-progress .bar,
.gantt-bar-progress .bar-progress { fill: #2563eb; }
.gantt-bar-paused .bar,
.gantt-bar-paused .bar-progress { fill: #d97706; }
.gantt-bar-finished .bar,
.gantt-bar-finished .bar-progress { fill: #16a34a; }

.gantt-legend.pending { background: #64748b; }
.gantt-legend.progress { background: #2563eb; }
.gantt-legend.paused { background: #d97706; }
.gantt-legend.finished { background: #16a34a; }

/* Garantiza la altura mínima del diagrama (la librería calcula menos cuando hay pocas tareas) */
.gantt-chart-wrap .gantt-container {
    min-height: 35vh !important;
}

/* Botón "Hoy" de la librería con mejor estilo */
.gantt-chart-wrap .today-button {
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.gantt-chart-wrap .today-button:hover {
    background: #1d4ed8;
}

.gantt-popup {
    background: #1f2937;
    color: #e5e7eb;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.6;
    box-shadow: 0 10px 25px rgba(0, 0, 0, .3);
    max-width: 280px;
}
.gantt-popup-title { font-weight: 700; margin-bottom: 4px; color: #fff; }
.gantt-popup-row b { color: #9ca3af; font-weight: 600; margin-right: 2px; }

/* Texto de las barras: blanco con contorno oscuro para que se lea sobre cualquier fondo,
   incluso cuando la barra es corta y el texto sobresale */
.gantt .bar-wrapper .bar-label {
    fill: #fff;
    stroke: rgba(0, 0, 0, 0.45);
    stroke-width: 0.6px;
    paint-order: stroke;
    font-weight: 700;
    font-size: 11px;
}
</style>
