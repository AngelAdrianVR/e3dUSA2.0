<template>
    <AppLayout title="Proyectos">
        <div class="py-7">
            <div class="max-w-[110rem] mx-auto sm:px-6 lg:px-8">
                <div class="overflow-hidden sm:rounded-lg p-6 min-h-[calc(100vh-120px)] flex flex-col">

                    <!-- HEADER -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b dark:border-slate-700 pb-4">
                        <div>
                            <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-2">
                                <i class="fa-solid fa-diagram-project text-blue-600"></i> Proyectos
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión de proyectos con tareas, seguimiento y diagrama de Gantt</p>
                        </div>

                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="relative">
                                <input v-model="searchForm.search" @keyup.enter="search"
                                    type="text" placeholder="Buscar proyecto..."
                                    class="w-56 pl-9 pr-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            </div>
                            <PrimaryButton v-if="canCreate" @click="openCreate" class="!py-2">
                                <i class="fa-solid fa-plus mr-2"></i> Nuevo proyecto
                            </PrimaryButton>
                        </div>
                    </div>

                    <!-- TABLA DE PROYECTOS -->
                    <div v-if="projectList.length" class="flex-1">
                        <el-table :data="projectList" stripe class="w-full" @row-dblclick="openShow">
                            <!-- Proyecto -->
                            <el-table-column label="Proyecto" min-width="240">
                                <template #default="{ row }">
                                    <Link :href="route('projects.show', row.id)"
                                        class="font-semibold text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 transition line-clamp-2">
                                        {{ row.name }}
                                    </Link>
                                    <p v-if="row.description" class="text-xs text-gray-400 truncate mt-0.5 max-w-sm">{{ row.description }}</p>
                                </template>
                            </el-table-column>

                            <!-- Fechas -->
                            <el-table-column label="Fechas" width="215">
                                <template #default="{ row }">
                                    <div class="text-xs text-gray-600 dark:text-gray-300">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fa-regular fa-calendar-check text-blue-500"></i>{{ formatDate(row.start_date) }}
                                        </div>
                                        <div v-if="row.tentative_end_date" class="mt-1 flex items-center gap-1.5">
                                            <i class="fa-regular fa-calendar-xmark text-amber-500"></i>{{ formatDate(row.tentative_end_date) }}
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>

                            <!-- Presupuesto -->
                            <el-table-column label="Presupuesto" width="140" align="right">
                                <template #default="{ row }">
                                    <span v-if="row.budget !== null" class="font-medium text-emerald-600 dark:text-emerald-400">{{ formatMoney(row) }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </template>
                            </el-table-column>

                            <!-- Miembros -->
                            <el-table-column label="Miembros" width="120">
                                <template #default="{ row }">
                                    <div class="flex -space-x-2">
                                        <img v-for="member in row.members.slice(0, 3)" :key="member.id" :src="member.profile_photo_url"
                                            class="size-7 rounded-full object-cover border-2 border-white dark:border-slate-800"
                                            :title="member.name" />
                                        <span v-if="row.members.length > 3"
                                            class="size-7 rounded-full bg-gray-200 dark:bg-slate-600 text-gray-600 dark:text-gray-300 text-[10px] font-bold flex items-center justify-center border-2 border-white dark:border-slate-800">
                                            +{{ row.members.length - 3 }}
                                        </span>
                                    </div>
                                </template>
                            </el-table-column>

                            <!-- Avance -->
                            <el-table-column label="Avance" width="190">
                                <template #default="{ row }">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-500" :class="progressBarClass(row)"
                                                :style="{ width: projectProgress(row) + '%' }"></div>
                                        </div>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ projectProgress(row) }}%</span>
                                    </div>
                                    <span class="text-[10px] text-gray-400">{{ row.finished_tasks_count || 0 }}/{{ row.tasks_count || 0 }} tareas</span>
                                </template>
                            </el-table-column>

                            <!-- Acciones -->
                            <el-table-column label="" width="150" align="right">
                                <template #default="{ row }">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="openShow(row)" title="Ver proyecto"
                                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button v-if="canEditProject(row)" @click="openEdit(row)" title="Editar"
                                            class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-lg transition">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button v-if="canDeleteProject(row)" @click="confirmDelete(row)" title="Eliminar"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- VACÍO -->
                    <div v-else class="flex-1 flex flex-col items-center justify-center text-center py-20">
                        <i class="fa-solid fa-diagram-project text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">No hay proyectos registrados</p>
                        <p v-if="canCreate" class="text-sm text-gray-400 mt-1">Crea el primer proyecto para comenzar</p>
                        <PrimaryButton v-if="canCreate" @click="openCreate" class="mt-5">
                            <i class="fa-solid fa-plus mr-2"></i> Nuevo proyecto
                        </PrimaryButton>
                    </div>

                    <!-- PAGINACIÓN -->
                    <div v-if="projects.total" class="mt-6 flex justify-end">
                        <el-pagination background layout="total, prev, pager, next" :total="projects.total"
                            :page-size="projects.per_page" :current-page="projects.current_page"
                            @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal crear/editar proyecto (sin vistas separadas) -->
        <ProjectFormModal :show="showFormModal" :project="editingProject" :users="activeUsers" @close="showFormModal = false" @saved="showFormModal = false" />

        <!-- Confirmación de eliminación -->
        <el-dialog v-model="showDeleteModal" title="Eliminar proyecto" width="420px">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                ¿Estás seguro de eliminar el proyecto <strong>{{ projectToDelete?.name }}</strong>? Se eliminarán también todas sus tareas, comentarios y archivos. Esta acción no se puede deshacer.
            </p>
            <template #footer>
                <SecondaryButton @click="showDeleteModal = false" class="mr-3">Cancelar</SecondaryButton>
                <DangerButton @click="deleteProject" :disabled="deleting" :class="{ 'opacity-50': deleting }">
                    <i class="fa-solid fa-trash-can mr-2"></i> Eliminar
                </DangerButton>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import ProjectFormModal from './Partials/ProjectFormModal.vue';

const props = defineProps({
    projects: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    activeUsers: { type: Array, default: () => [] },
    canCreate: { type: Boolean, default: false },
});

const page = usePage();
const currentUser = page.props.auth.user;

const projectList = computed(() => props.projects.data || []);

const searchForm = ref({ search: props.filters?.search || '' });

const showFormModal = ref(false);
const editingProject = ref(null);
const showDeleteModal = ref(false);
const projectToDelete = ref(null);
const deleting = ref(false);

// ===== Acciones =====
const openCreate = () => {
    editingProject.value = null;
    showFormModal.value = true;
};

const openEdit = (project) => {
    editingProject.value = project;
    showFormModal.value = true;
};

const openShow = (project) => {
    router.visit(route('projects.show', project.id));
};

const search = () => {
    router.get(route('projects.index'), { search: searchForm.value.search || null }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const handlePageChange = (page) => {
    router.get(route('projects.index'), { search: searchForm.value.search || null, page }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// ===== Permisos por proyecto =====
const canEditProject = (project) => {
    if (currentUser.id === project.created_by) return true;
    if (page.props.auth.user.permissions.includes('Editar proyectos')) return true;
    return project.members.some(m => m.id === currentUser.id && m.pivot?.role === 'Administrador');
};

const canDeleteProject = (project) => {
    if (currentUser.id === project.created_by) return true;
    return page.props.auth.user.permissions.includes('Eliminar proyectos');
};

const confirmDelete = (project) => {
    projectToDelete.value = project;
    showDeleteModal.value = true;
};

const deleteProject = () => {
    deleting.value = true;
    router.delete(route('projects.destroy', projectToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Proyecto eliminado.');
            showDeleteModal.value = false;
            projectToDelete.value = null;
        },
        onError: (errors) => {
            ElMessage.error(errors.permission || 'No se pudo eliminar el proyecto.');
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};

// ===== Formato =====
const projectProgress = (project) => {
    const total = project.tasks_count || 0;
    if (!total) return 0;
    return Math.round(((project.finished_tasks_count || 0) / total) * 100);
};

const progressBarClass = (project) => {
    const p = projectProgress(project);
    if (p >= 100) return 'bg-emerald-500';
    if (p >= 50) return 'bg-blue-500';
    return 'bg-amber-500';
};

const formatDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatMoney = (project) => {
    if (project.budget === null || project.budget === undefined) return '—';
    const num = Number(project.budget);
    if (isNaN(num)) return '—';
    const currency = project.currency === 'USD' ? 'USD' : 'MXN';
    return num.toLocaleString(currency === 'USD' ? 'en-US' : 'es-MX', { style: 'currency', currency });
};
</script>
