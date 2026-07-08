<template>
    <div v-if="summary && summary.total_productions > 0" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg overflow-hidden">
        <!-- Encabezado Colapsable -->
        <div @click="isOpen = !isOpen" class="flex justify-between items-center p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-chart-pie text-emerald-500"></i>
                <h3 class="text-lg font-semibold">Resumen de Producción</h3>
            </div>
            <i :class="isOpen ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid text-gray-400 transition-transform"></i>
        </div>

        <el-collapse-transition>
            <div v-show="isOpen" class="p-4 pt-0 border-t dark:border-gray-600 mt-2">
                <!-- Estadísticas Detalladas -->
                <div class="grid grid-cols-2 text-center text-sm mb-4 bg-gray-50 dark:bg-slate-700/50 p-2 rounded-lg">
                    <div>
                        <p class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ summary.completed_productions }} / {{ summary.total_productions }}</p>
                        <p class="text-gray-500 dark:text-gray-400 text-xs">Productos</p>
                    </div>
                    <div>
                        <p class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ summary.completed_tasks }} / {{ summary.total_tasks }}</p>
                        <p class="text-gray-500 dark:text-gray-400 text-xs">Tareas</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">Estado:</span>
                        <span class="font-bold px-2 py-1 rounded-md text-xs"
                            :class="{
                                'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300': summary.status === 'Terminada',
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300': summary.status === 'En Proceso',
                                'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300': summary.status === 'Sin material',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300': summary.status === 'Pendiente',
                                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300': summary.status === 'Pausada',
                            }">
                            {{ summary.status }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">Inicio:</span>
                        <span class="font-bold px-2 py-1 rounded-md text-xs">
                            {{ formatDateTime(summary.started_at) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">Fin:</span>
                        <span class="font-bold px-2 py-1 rounded-md text-xs">
                            {{ formatDateTime(summary.finished_at) }}
                        </span>
                    </div>

                    <!-- Barra de Progreso Futurista -->
                    <div class="pt-2">
                        <div class="flex justify-between mb-1">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Progreso General</span>
                            <span class="text-xs font-bold text-green-500">{{ summary.percentage }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-slate-700">
                            <div class="bg-gradient-to-r from-emerald-600 to-green-500 h-3 rounded-full transition-all duration-500 ease-out"
                                :style="{ width: summary.percentage + '%' }">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-collapse-transition>
    </div>
</template>

<script>
export default {
    name: 'ProductionSummaryCard',
    props: {
        summary: { type: Object, default: null }
    },
    data() {
        return {
            isOpen: false // Cerrado por defecto
        }
    },
    methods: {
        formatDateTime(dateString) {
            if (!dateString) return '---';
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace('.', '');
        }
    }
}
</script>