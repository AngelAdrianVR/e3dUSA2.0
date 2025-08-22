<template>
    <AppLayout title="Cotizaciones">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Cotizaciones
        </h2>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- Barra de acciones superior -->
                    <div class="flex justify-between items-center mb-4">
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear cotizaciones')"
                            :href="route('quotes.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Cotización
                            </SecondaryButton>
                        </Link>

                        <div class="flex items-center space-x-2">
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar cotizaciones')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar las cotizaciones seleccionadas?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                            <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" />
                        </div>
                    </div>

                    <!-- Leyenda de estatus -->
                    <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-xs mb-4 dark:text-white">
                        <p class="flex items-center"><i class="fa-solid fa-circle text-green-500 mr-2"></i>Cliente</p>
                        <p class="flex items-center"><i class="fa-solid fa-circle text-blue-500 mr-2"></i>Prospecto</p>
                        <p class="flex items-center"><i class="fa-solid fa-square text-orange-400 mr-2"></i>Creada por cliente (sin revisar)</p>
                        <p class="flex items-center"><i class="fa-solid fa-square text-green-400 mr-2"></i>Creada por cliente (revisada)</p>
                    </div>

                    <!-- Tabla de Cotizaciones -->
                    <div class="relative">
                        <div v-if="loading" class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <el-table 
                            max-height="550" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            :row-class-name="tableRowClassName"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="35" />
                            <el-table-column prop="id" label="Folio" width="130">
                                <template #default="scope">
                                    <div class="flex items-center space-x-1">
                                        <el-tooltip placement="top">
                                            <template #content>
                                                <p v-html="getStatusTooltip(scope.row)"></p>
                                            </template>
                                            <span v-html="getStatusIcon(scope.row.status)" class="text-lg"></span>
                                        </el-tooltip>
                                        <span>{{ 'COT-' + String(scope.row.id).padStart(4, '0') }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="branch.name" label="Cliente" width="220">
                                 <template #default="scope">
                                    <div class="flex items-center">
                                        <i class="fa-solid fa-circle text-[8px] mr-2" :class="scope.row.branch.status === 'Cliente' ? 'text-green-500' : 'text-blue-500'"></i>
                                        <span>{{ scope.row.branch.name }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                             <el-table-column prop="user.name" label="Creado por" width="180">
                                 <template #default="scope">
                                    <div v-if="scope.row.created_by_customer" class="flex items-center space-x-1 text-gray-500 dark:text-gray-400">
                                        <i class="fa-solid fa-user-tie"></i>
                                        <span>Portal de Clientes</span>
                                        <el-tooltip v-if="scope.row.user" content="Revisado por vendedor" placement="top">
                                            <i class="fa-solid fa-check-double text-green-500"></i>
                                        </el-tooltip>
                                    </div>
                                    <span v-else>{{ scope.row.user?.name }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Total" width="150">
                                <template #default="scope">
                                    <span class="font-semibold">{{ scope.row.total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ scope.row.currency }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="OV" width="100">
                                <template #default="scope">
                                     <a v-if="scope.row.sale_id" @click.stop :href="route('sales.show', scope.row.sale_id)" target="_blank" class="text-blue-500 hover:underline">
                                        OV-{{ String(scope.row.sale_id).padStart(4, '0') }}
                                     </a>
                                    <span v-else class="text-gray-400">N/A</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="created_at" label="Fecha de creación">
                                <template #default="scope">
                                    {{ formatDate(scope.row.created_at) }}
                                </template>
                            </el-table-column>

                            <!-- Menú de acciones -->
                            <el-table-column align="right" width="80">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop class="el-dropdown-link justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :command="'show-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    Ver
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="'edit-' + scope.row.id" v-if="$page.props.auth.user.permissions.includes('Editar cotizaciones')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                    Editar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Autorizar cotizaciones') && !scope.row.authorized_at"
                                                    :disabled="!scope.row.user?.name" :command="'authorize-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    Autorizar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="scope.row.status !== 'Rechazada' && scope.row.status !== 'Aceptada' && scope.row.authorized_at"
                                                    :disabled="!scope.row.user?.name" :command="'changeStatus-' + scope.row.id + '-Aceptada'">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    Marcar como aceptada
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="scope.row.status !== 'Rechazada' && scope.row.status !== 'Aceptada' && scope.row.authorized_at"
                                                    :disabled="!scope.row.user?.name" :command="'changeStatus-' + scope.row.id + '-Rechazada'">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                    </svg>
                                                    Marcar como rechazada
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta') && scope.row.status === 'Aceptada' && !scope.row.sale_id"
                                                    :command="'make_so-' + scope.row.id" :disabled="!scope.row.authorized_at">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                                    </svg>
                                                    Convertir a OV
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="'clone-' + scope.row.id" v-if="$page.props.auth.user.permissions.includes('Crear cotizaciones')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                                    </svg>
                                                    Clonar
                                                </el-dropdown-item>
                                                <el-dropdown-item v-if="scope.row.status === 'Aceptada' && !scope.row.sale_id" :command="'make_so-' + scope.row.id"><i class="fa-solid fa-file-invoice-dollar mr-2"></i>Convertir a OV</el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="quotes.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="quotes.current_page"
                            :page-size="quotes.per_page" :total="quotes.total"
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
import { Link } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import axios from 'axios';

export default {
    // Usando Options API
    data() {
        return {
            loading: false,
            search: '',
            selectedItems: [],
            tableData: this.quotes.data,
            SearchProps: ['ID', 'Cliente', 'Creador'], // indica por cuales propiedades del registro puedes buscar
            statusMap: {
                'Aceptada': { icon: '<i class="fa-solid fa-circle-check text-green-500 text-sm mr-2"></i>', text: 'Aceptada por el cliente' },
                'Rechazada': { icon: '<i class="fa-solid fa-circle-xmark text-red-500 text-sm mr-2"></i>', text: 'Rechazada por el cliente' },
                'Esperando respuesta': { icon: '<i class="fa-solid fa-hourglass-half text-amber-500 text-sm mr-2"></i>', text: 'Esperando respuesta del cliente' },
            }
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
        quotes: Object,
    },
    methods: {
        formatDate(dateString) {
            return format(new Date(dateString), "d MMM, yyyy 'a las' h:mm a", { locale: es });
        },
        getStatusIcon(status) {
            return this.statusMap[status]?.icon || '<i class="fa-solid fa-circle-question text-gray-400"></i>';
        },
        getStatusTooltip(row) {
            let baseText = this.statusMap[row.status]?.text || 'Estatus desconocido';
            if (row.customer_responded_at) {
                baseText += `<br>Fecha: ${this.formatDate(row.customer_responded_at)}`;
            }
            if (row.status === 'Rechazada' && row.rejection_reason) {
                baseText += `<br>Motivo: <b>${row.rejection_reason}</b>`;
            }
            return baseText;
        },
        async authorize(quote_id) {
            try {
                const response = await axios.put(route('quotes.authorize', quote_id));

                if (response.status === 200) {
                    const index = this.quotes.data.findIndex(item => item.id == quote_id);
                    this.quotes.data[index].authorized_at = response.data.item.authorized_at;
                    this.quotes.data[index].authorized_user_name = response.data.item.authorized_by?.name;
                    ElMessage({
                        title: 'Éxito',
                        message: response.data.message,
                        type: 'success'
                    });
                } else {
                    ElMessage({
                        title: 'Algo salió mal',
                        message: response.data.message,
                        type: 'error'
                    });
                }
            } catch (err) {
                ElMessage({
                    title: 'Algo salió mal',
                    message: err.message,
                    type: 'error'
                });
                console.log(err);
            }
        },
        tableRowClassName({ row }) {
            if (row.created_by_customer) {
                return row.user ? 'created-by-customer-revised' : 'created-by-customer-pending';
            }
            return '';
        },
        async handleSearch() {
            this.loading = true;
            try {
                if (!this.search) {
                    this.tableData = this.quotes.data;
                    this.$inertia.get(this.route('quotes.index'), {}, {
                        preserveState: true,
                        replace: true,
                        onFinish: () => { this.loading = false; },
                    });
                    return;
                }
                const response = await axios.post(route('quotes.get-matches', { query: this.search }));
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
            this.$inertia.get(route('quotes.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            if (action === 'make_so') {
                // Aquí podrías abrir un modal de confirmación si quieres
                console.log('Convertir a OV: ', id);
            } else if (action == 'authorize') {
                this.authorize(id);
            } else if ( action == 'changeStatus' ) {
                if (newStatus === 'Rechazada') {
                    this.selectedQuoteId = rowId;
                    this.showRejectedModal = true;
                    return;
                }
                this.changeStatus(rowId, newStatus);
            } else {
                this.$inertia.get(route(`quotes.${action}`, id));
            }
             
            
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('quotes.massive-delete'), { ids }, {
                onSuccess: () => ElMessage.success('Cotizaciones eliminadas'),
                onError: () => ElMessage.error('Ocurrió un error'),
            });
        },
        handlePageChange(page) {
            this.$inertia.get(route('quotes.index', { page }), {
                preserveState: true,
                replace: true,
            });
        },
        async changeStatus(quoteId, newStatus, rejectedRazon = null) {
        try {
            const response = await axios.post(route('quotes.change-status', quoteId), {
                new_status: newStatus,
                rejected_razon: rejectedRazon
            });

            if (response.status == 200) {
                const index = this.quotes.data.findIndex(item => item.id == quoteId);
                this.quotes.data[index].status = response.data.quote.status;
                this.quotes.data[index].responded_at = response.data.quote.responded_at;
                this.quotes.data[index].rejected_razon = response.data.quote.rejected_razon;
                this.$notify({
                    title: 'Éxito',
                    message: response.data.message,
                    type: 'success'
                });
            } else {
                this.$notify({
                    title: 'Algo salió mal',
                    message: response.data.message,
                    type: 'error'
                });
            }
        } catch (err) {
            this.$notify({
                title: 'Algo salió mal',
                message: err.message,
                type: 'error'
            });
            console.log(err);
        } finally {
            this.showRejectedModal = false;
            this.rejectedRazon = null;
        }
    }
    },
    watch: {
        'quotes.data': {
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
/* Estilos para filas creadas por clientes */
.el-table .created-by-customer-pending td {
    background-color: #fff3e0 !important; /* Naranja claro */
}
.dark .el-table .created-by-customer-pending td {
    background-color: #6c3802 !important;
}
.el-table .created-by-customer-revised td {
    background-color: #e8f5e9 !important; /* Verde claro */
}
.dark .el-table .created-by-customer-revised td {
    background-color: #1c4b27 !important;
}

/* Estilos para paginación en modo oscuro */
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
