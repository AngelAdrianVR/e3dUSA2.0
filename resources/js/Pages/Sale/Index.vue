<template>
    <AppLayout title="Órdenes de Venta">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Órdenes de Venta
        </h2>

        <div class="py-7">
            <div class="max-w-[95rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para crear nueva venta -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta')"
                            :href="route('sales.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Orden
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
                            <div
                                v-if="$page.props.auth.user.permissions.includes('Ver todas las ventas')"
                                class="flex items-center px-3 py-1.5 bg-gray-100 dark:bg-slate-800 rounded-full shadow-sm border border-gray-200 dark:border-slate-700"
                            >
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 mr-2">Mías</span>
                                <el-switch
                                    v-model="showAllSales"
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
                            <el-table-column prop="id" label="Folio" width="120">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <el-tooltip :content="scope.row.type === 'venta' ? 'Orden de Venta' : 'Orden de Stock'" placement="top">
                                            <i :class="scope.row.type === 'venta' ? 'fa-solid fa-cart-shopping text-purple-500' : 'fa-solid fa-box text-rose-500'"></i>
                                        </el-tooltip>
                                        <span v-if="scope.row.type === 'venta'">{{ 'OV-' + scope.row.id.toString().padStart(4, '0') }}</span>
                                        <span v-else>{{ 'OS-' + scope.row.id.toString().padStart(4, '0') }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Cliente" width="140">
                                <template #default="scope">
                                    {{ scope.row.branch?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <!-- COLUMNA DE PRODUCTOS -->
                            <el-table-column label="Productos" width="130">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.sale_products?.length" placement="top">
                                        <template #content>
                                            <ul class="list-disc list-inside text-xs">
                                                <li v-for="item in scope.row.sale_products" :key="item.id">
                                                    ({{ item.quantity }}) {{ item.product.name }}
                                                </li>
                                            </ul>
                                        </template>
                                        <span class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                                            {{ scope.row.sale_products.length }} producto(s)
                                        </span>
                                    </el-tooltip>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </template>
                            </el-table-column>
                            <!-- COLUMNA DE UTILIDAD -->
                            <el-table-column v-if="$page.props.auth.user.permissions.includes('Ver utilidad ventas')" label="Utilidad" width="100">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.type === 'venta'" placement="top" effect="dark">
                                        <template #content>
                                            <div class="text-xs">
                                                <p class="text-white dark:text-gray-700 font-bold mb-2">No se toma en cuenta flete</p>
                                                <p class="text-blue-300 dark:text-blue-700">Venta: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.total_sale) }}</strong></p>
                                                <p class="text-amber-400 dark:text-amber-700">Costo: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.total_cost) }}</strong></p>
                                                <p class="text-green-400 dark:text-green-700">Utilidad: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.profit) }}</strong></p>
                                            </div>
                                        </template>
                                        <div class="flex flex-col justify-center items-center space-x-2" :class="getProfitabilityClass(scope.row.utility_data.percentage)">
                                            <i class="fa-solid fa-flag"></i>
                                            <div class="flex">
                                                <i v-for="n in getProfitabilityStars(scope.row.utility_data.percentage)" :key="`filled-${n}`" class="fa-solid fa-star text-xs text-yellow-500"></i>
                                                <i v-for="n in (3 - getProfitabilityStars(scope.row.utility_data.percentage))" :key="`unfilled-${n}`" class="fa-regular fa-star text-xs"></i>
                                            </div>
                                                <span class="font-semibold text-xs">{{ scope.row.utility_data.percentage.toFixed(1).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}%</span>
                                        </div>
                                    </el-tooltip>
                                    <p v-else class="text-center">N/A</p>
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
                            <el-table-column prop="status" label="Estatus" width="145">
                                <template #default="scope">
                                    <el-tag :type="getStatusTagType(scope.row.status)">
                                        {{ scope.row.status }}
                                    </el-tag>
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
                            <el-table-column label="Monto Total" width="110">
                                 <template #default="scope">
                                    {{ formatCurrency(scope.row.total_amount) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Cotización" width="100">
                                 <template #default="scope">
                                    <a v-if="scope.row.quote_id" @click.stop
                                        :href="route('quotes.show', scope.row.quote_id)" target="_blank"
                                        class="text-blue-500 hover:underline">
                                        COT-{{ String(scope.row.quote_id).padStart(4, '0') }}
                                    </a>
                                    <span v-else class="text-gray-400">N/A</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Factura" width="100">
                                 <template #default="scope">
                                    <a v-if="scope.row.invoice_id" @click.stop
                                        :href="route('quotes.show', scope.row.invoice_id)" target="_blank"
                                        class="text-blue-500 hover:underline">
                                        FACT-{{ String(scope.row.invoice_id).padStart(4, '0') }}
                                    </a>
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
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>Ver
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Editar ordenes de venta') && scope.row.authorized_at === null"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>Editar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Autorizar ordenes de venta') && !scope.row.authorized_at"
                                                    :command="`authorize-${scope.row.id}`">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    Autorizar
                                                </el-dropdown-item>
                                                <!-- <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta')"
                                                    :command="'clone-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                                    </svg>
                                                    Clonar</el-dropdown-item> -->
                                                <el-dropdown-item :command="'print-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                                    </svg>
                                                    Imprimir</el-dropdown-item>
                                                <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Crear facturas') && !scope.row.invoices?.length" :command="'create-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                    Crear factura
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
            tableData: this.sales.data,
            showAllSales: this.filters.view === 'all',
            SearchProps: ['ID', 'Cliente', 'Creador', 'Estatus'],
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
            this.loading = true;
            try {
                if (!this.search) {
                    this.tableData = this.sales.data;
                    this.$inertia.get(this.route('sales.index'), {}, {
                        preserveState: true,
                        replace: true,
                    });
                    return;
                }
                const response = await axios.post(route('sales.get-matches', { query: this.search }));
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
            router.get(route('sales.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            
            if (action === 'authorize') {
                this.authorize(id);
            } else if ( action === 'print' ) {
                window.open(route('sales.print', id), '_blank');
            } else if ( action === 'clone' ) {
                this.clone(id);
            }
            else {
                router.get(route(`sales.${action}`, id));
            }
        },
        // async clone(sale_id) {
        //     this.loading = true; // Muestra el indicador de carga
        //     try {
        //         const response = await axios.get(route('sales.clone', sale_id));

        //         if (response.status === 200) {
        //             // Agrega la nueva orden de venta al inicio de la tabla
        //             this.tableData.unshift(response.data.newItem);
                    
        //             // Muestra un mensaje de éxito
        //             ElMessage.success(response.data.message);
        //         }
        //     } catch (err) {
        //         // Muestra un mensaje de error si algo sale mal
        //         ElMessage.error('Ocurrió un error al clonar la orden de venta.');
        //         console.error(err);
        //     } finally {
        //         this.loading = false; // Oculta el indicador de carga
        //     }
        // },
        // --- Método para autorizar ---
        async authorize(sale_id) {
            try {
                const response = await axios.put(route('sales.authorize', sale_id));
                if (response.status === 200) {
                    const index = this.tableData.findIndex(item => item.id == sale_id);
                    if (index !== -1) {
                        this.tableData[index].authorized_at = response.data.item.authorized_at;
                        this.tableData[index].authorized_user_name = response.data.item.authorized_user_name;
                        this.tableData[index].status = response.data.item.status;
                    }
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar la venta');
                console.error(err);
            }
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
            const num = Number(value);
            return num.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
        // --- NUEVO: Helpers para la columna de utilidad ---
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        getProfitabilityClass(margin) {
            if (margin < 20) return 'text-red-600';
            if (margin >= 20 && margin < 100) return 'text-amber-600';
            return 'text-green-600';
        },
        getProfitabilityStars(margin) {
            if (margin < 20) return 1;
            if (margin >= 20 && margin < 100) return 2;
            return 3;
        },
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'info',
                'Autorizada': 'primary',
                'En Proceso': 'warning',
                'En Producción': 'primary',
                'Stock Terminado': 'success',
                'Preparando Envío': 'success',
                'Enviada': 'success',
            };
            return statusMap[status] || '';
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
