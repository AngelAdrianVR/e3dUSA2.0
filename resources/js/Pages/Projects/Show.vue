<template>
    <AppLayout :title="project.name">
        <div class="py-7">
            <div class="max-w-[110rem] mx-auto sm:px-6 lg:px-8">
                <div class="overflow-hidden sm:rounded-lg min-h-[calc(100vh-120px)] flex flex-col">

                    <!-- HEADER -->
                    <div class="p-6 pb-4 border-b dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <Link :href="route('projects.index')" class="mt-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition" title="Volver a proyectos">
                                <i class="fa-solid fa-arrow-left"></i>
                            </Link>
                            <div class="min-w-0">
                                <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-3 flex-wrap">
                                    {{ project.name }}
                                    <span v-if="memberRole === 'Colaborador'" class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded-full">Colaborador</span>
                                    <span v-else-if="memberRole === 'Administrador'" class="text-[10px] font-semibold text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full">Administrador</span>
                                </h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Creado por <strong>{{ project.creator?.name }}</strong> · {{ formatDate(project.start_date) }}
                                    <template v-if="project.tentative_end_date"> → {{ formatDate(project.tentative_end_date) }} (tentativo)</template>
                                    <template v-if="project.actual_end_date"> · Finalizado {{ formatDate(project.actual_end_date) }}</template>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="canEdit" @click="openEditProject"
                                class="flex items-center gap-2 bg-slate-800 dark:bg-slate-700 text-white px-4 py-2 rounded-lg shadow hover:bg-slate-700 dark:hover:bg-slate-600 transition text-sm">
                                <i class="fa-solid fa-pen"></i> Editar proyecto
                            </button>
                            <el-popconfirm v-if="canDelete" title="¿Eliminar este proyecto y todas sus tareas?" confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#D33" @confirm="deleteProject">
                                <template #reference>
                                    <button class="flex items-center gap-2 text-red-500 border border-red-200 dark:border-red-900/50 px-4 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 transition text-sm">
                                        <i class="fa-solid fa-trash-can"></i> Eliminar
                                    </button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>

                    <!-- PESTAÑAS -->
                    <div class="flex-1">
                        <el-tabs v-model="activeTab" class="p-5">
                            <!-- ============ TAB: INFORMACIÓN GENERAL ============ -->
                            <el-tab-pane label="Información general" name="info">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <!-- Columna izquierda: datos -->
                                    <div class="lg:col-span-2 space-y-5">
                                        <!-- Progreso -->
                                        <div class="bg-gray-50 dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Avance del proyecto</span>
                                                <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ progress }}%</span>
                                            </div>
                                            <div class="h-2.5 bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-blue-500 to-emerald-500 rounded-full transition-all duration-500" :style="{ width: progress + '%' }"></div>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                {{ project.finished_tasks_count || 0 }} de {{ project.tasks_count || 0 }} tareas terminadas
                                            </p>
                                        </div>

                                        <!-- Descripción -->
                                        <div v-if="project.description" class="bg-gray-50 dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700">
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 block mb-1">Descripción</span>
                                            <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ project.description }}</p>
                                        </div>

                                        <!-- Datos financieros -->
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div class="bg-gray-50 dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700">
                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Presupuesto de inversión</span>
                                                <p class="text-xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ formatMoney(project.budget) }}</p>
                                            </div>
                                            <div class="bg-gray-50 dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700">
                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Moneda</span>
                                                <p class="text-xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ project.currency }}</p>
                                            </div>
                                        </div>

                                        <!-- Archivos del proyecto -->
                                        <div class="bg-gray-50 dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700">
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 block mb-3">Archivos / imágenes</span>
                                            <div v-if="project.media?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                                                <FileView v-for="file in project.media" :key="file.id" :file="file" :deletable="canEdit" @delete-file="removeProjectFile(file.id)" />
                                            </div>
                                            <p v-if="!project.media?.length" class="text-xs text-gray-400 mb-3">Sin archivos adjuntos.</p>
                                            <FileUploader v-if="canEdit" :multiple="true" format="Todo" :max-files="10" :max-file-size="10" @files-selected="uploadProjectFiles" />
                                        </div>
                                    </div>

                                    <!-- Columna derecha: miembros -->
                                    <div class="space-y-5">
                                        <div class="bg-gray-50 dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700">
                                            <div class="flex items-center justify-between mb-3">
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                                    <i class="fa-solid fa-users"></i> Miembros
                                                </span>
                                                <button v-if="canEdit" @click="openEditProject" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                                    Gestionar
                                                </button>
                                            </div>
                                            <div class="space-y-2">
                                                <div v-for="member in allMembers" :key="member.id" class="flex items-center gap-2.5 p-2 rounded-lg bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700">
                                                    <img :src="member.profile_photo_url" class="size-8 rounded-full object-cover" :alt="member.name" />
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ member.name }}</p>
                                                        <p class="text-[10px] text-gray-400">{{ member.roleLabel }}</p>
                                                    </div>
                                                    <i v-if="member.id === project.created_by" class="fa-solid fa-crown text-amber-400" title="Creador"></i>
                                                </div>
                                            </div>
                                            <p class="text-[11px] text-gray-400 mt-3 leading-relaxed border-t border-gray-200 dark:border-slate-700 pt-2">
                                                <i class="fa-solid fa-circle-info text-blue-500 mr-1"></i>
                                                <strong>Colaborador</strong>: ver y comentar · <strong>Administrador</strong>: editar datos y crear/mover tareas. Todos comentan.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </el-tab-pane>

                            <!-- ============ TAB: TAREAS (KANBAN) ============ -->
                            <el-tab-pane label="Tareas" name="tasks">
                                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <i class="fa-solid fa-table-columns mr-1"></i>
                                        {{ canEdit ? 'Arrastra las tareas para cambiar su estatus' : 'Arrastra tus propias tareas para cambiar su estatus' }}
                                    </p>
                                    <div class="flex items-center gap-3">
                                        <button v-if="canEdit" @click="openCreateTask"
                                            class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-500 transition text-sm">
                                            <i class="fa-solid fa-plus"></i> Nueva tarea
                                        </button>
                                    </div>
                                </div>
                                <KanbanBoard :tasks="tasks" :can-edit="canEdit" :member-role="memberRole" :current-user-id="currentUserId" @update-status="handleStatusUpdate" @task-click="openTask" />
                            </el-tab-pane>

                            <!-- ============ TAB: GANTT ============ -->
                            <el-tab-pane label="Gantt" name="gantt">
                                <GanttChart :tasks="tasks" />
                            </el-tab-pane>
                        </el-tabs>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal crear/editar proyecto -->
        <ProjectFormModal :show="showProjectFormModal" :project="editingProject" :users="activeUsers" @close="showProjectFormModal = false" @saved="showProjectFormModal = false" />

        <!-- Modal tarea -->
        <TaskModal :show="showTaskModal" :task="selectedTask" :project="project" :can-edit="canEdit" :is-member="isMember" @close="closeTaskModal" />
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, useForm, usePage, Link } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import FileUploader from '@/Components/MyComponents/FileUploader.vue';
import FileView from '@/Components/MyComponents/FileView.vue';
import KanbanBoard from './Partials/KanbanBoard.vue';
import GanttChart from './Partials/GanttChart.vue';
import TaskModal from './Partials/TaskModal.vue';
import ProjectFormModal from './Partials/ProjectFormModal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    memberRole: { type: String, default: null },
    isMember: { type: Boolean, default: false },
    activeUsers: { type: Array, default: () => [] },
});

const page = usePage();
const currentUserId = page.props.auth.user.id;

// La pestaña activa persiste en la URL (?tab=) para que sobreviva a recargas de página
const activeTab = ref(new URL(window.location.href).searchParams.get('tab') || 'info');

// Sincroniza la pestaña activa con la URL sin recargar la página
watch(activeTab, (tab) => {
    if (!['info', 'tasks', 'gantt'].includes(tab)) return;
    const url = new URL(window.location.href);
    if (tab === 'info') url.searchParams.delete('tab');
    else url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
});
const showProjectFormModal = ref(false);
const editingProject = ref(null);
const showTaskModal = ref(false);
const selectedTask = ref(null);

const tasks = computed(() => props.project.tasks || []);

// Miembros con etiqueta de rol legible
const allMembers = computed(() => {
    const members = [...(props.project.members || [])];
    if (props.project.creator && !members.some(m => m.id === props.project.creator.id)) {
        members.unshift(props.project.creator);
    }
    return members.map(m => ({
        ...m,
        roleLabel: m.id === props.project.created_by
            ? 'Creador · Administrador'
            : (m.pivot?.role === 'Administrador' ? 'Administrador' : 'Colaborador'),
    }));
});

const progress = computed(() => {
    const total = props.project.tasks_count || 0;
    if (!total) return 0;
    return Math.round(((props.project.finished_tasks_count || 0) / total) * 100);
});

// ===== Proyecto =====
const openEditProject = () => {
    editingProject.value = props.project;
    showProjectFormModal.value = true;
};

const deleteProject = () => {
    router.delete(route('projects.destroy', props.project.id), {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Proyecto eliminado.'),
        onError: (errors) => ElMessage.error(errors.permission || 'No se pudo eliminar el proyecto.'),
    });
};

const removeProjectFile = (id) => {
    const idx = props.project.media.findIndex(f => f.id === id);
    if (idx !== -1) props.project.media.splice(idx, 1);
};

const uploadProjectFiles = (files) => {
    useForm({ files }).post(route('projects.files.store', props.project.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Archivos subidos.'),
        onError: () => ElMessage.error('No se pudieron subir los archivos.'),
    });
};

// ===== Tareas =====
const openCreateTask = () => {
    selectedTask.value = null;
    showTaskModal.value = true;
};

const openTask = (task) => {
    selectedTask.value = task;
    showTaskModal.value = true;
};

const closeTaskModal = () => {
    showTaskModal.value = false;
    selectedTask.value = null;
};

const handleStatusUpdate = ({ task, newStatus, position }) => {
    router.post(route('projects.tasks.update-status', [props.project.id, task.id]), {
        status: newStatus,
        position,
    }, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Tarea movida a ' + newStatus),
        onError: (errors) => ElMessage.error(errors.status || errors.assigned_to || 'No se pudo mover la tarea.'),
    });
};

// Si llegamos con ?task={id} (desde una notificación de tarea), abrir el modal una sola vez.
// Al hacerlo, se elimina ?task= de la URL para que al refrescar NO se vuelva a abrir solo.
onMounted(() => {
    const url = new URL(window.location.href);
    const taskId = url.searchParams.get('task');
    if (taskId && props.project.tasks) {
        const found = props.project.tasks.find(t => String(t.id) === String(taskId));
        if (found) {
            activeTab.value = 'tasks';
            selectedTask.value = found;
            showTaskModal.value = true;
        }
        // Limpia el parámetro ?task= para que el modal solo se abra al llegar por notificación
        url.searchParams.delete('task');
        window.history.replaceState({}, '', url.toString());
    }
});

const formatDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatMoney = (value) => {
    if (value === null || value === undefined) return '—';
    const num = Number(value);
    if (isNaN(num)) return '—';
    const opts = props.project.currency === 'USD'
        ? { style: 'currency', currency: 'USD' }
        : { style: 'currency', currency: 'MXN' };
    return num.toLocaleString(props.project.currency === 'USD' ? 'en-US' : 'es-MX', opts);
};
</script>

<style>
/* Ajustes de las pestañas Element Plus para que ocupen bien el espacio */
.el-tabs__nav-wrap::after {
    background-color: #e5e7eb;
}
.dark .el-tabs__nav-wrap::after {
    background-color: #334155;
}
</style>
