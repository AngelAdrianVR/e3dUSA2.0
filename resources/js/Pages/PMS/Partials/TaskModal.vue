<template>
    <DialogModal :show="show" @close="closeModal" maxWidth="2xl">
        <template #title>
            <div class="flex justify-between items-center w-full pr-4">
                <span class="font-bold text-gray-800 dark:text-gray-200">
                    {{ isEditing ? (task.folio + ' - Editar Tarea') : 'Nueva Tarea' }}
                </span>
                <span v-if="isEditing" :class="deptColor(form.department)" class="px-3 py-1 text-xs font-bold uppercase rounded-md text-white shadow-sm">
                    {{ form.department }}
                </span>
            </div>
        </template>

        <template #content>
            <form @submit.prevent="submit" class="space-y-4 mt-2">
                <!-- Título -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Título de la Tarea <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600 dark:text-white sm:text-sm" placeholder="Ej. Revisión de inventario" />
                    <div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Departamento -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Departamento <span class="text-red-500">*</span></label>
                        <el-select v-model="form.department" class="w-full mt-1" :disabled="!canEdit" :teleported="false">
                            <el-option label="Producción" value="Producción" />
                            <el-option label="Ventas" value="Ventas" />
                            <el-option label="Administración" value="Administración" />
                            <el-option label="Diseño" value="Diseño" />
                            <el-option label="General" value="General" />
                        </el-select>
                        <div v-if="form.errors.department" class="text-red-500 text-xs mt-1">{{ form.errors.department }}</div>
                    </div>

                    <!-- Prioridad -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Prioridad <span class="text-red-500">*</span></label>
                        <el-select v-model="form.priority" class="w-full mt-1" :disabled="!canEdit" :teleported="false">
                            <el-option label="Baja" value="Baja" />
                            <el-option label="Media" value="Media" />
                            <el-option label="Alta" value="Alta" />
                        </el-select>
                    </div>

                    <!-- Fecha Compromiso -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha Compromiso <span class="text-red-500">*</span></label>
                        <el-date-picker
                            v-model="form.due_date"
                            type="datetime"
                            placeholder="Selecciona fecha y hora"
                            class="w-full mt-1"
                            :disabled="!canEdit"
                            format="YYYY-MM-DD HH:mm"
                            value-format="YYYY-MM-DD HH:mm:ss"
                            :teleported="false"
                        />
                        <div v-if="form.errors.due_date" class="text-red-500 text-xs mt-1">{{ form.errors.due_date }}</div>
                    </div>

                    <!-- Responsable -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Responsable</label>
                        <el-select v-model="form.responsible_id" :teleported="false" filterable clearable class="w-full mt-1" placeholder="Dejar en blanco para Backlog" :disabled="!canEdit">
                            <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                        </el-select>
                    </div>
                </div>

                <div v-if="isEditing">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estatus en Tablero</label>
                    <el-select v-model="form.kanban_status" class="w-full mt-1" :disabled="!canEdit" :teleported="false">
                        <el-option label="Pendiente" value="Pendiente" />
                        <el-option label="En proceso" value="En proceso" />
                        <el-option label="Validación" value="Validación" />
                        <el-option label="Terminado" value="Terminado" />
                    </el-select>
                    <p v-if="['Validación', 'Terminado'].includes(form.kanban_status)" class="text-xs text-orange-600 mt-1">
                        <i class="fa-solid fa-triangle-exclamation"></i> Recuerda que la norma ISO requiere adjuntar evidencia física o digital.
                    </p>
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción / Detalles</label>
                    <textarea v-model="form.description" rows="3" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-600 dark:text-white sm:text-sm"></textarea>
                </div>

                <!-- Módulo de Evidencias / Archivos -->
                <div class="mt-4 p-4 bg-gray-50 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-600">
                    <h4 class="font-semibold text-gray-700 dark:text-gray-300 text-sm mb-3">Evidencias Adjuntas</h4>
                    
                    <!-- Archivos Existentes -->
                    <div v-if="isEditing && task.media && task.media.length > 0" class="mb-4 space-y-2">
                        <div v-for="file in task.media" :key="file.id" class="flex items-center justify-between p-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded text-sm">
                            <div class="flex items-center space-x-2 truncate max-w-[85%]">
                                <i class="fa-solid fa-file-pdf text-red-500" v-if="file.mime_type.includes('pdf')"></i>
                                <i class="fa-solid fa-image text-blue-500" v-else-if="file.mime_type.includes('image')"></i>
                                <i class="fa-solid fa-file text-gray-500" v-else></i>
                                <a :href="file.original_url" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline truncate">
                                    {{ file.file_name }}
                                </a>
                            </div>
                            <button v-if="canEdit" type="button" @click="deleteExistingFile(file.id)" class="text-red-500 hover:text-red-700 px-2">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div v-else-if="isEditing" class="mb-4 text-xs text-gray-500">
                        No hay archivos adjuntos.
                    </div>

                    <!-- Input Nuevos Archivos -->
                    <div v-if="canEdit">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Cargar Nuevas Evidencias (Múltiples permitidas)</label>
                        <input type="file" multiple @change="handleFileUpload" class="block w-full text-sm text-gray-500 dark:text-gray-300
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100" 
                        />
                        <div v-if="form.errors.evidence_files" class="text-red-500 text-xs mt-1">{{ form.errors.evidence_files }}</div>
                    </div>
                </div>

            </form>
        </template>

        <template #footer>
            <div class="flex justify-between w-full">
                <div>
                    <el-popconfirm v-if="isEditing && canDelete" confirm-button-text="Sí, eliminar" :teleported="false" cancel-button-text="No" icon-color="#EF4444" title="¿Eliminar esta tarea permanentemente?" @confirm="deleteTask">
                        <template #reference>
                            <button type="button" class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition text-sm font-semibold">
                                <i class="fa-solid fa-trash"></i> Eliminar
                            </button>
                        </template>
                    </el-popconfirm>
                </div>
                
                <div class="flex space-x-3">
                    <button @click="closeModal" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-semibold transition">
                        {{ canEdit ? 'Cancelar' : 'Cerrar' }}
                    </button>
                    
                    <button v-if="canEdit" @click="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold transition disabled:opacity-50">
                        {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar Cambios' : 'Crear Tarea') }}
                    </button>
                </div>
            </div>
        </template>
    </DialogModal>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import { ElMessage } from 'element-plus';
import { format } from 'date-fns';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    task: Object,
    users: Array
});

const emit = defineEmits(['close']);
const page = usePage();

const canEdit = computed(() => {
    if (!isEditing.value) return page.props.auth.user.permissions.includes('Crear tareas');
    return page.props.auth.user.permissions.includes('Editar tareas');
});
const canDelete = computed(() => page.props.auth.user.permissions.includes('Eliminar tareas'));

const isEditing = computed(() => !!props.task);

const form = useForm({
    title: '',
    department: 'General',
    priority: 'Media',
    due_date: '',
    responsible_id: null,
    kanban_status: 'Pendiente',
    description: '',
    evidence_files: [] // <-- Array para múltiples archivos
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.task) {
            // Se usa el formato adecuado para Element Plus datepicker (YYYY-MM-DD HH:mm:ss)
            const formattedDate = props.task.due_date ? format(new Date(props.task.due_date), 'yyyy-MM-dd HH:mm:ss') : '';
            
            form.title = props.task.title;
            form.department = props.task.department;
            form.priority = props.task.priority;
            form.due_date = formattedDate;
            form.responsible_id = props.task.responsible_id;
            form.kanban_status = props.task.kanban_status;
            form.description = props.task.description || '';
            form.evidence_files = []; // reset files
        } else {
            form.reset();
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            form.due_date = format(tomorrow, 'yyyy-MM-dd HH:mm:ss');
            form.evidence_files = [];
        }
    }
});

const handleFileUpload = (e) => {
    form.evidence_files = Array.from(e.target.files);
};

const deleteExistingFile = async (mediaId) => {
    if (confirm('¿Estás seguro de eliminar este archivo?')) {
        try {
            await axios.delete(route('media.delete-file', mediaId));
            ElMessage.success('Archivo eliminado correctamente');
            // Removemos de la vista actual
            props.task.media = props.task.media.filter(m => m.id !== mediaId);
        } catch (e) {
            ElMessage.error('Ocurrió un error al eliminar el archivo');
        }
    }
};

const submit = () => {
    if (isEditing.value) {
        // MUY IMPORTANTE: Cuando se envían archivos mediante PUT, Inertia/PHP fallan leyendo los binarios.
        // Se debe usar POST y transformar enviando `_method: put`
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('pms.update', props.task.id), {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Tarea actualizada correctamente');
                closeModal();
            }
        });
    } else {
        form.post(route('pms.store'), {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Tarea creada y añadida al tablero');
                closeModal();
            }
        });
    }
};

const deleteTask = () => {
    form.delete(route('pms.destroy', props.task.id), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Tarea eliminada');
            closeModal();
        }
    });
};

const closeModal = () => {
    form.clearErrors();
    emit('close');
};

const deptColor = (dept) => {
    const map = {
        'Producción': 'bg-blue-600',
        'Ventas': 'bg-green-600',
        'Administración': 'bg-yellow-500',
        'Diseño': 'bg-red-600',
        'General': 'bg-gray-600'
    };
    return map[dept] || 'bg-gray-600';
};
</script>