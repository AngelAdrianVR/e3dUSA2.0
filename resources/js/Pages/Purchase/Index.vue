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
                                    <el-tooltip v-if="scope.row.items?.length" placement="right" effect="dark">
                                        <template #content>
                                            <div class="max-h-64 overflow-y-auto pr-2">
                                            <ul class="list-disc list-inside text-xs space-y-3">
                                                <li
                                                v-for="item in scope.row.items"
                                                :key="item.id"
                                                class="flex items-start space-x-3"
                                                >
                                                <img
                                                    draggable="false"
                                                    :src="item.product.media[0]?.original_url"
                                                    :alt="item.product.name"
                                                    class="size-12 rounded-md object-cover flex-shrink-0"
                                                />
                                                <div class="leading-relaxed">
                                                    <p class="text-amber-500 font-semibold">
                                                    Tipo: <span class="text-white ml-1">{{ item.type }}</span>
                                                    </p>
                                                    <p class="text-amber-500 font-semibold">
                                                    Nombre: <span class="text-white ml-1">{{ item.product.name }}</span>
                                                    </p>
                                                    <p class="text-amber-500 font-semibold">
                                                    Cantidad:
                                                    <span class="text-white ml-1">
                                                        {{ item.quantity }} {{ item.product.measure_unit }}
                                                    </span>
                                                    </p>

                                                    <div v-if="scope.row.type === 'Venta'" class="mt-2">
                                                    <h2 class="mb-1 text-center font-bold text-blue-300 text-xs">
                                                        DISTRIBUCIÓN
                                                    </h2>
                                                    <p class="text-blue-400 font-semibold">
                                                        A favor:
                                                        <span class="text-white ml-1">
                                                        {{ item.additional_stock }} {{ item.product.measure_unit }}
                                                        </span>
                                                    </p>
                                                    <p class="text-blue-400 font-semibold">
                                                        En avión:
                                                        <span class="text-white ml-1">
                                                        {{ item.plane_stock }} {{ item.product.measure_unit }}
                                                        </span>
                                                    </p>
                                                    <p class="text-blue-400 font-semibold">
                                                        En barco:
                                                        <span class="text-white ml-1">
                                                        {{ item.ship_stock }} {{ item.product.measure_unit }}
                                                        </span>
                                                    </p>
                                                    </div>
                                                </div>
                                                </li>
                                            </ul>
                                            </div>
                                        </template>

                                        <span
                                            class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300"
                                        >
                                            {{ scope.row.items?.length }} producto(s)
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
                            <el-table-column label="Creado el" width="180">
                                <template #default="scope">
                                    {{ formatDate(scope.row.created_at) ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="status" label="Estatus" width="130">
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
                            <!-- <el-table-column label="Factura">
                                 <template #default="scope">
                                    <p v-if="scope.row.invoice_folio">{{ scope.row.invoice_folio }}</p>
                                    <span v-else class="text-gray-400">N/A</span>
                                </template>
                            </el-table-column> -->

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
                                                    v-if="$page.props.auth.user.permissions.includes('Editar ordenes de compra')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>Editar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Autorizar ordenes de compra') && !scope.row.authorized_at"
                                                    :command="`authorize-${scope.row.id}`">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>Autorizar
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="'print-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                                    </svg>Imprimir
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
                'Autorizada': 'primary',
                'Compra realizada': 'warning',
                'Compra recibida': 'success',
                'Cancelada': 'danger',
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
