<template>
    <div class="py-7 min-h-screen">
        <div class="max-w-[90rem] mx-auto sm:px-5">
            <div v-if="groupedTasks.length > 0" class="space-y-6">
                <!-- Itera sobre cada grupo de orden de venta -->
                <div v-for="group in groupedTasks" :key="group.saleId" class="bg-slate-200 dark:bg-slate-900/50 p-4 rounded-xl">
                    <!-- Encabezado del Grupo (Orden de Venta) -->
                    <div class="flex justify-between items-start px-2 mb-2">
                        <div class="flex items-center space-x-2">
                             <div class="h-8 w-1 rounded-full" :class="group.is_high_priority ? 'bg-red-500' : 'bg-secondary'"></div>

                             <el-tooltip v-if="group.is_high_priority" content="Alta Prioridad" placement="top">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7 text-red-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                             </el-tooltip>

                             <div>
                                <h2 class="text-base font-bold text-gray-800 dark:text-gray-200">
                                    {{ group.type === 'venta' ? 'OV' : 'OS' }}-{{ group.saleId.toString().padStart(4, '0') }}
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ group.branchName }}</p>
                             </div>
                        </div>

                        <div v-if="group.promise_date" class="flex items-center justify-end space-x-2 text-sm mt-2" :class="isDateInPast(group.promise_date) ? 'text-red-500 font-semibold' : 'text-gray-500 dark:text-gray-400'">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0h18" />
                            </svg>
                            <span>Fecha promesa de embarque: {{ formatDate(group.promise_date) }}</span>
                            <el-tooltip v-if="isDateInPast(group.promise_date)" content="La fecha promesa ha vencido" placement="top">
                                <i class="fa-solid fa-triangle-exclamation text-red-500"></i>
                            </el-tooltip>
                        </div>

                        <div class="text-right">
                             <el-tooltip :content="group.type === 'venta' ? 'Orden de Venta' : 'Orden de Stock'" placement="top">
                                <svg v-if=" group.type === 'venta'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-9 text-purple-500 bg-gray-300 dark:bg-slate-700 p-1 rounded-full inline-block">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-9 text-red-500 bg-gray-300 dark:bg-slate-700 p-1 rounded-full inline-block">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>
                            </el-tooltip>
                        </div>
                    </div>
                    
                    <!-- Grid de Tareas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="task in group.tasks" :key="task.id"
                             class="bg-white dark:bg-slate-900 shadow-md rounded-lg border border-slate-200 dark:border-slate-800 flex flex-col self-start transition-all duration-300"
                             :class="{'ring-2 ring-primary dark:ring-primary shadow-xl': selectedTask && selectedTask.id === task.id}">
                            <!-- Card Principal (Clickable) -->
                            <div @click="toggleDetails(task)" class="p-4 cursor-pointer flex-grow">
                                <div class="flex items-start space-x-4">
                                    <img draggable="false" :src="getProductImage(task.production.sale_product.product)" class="w-16 h-16 rounded-md object-cover border dark:border-slate-700 flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate" :title="task.name">{{ task.name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ task.production.sale_product.product.name }}</p>
                                            </div>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full flex-shrink-0 ml-2" :class="statusClasses[task.status] || statusClasses['default']">
                                                {{ task.status }}
                                            </span>
                                        </div>
                                         <!-- START: Customization details -->
                                         <div
                                            v-if="getCustomizationDetails(task).length > 0"
                                            class="mt-4 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm bg-gray-100 dark:bg-slate-800"
                                            >
                                            <h3 class="text-blue-500 font-semibold text-sm mb-3 tracking-wide">
                                                ✨ Detalles de Personalización
                                            </h3>

                                            <div
                                                v-for="(group, index) in groupByType(getCustomizationDetails(task))"
                                                :key="index"
                                                class="mb-4 last:mb-0"
                                            >
                                                <!-- Tipo de personalización -->
                                                <div class="text-xs font-bold text-slate-700 dark:text-slate-200 mb-2 border-b border-dashed border-slate-300 dark:border-slate-600 pb-1">
                                                {{ group.type }}
                                                </div>

                                                <!-- Lista de detalles -->
                                                <ul class="space-y-1 text-sm">
                                                <li
                                                    v-for="(detail, idx) in group.details"
                                                    :key="idx"
                                                    class="flex items-start gap-2"
                                                >
                                                    <span class="font-medium text-slate-600 dark:text-slate-300 min-w-[80px]">
                                                    {{ detail.key }}:
                                                    </span>
                                                    <span class="text-slate-500 dark:text-slate-400">
                                                    {{ detail.value }}
                                                    </span>
                                                </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <!-- END: Customization details -->
                                         <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs border-t border-slate-200 dark:border-slate-700 pt-2">
                                            <div>
                                                <span class="text-gray-400">Ordenado:</span>
                                                <span class="font-bold text-gray-600 dark:text-gray-300 ml-1">{{ task.production.sale_product.quantity }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-400">A Producir:</span>
                                                <span class="font-bold text-gray-600 dark:text-gray-300 ml-1">{{ getQuantityToProduce(task) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-400">De Stock:</span>
                                                <span class="font-bold text-gray-600 dark:text-gray-300 ml-1">{{ stockUsed(task) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-400">T. Estimado:</span>
                                                <span class="font-bold text-gray-600 dark:text-gray-300 ml-1">{{ task.estimated_time_minutes }} min</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Acciones -->
                            <div class="px-4 py-2 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 flex items-center justify-end space-x-2">
                                 <el-tooltip content="Reportar Falta de Material" placement="top">
                                    <button @click="reportIssue(task)" v-if="['Pendiente', 'En Proceso', 'Pausada'].includes(task.status)" class="h-8 w-8 rounded-full text-gray-500 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/50 transition">
                                       <i class="fa-solid fa-triangle-exclamation"></i>
                                   </button>
                                 </el-tooltip>

                                 <el-tooltip content="Pausar Tarea" placement="top">
                                    <button @click="pauseTask(task.id)" v-if="task.status === 'En Proceso'" class="h-8 w-8 rounded-full text-gray-500 hover:bg-orange-100 hover:text-orange-600 dark:hover:bg-orange-900/50 transition">
                                       <i class="fa-solid fa-pause"></i>
                                   </button>
                                 </el-tooltip>
                                 
                                <el-tooltip content="Iniciar Tarea" placement="top">
                                    <button @click="startTask(task.id)" v-if="task.status === 'Pendiente'" class="h-8 w-8 rounded-full text-gray-500 hover:bg-blue-100 hover:text-blue-600 dark:hover:bg-blue-900/50 transition">
                                       <i class="fa-solid fa-play"></i>
                                   </button>
                                </el-tooltip>

                                 <el-tooltip content="Reanudar Tarea" placement="top">
                                    <button @click="resumeTask(task.id)" v-if="task.status === 'Pausada'" class="h-8 w-8 rounded-full text-gray-500 hover:bg-blue-100 hover:text-blue-600 dark:hover:bg-blue-900/50 transition">
                                       <i class="fa-solid fa-play"></i>
                                   </button>
                                 </el-tooltip>

                                <button @click="finishTask(task)" v-if="['En Proceso', 'Pausada'].includes(task.status)" class="px-3 py-1 bg-green-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase hover:bg-green-500 transition">
                                   <i class="fa-solid fa-check mr-1.5"></i> Finalizar
                                </button>
                            </div>

                             <!-- Vista de Detalles (Expandible) -->
                            <div v-if="selectedTask && selectedTask.id === task.id" class="border-t border-dashed border-slate-300 dark:border-slate-700">
                                <div v-if="isLoadingDetails" class="text-center p-8">
                                    <i class="fa-solid fa-spinner fa-spin text-2xl text-primary"></i>
                                    <p class="text-sm text-gray-500 mt-2">Cargando detalles...</p>
                                </div>
                                <div v-else-if="taskDetails" class="p-4 bg-slate-50 dark:bg-slate-800/50">
                                   <h4 class="font-bold text-sm mb-2 text-gray-700 dark:text-gray-300">Componentes Requeridos</h4>
                                    <ul v-if="taskDetails.production.sale_product.product.components.length > 0" class="space-y-2">
                                        <li v-for="component in taskDetails.production.sale_product.product.components" :key="component.id" class="flex items-center space-x-3 text-xs bg-white dark:bg-slate-900/70 p-2 rounded-md">
                                            <img :src="getProductImage(component)" class="w-10 h-10 object-cover rounded-md border dark:border-slate-700">
                                            <div class="flex-grow">
                                                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ component.name }}</p>
                                                <p class="text-gray-500 dark:text-gray-400">Stock: <span class="font-bold">{{ component.storages[0]?.quantity || 0 }}</span> | Ubicación: {{ component.storages[0]?.location || 'N/A' }}</p>
                                            </div>
                                        </li>
                                    </ul>
                                    <p v-else class="text-xs text-gray-400 italic">Este producto no tiene componentes definidos.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-20 bg-white dark:bg-slate-900/50 rounded-xl shadow-md border border-slate-200 dark:border-slate-800">
                <i class="fa-solid fa-mug-hot text-6xl text-gray-400 dark:text-gray-600"></i>
                <h3 class="mt-4 text-2xl font-bold text-gray-700 dark:text-gray-300">¡Todo en orden!</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-2">No tienes tareas pendientes por ahora. ¡Buen trabajo!</p>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage, ElMessageBox } from 'element-plus';
import axios from 'axios';

export default {
    name: 'OperatorView',
    props: {
        tasks: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            selectedTask: null,
            taskDetails: null,
            isLoadingDetails: false,
            statusClasses: {
                'Pendiente': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                'En Proceso': 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                'Pausada': 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300',
                'Sin material': 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                'Terminada': 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                'default': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            },
        };
    },
    computed: {
        groupedTasks() {
            if (!this.tasks || this.tasks.length === 0) return [];
            
            const groups = this.tasks.reduce((acc, task) => {
                const sale = task.production.sale_product.sale;
                if (!sale) return acc;

                const saleId = sale.id;
                
                if (!acc[saleId]) {
                    acc[saleId] = {
                        saleId: saleId,
                        branchName: sale.branch?.name || 'N/A',
                        is_high_priority: sale.is_high_priority,
                        type: sale.type,
                        promise_date: sale.promise_date,
                        tasks: []
                    };
                }
                
                acc[saleId].tasks.push(task);
                return acc;
            }, {});
            
            return Object.values(groups);
        }
    },
    methods: {
        groupByType(details) {
            const grouped = {}
            details.forEach(d => {
            if (!grouped[d.type]) {
                grouped[d.type] = []
            }
            grouped[d.type].push(d)
            })
            return Object.keys(grouped).map(type => ({
            type,
            details: grouped[type]
            }))
        },
        async toggleDetails(task) {
            if (this.selectedTask && this.selectedTask.id === task.id) {
                this.selectedTask = null;
                this.taskDetails = null;
                return;
            }

            this.selectedTask = task;
            this.isLoadingDetails = true;
            this.taskDetails = null;

            try {
                const response = await axios.get(route('production-tasks.details', task.id));
                this.taskDetails = response.data;
            } catch (error) {
                console.error("Error fetching task details:", error);
                ElMessage.error('No se pudieron cargar los detalles de la tarea.');
                this.selectedTask = null; // Cierra si hay error
            } finally {
                this.isLoadingDetails = false;
            }
        },
        getProductImage(product) {
            return product?.media?.length > 0
                ? product.media[0].original_url
                : 'https://placehold.co/100x100/E2E8F0/475569?text=Sin+Img';
        },
        updateTaskStatus(taskId, newStatus, additionalData = {}) {
            this.$inertia.put(route('production-tasks.updateStatus', taskId), {
                status: newStatus,
                ...additionalData
            }, {
                preserveScroll: true,
                onSuccess: () => ElMessage.success(`Tarea actualizada a "${newStatus}"`),
                onError: () => ElMessage.error('No se pudo actualizar la tarea.'),
            });
        },
        startTask(taskId) { this.updateTaskStatus(taskId, 'En Proceso'); },
        resumeTask(taskId) { this.updateTaskStatus(taskId, 'En Proceso'); },
        pauseTask(taskId) { this.updateTaskStatus(taskId, 'Pausada'); },
        reportIssue(task) {
             ElMessageBox.confirm(
                `¿Estás seguro de reportar falta de material para la tarea "${task.name}"?`, 'Confirmar Reporte',
                { confirmButtonText: 'Sí, reportar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.updateTaskStatus(task.id, 'Sin material')
            ).catch(() => ElMessage.info('Acción cancelada'));
        },
        finishTask(task) {
            const quantityToProduce = this.getQuantityToProduce(task);
            ElMessageBox.prompt('Ingresa la cantidad de UNIDADES BUENAS terminadas.', 'Finalizar Tarea', {
                confirmButtonText: 'Siguiente',
                cancelButtonText: 'Cancelar',
                inputType: 'number',
                inputValue: quantityToProduce,
                inputValidator: (v) => (v !== null && v !== '' && v >= 0) || 'La cantidad es requerida.',
            }).then(({ value: good_units }) => {
                 ElMessageBox.prompt('Ingresa la cantidad de UNIDADES CON DEFECTO (merma).', 'Merma', {
                    confirmButtonText: 'Finalizar',
                    cancelButtonText: 'Cancelar',
                    inputType: 'number',
                    inputValue: 0,
                    inputValidator: (v) => (v !== null && v !== '' && v >= 0) || 'La cantidad es requerida.',
                }).then(({ value: scrap }) => {
                    this.updateTaskStatus(task.id, 'Terminada', { good_units, scrap });
                }).catch(() => ElMessage.info('Finalización cancelada en paso de merma.'));
            }).catch(() => ElMessage.info('Acción cancelada'));
        },
        getQuantityToProduce(task) {
            const saleProduct = task.production?.sale_product;
            const productionsOnSale = saleProduct?.sale?.productions;

            if (!saleProduct || !productionsOnSale) {
                return task.production?.quantity_to_produce ?? 0;
            }
            
            const correctProduction = productionsOnSale.find(p => p.sale_product_id === saleProduct.id);
            
            return correctProduction?.quantity_to_produce ?? 0;
        },
        stockUsed(task) {
            const saleProduct = task.production.sale_product;
            if (!saleProduct) return 0;
            
            const quantityToProduce = this.getQuantityToProduce(task);
            return saleProduct.quantity - quantityToProduce;
        },
        getCustomizationDetails(task) {
            const currentSaleProduct = task.production?.sale_product;
            const saleWithProducts = currentSaleProduct?.sale;

            if (!currentSaleProduct || !saleWithProducts?.sale_products) {
                return [];
            }

            const correctSaleProductDetails = saleWithProducts.sale_products.find(
                sp => sp.id === currentSaleProduct.id
            );

            return correctSaleProductDetails?.customization_details ?? [];
        },
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString('es-MX', options);
        },
        isDateInPast(dateString) {
            if (!dateString) return false;
            const promiseDate = new Date(dateString);
            promiseDate.setHours(0, 0, 0, 0);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return promiseDate < today;
        }
    },
};
</script>

