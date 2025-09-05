<template>
    <AppLayout title="Órdenes de Compra">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Órdenes de Compra
        </h2>

        <div class="py-7">
            <div class="max-w-[95rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para crear nueva compra -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear ordenes de compra')"
                            :href="route('purchases.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Orden
                            </SecondaryButton>
                        </Link>

                        <div class="flex items-center space-x-2">
                             <!-- Botón para eliminar seleccionados -->
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar ordenes de compra')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar las compras seleccionadas?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                            
                            <!-- Switch para ver todas las compras / mis compras -->
                            <div
                                v-if="$page.props.auth.user.permissions.includes('Ver todas las compras')"
                                class="flex items-center px-3 py-1.5 bg-gray-100 dark:bg-slate-800 rounded-full shadow-sm border border-gray-200 dark:border-slate-700"
                            >
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 mr-2">Mías</span>
                                <el-switch
                                    v-model="showAllPurchases"
                                    @change="toggleView"
                                    style="--el-switch-on-color: #10b981; --el-switch-off-color: #3b82f6;"
                                />
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 ml-2">Todas</span>
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
                        
                        <!-- Tabla de Compras -->
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
                                         <el-tooltip content="Orden de Compra" placement="top">
                                            <i class="fa-solid fa-cart-arrow-down text-blue-500"></i>
                                        </el-tooltip>
                                        <span>{{ 'OC-' + scope.row.id.toString().padStart(4, '0') }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Proveedor" width="140">
                                <template #default="scope">
                                    {{ scope.row.supplier?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <!-- COLUMNA DE PRODUCTOS -->
                            <el-table-column label="Productos" width="130">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.purchase_items?.length" placement="top">
                                        <template #content>
                                            <ul class="list-disc list-inside text-xs">
                                                <li v-for="item in scope.row.purchase_items" :key="item.id">
                                                    ({{ item.quantity }}) {{ item.product.name }}
                                                </li>
                                            </ul>
                                        </template>
                                        <span class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                                            {{ scope.row.purchase_items.length }} producto(s)
                                        </span>
                                    </el-tooltip>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Creado por" width="120">
                                <template #default="scope">
                                    {{ scope.row.user?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Creado el" width="140">
                                <template #default="scope">
                                    {{ formatDate(scope.row.created_at) ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="status" label="Estatus" width="150">
                                <template #default="scope">
                                    <el-tag :type="getStatusTagType(scope.row.status)" effect="light">
                                        {{ scope.row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="Autorizado" width="120" align="center">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.authorized_at" placement="top">
                                        <template #content>
                                            Autorizado por: {{ scope.row.authorizer?.name ?? 'N/A' }} <br>
                                            Fecha: {{ formatDate(scope.row.authorized_at) }}
                                        </template>
                                        <i class="fa-solid fa-check-double text-green-500 text-lg"></i>
                                    </el-tooltip>
                                    <p v-else class="text-xs">No autorizada</p>
                                </template>
                            </el-table-column>
                            <el-table-column label="Monto Total" width="110">
                                 <template #default="scope">
                                    {{ formatCurrency(scope.row.total) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Factura" width="100">
                                 <template #default="scope">
                                    <p v-if="scope.row.invoice_folio">{{ scope.row.invoice_folio }}</p>
                                    <span v-else class="text-gray-400">N/A</span>
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
                                                    v-if="$page.props.auth.user.permissions.includes('Editar ordenes de compra') && scope.row.authorized_at === null"
                                                    :command="'edit-' + scope.row.id">
                                                    <i class="fa-solid fa-pen-to-square mr-2"></i>Editar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Autorizar ordenes de compra') && !scope.row.authorized_at"
                                                    :command="`authorize-${scope.row.id}`">
                                                    <i class="fa-solid fa-check mr-2"></i>Autorizar
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="'print-' + scope.row.id">
                                                    <i class="fa-solid fa-print mr-2"></i>Imprimir
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="purchases.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="purchases.current_page"
                            :page-size="purchases.per_page" :total="purchases.total"
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
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';
import { Link, router } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    // Definición del componente en Options API
    data() {
        return {
            loading: false,
            search: '',
            selectedItems: [],
            tableData: this.purchases.data,
            showAllPurchases: this.filters.view === 'all',
            SearchProps: ['ID', 'Proveedor', 'Creador', 'Estatus'],
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
        purchases: Object,
        filters: Object,
    },
    methods: {
        async handleSearch() {
            this.loading = true;
            try {
                if (!this.search) {
                    this.tableData = this.purchases.data;
                    this.$inertia.get(this.route('purchases.index'), {}, {
                        preserveState: true,
                        replace: true,
                    });
                    return;
                }
                const response = await axios.post(route('purchases.get-matches', { query: this.search }));
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
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            router.get(route('purchases.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            
            if (action === 'authorize') {
                this.authorize(id);
            } else if ( action === 'print' ) {
                window.open(route('purchases.print', id), '_blank');
            }
            else {
                router.get(route(`purchases.${action}`, id));
            }
        },
        async authorize(purchaseId) {
            try {
                const response = await axios.put(route('purchases.authorize', purchaseId));
                if (response.status === 200) {
                    const index = this.tableData.findIndex(item => item.id == purchaseId);
                    if (index !== -1) {
                        this.tableData[index].authorized_at = response.data.item.authorized_at;
                        this.tableData[index].authorizer = response.data.item.authorizer;
                        this.tableData[index].status = response.data.item.status;
                    }
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar la compra');
                console.error(err);
            }
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            router.post(route('purchases.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Compras eliminadas correctamente');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar las compras');
                }
            });
        },
        handlePageChange(page) {
            const params = { page };
            if (this.showAllPurchases) {
                params.view = 'all';
            }
            router.get(route('purchases.index', params), {
                preserveState: true,
                replace: true,
            });
        },
        toggleView() {
            const params = {};
            if (this.showAllPurchases) {
                params.view = 'all';
            }
            router.get(route('purchases.index', params), {
                preserveState: true,
                replace: true,
                onStart: () => this.loading = true,
                onFinish: () => this.loading = false,
            });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            const num = Number(value);
            return num.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'info',
                'Autorizado': 'primary',
                'Compra realizada': 'warning',
                'Compra recibida': 'success',
                'Cancelado': 'danger',
            };
            return statusMap[status] || 'default';
        }
    },
    watch: {
        'purchases.data': {
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
