<template>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div v-if="tasks && tasks.length > 0" class="space-y-4">
                <div v-for="task in tasks" :key="task.id"
                     class="bg-white dark:bg-slate-900/70 backdrop-blur-sm shadow-lg rounded-xl p-5 border border-slate-200 dark:border-slate-800 flex items-center space-x-5 transition-all duration-300 hover:border-primary dark:hover:border-primary">
                    
                    <img :src="getProductImage(task.production.sale_product.product)"
                         class="w-24 h-24 rounded-lg object-cover border-2 border-white dark:border-slate-700 shadow-md flex-shrink-0">

                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-center">
                            <p class="text-sm font-bold text-primary">OP-{{ task.production_id }}</p>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                  :class="statusClasses[task.status] || statusClasses['default']">
                                {{ task.status }}
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 truncate" :title="task.name">{{ task.name }}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 truncate">
                            {{ task.production.sale_product.product.name }} para <span class="font-semibold">{{ task.production.sale_product.sale.branch.name }}</span>
                        </p>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fa-regular fa-clock mr-1"></i>
                            Tiempo Estimado: {{ task.estimated_time_minutes }} min.
                        </p>
                    </div>

                    <div class="flex flex-col items-center justify-center space-y-2 flex-shrink-0">
                         <PrimaryButton @click="startTask(task.id)" v-if="task.status === 'Pendiente'" class="!w-10 !h-10 !p-0 flex items-center justify-center" title="Iniciar Tarea">
                            <i class="fa-solid fa-play"></i>
                        </PrimaryButton>
                         <SecondaryButton @click="pauseTask(task.id)" v-if="task.status === 'En Proceso'" class="!w-10 !h-10 !p-0 flex items-center justify-center" title="Pausar Tarea">
                            <i class="fa-solid fa-pause"></i>
                        </SecondaryButton>
                        <button @click="finishTask(task)" v-if="task.status === 'En Proceso' || task.status === 'Pausada'" class="w-10 h-10 rounded-full bg-green-500 text-white hover:bg-green-600 transition shadow-md flex items-center justify-center" title="Finalizar Tarea">
                             <i class="fa-solid fa-check"></i>
                        </button>
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
                'default': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            },
        };
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
        pauseTask(taskId) {
            this.updateTaskStatus(taskId, 'Pausada');
        },
        finishTask(task) {
            ElMessageBox.prompt('Ingresa la cantidad de unidades terminadas correctamente.', 'Finalizar Tarea', {
                confirmButtonText: 'Finalizar',
                cancelButtonText: 'Cancelar',
                inputType: 'number',
                inputValidator: (value) => {
                    if (!value || value <= 0) {
                        return 'La cantidad debe ser mayor a cero';
                    }
                    return true;
                },
            }).then(({ value }) => {
                const goodUnits = parseInt(value);
                this.updateTaskStatus(task.id, 'Terminada', { good_units: goodUnits });
            }).catch(() => {
                ElMessage.info('Acción cancelada');
            });
        },
    },
};
</script>