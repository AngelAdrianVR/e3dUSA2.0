<template>
    <AppLayout title="Órdenes de Venta">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Órdenes de Venta
        </h2>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para crear nueva venta -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta')"
                            :href="route('sales.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Venta
                            </SecondaryButton>
                        </Link>

                        <div class="flex items-center space-x-2">
                             <!-- Botón para eliminar seleccionados -->
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar ordenes de venta')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar las ventas seleccionadas?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                            
                            <!-- Switch para ver todas las ventas / mis ventas -->
                            <el-switch
                                v-if="$page.props.auth.user.permissions.includes('Ver todas las ventas')"
                                v-model="showAllSales"
                                @change="toggleView"
                                style="--el-switch-on-color: #13ce66; --el-switch-off-color: #3b82f6"
                                inline-prompt
                                active-text="Todas"
                                inactive-text="Mías"
                            />
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
                        
                        <!-- Tabla de Ventas -->
                        <el-table 
                            max-height="550" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="Folio" width="100">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <el-tooltip :content="scope.row.type === 'venta' ? 'Orden de Venta' : 'Orden de Stock'" placement="top">
                                            <i :class="scope.row.type === 'venta' ? 'fa-solid fa-cart-shopping text-purple-500' : 'fa-solid fa-box text-rose-500'"></i>
                                        </el-tooltip>
                                        <span>{{ 'OV-' + scope.row.id.toString().padStart(4, '0') }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Cliente" width="250">
                                <template #default="scope">
                                    {{ scope.row.branch?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Creado por" width="200">
                                <template #default="scope">
                                    {{ scope.row.user?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="status" label="Estatus" width="220">
                                <template #default="scope">
                                    <el-tag :type="getStatusTagType(scope.row.status)" disable-transitions>
                                        {{ scope.row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="promise_date" label="Fecha Promesa" width="150" />
                            <el-table-column label="Monto Total" width="150">
                                 <template #default="scope">
                                    {{ formatCurrency(scope.row.total_amount) }}
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
                                                    <i class="fa-solid fa-eye mr-2"></i>Ver
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Editar ordenes de venta')"
                                                    :command="'edit-' + scope.row.id">
                                                    <i class="fa-solid fa-pen-to-square mr-2"></i>Editar
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="sales.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="sales.current_page"
                            :page-size="sales.per_page" :total="sales.total"
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
import { ElMessage } from 'element-plus';
import { Link, router } from "@inertiajs/vue3";

export default {
    data() {
        return {
            loading: false,
            search: '',
            selectedItems: [],
            tableData: this.sales.data,
            showAllSales: this.filters.view === 'all',
            SearchProps: ['Folio', 'Cliente', 'Estatus'],
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
        sales: Object,
        filters: Object,
    },
    methods: {
        async handleSearch() {
            // Lógica de búsqueda (puedes adaptarla para usar axios si prefieres)
            router.get(route('sales.index'), { search: this.search }, {
                preserveState: true,
                replace: true,
                onStart: () => this.loading = true,
                onFinish: () => this.loading = false,
            });
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            router.get(route('sales.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            router.get(route(`sales.${action}`, id));
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            router.post(route('sales.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Ventas eliminadas correctamente');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar las ventas');
                }
            });
        },
        handlePageChange(page) {
            const params = { page };
            if (this.showAllSales) {
                params.view = 'all';
            }
            router.get(route('sales.index', params), {
                preserveState: true,
                replace: true,
            });
        },
        toggleView() {
            const params = {};
            if (this.showAllSales) {
                params.view = 'all';
            }
            router.get(route('sales.index', params), {
                preserveState: true,
                replace: true,
                onStart: () => this.loading = true,
                onFinish: () => this.loading = false,
            });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return value.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
        getStatusTagType(status) {
            const statusMap = {
                'Autorizado. Sin orden de producción': 'info',
                'Producción sin iniciar': 'warning',
                'Producción en proceso': '', // default (azul)
                'Producción terminada': 'success',
                'Parcialmente enviado': 'warning',
                'Enviado': 'success',
            };
            return statusMap[status] || 'danger'; // danger para estatus no mapeados
        }
    },
    watch: {
        'sales.data': {
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
