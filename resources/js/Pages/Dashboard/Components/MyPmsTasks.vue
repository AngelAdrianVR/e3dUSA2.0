<template>
    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-md dark:bg-gray-800 dark:border-gray-700 h-full flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div>
                <h5 class="text-xl font-bold leading-none text-gray-900 dark:text-white">Mis Tareas (PMS)</h5>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Tareas pendientes y en proceso asignadas a ti.</p>
            </div>
            <Link :href="route('pms.index')" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500">
                Ver tablero completo
            </Link>
        </div>

        <!-- Body / List -->
        <div class="flow-root overflow-y-auto custom-scrollbar flex-1 min-h-[250px] pr-2">
            <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="task in tasks" :key="task.id" class="py-3 sm:py-4 group">
                    <div class="flex items-center space-x-4">
                        
                        <!-- Icono / Avatar de la tarea -->
                        <div class="flex-shrink-0 cursor-pointer" @click="$emit('view', task)">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white" :class="deptColor(task.department)">
                                <i class="fa-solid fa-list-check"></i>
                            </div>
                        </div>
                        
                        <!-- Textos de la Tarea -->
                        <div class="flex-1 min-w-0 cursor-pointer" @click="$emit('view', task)">
                            <p class="text-sm font-bold text-gray-900 truncate dark:text-white group-hover:text-blue-600 transition-colors">
                                {{ task.folio }} - {{ task.title }}
                            </p>
                            <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 mt-0.5 space-x-2">
                                <span class="font-medium">{{ task.department }}</span>
                                <span>&bull;</span>
                                <span :class="{'text-red-500 font-bold': isExpired(task.due_date)}">
                                    <i class="fa-regular fa-clock"></i> {{ formatDate(task.due_date) }}
                                </span>
                            </div>
                        </div>

                        <!-- Acciones: Subir Evidencia + Estatus -->
                        <div class="flex flex-col sm:flex-row items-end sm:items-center gap-2">
                            
                            <!-- Botón de Subir Evidencia Rápida -->
                            <div class="relative">
                                <el-tooltip content="Subir Evidencia (Regla ISO)" placement="top">
                                    <button 
                                        @click="triggerFileInput(task.id)"
                                        :disabled="uploadingTask === task.id"
                                        class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 hover:bg-blue-100 text-gray-600 hover:text-blue-600 dark:bg-gray-700 dark:hover:bg-blue-900/50 dark:text-gray-300 dark:hover:text-blue-400 transition-colors"
                                    >
                                        <i v-if="uploadingTask === task.id" class="fa-solid fa-spinner fa-spin"></i>
                                        <i v-else class="fa-solid fa-cloud-arrow-up"></i>
                                    </button>
                                </el-tooltip>
                                
                                <!-- Mini badge para ver cuántos archivos tiene -->
                                <span v-if="task.media && task.media.length > 0" class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-[9px] font-bold text-white shadow-sm border-2 border-white dark:border-gray-800">
                                    {{ task.media.length }}
                                </span>

                                <!-- Input oculto para cada tarea -->
                                <input 
                                    :id="'file-upload-' + task.id" 
                                    type="file" 
                                    multiple 
                                    class="hidden" 
                                    @change="(e) => handleFileUpload(e, task)" 
                                />
                            </div>

                            <!-- Dropdown de Estatus -->
                            <select
                                :value="task.kanban_status"
                                @change="(e) => updateStatus(task, e)"
                                class="w-[110px] sm:w-28 py-1 pl-2 pr-6 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-xs font-semibold cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 transition-colors"
                                :class="statusColorSelect(task.kanban_status)"
                            >
                                <option value="Pendiente">Pendiente</option>
                                <option value="En proceso">En proceso</option>
                                <option value="Validación">Validación</option>
                                <option value="Terminado">Terminado</option>
                            </select>
                        </div>
                        
                    </div>
                </li>
            </ul>
            
            <!-- Empty State -->
            <div v-if="!tasks.length" class="text-center py-10">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">No tienes tareas pendientes asignadas.</p>
                <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">¡Buen trabajo, todo está al día!</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { isPast, parseISO, format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => []
    }
});

defineEmits(['view']);

const page = usePage();
const uploadingTask = ref(null);

// --- LÓGICA DE SUBIDA DE EVIDENCIA RÁPIDA ---
const triggerFileInput = (taskId) => {
    const input = document.getElementById(`file-upload-${taskId}`);
    if (input) input.click();
};

const handleFileUpload = (e, task) => {
    const files = Array.from(e.target.files);
    if (!files.length) return;

    uploadingTask.value = task.id;

    // Usamos la misma ruta de actualización de estatus, enviando el estatus actual y los archivos.
    router.post(route('pms.update-status', task.id), {
        kanban_status: task.kanban_status, // Mantenemos el estatus como está
        evidence_files: files
    }, {
        forceFormData: true, // Requerido para enviar archivos
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Evidencia subida correctamente');
            e.target.value = ''; // Limpiar el input para permitir subir más después
        },
        onError: (errors) => {
            if (errors.evidence_files) {
                ElMessage.error(`Error: ${errors.evidence_files}`);
            } else {
                ElMessage.error('Error al subir la evidencia');
            }
        },
        onFinish: () => {
            uploadingTask.value = null;
        }
    });
};

// --- LÓGICA DE CAMBIO DE ESTATUS ---
const updateStatus = (task, event) => {
    const newStatus = event.target.value;
    const permissions = page.props.auth.user.permissions;

    // Front-end validación de seguridad (igual al backend)
    if (task.kanban_status === 'Validación' && newStatus === 'Terminado' && !permissions.includes('Validar tareas')) {
        ElMessage.warning('No tienes permiso para pasar a Terminado (Requiere permiso "Validar tareas").');
        event.target.value = task.kanban_status; // Revertir select
        return;
    }

    router.post(route('pms.update-status', task.id), {
        kanban_status: newStatus
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Estatus actualizado');
        },
        onError: (errors) => {
            // Manejador de errores del backend (ISO o Permisos)
            if (errors.evidence_files) {
                ElMessage.error({ message: `Regla ISO 9001: ${errors.evidence_files}`, duration: 5000 });
            } else if (errors.permission) {
                ElMessage.error(errors.permission);
            } else {
                ElMessage.error('Error al actualizar el estatus');
            }
            event.target.value = task.kanban_status; // Revertir select visualmente en caso de error
        }
    });
};


// --- UTILERÍAS VISUALES ---
const deptColor = (dept) => {
    const map = {
        'Producción': 'bg-blue-500',
        'Ventas': 'bg-green-500',
        'Administración': 'bg-yellow-500',
        'Diseño': 'bg-red-500',
        'General': 'bg-gray-500'
    };
    return map[dept] || 'bg-gray-500';
};

const statusColorSelect = (status) => {
    const map = {
        'Pendiente': 'text-gray-700 bg-gray-50',
        'En proceso': 'text-yellow-700 bg-yellow-50',
        'Validación': 'text-blue-700 bg-blue-50',
        'Terminado': 'text-green-700 bg-green-50'
    };
    return map[status] || 'text-gray-700';
};

const isExpired = (dateString) => {
    if (!dateString) return false;
    return isPast(parseISO(dateString));
};

const formatDate = (dateString) => {
    if (!dateString) return 'Sin fecha';
    return format(parseISO(dateString), "dd MMM", { locale: es });
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #4b5563; /* slate-600 */
}
</style>