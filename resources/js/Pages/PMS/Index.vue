<template>
    <AppLayout title="Dashboard PMS">
        <div class="py-7">
            <div class="max-w-[110rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 min-h-[calc(100vh-120px)] flex flex-col">
                    
                    <!-- HEADER: TÍTULO Y CONTROLES -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b dark:border-slate-700 pb-4">
                        <div>
                            <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                                Control de Actividades
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión operativa y cumplimiento ISO</p>
                        </div>

                        <div class="flex items-center space-x-3 flex-wrap gap-y-3">
                            <el-radio-group v-model="activeView" size="default">
                                <el-radio-button label="kanban"><i class="fa-solid fa-table-columns mr-1"></i> Kanban</el-radio-button>
                                <el-radio-button label="list"><i class="fa-solid fa-list mr-1"></i> Lista</el-radio-button>
                            </el-radio-group>

                            <el-select v-model="filtersForm.department" placeholder="Departamento" clearable class="w-36" @change="fetchData">
                                <el-option label="Producción" value="Producción" />
                                <el-option label="Ventas" value="Ventas" />
                                <el-option label="Administración" value="Administración" />
                                <el-option label="Diseño" value="Diseño" />
                                <el-option label="General" value="General" />
                            </el-select>

                            <!-- <el-select v-model="filtersForm.status" placeholder="Estatus" clearable class="w-36" @change="fetchData">
                                <el-option label="Pendiente" value="Pendiente" />
                                <el-option label="En proceso" value="En proceso" />
                                <el-option label="Validación" value="Validación" />
                                <el-option label="Terminado" value="Terminado" />
                            </el-select> -->

                            <el-checkbox v-model="filtersForm.expired_only" @change="fetchData" border class="!mr-0">
                                Expiradas
                            </el-checkbox>

                            <el-checkbox v-model="filtersForm.hide_completed" @change="fetchData" border class="!mr-0">
                                Ocultar Terminadas
                            </el-checkbox>
                            
                            <SecondaryButton @click="showMetrics = true" class="!py-2">
                                <i class="fa-solid fa-chart-pie mr-2"></i> Métricas
                            </SecondaryButton>
                            
                            <PrimaryButton v-if="canCreate" @click="openCreateModal" class="!py-2">
                                <i class="fa-solid fa-plus mr-2"></i> Nueva Tarea
                            </PrimaryButton>
                        </div>
                    </div>

                    <!-- OVERLAY CARGA -->
                    <div class="relative flex-1 flex flex-col">
                        <div v-if="loading" class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>

                        <!-- VISTA KANBAN -->
                        <KanbanBoard 
                            v-if="activeView === 'kanban'" 
                            :tasks="taskData" 
                            @update-status="handleStatusUpdate"
                            @task-click="openTaskModal" 
                            @require-assignment="handleRequireAssignment"
                        />

                        <!-- VISTA LISTA -->
                        <div v-else class="flex-1">
                            <el-table 
                                max-height="600" 
                                :data="taskData"
                                style="width: 100%" 
                                stripe
                                @row-click="openTaskModal"
                                class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">
                                
                                <el-table-column prop="folio" label="Folio" width="120" font-bold />
                                <el-table-column prop="department" label="Departamento" width="150" />
                                <el-table-column prop="title" label="Título" min-width="200" show-overflow-tooltip />
                                <el-table-column label="Responsable" width="160">
                                    <template #default="scope">
                                        <span v-if="scope.row.responsible_id" class="font-medium text-gray-700 dark:text-gray-300">{{ scope.row.responsible?.name }}</span>
                                        <span v-else class="text-orange-500 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sin asignar</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="kanban_status" label="Estatus" width="130">
                                    <template #default="scope">
                                        <el-tag :type="getStatusTagType(scope.row.kanban_status)">{{ scope.row.kanban_status }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="due_date" label="Vencimiento" width="140">
                                    <template #default="scope">
                                        {{ scope.row.due_date ? new Date(scope.row.due_date).toLocaleDateString() : 'N/A' }}
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </div>
                    
                    <!-- Paginador Element Plus -->
                    <div v-if="tasks.total" class="mt-4 flex justify-end">
                        <el-pagination
                            background
                            layout="total, prev, pager, next"
                            :total="tasks.total"
                            :page-size="tasks.per_page"
                            :current-page="tasks.current_page"
                            @current-change="handlePageChange"
                        />
                    </div>
                </div>
            </div>
        </div>

        <MetricsDrawer v-model:show="showMetrics" :tasks="taskData" />

        <TaskModal 
            :show="showTaskModal" 
            :task="selectedTask" 
            :users="users"
            @close="closeTaskModal" 
        />

        <DialogModal :show="showAssignModal" @close="closeAssignModal">
            <template #title>Asignar Responsable</template>
            <template #content>
                <div v-if="taskToAssign" class="mt-2 h-96">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                        La tarea <strong>{{ taskToAssign.title }}</strong> requiere que asocies a un responsable antes de que pueda pasar a estado de <strong>{{ pendingStatus }}</strong>.
                    </p>
                    <el-select v-model="assignForm.responsible_id" placeholder="Selecciona un colaborador" class="w-full" filterable :teleported="false">
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                    </el-select>
                    <div v-if="assignForm.errors.responsible_id" class="text-red-500 text-xs mt-1">
                        {{ assignForm.errors.responsible_id }}
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="closeAssignModal" class="mr-3">Cancelar</SecondaryButton>
                <PrimaryButton @click="submitAssignment" :class="{ 'opacity-50': assignForm.processing }" :disabled="assignForm.processing">
                    Asignar y Mover
                </PrimaryButton>
            </template>
        </DialogModal>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import KanbanBoard from './Partials/KanbanBoard.vue';
import MetricsDrawer from './Partials/MetricsDrawer.vue';
import TaskModal from './Partials/TaskModal.vue';

const props = defineProps({
    tasks: {
        type: [Array, Object],
        required: true
    },
    filters: Object,
    users: Array, 
});

const page = usePage();
const loading = ref(false);
const activeView = ref('kanban');
const showMetrics = ref(false);

const showTaskModal = ref(false);
const selectedTask = ref(null);

const showAssignModal = ref(false);
const taskToAssign = ref(null);
const pendingStatus = ref(null);

// Formulario para asignar responsable optimizado
const assignForm = useForm({
    responsible_id: null,
    kanban_status: null
});

const taskData = computed(() => Array.isArray(props.tasks) ? props.tasks : props.tasks.data || []);

const canManage = computed(() => page.props.auth.user.permissions.includes('Gestionar pms'));
const canCreate = computed(() => page.props.auth.user.permissions.includes('Crear tareas'));

const filtersForm = ref({
    department: props.filters?.department || '',
    status: props.filters?.status || '',
    search: props.filters?.search || '',
    expired_only: props.filters?.expired_only === 'true' || props.filters?.expired_only === true,
    hide_completed: props.filters?.hide_completed === 'true' || props.filters?.hide_completed === true,
    page: props.filters?.page || 1,
});

const fetchData = () => {
    router.get(route('pms.index'), filtersForm.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false,
    });
};

const handlePageChange = (val) => {
    filtersForm.value.page = val;
    fetchData();
};

const handleStatusUpdate = ({ task, newStatus }) => {
    let payload = {};
    if (newStatus === 'Backlog') {
        payload = { kanban_status: 'Pendiente', responsible_id: null };
    } else {
        payload = { kanban_status: newStatus };
    }

    router.post(route('pms.update-status', task.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Tarea movida exitosamente');
        },
        onError: (errors) => {
            if (errors.evidence_files) {
                ElMessage.error({
                    message: `Regla ISO 9001: ${errors.evidence_files}`,
                    duration: 5000,
                    showClose: true
                });
            } else {
                ElMessage.error('Error al actualizar la tarea');
            }
        }
    });
};

const openCreateModal = () => {
    selectedTask.value = null;
    showTaskModal.value = true;
};

const openTaskModal = (task) => {
    selectedTask.value = task;
    showTaskModal.value = true;
};

const closeTaskModal = () => {
    showTaskModal.value = false;
    selectedTask.value = null;
};

const handleRequireAssignment = ({ task, newStatus }) => {
    taskToAssign.value = task;
    pendingStatus.value = newStatus;
    assignForm.responsible_id = null;
    assignForm.kanban_status = newStatus;
    showAssignModal.value = true;
};

const closeAssignModal = () => {
    showAssignModal.value = false;
    taskToAssign.value = null;
    pendingStatus.value = null;
    assignForm.reset();
};

const submitAssignment = () => {
    if (!assignForm.responsible_id) {
        ElMessage.warning('Debes seleccionar un responsable para la tarea');
        return;
    }

    // Usamos POST apuntando a la ruta update-status para que envíe sólo lo necesario y valide
    assignForm.post(route('pms.update-status', taskToAssign.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Tarea asignada y movida exitosamente');
            closeAssignModal();
        },
        onError: (errors) => {
            ElMessage.error(errors.evidence_files ? `Regla ISO: ${errors.evidence_files}` : 'Ocurrió un error en la asignación');
        }
    });
};

const getStatusTagType = (status) => {
    const statusMap = {
        'Pendiente': 'info',
        'En proceso': 'warning',
        'Validación': 'primary',
        'Terminado': 'success',
    };
    return statusMap[status] || 'default';
};
</script>

<style>
.dark .el-radio-button__inner {
    background-color: #1e293b;
    border-color: #334155;
    color: #94a3b8;
}
.dark .el-radio-button__original-radio:checked + .el-radio-button__inner {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: white;
}
</style>