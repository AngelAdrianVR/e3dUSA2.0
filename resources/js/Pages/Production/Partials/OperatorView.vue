<template>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div v-if="groupedTasks.length > 0" class="space-y-3">
                <!-- Itera sobre cada grupo de orden de venta -->
                <div v-for="group in groupedTasks" :key="group.saleId" 
                     class="bg-white dark:bg-slate-900/50 backdrop-blur-sm shadow-md rounded-xl border border-slate-200 dark:border-slate-800">
                    
                    <!-- Encabezado del Grupo (Orden de Venta) -->
                    <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-800">
                        <h2 class="text-base font-bold text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-purple-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-red-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                            <span>OV-{{ group.saleId.toString().padStart(4, '0') }}</span>
                            <span>OS-{{ group.saleId.toString().padStart(4, '0') }}</span>
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ group.branchName }}</p>
                    </div>

                    <!-- Lista de Tareas para este grupo -->
                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        <div v-for="task in group.tasks" :key="task.id"
                             class="p-4 flex items-center space-x-4 transition-all duration-300">
                            
                            <img :src="getProductImage(task.production.sale_product.product)"
                                 class="w-20 h-20 rounded-md object-cover border-2 border-white dark:border-slate-700 shadow-sm flex-shrink-0">

                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-md font-bold text-gray-800 dark:text-gray-200 truncate" :title="task.name">{{ task.name }}</h3>
                                        <p class="text-sm text-gray-500 truncate">
                                            {{ task.production.sale_product.product.name }}
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full flex-shrink-0 ml-2"
                                          :class="statusClasses[task.status] || statusClasses['default']">
                                        {{ task.status }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1.5">
                                    <i class="fa-regular fa-clock mr-1"></i>
                                    Tiempo Estimado: {{ task.estimated_time_minutes }} min. |
                                    <span class="ml-1 font-semibold text-gray-600 dark:text-gray-300">OP-{{ task.production_id }}</span>
                                </p>
                            </div>

                            <div class="flex flex-col items-center justify-center space-y-2 flex-shrink-0">
                                 <PrimaryButton @click="startTask(task.id)" v-if="task.status === 'Pendiente'" class="!w-10 !h-10 !p-0 flex items-center justify-center" title="Iniciar Tarea">
                                    <i class="fa-solid fa-play"></i>
                                </PrimaryButton>
                                 <button @click="resumeTask(task.id)" v-if="task.status === 'Pausada'" class="w-10 h-10 rounded-full bg-blue-500 text-white hover:bg-blue-600 transition shadow-md flex items-center justify-center" title="Reanudar Tarea">
                                     <i class="fa-solid fa-play"></i>
                                 </button>
                                 <SecondaryButton @click="pauseTask(task.id)" v-if="task.status === 'En Proceso'" class="!w-10 !h-10 !p-0 flex items-center justify-center" title="Pausar Tarea">
                                    <i class="fa-solid fa-pause"></i>
                                </SecondaryButton>
                                <button @click="finishTask(task)" v-if="['En Proceso', 'Pausada'].includes(task.status)" class="w-10 h-10 rounded-full bg-green-500 text-white hover:bg-green-600 transition shadow-md flex items-center justify-center" title="Finalizar Tarea">
                                     <i class="fa-solid fa-check"></i>
                                </button>
                                 <button @click="reportIssue(task)" v-if="['Pendiente', 'En Proceso', 'Pausada'].includes(task.status)" class="w-10 h-10 rounded-full bg-red-500 text-white hover:bg-red-600 transition shadow-md flex items-center justify-center" title="Reportar Falta de Material">
                                     <i class="fa-solid fa-triangle-exclamation"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-16 bg-white dark:bg-slate-900 rounded-lg shadow-md">
                <i class="fa-solid fa-mug-hot text-5xl text-gray-400"></i>
                <h3 class="mt-4 text-xl font-semibold text-gray-700 dark:text-gray-300">¡Todo en orden!</h3>
                <p class="text-gray-500">No tienes tareas pendientes por ahora.</p>
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
                        tasks: []
                    };
                }
                
                acc[saleId].tasks.push(task);
                return acc;
            }, {});
            
            return Object.values(groups).sort((a, b) => b.saleId - a.saleId);
        }
    },
    methods: {
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
