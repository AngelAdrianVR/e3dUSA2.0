<template>
    <div>
        <!-- Vista Kanban -->
        <div v-show="activeView === 'kanban'" class="kanban-board-container">
            <div class="flex space-x-4 overflow-x-auto p-4">
                <!-- Columnas del Kanban -->
                <div v-for="column in columns" :key="column.id" class="bg-gray-200 dark:bg-slate-800/50 rounded-lg w-80 flex-shrink-0 flex flex-col h-[calc(100vh-14rem)]">
                    <!-- Encabezado de la columna -->
                    <h3 class="font-bold text-gray-800 dark:text-gray-200 px-4 pt-4 pb-3 flex items-center border-b-2 border-gray-300 dark:border-slate-700 flex-shrink-0">
                       <span class="w-3 h-3 rounded-full mr-2" :class="column.color"></span>
                        {{ column.title }}
                        <span class="ml-auto text-sm bg-gray-300 dark:bg-slate-700 text-gray-500 dark:text-gray-400 rounded-full px-2 py-0.5">{{ kanbanState[column.id]?.length || 0 }}</span>
                    </h3>

                    <!-- Área de tarjetas (draggable) -->
                    <draggable v-model="kanbanState[column.id]"
                               :item-key="item => item.id"
                               group="sales"
                               class="p-3 overflow-y-auto flex-grow"
                               :disabled="true">
                        <template #item="{element: sale}">
                           <div class="relative bg-white dark:bg-slate-900 rounded-lg shadow-md p-4 mb-3 cursor-pointer transition-all duration-200 hover:shadow-xl hover:scale-[1.02] overflow-hidden">
                                <!-- Indicador de Alta Prioridad (Más visible) -->
                                <div v-if="sale.is_high_priority" class="absolute top-0 right-0 h-16 w-16">
                                    <div class="absolute transform rotate-45 bg-red-600 text-center text-white font-semibold py-1 right-[-34px] top-[32px] w-[170px] shadow-lg">
                                        <i class="fa-solid fa-fire mr-1"></i> Prioridad
                                    </div>
                                </div>

                                <!-- Encabezado de la tarjeta -->
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <svg v-if="sale.type === 'venta'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-purple-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-red-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                            </svg>
                                            <Link :href="route('sales.show', sale.id)">
                                                <p v-if="sale.type === 'venta'" class="text-sm font-bold text-primary hover:underline">OV-{{ sale.id.toString().padStart(4, '0') }}</p>
                                                <p v-else class="text-sm font-bold text-primary hover:underline">OS-{{ sale.id.toString().padStart(4, '0') }}</p>
                                            </Link>
                                        </div>
                                        <p class="font-semibold text-base text-gray-800 dark:text-gray-200 mt-1">{{ sale.branch?.name || 'N/A' }}</p>
                                    </div>
                                </div>

                                <!-- Resumen de producción con Tooltip de productos -->
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-700 text-xs text-gray-500 dark:text-gray-400 space-y-2">
                                    <el-tooltip effect="dark" placement="top">
                                        <template #content>
                                            <div class="max-w-xs">
                                                <h4 class="font-semibold text-xs text-gray-300 mb-2 border-b border-slate-600 pb-1">PRODUCTOS</h4>
                                                <div class="space-y-2">
                                                    <div v-for="item in sale.sale_products" :key="item.id" class="flex items-center space-x-3">
                                                        <img draggable="off" :src="item.product.media[0]?.original_url" class="w-10 h-10 rounded-md object-cover bg-gray-500" v-if="item.product.media?.length > 0">
                                                        <div class="w-10 h-10 rounded-md bg-gray-700 flex items-center justify-center" v-else>
                                                            <i class="fa-solid fa-image text-gray-500"></i>
                                                        </div>
                                                        <div>
                                                            <p class="text-xs font-semibold text-gray-200 flex items-center">
                                                                {{ item.product.name }}
                                                                <i v-if="getProductionStatusForProduct(sale, item.id) === 'Sin material'"
                                                                   class="fa-solid fa-triangle-exclamation text-yellow-400 ml-2"
                                                                   title="Falta material para este producto"></i>
                                                            </p>
                                                            <p class="text-xs text-gray-400">Cantidad: {{ item.quantity_to_produce }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <div class="flex justify-between hover:bg-gray-100 dark:hover:bg-slate-800 rounded-md -mx-1 px-1 py-0.5">
                                            <span>Productos en Producción:</span>
                                            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ sale.production_summary.total_productions }} <i class="fa-solid fa-circle-info ml-1"></i></span>
                                        </div>
                                    </el-tooltip>
                                    <div class="flex justify-between">
                                        <span>Productos Terminados:</span>
                                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ sale.production_summary.completed_productions }} / {{ sale.production_summary.total_productions }}</span>
                                    </div>
                                </div>


                                <!-- Barra de Progreso General -->
                                <div class="mt-4">
                                    <div class="flex justify-between items-center text-xs text-gray-500">
                                        <span>Progreso General (Tareas)</span>
                                        <span>{{ sale.production_summary.completed_tasks }}/{{ sale.production_summary.total_tasks }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-2.5 mt-1">
                                        <div class="h-2.5 rounded-full transition-all duration-500" :style="getProgressBarStyle(sale.production_summary.percentage)"></div>
                                    </div>
                                </div>
                                
                                <!-- Collapse de Tareas -->
                                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-slate-700">
                                    <button @click="toggleTasks(sale.id)" class="text-xs font-semibold text-primary hover:underline w-full text-left">
                                        Ver Tareas <i class="fa-solid ml-1" :class="expandedCardId === sale.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                    <div v-if="expandedCardId === sale.id" class="mt-2 space-y-2">
                                        <div v-for="production in sale.productions" :key="production.id" class="space-y-1">
                                            <div v-for="task in production.tasks" :key="task.id" class="bg-gray-100 dark:bg-slate-800 p-2 rounded-md">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center space-x-2">
                                                        <img :src="task.operator.profile_photo_url" class="size-7 rounded-full" :title="task.operator.name">
                                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ task.name }}</span>
                                                    </div>
                                                    <el-tag size="small" :type="statusTagType(task.status)">{{ task.status }}</el-tag>
                                                </div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1 pl-8">
                                                    <p>Inicio: {{ task.started_at ? new Date(task.started_at).toLocaleString() : 'Sin iniciar' }}</p>
                                                    <p>Fin: {{ task.finished_at ? new Date(task.finished_at).toLocaleString() : 'No termiando' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pie de la tarjeta: Creador -->
                                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-slate-700 flex items-center space-x-2">
                                     <img :src="sale.user.profile_photo_url" class="size-7 rounded-full">
                                     <div>
                                         <p class="text-xs text-gray-400">Creado por</p>
                                         <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ sale.user.name }}</p>
                                     </div>
                                </div>
                            </div>
                        </template>
                    </draggable>
                </div>
            </div>
        </div>

        <!-- Vista Tabla -->
        <div v-show="activeView === 'table'" class="p-4 sm:p-6 lg:p-8">
            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg">
                <el-table :data="sales" style="width: 100%" class="dark:!bg-slate-900 dark:!text-gray-300">
                    <el-table-column prop="id" label="Folio" width="150" #default="scope">
                        <div class="flex items-center space-x-2">
                            <svg v-if="scope.row.type === 'venta'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-purple-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-red-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                            <p v-if="scope.row.type === 'venta'">OV-{{ scope.row.id.toString().padStart(4, '0') }}</p>
                            <p v-else>OS-{{ scope.row.id.toString().padStart(4, '0') }}</p>
                        </div>
                    </el-table-column>
                    <el-table-column label="Prioridad" width="100" align="center">
                        <template #default="scope">
                            <div class="flex justify-center">
                                 <span v-if="scope.row.is_high_priority" class="h-8 w-8 bg-red-100 dark:bg-red-900/50 rounded-full flex items-center justify-center">
                                    <i class="fa-solid fa-fire text-red-500 text-lg" title="Alta Prioridad"></i>
                                 </span>
                                 <span v-else class="text-gray-400 dark:text-gray-600">-</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="Cliente" #default="scope">
                        {{ scope.row.branch?.name ?? 'N/A' }}
                    </el-table-column>
                    <el-table-column label="Creado por" width="200">
                        <template #default="scope">
                            <div class="flex items-center space-x-3">
                                <img :src="scope.row.user.profile_photo_url" class="size-9 rounded-full object-cover">
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ scope.row.user.name }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="production_summary.status" label="Estatus General" width="150" align="center">
                         <template #default="scope">
                            <el-tag :type="statusTagType(scope.row.production_summary.status)" class="!text-xs !font-bold">{{ scope.row.production_summary.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Productos Terminados" width="180" align="center">
                         <template #default="scope">
                            <span class="font-semibold text-gray-700 dark:text-gray-200 text-sm">
                                {{ scope.row.production_summary.completed_productions }} / {{ scope.row.production_summary.total_productions }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="Progreso Tareas" width="220">
                         <template #default="scope">
                            <div>
                                <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400 mb-1">
                                    <span>Tareas completadas</span>
                                    <span class="font-semibold">{{ scope.row.production_summary.completed_tasks }}/{{ scope.row.production_summary.total_tasks }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-2.5 mt-1">
                                    <div class="h-2.5 rounded-full transition-all duration-500" :style="getProgressBarStyle(scope.row.production_summary.percentage)"></div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="Acciones" width="120" align="right">
                        <template #default="scope">
                            <Link :href="route('sales.show', scope.row.id)">
                                <PrimaryButton><i class="fa-solid fa-eye"></i></PrimaryButton>
                            </Link>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    </div>
</template>

<script>
import draggable from 'vuedraggable';
import { Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

export default {
    name: 'ManagerView',
    components: { draggable, Link, PrimaryButton },
    props: {
        sales: { type: Array, default: () => [] },
        activeView: { type: String, default: 'kanban' },
    },
    data() {
        return {
            expandedCardId: null, // ID de la tarjeta con tareas expandidas
            columns: [
                { id: 'Pendiente', title: 'Pendiente', color: 'bg-yellow-400' },
                { id: 'En Proceso', title: 'En Proceso', color: 'bg-blue-400' },
                { id: 'Sin material', title: 'Sin material', color: 'bg-red-500' },
                { id: 'Pausada', title: 'Pausada', color: 'bg-orange-400' },
                { id: 'Terminada', title: 'Terminada', color: 'bg-green-400' },
            ],
        };
    },
    computed: {
        kanbanState() {
            const grouped = {};
            this.columns.forEach(col => { grouped[col.id] = []; });
            this.sales.forEach(sale => {
                const status = sale.production_summary?.status;
                if (grouped[status]) {
                    grouped[status].push(sale);
                }
            });
            return grouped;
        }
    },
    methods: {
        getProgressBarStyle(percentage) {
             const red = [239, 68, 68], yellow = [245, 158, 11], green = [34, 197, 94];
             let color;
             if (percentage < 50) {
                 const ratio = percentage / 50;
                 color = [ Math.round(red[0] + (yellow[0] - red[0]) * ratio), Math.round(red[1] + (yellow[1] - red[1]) * ratio), Math.round(red[2] + (yellow[2] - red[2]) * ratio) ];
             } else {
                 const ratio = (percentage - 50) / 50;
                 color = [ Math.round(yellow[0] + (green[0] - yellow[0]) * ratio), Math.round(yellow[1] + (green[1] - yellow[1]) * ratio), Math.round(yellow[2] + (green[2] - yellow[2]) * ratio) ];
             }
             return { width: percentage + '%', background: `linear-gradient(90deg, rgb(${red.join(',')}) 0%, rgb(${color.join(',')}) 100%)` };
        },
        statusTagType(status) {
            const map = {
                'Pendiente': 'warning', 'En Proceso': '', 'Pausada': 'info', 'Terminada': 'success', 'Sin material': 'danger',
            };
            return map[status] || 'info';
        },
        toggleTasks(saleId) {
            if (this.expandedCardId === saleId) {
                this.expandedCardId = null;
            } else {
                this.expandedCardId = saleId;
            }
        },
        getProductionStatusForProduct(sale, saleProductId) {
            const production = sale.productions.find(p => p.sale_product_id === saleProductId);
            return production ? production.status : null;
        }
    },
};
</script>

<style scoped>
/* Estilos para la barra de scroll horizontal del Kanban */
.overflow-x-auto::-webkit-scrollbar { height: 8px; }
.overflow-x-auto::-webkit-scrollbar-track { background-color: transparent; }
.dark .overflow-x-auto::-webkit-scrollbar-track { background-color: transparent; }
.overflow-x-auto::-webkit-scrollbar-thumb { background-color: #a8a29e; border-radius: 10px; border: 2px solid transparent; background-clip: content-box; }
.dark .overflow-x-auto::-webkit-scrollbar-thumb { background-color: #52525b; }
.overflow-x-auto::-webkit-scrollbar-thumb:hover { background-color: #78716c; }
.dark .overflow-x-auto::-webkit-scrollbar-thumb:hover { background-color: #71717a; }
</style>
