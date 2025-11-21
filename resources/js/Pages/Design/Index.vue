<template>
    <AppLayout title="Órdenes de Diseño">
        <!-- componente de carga de trabajo de diseñadores -->
        <DesignersWorkload v-if="$page.props.auth.user.permissions.includes('Crear ordenes de diseño')" />

        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Órdenes de Diseño
        </h2>

        <div class="py-7">
            <div class="max-w-[95rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-2">
                            <!-- Botón para crear nueva orden de diseño -->
                            <Link v-if="$page.props.auth.user.permissions.includes('Crear ordenes de diseño')"
                                :href="route('design-orders.create')">
                                <SecondaryButton>
                                    <i class="fa-solid fa-plus mr-2"></i>
                                    Nueva Orden
                                </SecondaryButton>
                            </Link>
                            <!-- Botón para generar reporte -->
                            <SecondaryButton @click="showReportModal = true">
                                <i class="fa-solid fa-file-invoice mr-2"></i>
                                Reporte de Actividades
                            </SecondaryButton>
                        </div>

                        <div class="flex items-center space-x-4">
                             <!-- Botón para eliminar seleccionados -->
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar ordenes de diseño')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar las órdenes seleccionadas?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                            
                            <!-- Nuevo: Grupo de botones para los filtros de vista -->
                            <div class="flex items-center p-1 bg-gray-100 dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700">
                                <button @click="switchView('mine')" :class="{'bg-white dark:bg-slate-600 text-blue-600 dark:text-blue-400 shadow-sm': activeView === 'mine', 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700': activeView !== 'mine'}" class="px-4 py-1.5 text-sm font-semibold rounded-md transition-all duration-200">
                                    Mías
                                </button>
                                <button v-if="$page.props.auth.user.permissions.includes('Ver todas las ordenes de diseño')" @click="switchView('all')" :class="{'bg-white dark:bg-slate-600 text-blue-600 dark:text-blue-400 shadow-sm': activeView === 'all', 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700': activeView !== 'all'}" class="px-4 py-1.5 text-sm font-semibold rounded-md transition-all duration-200">
                                    Todas
                                </button>
                                <button v-if="$page.props.auth.user.permissions.includes('Asignar diseños')" @click="switchView('unassigned')" :class="{'bg-white dark:bg-slate-600 text-blue-600 dark:text-blue-400 shadow-sm': activeView === 'unassigned', 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700': activeView !== 'unassigned'}" class="relative px-4 py-1.5 text-sm font-semibold rounded-md transition-all duration-200">
                                    Sin Asignar
                                    <span v-if="unassignedOrdersCount > 0" class="absolute -top-2 -right-2 inline-flex items-center justify-center size-5 text-xs font-bold text-white bg-red-500 rounded-full ring-2 ring-white dark:ring-slate-800">
                                        {{ unassignedOrdersCount }}
                                    </span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Input de búsqueda -->
                        <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" />
                    </div>

                    <!-- Overlay de carga -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <!-- Tabla de Órdenes de Diseño -->
                        <el-table 
                            max-height="550" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="Folio" width="120">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                         <el-tooltip content="Orden de Diseño" placement="top">
                                            <i class="fa-solid fa-palette text-blue-500"></i>
                                        </el-tooltip>
                                        <span>{{ 'OD-' + scope.row.id.toString().padStart(4, '0') }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="order_title" label="Título" width="170" />
                            <el-table-column label="Categoría" width="120">
                                <template #default="scope">
                                    {{ scope.row.design_category?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Solicitante" width="140">
                                <template #default="scope">
                                    {{ scope.row.requester?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Diseñador Asignado" width="140">
                                <template #default="scope">
                                    <span v-if="scope.row.designer?.name">{{ scope.row.designer?.name }}</span>
                                    <span class="text-amber-500" v-else>Sin asignar</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Solicitado el" width="150">
                                <template #default="scope">
                                    {{ formatDate(scope.row.created_at) ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="status" label="Estatus" width="120">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <el-tag :type="getStatusTagType(scope.row.status)" effect="light">
                                            {{ scope.row.status }}
                                        </el-tag>
                                        <el-tooltip v-if="scope.row.is_hight_priority" content="Prioridad Alta" placement="top">
                                            <i class="fa-solid fa-fire text-red-500"></i>
                                        </el-tooltip>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Autorizado" width="100" align="center">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.authorized_at" placement="top">
                                        <template #content>
                                            Autorizado por: {{ scope.row.authorized_user_name }} <br>
                                            Fecha: {{ formatDate(scope.row.authorized_at) }}
                                        </template>
                                        <i class="fa-solid fa-check-double text-green-500 text-lg"></i>
                                    </el-tooltip>
                                    <p v-else>No autorizada</p>
                                </template>
                            </el-table-column>
                             <el-table-column label="Terminado el" width="130">
                                <template #default="scope">
                                    {{ formatDate(scope.row.finished_at) ?? 'N/A' }}
                                </template>
                            </el-table-column>
                          
                            <!-- Menú de acciones por fila -->
                            <el-table-column align="right">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop
                                            class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :command="'show-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>Ver
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Editar ordenes de diseño') && (scope.row.status === 'Pendiente' || scope.row.status === 'Autorizada')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>Editar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Autorizar ordenes de diseño') && !scope.row.authorized_at"
                                                    :disabled="!scope.row.designer_id"
                                                    :command="`authorize-${scope.row.id}`">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    Autorizar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Asignar diseños') && !scope.row.designer_id"
                                                    :command="`assign-${scope.row.id}`">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                                                    </svg>
                                                    Asignar
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="designOrders.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="designOrders.current_page"
                            :page-size="designOrders.per_page" :total="designOrders.total"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Asignar Diseñador -->
        <DialogModal :show="showAssignModal" @close="closeAssignModal">
            <template #title>
                Asignar Diseñador a Orden
            </template>

            <template #content>
                <div v-if="selectedOrder">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        {{ 'OD-' + selectedOrder.id.toString().padStart(4, '0') }}: {{ selectedOrder.order_title }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Solicitado por: <span class="font-medium">{{ selectedOrder.requester?.name }}</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Fecha de Solicitud: <span class="font-medium">{{ formatDate(selectedOrder.created_at) }}</span>
                    </p>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Seleccionar diseñador
                        </label>
                        <el-select v-model="assignmentForm.designer_id" :teleported="false"
                                placeholder="Selecciona un diseñador"
                                class="!w-1/2 mt-1"
                                filterable>
                            <el-option v-for="designer in designers"
                                    :key="designer.id"
                                    :label="designer.name"
                                    :value="designer.id" />
                        </el-select>
                        <div v-if="assignmentForm.errors.designer_id" class="text-red-500 text-xs mt-1">
                            {{ assignmentForm.errors.designer_id }}
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <CancelButton @click="closeAssignModal">
                    Cancelar
                </CancelButton>

                <SecondaryButton @click="submitAssignment" :loading="assignmentForm.processing" class="ml-3">
                    <span v-if="assignmentForm.processing">Asignando...</span>
                    <span v-else>Asignar</span>
                </SecondaryButton>
            </template>
        </DialogModal>

        <!-- Modal para Reporte de Actividades -->
        <DesignersReport :show="showReportModal" @close="showReportModal = false" />
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import DesignersWorkload from "@/Components/MyComponents/DesignersWorkload.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import DialogModal from '@/Components/DialogModal.vue';
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import DesignersReport from './Partials/DesignersReport.vue'; // <-- Importar componente
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';
import { Link, router } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        return {
            loading: false,
            search: '',
            selectedItems: [],
            tableData: this.designOrders.data,
            activeView: this.filters.view || 'mine', 
            SearchProps: ['Folio', 'Título', 'Solicitante', 'Diseñador', 'Estatus'],
            showAssignModal: false,
            showReportModal: false, // <-- Estado para el modal de reporte
            designers: [],
            selectedOrder: null,
            assignmentForm: this.$inertia.form({
                designer_id: null,
            }),
        };
    },
    components: {
        Link,
        AppLayout,
        SearchInput,
        DialogModal,
        CancelButton,
        LoadingIsoLogo,
        SecondaryButton,
        DesignersWorkload,
        DesignersReport, // <-- Registrar componente
    },
    props: {
        designOrders: Object,
        filters: Object,
        unassignedOrdersCount: Number,
    },
    methods: {
        async handleSearch() {
            this.loading = true;
            try {
                if (!this.search) {
                    this.tableData = this.designOrders.data;
                    this.$inertia.get(this.route('design-orders.index'), {}, {
                        preserveState: true,
                        replace: true,
                    });
                    return;
                }
                const response = await axios.post(route('design-orders.get-matches', { query: this.search }));
                this.tableData = response.data.items;
            } catch (error) {
                console.error(error);
                ElMessage.error('No se pudo realizar la búsqueda');
            } finally {
                this.loading = false;
            }
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMM, yyyy", { locale: es });
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            router.get(route('design-orders.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            
            if (action === 'assign'){
                this.openAssignModal(id);
            }
            else if (action === 'authorize') {
                this.authorize(id);
            } else {
                router.get(route(`design-orders.${action}`, id));
            }
        },
        async fetchDesigners() {
            try {
                const response = await axios.get(route('design-orders.get-designers'));
                this.designers = response.data;
            } catch (error) {
                console.error("Error fetching designers:", error);
                ElMessage.error('No se pudo cargar la lista de diseñadores.');
            }
        },
        openAssignModal(orderId) {
            this.selectedOrder = this.tableData.find(order => order.id == orderId);
            if (this.selectedOrder) {
                this.fetchDesigners();
                this.showAssignModal = true;
            }
        },
        closeAssignModal() {
            this.showAssignModal = false;
            this.selectedOrder = null;
            this.assignmentForm.reset();
        },
        submitAssignment() {
            if (!this.assignmentForm.designer_id) {
                ElMessage.warning('Debes seleccionar un diseñador.');
                return;
            }

            this.assignmentForm.put(route('design-orders.assign-designer', this.selectedOrder.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.closeAssignModal();
                    ElMessage.success('Diseñador asignado correctamente.');
                    router.reload({ preserveScroll: true });
                },
                onError: (errors) => {
                     let message = 'Ocurrió un error al asignar el diseñador.';
                     if (errors.designer_id) {
                         message = errors.designer_id[0];
                     }
                     ElMessage.error(message);
                }
            });
        },
        async authorize(dsignOrderId) {
            try {
                const response = await axios.get(route('design-orders.authorize', dsignOrderId));
                if (response.status === 200) {
                    router.reload({ 
                        preserveScroll: true,
                    })                    
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar la orden');
                console.error(err);
            }
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            router.post(route('design-orders.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Órdenes eliminadas correctamente');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar las órdenes');
                }
            });
        },
        handlePageChange(page) {
            const params = { page };
            if (this.activeView !== 'mine') {
                params.view = this.activeView;
            }
            router.get(route('design-orders.index', params), {
                preserveState: true,
                replace: true,
            });
        },
        switchView(view) {
            this.activeView = view;
            const params = {};
            if (this.activeView !== 'mine') {
                params.view = this.activeView;
            }
            router.get(route('design-orders.index', params), {
                preserveState: true,
                replace: true,
                onStart: () => this.loading = true,
                onFinish: () => this.loading = false,
            });
        },
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'info',
                'Autorizada': 'primary',
                'En proceso': 'warning',
                'Terminada': 'success',
                'Cancelada': 'danger',
            };
            return statusMap[status] || 'default';
        }
    },
    watch: {
        'designOrders.data': {
            handler(newData) {
                this.tableData = newData;
            },
            deep: true,
            immediate: true,
        }
    }
};
</script>

<style>
/* Estilos para la paginación en modo oscuro */
.dark .el-pagination button,
.dark .el-pager li {
    background-color: #1f2937 !important;
    color: #d1d5db !important;
}
.dark .el-pager li.is-active {
    color: #ffffff !important;
    background-color: #3b82f6 !important;
}
</style>
