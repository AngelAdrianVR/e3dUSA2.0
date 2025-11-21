<template>
    <AppLayout title="Seguimiento de Muestras">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Seguimiento de Muestras
        </h2>

        <div class="py-7">
            <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para crear nueva solicitud de muestra -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear muestras')"
                            :href="route('sample-trackings.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Solicitud
                            </SecondaryButton>
                        </Link>

                        <div class="flex items-center space-x-2">
                             <!-- Botón para eliminar seleccionados -->
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar muestras')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar las solicitudes seleccionadas?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
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
                        
                        <!-- Tabla de Seguimiento de Muestras -->
                        <el-table 
                            max-height="550" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="ID" width="60" />
                            <el-table-column prop="name" label="Nombre" width="150" />
                            <el-table-column prop="status" label="Estatus" width="120">
                                <template #default="scope">
                                    <el-tag :type="getStatusTagType(scope.row.status)" disable-transitions>
                                        {{ scope.row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                             <el-table-column label="Cliente" width="180">
                                <template #default="scope">
                                    {{ scope.row.branch?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                             <el-table-column label="Contacto" width="150">
                                <template #default="scope">
                                    {{ scope.row.contact?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Solicitante" width="170">
                                <template #default="scope">
                                    {{ scope.row.requester?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Fecha de solicitud" width="150">
                               <template #default="scope">
                                    {{ formatDate(scope.row.created_at) }}
                                </template>
                            </el-table-column>
                             <el-table-column label="Devolución esperada" width="150">
                               <template #default="scope">
                                    {{ scope.row.expected_devolution_date ? formatDate(scope.row.expected_devolution_date) : 'No aplica' }}
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
                                                    v-if="scope.row.status === 'Pendiente' && $page.props.auth.user.permissions.includes('Editar muestras')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>Editar
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="sampleTrackings.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="sampleTrackings.current_page"
                            :page-size="sampleTrackings.per_page" :total="sampleTrackings.total"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';
import { Link } from "@inertiajs/vue3";

export default {
    data() {
        return {
            loading: false,
            search: '',
            selectedItems: [],
            tableData: this.sampleTrackings.data,
            SearchProps: ['ID', 'Nombre', 'Cliente', 'Contacto', 'Estatus', 'Solicitante'], // propiedades por las que se puede buscar
        };
    },
    components: {
        Link,
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
    },
    props: {
        sampleTrackings: Object,
    },
    methods: {
        // Mapea el estatus a un tipo de tag de Element Plus para darle color
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'warning',
                'Autorizado': '', // default
                'Enviado': 'info',
                'Aprobado': 'success',
                'Rechazado': 'danger',
                'Devuelto': 'info',
                'Completado': 'success',
            };
            return statusMap[status] || '';
        },
        async handleSearch() {
            this.loading = true;
            try {
                if (!this.search) {
                    this.$inertia.get(this.route('sample-trackings.index'), {}, {
                        preserveState: true,
                        replace: true,
                        onFinish: () => { this.loading = false; },
                    });
                    return;
                }

                const response = await axios.post(route('sample-trackings.get-matches', { query: this.search }));
                if (response.status === 200) {
                    this.tableData = response.data.items;
                }
            } catch (error) {
                console.error(error);
                ElMessage.error('No se pudo realizar la búsqueda');
            } finally {
                this.loading = false;
            }
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            this.$inertia.get(route('sample-trackings.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            this.$inertia.get(route(`sample-trackings.${action}`, id));
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('sample-trackings.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Solicitudes eliminadas correctamente');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar las solicitudes');
                }
            });
        },
        handlePageChange(page) {
            this.$inertia.get(route('sample-trackings.index', { page: page }), {
                preserveState: true,
                replace: true,
            });
        },
        formatDate(dateString) {
          if (!dateString) return '';
          return format(parseISO(dateString), 'dd MMM yy', { locale: es });
        },
    },
    watch: {
        // Observador para actualizar `tableData` si los props cambian (ej. por paginación)
        'sampleTrackings.data': {
            handler(newData) {
                if (!this.search) {
                    this.tableData = newData;
                }
            },
            deep: true,
            immediate: true,
        }
    }
};
</script>

<style>
/* Estilos para la paginación en modo oscuro (heredados de tu ejemplo) */
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
