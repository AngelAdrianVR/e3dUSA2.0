<template>
    <p class="text-xs mb-1 ml-3">Últimas 20 cotizaciones</p>
    <div v-if="quotes.length">
        <el-table 
            :data="tableData" 
            style="width: 100%" 
            max-height="600"
            @row-click="handleRowClick"
            :row-class-name="tableRowClassName"
            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300 !h-[500px]"
            stripe>
            
            <!-- Folio -->
            <el-table-column prop="id" label="Folio" width="140">
                <template #default="scope">
                    <div class="flex items-center space-x-1">
                        <!-- Tooltip de estatus -->
                        <el-tooltip placement="top">
                            <template #content>
                                <p v-html="getStatusTooltip(scope.row)"></p>
                            </template>
                            <span v-html="getStatusIcon(scope.row.status)" class="text-sm mr-2"></span>
                        </el-tooltip>
                        
                        <!-- Folio -->
                        <span class="text-xs font-semibold">{{ 'COT-' + String(scope.row.root_quote_id ?? scope.row.id).padStart(4, '0') }}</span>
                        
                        <!-- Badge de Versiones -->
                        <el-tooltip v-if="scope.row.all_versions_count > 1"
                                    :content="`Viendo la versión ${scope.row.version} de ${scope.row.all_versions_count} totales`" 
                                    placement="top">
                            <span class="ml-1 px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-full dark:bg-blue-900 dark:text-blue-300">
                                v{{ scope.row.version }}/{{ scope.row.all_versions_count }}
                            </span>
                        </el-tooltip>
                    </div>
                </template>
            </el-table-column>

            <!-- Productos -->
            <el-table-column label="Productos" width="130">
                <template #default="scope">
                    <el-tooltip v-if="scope.row.products?.length" placement="top">
                        <template #content>
                            <ul class="list-disc list-inside text-xs">
                                <li v-for="item in scope.row.products" :key="item.id">
                                    ({{ item.pivot?.quantity ?? item.quantity }}) {{ item.name }}
                                </li>
                            </ul>
                        </template>
                        <span class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                            {{ scope.row.products.length }} producto(s)
                        </span>
                    </el-tooltip>
                    <span v-else class="text-xs text-gray-400">N/A</span>
                </template>
            </el-table-column>

            <!-- Utilidad -->
            <el-table-column v-if="$page.props.auth.user.permissions.includes('Utilidad cotizaciones')" label="Utilidad" width="90">
                <template #default="scope">
                    <el-tooltip v-if="scope.row.utility_data" placement="right">
                        <template #content>
                            <div class="text-xs">
                                <p class="text-white dark:text-gray-600 font-bold mb-2">No se toma en cuenta herramental ni flete</p>
                                <p class="text-blue-300 dark:text-blue-700">Venta: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.total_sale) }} {{ scope.row.currency }}</strong></p>
                                <p class="text-amber-400 dark:text-amber-700">Costo: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.total_cost) }} {{ scope.row.currency }}</strong></p>
                                <p class="text-green-400 dark:text-green-700">Utilidad: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.profit) }} {{ scope.row.currency }}</strong></p>
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
                    <span v-else class="text-gray-400 text-xs">N/A</span>
                </template>
            </el-table-column>

            <!-- Creado por -->
            <el-table-column label="Creado por" width="130">
                <template #default="scope">
                    <div v-if="scope.row.created_by_customer"
                        class="flex items-center space-x-1 text-gray-500 dark:text-gray-400">
                        <i class="fa-solid fa-user-tie" title="Portal de Clientes"></i>
                        <span class="text-xs">Portal</span>
                        <el-tooltip v-if="scope.row.user" content="Revisado por vendedor" placement="top">
                            <template #content>
                                <p class="font-bold">Revisado por vendedor</p>
                                <p class="text-blue-300">Vendedor: <span class="text-white">{{ scope.row.user?.name }}</span></p>
                            </template>
                            <i class="fa-solid fa-check-double text-green-500 text-xs"></i>
                        </el-tooltip>
                    </div>
                    <span v-else class="text-xs">{{ scope.row.user?.name }}</span>
                </template>
            </el-table-column>

            <!-- Total -->
            <el-table-column label="Total" width="140" align="right">
                <template #default="scope">
                    <!-- Tooltip de descuento -->
                    <el-tooltip v-if="scope.row.has_early_payment_discount" placement="top">
                        <template #content>
                            <div v-if="scope.row.early_paid_at">
                                <p>Descuento por pago anticipado aplicado.</p>
                                <span class="text-green-500">
                                    Descuento: 
                                    <span class="text-white">{{ scope.row.total_data.discount_percentage }}% 
                                        -> ${{ scope.row.total_data.discount_amount?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,",") }}
                                    </span> <br>
                                    <span class="text-green-500">Total sin descuento: <span class="text-white">${{ scope.row.total_data.total_before_discount?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,",") }}</span></span> <br>
                                    <span class="text-green-500">Total con descuento: <span class="text-white">${{ scope.row.total_data.total_after_discount?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,",") }}</span></span>
                                </span> <br>
                                <span class="text-blue-300">Pagado el: <strong class="text-white">{{ formatDate(scope.row.early_paid_at) }}</strong></span>
                            </div>
                            <p class="text-white font-bold" v-else>
                                Descuento por pago anticipado aún no pagado <br>
                                <span class="text-amber-500">
                                    Descuento: 
                                    <span class="text-white">{{ scope.row.total_data.discount_percentage }}% 
                                        -> ${{ scope.row.total_data.discount_amount?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,",") }}
                                    </span> <br>
                                    <span class="text-amber-500">Total sin descuento: <span class="text-white">${{ scope.row.total_data.total_before_discount?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,",") }}</span></span> <br>
                                    <span class="text-amber-500">Total con descuento: <span class="text-white">${{ scope.row.total_data.total_after_discount?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,",") }}</span></span>
                                </span>
                            </p>
                        </template>
                        <i class="fa-solid fa-fire text-sm mr-2"
                            :class="scope.row.early_paid_at ? 'text-green-500' : 'text-red-500'"></i>
                    </el-tooltip>
                    <span class="font-semibold">{{ scope.row.total_data.total_after_discount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ scope.row.currency }}</span>
                </template>
            </el-table-column>

            <!-- Autorizado -->
            <el-table-column label="Autorizado" width="100" align="center">
                <template #default="scope">
                    <el-tooltip v-if="scope.row.authorized_at" placement="top">
                        <template #content>
                            Autorizado por: {{ scope.row.authorized_by?.name ?? scope.row.authorized_by }} <br>
                            Fecha: {{ formatDate(scope.row.authorized_at) }}
                        </template>
                        <i class="fa-solid fa-check-double text-green-500 text-lg"></i>
                    </el-tooltip>
                    <p v-else class="text-xs text-gray-400">No autorizada</p>
                </template>
            </el-table-column>

            <!-- OV Link -->
            <el-table-column label="OV" width="90" align="center">
                <template #default="scope">
                    <a v-if="scope.row.sale_id" @click.stop
                        :href="route('sales.show', scope.row.sale_id)" target="_blank"
                        class="text-blue-500 hover:underline">
                        OV-{{ String(scope.row.sale_id).padStart(4, '0') }}
                    </a>
                    <span v-else class="text-gray-400">N/A</span>
                </template>
            </el-table-column>

            <!-- Fecha creación -->
            <el-table-column label="Fecha" width="140">
                <template #default="scope">
                    {{ formatDate(scope.row.created_at) }}
                </template>
            </el-table-column>

            <!-- Menú de acciones -->
            <el-table-column align="right" width="60">
                <template #default="scope">
                    <el-dropdown trigger="click" @command="handleCommand">
                        <button @click.stop
                            class="el-dropdown-link justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item :command="`show-${scope.row.id}`">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>Ver
                                </el-dropdown-item>
                                <el-dropdown-item
                                    v-if="$page.props.auth.user.permissions.includes('Editar cotizaciones')"
                                    :command="`edit-${scope.row.id}`">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>Editar
                                </el-dropdown-item>
                                <el-dropdown-item
                                    v-if="$page.props.auth.user.permissions.includes('Autorizar cotizaciones') && !scope.row.authorized_at"
                                    :disabled="!scope.row.user"
                                    :command="`authorize-${scope.row.id}`">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>Autorizar
                                </el-dropdown-item>
                                <el-dropdown-item
                                    v-if="scope.row.status === 'Esperando respuesta' && scope.row.authorized_at"
                                    :command="`changeStatus-${scope.row.id}-Aceptada`">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Aceptada por cliente
                                </el-dropdown-item>
                                <el-dropdown-item
                                    v-if="scope.row.status === 'Esperando respuesta' && scope.row.authorized_at"
                                    :command="`changeStatus-${scope.row.id}-Rechazada`">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>Rechazada por cliente
                                </el-dropdown-item>
                                <el-dropdown-item
                                    v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta') && scope.row.status === 'Aceptada' && !scope.row.sale_id"
                                    :command="`make_so-${scope.row.id}`"
                                    :disabled="!scope.row.authorized_at">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                                    </svg>Convertir a OV
                                </el-dropdown-item>
                                <el-dropdown-item
                                    v-if="$page.props.auth.user.permissions.includes('Crear cotizaciones')"
                                    :command="`clone-${scope.row.id}`">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                    </svg>Clonar
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </template>
            </el-table-column>
        </el-table>
    </div>
    <div v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">
        No hay cotizaciones para mostrar.
    </div>

    <!-- Modal para motivo de rechazo -->
    <el-dialog v-model="rejectionModalVisible" title="Motivo de Rechazo" width="30%">
        <el-input v-model="rejectionReason" type="textarea" :rows="3"
            placeholder="Por favor, introduce el motivo del rechazo." />
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="rejectionModalVisible = false">Cancelar</el-button>
                <el-button type="primary" @click="submitRejection">Confirmar</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage, ElMessageBox } from 'element-plus';
import axios from 'axios';

export default {
    props: {
        quotes: Array,
    },
    data() {
        return {
            tableData: this.quotes,
            rejectionModalVisible: false,
            rejectionReason: '',
            selectedQuoteIdForRejection: null,
            statusMap: {
                'Aceptada': { icon: '<i class="fa-solid fa-circle-check text-green-500"></i>', text: 'Aceptada por el cliente' },
                'Rechazada': { icon: '<i class="fa-solid fa-circle-xmark text-red-500"></i>', text: 'Rechazada por el cliente' },
                'Esperando respuesta': { icon: '<i class="fa-solid fa-hourglass-half text-amber-500"></i>', text: 'Esperando respuesta del cliente' },
            }
        }
    },
    methods: {
        handleRowClick(row) {
            window.open(route('quotes.show', row.id), '_blank');
        },
        handleCommand(command) {
            const [action, id, newStatus] = command.split('-');

            const actions = {
                'show': () => window.open(route('quotes.show', id), '_blank'),
                'edit': () => this.$inertia.visit(route('quotes.edit', id)),
                'clone': () => this.clone(id),
                'make_so': () => this.$inertia.visit(route('sales.create', { quote_id: id })),
                'authorize': () => this.authorize(id),
                'changeStatus': () => {
                    if (newStatus === 'Rechazada') {
                        this.selectedQuoteIdForRejection = id;
                        this.rejectionModalVisible = true;
                    } else {
                        this.changeStatus(id, newStatus);
                    }
                }
            };

            if (actions[action]) actions[action]();
        },
        // --- Lógica del Index ---
        async authorize(quote_id) {
            try {
                const response = await axios.put(route('quotes.authorize', quote_id));
                if (response.status === 200) {
                    const index = this.tableData.findIndex(item => item.id == quote_id);
                    if (index !== -1) {
                        this.tableData[index].authorized_at = response.data.item.authorized_at;
                        this.tableData[index].authorized_by = response.data.item.authorized_by?.name;
                    }
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar');
                console.error(err);
            }
        },
        async changeStatus(quoteId, newStatus, rejectionReason = null) {
            try {
                const response = await axios.post(route('quotes.change-status', quoteId), {
                    new_status: newStatus,
                    rejection_reason: rejectionReason
                });

                if (response.status === 200) {
                    const index = this.tableData.findIndex(item => item.id == quoteId);
                    if (index !== -1) {
                        this.tableData[index].status = newStatus;
                        if (rejectionReason) this.tableData[index].rejection_reason = rejectionReason;
                        if (newStatus === 'Aceptada') this.tableData[index].customer_responded_at = new Date().toISOString();
                    }
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Error al cambiar el estatus.');
                console.error(err);
            }
        },
        submitRejection() {
            if (!this.rejectionReason) {
                ElMessage.warning('El motivo de rechazo es obligatorio.');
                return;
            }
            this.changeStatus(this.selectedQuoteIdForRejection, 'Rechazada', this.rejectionReason);
            this.rejectionModalVisible = false;
            this.rejectionReason = '';
            this.selectedQuoteIdForRejection = null;
        },
        clone(quote_id) {
            ElMessageBox.confirm(
                '¿Estás seguro de clonar esta cotización? Se creará una nueva versión.',
                'Clonar Cotización',
                {
                    confirmButtonText: 'Sí, clonar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            )
            .then(async () => {
                try {
                    const response = await axios.get(route('quotes.clone', quote_id));
                    if (response.status === 200) {
                        this.tableData.unshift(response.data.newItem);
                        ElMessage.success(response.data.message);
                    }
                } catch (err) {
                    ElMessage.error('Ocurrió un error al clonar la cotización.');
                }
            })
            .catch(() => {});
        },
        // --- Helpers de Formato ---
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "d MMM, yyyy", { locale: es });
        },
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
        tableRowClassName({ row }) {
            if (row.created_by_customer) {
                return row.user ? 'created-by-customer-revised' : 'created-by-customer-pending';
            }
            return '';
        }
    },
    watch: {
        quotes: {
            handler(newVal) {
                this.tableData = newVal;
            },
            deep: true
        }
    }
}
</script>

<style>
.el-table .created-by-customer-pending td {
    background-color: #fff3e0 !important;
}
.dark .el-table .created-by-customer-pending td {
    background-color: #6c3802 !important;
}
</style>