<template>
    <div class="p-1 md:p-4 font-sans">
        <div v-if="!arrivals.enabled" class="text-center text-gray-500 dark:text-gray-400 py-10">
            No tienes acceso al calendario de arribos de mercancía.
        </div>

        <template v-else>
            <!-- Leyenda de estatus -->
            <div class="flex flex-wrap items-center gap-4 mb-4">
                <p class="font-semibold text-gray-700 dark:text-gray-200">Estatus de arribo:</p>
                <div v-for="(color, status) in legend" :key="status" class="flex items-center gap-2">
                    <span class="inline-block w-3.5 h-3.5 rounded-full" :style="{ backgroundColor: color }"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ status }}</span>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 ml-auto">
                    Destino: Guadalajara (GDL). Una guía agrupa todas las requisiciones que viajan en ella.
                </p>
            </div>

            <!-- Controles de mes -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button @click="changeMonth(-1)"
                        class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 w-48 text-center capitalize">
                        {{ monthAndYear }}
                    </h2>
                    <button @click="changeMonth(1)"
                        class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ arrivals.arrivals.length }} arribo(s) programado(s)
                </p>
            </div>

            <!-- Calendario -->
            <div class="grid grid-cols-7 gap-px mt-6 bg-gray-200 dark:bg-gray-700 border rounded-lg overflow-hidden shadow">
                <div v-for="day in weekDays" :key="day"
                    class="py-2 text-center text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800">
                    {{ day }}
                </div>

                <div v-for="day in calendarDays" :key="day.date"
                    :class="['relative min-h-[120px] p-2 bg-white dark:bg-gray-800 transition-colors', { 'bg-gray-50 dark:bg-gray-900': !day.isCurrentMonth }]">
                    <span
                        :class="['font-semibold', day.isToday ? 'bg-blue-600 text-white rounded-full h-7 w-7 flex items-center justify-center' : 'text-gray-700 dark:text-gray-200']">
                        {{ day.dayNumber }}
                    </span>
                    <div class="mt-2 space-y-1">
                        <div v-for="entry in entriesByDay[day.date]" :key="entry.key"
                            @click="selectedEntry = entry"
                            class="px-2 py-1 rounded-md text-xs font-medium cursor-pointer truncate flex items-center gap-2 text-white transition-transform hover:scale-[1.02]"
                            :style="{ backgroundColor: entry.color }">
                            <i :class="entryIcon(entry)"></i>
                            <span class="truncate">
                                {{ entryLabel(entry) }}
                                <template v-if="entry.kind === 'shipment' && entry.requisitions.length > 1">
                                    ({{ entry.requisitions.length }})
                                </template>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Arribos sin fecha -->
            <div v-if="arrivals.undated?.length" class="mt-6 bg-white dark:bg-slate-900 rounded-lg shadow p-4">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-2">
                    <i class="fa-solid fa-hourglass-half mr-2"></i> Guías sin fecha estimada
                </h3>
                <ul class="space-y-1">
                    <li v-for="entry in arrivals.undated" :key="entry.key"
                        class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2 cursor-pointer"
                        @click="selectedEntry = entry">
                        <span class="inline-block w-3 h-3 rounded-full" :style="{ backgroundColor: entry.color }"></span>
                        <span class="font-mono">{{ entryLabel(entry) }}</span>
                        <span class="text-xs text-gray-400">{{ entry.subtitle }}</span>
                        <span class="text-xs">· {{ entry.status }}</span>
                    </li>
                </ul>
            </div>
        </template>

        <!-- Detalle del arribo -->
        <el-drawer v-model="drawerOpen" :title="selectedEntry?.title ?? 'Detalle de arribo'" size="480px">
            <div v-if="selectedEntry" class="p-4 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-4 h-4 rounded-full" :style="{ backgroundColor: selectedEntry.color }"></span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ selectedEntry.status }}</span>
                    <el-tag size="small" :type="selectedEntry.kind === 'shipment' ? 'primary' : 'info'">
                        {{ selectedEntry.kind === 'shipment' ? 'Guía de paquetería' : (selectedEntry.kind === 'item' ? 'Producto' : 'Requisición') }}
                    </el-tag>
                </div>

                <div v-if="selectedEntry.kind === 'shipment'" class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Número de guía</p>
                        <p class="font-mono font-semibold dark:text-gray-200">{{ selectedEntry.title }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Paquetería</p>
                        <p class="dark:text-gray-200">{{ selectedEntry.subtitle ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Arribo estimado</p>
                        <p class="dark:text-gray-200">{{ formatDate(selectedEntry.estimated_arrival_date) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Arribo confirmado</p>
                        <p class="dark:text-gray-200">{{ selectedEntry.arrived_at ? formatDateTime(selectedEntry.arrived_at) : 'Pendiente' }}</p>
                    </div>
                    <div v-if="selectedEntry.notes" class="col-span-2">
                        <p class="text-gray-500 dark:text-gray-400">Notas</p>
                        <p class="dark:text-gray-200 whitespace-pre-line">{{ selectedEntry.notes }}</p>
                    </div>
                </div>

                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Productos en este arribo ({{ selectedEntry.items?.length ?? 0 }})
                    </p>
                    <ul class="space-y-2">
                        <li v-for="item in selectedEntry.items" :key="item.id"
                            class="border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                            <div class="flex justify-between items-center gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <el-image v-if="item.image_url" :src="item.image_url" fit="cover"
                                        :preview-src-list="productImages" :initial-index="previewIndex(item)"
                                        preview-teleported hide-on-click-modal
                                        class="size-14 rounded-lg border border-gray-200 dark:border-slate-700 cursor-pointer shrink-0"
                                        :alt="item.product" />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ item.product }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <span v-if="item.code">{{ item.code }} · </span>
                                            {{ item.quantity }} pieza(s)
                                            <template v-if="item.folio">
                                                · <a :href="item.url" class="text-primary hover:underline font-mono">{{ item.folio }}</a>
                                            </template>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="inline-block w-3 h-3 rounded-full"
                                        :style="{ backgroundColor: item.color ?? '#9CA3AF' }"></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.status }}</span>
                                </div>
                            </div>
                        </li>
                        <li v-if="!selectedEntry.items?.length" class="text-xs text-gray-400">
                            Sin detalle de productos.
                        </li>
                    </ul>
                </div>

                <div v-if="selectedEntry.requisitions?.length">
                    <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Requisiciones en este arribo ({{ selectedEntry.requisitions.length }})
                    </p>
                    <ul class="space-y-2">
                        <li v-for="requisition in selectedEntry.requisitions" :key="requisition.id"
                            class="border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                            <div class="flex justify-between items-center">
                                <a :href="requisition.url" class="font-mono font-semibold text-primary hover:underline">
                                    {{ requisition.folio }}
                                </a>
                                <el-tag size="small" :type="requisition.type === 'Importación' ? 'primary' : 'success'">
                                    {{ requisition.type }}
                                </el-tag>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ requisition.label }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Estatus: {{ requisition.status }}</p>
                        </li>
                    </ul>
                </div>

                <!-- Actualización del arribo (requiere permiso de gestión de arribos) -->
                <div v-if="canManage" class="border-t border-dashed border-gray-200 dark:border-slate-700 pt-4 space-y-3">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">
                        <i class="fa-solid fa-pen-to-square mr-2"></i> Actualizar arribo
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Estatus</p>
                            <el-select v-model="arrivalForm.status" class="w-full">
                                <el-option v-for="status in statuses" :key="status" :label="status" :value="status" />
                            </el-select>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha tentativa</p>
                            <el-date-picker v-model="arrivalForm.date" type="date" value-format="YYYY-MM-DD"
                                placeholder="Selecciona la fecha" class="w-full" />
                        </div>
                    </div>
                    <div v-if="selectedEntry.kind === 'shipment'">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Notas de la guía</p>
                        <textarea v-model="arrivalForm.notes" rows="2"
                            class="w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200 text-sm"
                            placeholder="Ej. Contiene 3 cajas, pendiente de aduana..."></textarea>
                        <p class="text-[11px] text-gray-400 mt-1">
                            La nota se muestra junto al número de guía en el calendario.
                        </p>
                    </div>
                    <div class="flex justify-end">
                        <el-button type="primary" :loading="saving" @click="saveArrival">Guardar cambios</el-button>
                    </div>
                </div>
            </div>
        </el-drawer>
    </div>
</template>

<script>
import {
    addMonths,
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameMonth,
    isToday,
    parseISO,
    startOfMonth,
    startOfWeek,
} from 'date-fns';
import { es } from 'date-fns/locale';
import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    props: {
        arrivals: {
            type: Object,
            default: () => ({ enabled: false, arrivals: [], undated: [] }),
        },
    },
    data() {
        return {
            currentMonth: new Date(),
            selectedEntry: null,
            saving: false,
            arrivalForm: { status: 'Pendiente', date: null, notes: '' },
            weekDays: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            legend: {
                Pendiente: '#9CA3AF',
                'En tránsito': '#F59E0B',
                Incidencia: '#EF4444',
                Arribado: '#22C55E',
            },
        };
    },
    computed: {
        // La edición de arribos se habilita con el permiso de gestión de arribos.
        canManage() {
            return Boolean(this.arrivals?.can_manage);
        },
        statuses() {
            return Object.keys(this.legend);
        },
        productImages() {
            return (this.selectedEntry?.items ?? []).map(item => item.image_url).filter(Boolean);
        },
        drawerOpen: {
            get() {
                return Boolean(this.selectedEntry);
            },
            set(value) {
                if (!value) this.selectedEntry = null;
            },
        },
        monthAndYear() {
            return format(this.currentMonth, 'MMMM yyyy', { locale: es });
        },
        calendarDays() {
            const firstDay = startOfWeek(startOfMonth(this.currentMonth), { weekStartsOn: 1 });
            const lastDay = endOfWeek(endOfMonth(this.currentMonth), { weekStartsOn: 1 });

            return eachDayOfInterval({ start: firstDay, end: lastDay }).map(day => ({
                date: format(day, 'yyyy-MM-dd'),
                dayNumber: format(day, 'd'),
                isCurrentMonth: isSameMonth(day, this.currentMonth),
                isToday: isToday(day),
            }));
        },
        entriesByDay() {
            return (this.arrivals.arrivals ?? []).reduce((grouped, entry) => {
                if (!entry.date) return grouped;
                grouped[entry.date] = [...(grouped[entry.date] ?? []), entry];
                return grouped;
            }, {});
        },
    },
    watch: {
        // Precarga el formulario de actualización al abrir un arribo.
        selectedEntry(entry) {
            this.arrivalForm.status = entry?.status ?? 'Pendiente';
            this.arrivalForm.date = entry?.estimated_arrival_date ?? null;
            this.arrivalForm.notes = entry?.notes ?? '';
        },
    },
    methods: {
        changeMonth(offset) {
            this.currentMonth = addMonths(this.currentMonth, offset);
        },
        /**
         * Título del arribo con la nota de la guía concatenada y truncada.
         */
        entryLabel(entry) {
            const note = (entry.notes ?? '').trim();

            if (!note) return entry.title;

            const maxLength = 40;
            const short = note.length > maxLength ? `${note.slice(0, maxLength).trimEnd()}…` : note;

            return `${entry.title} · ${short}`;
        },
        previewIndex(item) {
            return Math.max(this.productImages.indexOf(item.image_url), 0);
        },
        saveArrival() {
            const entry = this.selectedEntry;

            if (!entry) return;

            const status = this.arrivalForm.status;
            const date = this.arrivalForm.date || null;
            const notes = entry.kind === 'shipment' ? (this.arrivalForm.notes || '') : entry.notes;

            const options = {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    // Se refleja el cambio en el detalle abierto sin cerrar el panel.
                    entry.status = status;
                    entry.estimated_arrival_date = date;
                    entry.color = this.legend[status] ?? '#9CA3AF';
                    entry.notes = notes;
                    ElMessage.success('Arribo actualizado.');
                },
                onError: () => ElMessage.error('No se pudo actualizar el arribo.'),
                onFinish: () => (this.saving = false),
            };

            this.saving = true;

            // Las guías tienen estatus, fecha y notas propias; las partidas sin
            // guía actualizan su estatus y la fecha tentativa de la requisición.
            if (entry.kind === 'shipment') {
                router.put(route('requisition-shipments.update-status', entry.id), {
                    status,
                    estimated_arrival_date: date,
                    notes,
                }, options);

                return;
            }

            router.put(route('requisition-items.arrival-status', entry.id), {
                arrival_status: status,
                expected_delivery_date: date,
            }, options);
        },
        entryIcon(entry) {
            if (entry.kind === 'shipment') return 'fa-solid fa-truck-fast';
            if (entry.kind === 'item') return 'fa-solid fa-box';

            return 'fa-solid fa-clipboard-list';
        },
        formatDate(dateString) {
            if (!dateString) return 'Sin definir';
            return format(parseISO(dateString), "d 'de' MMM, yyy", { locale: es });
        },
        formatDateTime(dateString) {
            if (!dateString) return '—';
            return format(parseISO(dateString), "d 'de' MMM, yyy HH:mm", { locale: es });
        },
    },
};
</script>
