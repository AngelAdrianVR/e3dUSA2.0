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

                            <!-- Orden de venta vinculada (muestra/regalo) -->
                            <el-table-column width="125">
                                <template #header>
                                    <div class="flex items-center gap-1">
                                        <span>OV Vinculada</span>
                                        <el-tooltip placement="top" effect="dark">
                                            <template #content>
                                                <div class="w-64 text-xs leading-relaxed">
                                                    Las Órdenes de Venta vinculadas son necesarias para conservar el
                                                    <b>historial de facturas</b> del cliente: la muestra que no se devuelve
                                                    o el producto regalado se factura a través de esta OV.
                                                </div>
                                            </template>
                                            <i class="fa-regular fa-circle-question text-gray-400 cursor-help"></i>
                                        </el-tooltip>
                                    </div>
                                </template>
                                <template #default="scope">
                                    <a v-if="scope.row.sale_id" @click.stop="$inertia.visit(route('sales.show', scope.row.sale_id))"
                                        class="text-blue-500 hover:underline font-semibold flex items-center gap-1">
                                        <i class="fa-solid fa-gift text-emerald-500 text-xs"></i>
                                        OV-{{ scope.row.sale_id.toString().padStart(4, '0') }}
                                    </a>
                                    <span v-else class="text-xs text-gray-400">—</span>
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
                                                <!-- Órdenes de Venta de muestra/regalo (solo una por seguimiento) -->
                                                <el-dropdown-item v-if="scope.row.sale_id" disabled>
                                                    <i class="fa-solid fa-lock mr-2 text-gray-400 w-4"></i>Ya existe una OV vinculada
                                                </el-dropdown-item>
                                                <el-dropdown-item v-if="scope.row.sale_id" :command="'viewSale-' + scope.row.sale_id">
                                                    <i class="fa-solid fa-file-invoice mr-2 text-emerald-500 w-4"></i>Ver OV-{{ scope.row.sale_id.toString().padStart(4, '0') }}
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-else-if="!scope.row.will_be_returned && $page.props.auth.user.permissions.includes('Crear ordenes de venta')"
                                                    :command="'createSale-' + scope.row.id">
                                                    <i class="fa-solid fa-gift mr-2 text-emerald-500 w-4"></i>Crear Orden de Venta
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

        <!-- Modal: stock de los productos nuevos al registrar como Muestra/Regalo -->
        <MuestraProductsStockModal
            :show="showStockModal"
            :items="stockModalItems"
            :processing="isPreparingSale"
            confirm-text="Crear Orden de Venta"
            @close="showStockModal = false"
            @confirm="confirmStockModal"
        />

        <!-- Capa de carga (sin blur) mientras se registra / crea la Orden de Venta -->
        <div v-if="isPreparingSale" class="fixed inset-0 z-[9999] bg-gray-900/60 flex items-center justify-center">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl px-8 py-6 flex flex-col items-center text-center max-w-sm">
                <i class="fa-solid fa-circle-notch fa-spin text-4xl text-primary mb-3"></i>
                <p class="text-lg font-bold text-gray-800 dark:text-white">Creando Orden de Venta…</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Registrando los productos de la muestra. No cierres esta ventana.</p>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import MuestraProductsStockModal from "@/Components/MyComponents/MuestraProductsStockModal.vue";
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
            // Registro de productos nuevos como Muestra/Regalo (modal de stock + capa de carga)
            showStockModal: false,
            stockModalItems: [],
            stockModalRowId: null,
            isPreparingSale: false,
            SearchProps: ['ID', 'Nombre', 'Cliente', 'Contacto', 'Estatus', 'Solicitante'], // propiedades por las que se puede buscar
        };
    },
    components: {
        Link,
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
        MuestraProductsStockModal,
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

            // Acciones relacionadas con la Orden de Venta de muestra/regalo
            if (action === 'createSale') {
                const row = this.tableData.find(item => String(item.id) === String(id));
                const pending = Array.isArray(row?.pending_proposals) ? row.pending_proposals : [];

                // Si hay productos nuevos sin registrar, primero se pide su stock actual
                if (pending.length) {
                    this.stockModalItems = pending;
                    this.stockModalRowId = id;
                    this.showStockModal = true;
                    return;
                }

                this.submitPrepareSale(id, {});
                return;
            }
            if (action === 'viewSale') {
                this.$inertia.visit(route('sales.show', id));
                return;
            }

            this.$inertia.get(route(`sample-trackings.${action}`, id));
        },
        // Registra los productos nuevos como "Muestras y regalos" y continúa a la OV
        submitPrepareSale(id, payload = {}) {
            this.isPreparingSale = true;

            this.$inertia.post(route('sample-trackings.prepare-sale', id), payload, {
                onFinish: () => { this.isPreparingSale = false; },
                onError: () => ElMessage.error('No se pudieron registrar los productos de la muestra.'),
            });
        },
        confirmStockModal(stocks) {
            const id = this.stockModalRowId;
            this.showStockModal = false;
            this.submitPrepareSale(id, { stocks });
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
