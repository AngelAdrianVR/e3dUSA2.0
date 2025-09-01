<template>
    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div v-if="groupedTasks.length > 0" class="space-y-6">
                <!-- Itera sobre cada grupo de orden de venta -->
                <div v-for="group in groupedTasks" :key="group.saleId" 
                     class="relative bg-white dark:bg-slate-900/70 backdrop-blur-sm shadow-lg rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    
                    <!-- Indicador de Alta Prioridad -->
                    <div v-if="group.is_high_priority" class="absolute top-0 right-0 h-16 w-16">
                        <div class="absolute transform rotate-45 bg-red-600 text-center text-white font-semibold py-1 right-[-34px] top-[32px] w-[170px] shadow-lg">
                            <i class="fa-solid fa-fire mr-1"></i> Prioridad
                        </div>
                    </div>

                    <!-- Encabezado del Grupo (Orden de Venta) -->
                    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center space-x-2">
                                    <i v-if="group.type === 'venta'" class="fa-solid fa-cart-shopping text-purple-500" title="Orden de Venta"></i>
                                    <i v-else class="fa-solid fa-dolly text-red-500" title="Orden de Servicio"></i>
                                    <span>{{ group.type === 'venta' ? 'OV' : 'OS' }}-{{ group.saleId.toString().padStart(4, '0') }}</span>
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ group.branchName }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Tareas para este grupo -->
                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        <div v-for="task in group.tasks" :key="task.id" class="p-4 transition-all duration-300">
                           <div class="flex items-start space-x-4">
                               <img draggable="false" :src="getProductImage(task.production.sale_product.product)" class="w-24 h-24 rounded-lg object-cover border-2 border-white dark:border-slate-700 shadow-sm flex-shrink-0">

                               <div class="flex-1 min-w-0">
                                   <div class="flex justify-between items-start">
                                       <div>
                                            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200 truncate" :title="task.name">{{ task.name }}</h3>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                {{ task.production.sale_product.product.name }}
                                            </p>
                                       </div>
                                       <span class="px-3 py-1 text-xs font-bold rounded-full flex-shrink-0 ml-2" :class="statusClasses[task.status] || statusClasses['default']">
                                           {{ task.status }}
                                       </span>
                                   </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-2 space-y-1">
                                       <p v-if="task.started_at"><i class="fa-regular fa-clock w-4 text-center mr-1"></i> Inicio: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ formatDate(task.started_at) }}</span></p>
                                       <p v-if="task.finished_at"><i class="fa-regular fa-flag-checkered w-4 text-center mr-1"></i> Fin: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ formatDate(task.finished_at) }}</span></p>
                                   </div>
                                   
                                   <!-- Collapse para componentes -->
                                   <div v-if="task.production.sale_product.product.components && task.production.sale_product.product.components.length > 0" class="mt-3">
                                        <el-collapse accordion>
                                            <el-collapse-item name="1">
                                                 <template #title>
                                                    <span class="text-xs font-semibold text-primary"><i class="fa-solid fa-puzzle-piece mr-2"></i>Ver Componentes del Producto</span>
                                                </template>
                                                <ul class="divide-y divide-slate-200 dark:divide-slate-700 px-2">
                                                    <li v-for="component in task.production.sale_product.product.components" :key="component.id" class="py-2 text-xs">
                                                        <p class="font-bold text-gray-700 dark:text-gray-300">{{ component.name }}</p>
                                                        <p class="text-gray-500">Código: {{ component.code }} | U.M.: {{ component.measure_unit }}</p>
                                                    </li>
                                                </ul>
                                            </el-collapse-item>
                                        </el-collapse>
                                   </div>
                               </div>
                               
                               <!-- Botones de Acción -->
                               <div class="flex flex-col items-center justify-center space-y-2 flex-shrink-0 w-28">
                                    <PrimaryButton @click="startTask(task.id)" v-if="task.status === 'Pendiente'" class="w-full justify-center">
                                       <i class="fa-solid fa-play mr-2"></i> Iniciar
                                   </PrimaryButton>
                                    <PrimaryButton @click="resumeTask(task.id)" v-if="task.status === 'Pausada'" class="w-full justify-center">
                                       <i class="fa-solid fa-play mr-2"></i> Reanudar
                                   </PrimaryButton>
                                    <SecondaryButton @click="pauseTask(task.id)" v-if="task.status === 'En Proceso'" class="w-full justify-center">
                                       <i class="fa-solid fa-pause mr-2"></i> Pausar
                                   </SecondaryButton>
                                   <button @click="finishTask(task)" v-if="['En Proceso', 'Pausada'].includes(task.status)" class="w-full text-sm inline-flex items-center justify-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-white uppercase tracking-widest hover:bg-green-500 focus:outline-none focus:border-green-700 focus:ring focus:ring-green-200 active:bg-green-600 disabled:opacity-25 transition">
                                       <i class="fa-solid fa-check mr-2"></i> Finalizar
                                   </button>
                                    <button @click="reportIssue(task)" v-if="['Pendiente', 'En Proceso', 'Pausada'].includes(task.status)" class="w-full text-sm inline-flex items-center justify-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:border-red-700 focus:ring focus:ring-red-200 active:bg-red-600 disabled:opacity-25 transition">
                                       <i class="fa-solid fa-triangle-exclamation mr-2"></i> Reportar
                                   </button>
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
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    name: 'OperatorView',
    components: {
        PrimaryButton,
        SecondaryButton,
    },
    props: {
        tasks: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
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
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        getProductImage(product) {
            return product?.media?.length > 0
                ? product.media[0].original_url
                : 'https://placehold.co/100x100/424242/FFFFFF?text=P';
        },
        updateTaskStatus(taskId, newStatus, additionalData = {}) {
            this.$inertia.put(this.route('production-tasks.updateStatus', taskId), {
                status: newStatus,
                ...additionalData
            }, {
                preserveScroll: true,
                onSuccess: () => ElMessage.success(`Tarea actualizada a "${newStatus}"`),
                onError: () => ElMessage.error('No se pudo actualizar la tarea.'),
            });
        },
        startTask(taskId) {
            this.updateTaskStatus(taskId, 'En Proceso');
        },
        resumeTask(taskId) {
            this.updateTaskStatus(taskId, 'En Proceso');
        },
        pauseTask(taskId) {
            this.updateTaskStatus(taskId, 'Pausada');
        },
        reportIssue(task) {
            ElMessageBox.confirm(
                `¿Estás seguro de reportar falta de material para la tarea "${task.name}"? Esto afectará el estado de la producción.`,
                'Confirmar Falta de Material',
                {
                    confirmButtonText: 'Sí, reportar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            ).then(() => {
                this.updateTaskStatus(task.id, 'Sin material');
            }).catch(() => {
                ElMessage.info('Acción cancelada');
            });
        },
        finishTask(task) {
            let goodUnits, scrapUnits = 0, scrapReason = '';

            ElMessageBox.prompt('Paso 1/2: Ingresa la cantidad de UNIDADES BUENAS terminadas.', 'Finalizar Tarea', {
                confirmButtonText: 'Siguiente',
                cancelButtonText: 'Cancelar',
                inputType: 'number',
                inputValue: task.production.quantity_to_produce,
                inputValidator: (v) => (v !== null && v !== '' && v >= 0) || 'La cantidad no puede ser negativa.',
            }).then(({ value: good }) => {
                goodUnits = parseInt(good);
                return ElMessageBox.prompt('Paso 2/2: Ingresa la cantidad de UNIDADES CON DEFECTO (merma).', 'Finalizar Tarea', {
                    confirmButtonText: 'Siguiente',
                    cancelButtonText: 'Cancelar',
                    inputType: 'number',
                    inputValue: 0,
                    inputValidator: (v) => (v !== null && v !== '' && v >= 0) || 'La cantidad no puede ser negativa.',
                });
            }).then(({ value: scrap }) => {
                scrapUnits = parseInt(scrap);
                if (scrapUnits > 0) {
                    return ElMessageBox.prompt('Describe brevemente la razón de la merma (ej. mal corte, defecto de material).', 'Razón de Merma', {
                        confirmButtonText: 'Finalizar Tarea',
                        cancelButtonText: 'Cancelar',
                        inputType: 'textarea',
                        inputValidator: (v) => (v && v.trim() !== '') || 'Debes especificar una razón para la merma.',
                    });
                }
                return Promise.resolve({ value: '' });
            }).then(({ value: reason }) => {
                scrapReason = reason;
                this.updateTaskStatus(task.id, 'Terminada', { 
                    good_units: goodUnits,
                    scrap: scrapUnits,
                    scrap_reason: scrapReason
                });
            }).catch(() => {
                ElMessage.info('Acción cancelada');
            });
        },
    },
};
</script>
