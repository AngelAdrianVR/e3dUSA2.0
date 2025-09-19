<template>
    <AppLayout title="Facturación">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Módulo de Facturación
        </h2>

        <div class="py-7">
            <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para crear nueva factura -->
                        <Link :href="route('invoices.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Factura
                            </SecondaryButton>
                        </Link>

                        <!-- Input de búsqueda -->
                        <!-- <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" /> -->
                    </div>

                    <!-- Pestañas de navegación -->
                    <el-tabs v-model="activeTab" class="demo-tabs">
                        <!-- Pestaña 1: Facturas Registradas -->
                        <el-tab-pane label="Facturas Registradas" name="all_invoices">
                            <el-table :data="invoices.data" style="width: 100%" stripe @row-click="handleRowClick" class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">
                                <el-table-column prop="folio" label="Folio" width="120" />
                                <el-table-column prop="sale.id" label="OV" width="120">
                                    <template #default="scope">
                                        <a @click.stop="" class="text-blue-500 hover:underline"
                                        :href="route('sales.show', scope.row.sale.id)" target="_blank" rel="noopener noreferrer">OV-{{ scope.row.sale.id.toString().padStart(4, '0') }}</a>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="sale.branch.name" label="Cliente" width="150" />
                                <el-table-column prop="amount" label="Monto" width="150">
                                    <template #default="scope">
                                        ${{ scope.row.amount.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}
                                    </template>
                                </el-table-column>
                                <el-table-column prop="amount" label="No. Factura" width="150">
                                    <template #default="scope">
                                        {{ scope.row.installment_number }} de {{ scope.row.total_installments }}
                                    </template>
                                </el-table-column>
                                <el-table-column prop="created_at" label="Creado el" width="180">
                                    <template #default="scope">
                                        {{ formatDate(scope.row.created_at) }}
                                    </template>
                                </el-table-column>
                                <el-table-column prop="due_date" label="Fecha Vencimiento" width="180">
                                    <template #default="scope">
                                        {{ formatDate(scope.row.due_date) }}
                                    </template>
                                </el-table-column>
                                <el-table-column prop="status" label="Estatus" width="120">
                                    <template #default="scope">
                                        <el-tag :type="getStatusTag(scope.row.status)" disable-transitions>
                                            {{ scope.row.status }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <!-- Columna de acciones -->
                                <el-table-column v-if="$page.props.auth.user.permissions.includes('Cancelar facturas') || $page.props.auth.user.permissions.includes('Eliminar facturas')" label="" align="" width="100">
                                    <template #default="scope">
                                        <el-dropdown trigger="click" @command="handleCommand">
                                            <span @click.stop="" class="el-dropdown-link text-secondary rounded-full hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out size-7 flex items-center justify-center">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </span>
                                            <template #dropdown>
                                                <el-dropdown-menu>
                                                    <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Cancelar facturas')" :command="{ action: 'cancel', invoice: scope.row }">
                                                        <i class="fa-solid fa-ban mr-2"></i> Cancelar
                                                    </el-dropdown-item>
                                                    <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Eliminar facturas')" :command="{ action: 'delete', invoice: scope.row }">
                                                        <i class="fa-solid fa-trash-can mr-2"></i> Eliminar
                                                    </el-dropdown-item>
                                                </el-dropdown-menu>
                                            </template>
                                        </el-dropdown>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div v-if="invoices.total > 0" class="flex justify-center mt-6">
                            <el-pagination v-model:current-page="invoices.current_page"
                                    :page-size="invoices.per_page" :total="invoices.total"
                                    layout="prev, pager, next" background @current-change="handlePageChange('invoices_page', $event)" />
                            </div>
                        </el-tab-pane>
                        
                        <!-- Pestaña 2: OVs por Facturar -->
                        <el-tab-pane label="OVs por Facturar" name="sales_without_invoice">
                             <el-table :data="salesWithoutInvoice.data" style="width: 100%" stripe class="dark:!bg-slate-900 dark:!text-gray-300">
                                <el-table-column prop="id" label="Folio OV" width="120">
                                    <template #default="scope">
                                        <a @click.stop="" class="text-blue-500 hover:underline"
                                        :href="route('sales.show', scope.row.id)" target="_blank" rel="noopener noreferrer">OV-{{ scope.row.id.toString().padStart(4, '0') }}</a>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="branch.name" label="Cliente" />
                                <el-table-column prop="total_amount" label="Monto Total OV" width="160">
                                    <template #default="scope">
                                        ${{ formatCurrency(scope.row.total_amount) }}
                                    </template>
                                </el-table-column>
                                <el-table-column label="Total Facturado (Activo)" width="200">
                                    <template #default="scope">
                                        <span :class="scope.row.total_invoiced > 0 ? 'text-green-500' : ''">
                                            ${{ formatCurrency(scope.row.total_invoiced) }}
                                        </span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="invoices_count" label="No. Facturas" width="120" />
                                <el-table-column prop="promise_date" label="Fecha Promesa" width="180">
                                    <template #default="scope">
                                        {{ formatDate(scope.row.promise_date) }}
                                    </template>
                                </el-table-column>
                                <el-table-column align="right">
                                    <template #default="scope">
                                        <Link :href="route('invoices.create', { sale_id: scope.row.id })">
                                            <el-button type="primary" plain>Crear Factura</el-button>
                                        </Link>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div v-if="salesWithoutInvoice.total > 0" class="flex justify-center mt-6">
                               <el-pagination v-model:current-page="salesWithoutInvoice.current_page"
                                    :page-size="salesWithoutInvoice.per_page" :total="salesWithoutInvoice.total"
                                    layout="prev, pager, next" background @current-change="handlePageChange('sales_page', $event)" />
                            </div>
                        </el-tab-pane>

                        <!-- Pestaña 3: Facturas por Cobrar -->
                        <el-tab-pane label="Facturas por Cobrar" name="pending_invoices">
                            <el-table :data="pendingInvoices.data" style="width: 100%" stripe class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">
                               <el-table-column prop="folio" label="Folio" width="120" />
                                <el-table-column prop="sale.id" label="OV" width="100">
                                     <template #default="scope">
                                        <a @click.stop="" class="text-blue-500 hover:underline"
                                        :href="route('sales.show', scope.row.sale.id)" target="_blank" rel="noopener noreferrer">OV-{{ scope.row.sale.id.toString().padStart(4, '0') }}</a>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="sale.branch.name" label="Cliente" />
                                <el-table-column label="Monto Total" width="150">
                                     <template #default="scope">
                                        ${{ formatCurrency(scope.row.amount) }}
                                    </template>
                                </el-table-column>
                                <el-table-column label="Monto Pendiente" width="180">
                                     <template #default="scope">
                                        <span class="font-bold text-amber-600">${{ formatCurrency(getPendingAmount(scope.row)) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="due_date" label="Vencimiento" width="180">
                                    <template #default="scope">
                                        {{ formatDate(scope.row.due_date) }}
                                    </template>
                                </el-table-column>
                                <el-table-column prop="status" label="Estatus" width="150">
                                    <template #default="scope">
                                        <el-tag :type="getStatusTag(scope.row.status)" disable-transitions>
                                            {{ scope.row.status }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column align="right" width="180">
                                    <template #default="scope">
                                        <el-button type="success" plain @click="openPaymentModal(scope.row)">Registrar Pago</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                             <div v-if="pendingInvoices.total > 0" class="flex justify-center mt-6">
                               <el-pagination v-model:current-page="pendingInvoices.current_page"
                                    :page-size="pendingInvoices.per_page" :total="pendingInvoices.total"
                                    layout="prev, pager, next" background @current-change="handlePageChange('pending_page', $event)" />
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </div>

        <!-- Modal para Registrar Pago -->
        <el-dialog v-model="paymentModalVisible" title="Registrar Pago de Factura" width="500px">
            <div v-if="selectedInvoice">
                <p class="mb-2"><strong>Folio:</strong> {{ selectedInvoice.folio }}</p>
                <p class="mb-2"><strong>Cliente:</strong> {{ selectedInvoice.sale.branch.name }}</p>
                <p class="mb-4"><strong>Saldo Pendiente:</strong> <span class="font-bold text-lg text-amber-600">${{ formatCurrency(getPendingAmount(selectedInvoice)) }}</span></p>

                <form @submit.prevent="submitPayment">
                    <div class="space-y-4">
                        <!-- Monto del pago -->
                        <div>
                             <label class="text-sm ml-3">Monto a pagar*</label>
                             <el-input-number v-model="paymentForm.amount" :precision="2" :step="100" :min="0.01" :max="getPendingAmount(selectedInvoice)" class="!w-full" size="large" />
                             <InputError :message="paymentForm.errors.amount" />
                        </div>
                        <!-- Fecha de pago -->
                        <div>
                            <label class="text-sm ml-3">Fecha de pago*</label>
                            <el-date-picker v-model="paymentForm.payment_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" size="large" />
                            <InputError :message="paymentForm.errors.payment_date" />
                        </div>
                        <!-- Método de pago -->
                        <div>
                           <label class="text-sm ml-3">Método de pago*</label>
                            <el-select v-model="paymentForm.payment_method" placeholder="Seleccionar" class="!w-full" size="large">
                                <el-option v-for="item in paymentMethods" :key="item" :label="item" :value="item" />
                            </el-select>
                            <InputError :message="paymentForm.errors.payment_method" />
                        </div>
                        <!-- Notas -->
                        <div>
                             <label class="text-sm ml-3">Notas</label>
                             <el-input v-model="paymentForm.notes" :rows="2" type="textarea" placeholder="Añade notas o comentarios adicionales..." />
                             <InputError :message="paymentForm.errors.notes" />
                        </div>
                    </div>
                </form>
            </div>
             <template #footer>
                <span class="dialog-footer">
                    <el-button @click="paymentModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitPayment" :loading="paymentForm.processing">
                        Confirmar Pago
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import InputError from "@/Components/InputError.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    data() {
        return {
            search: '',
            activeTab: this.active_tab_prop,
            paymentModalVisible: false,
            selectedInvoice: null,
            paymentMethods: ['Efectivo', 'Transferencia electrónica de fondos', 'Tarjeta de crédito', 'Tarjeta de débito', 'Cheque nominativo', 'Por definir'],
            SearchProps: ['Estatus', 'Cliente', 'ID'], // indica por cuales propiedades del registro puedes buscar
        };
    },
    components: {
        Link,
        AppLayout,
        InputError,
        SearchInput,
        SecondaryButton,
    },
    props: {
        invoices: Object,
        salesWithoutInvoice: Object,
        pendingInvoices: Object,
        active_tab_prop: String,
    },
    setup() {
        const paymentForm = useForm({
            amount: null,
            payment_date: new Date().toISOString().split('T')[0],
            payment_method: 'Transferencia electrónica de fondos',
            notes: '',
        });
        return { paymentForm };
    },
    methods: {
        handleRowClick(row) {
            this.$inertia.get(route('invoices.show', row));
        },
        async handleSearch() {
            this.loading = true;
            try {
                // Si la búsqueda está vacía, volvemos a los datos originales de la paginación
                if (!this.search) {
                    // Forzamos un recharge con inertia para restaurar el estado original con paginación
                    this.$inertia.get(this.route('invoices.index'), {}, {
                        preserveState: true,
                        replace: true,
                        onFinish: () => { this.loading = false; },
                    });
                    return;
                }

                // Si hay texto, hacemos la petición con axios como en el original
                const response = await axios.post(route('invoices.get-matches', { query: this.search }));
                if (response.status === 200) {
                    this.invoices = response.data;
                }
            } catch (error) {
                console.error(error);
                ElMessage.error('No se pudo realizar la búsqueda');
            } finally {
                this.loading = false;
            }
        },
        // --- MODIFICACIÓN ---
        // Maneja los comandos del dropdown de acciones (cancelar y eliminar).
        handleCommand(command) {
            if (command.action === 'cancel') {
                this.confirmCancelInvoice(command.invoice);
            } else if (command.action === 'delete') {
                this.confirmDeleteInvoice(command.invoice);
            }
        },
        confirmCancelInvoice(invoice) {
            if (invoice.status === 'Cancelada') {
                ElMessage.info('Esta factura ya ha sido cancelada.');
                return;
            }

            ElMessageBox.confirm(
                `Se cancelará la factura con folio <strong class="text-indigo-500">${invoice.folio}</strong>. Esta acción no se puede deshacer. ¿Deseas continuar?`,
                'Confirmar Cancelación',
                {
                    confirmButtonText: 'Sí, Cancelar',
                    cancelButtonText: 'Salir',
                    type: 'warning',
                    dangerouslyUseHTMLString: true,
                }
            ).then(() => {
                this.cancelInvoice(invoice);
            }).catch(() => {
                ElMessage.info('Cancelación abortada');
            });
        },
        cancelInvoice(invoice) {
            router.put(route('invoices.cancel', invoice.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Factura cancelada correctamente');
                },
                onError: (errors) => {
                    ElMessage.error(errors.error || 'No se pudo cancelar la factura.');
                }
            });
        },
        // --- NUEVO ---
        // Muestra un diálogo de confirmación antes de eliminar permanentemente.
        confirmDeleteInvoice(invoice) {
            ElMessageBox.confirm(
                `Se eliminará permanentemente la factura con folio <strong class="text-red-500">${invoice.folio}</strong>. Esta acción no se puede deshacer. ¿Deseas continuar?`,
                'Confirmar Eliminación',
                {
                    confirmButtonText: 'Sí, Eliminar',
                    cancelButtonText: 'Salir',
                    type: 'warning',
                    dangerouslyUseHTMLString: true,
                }
            ).then(() => {
                this.deleteInvoice(invoice);
            }).catch(() => {
                ElMessage.info('Eliminación abortada');
            });
        },
        // --- NUEVO ---
        // Envía la petición DELETE para eliminar la factura.
        deleteInvoice(invoice) {
            router.delete(route('invoices.destroy', invoice.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Factura eliminada correctamente');
                },
                onError: (errors) => {
                    ElMessage.error(errors.error || 'No se pudo eliminar la factura.');
                }
            });
        },
        onTabChange(tabName) {
            router.get(route('invoices.index'), { tab: tabName }, {
                preserveState: true,
                replace: true,
            });
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '0.00';
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        handlePageChange(pageName, page) {
            const params = {
                [pageName]: page,
                tab: this.activeTab,
            };
            router.get(route('invoices.index', params), {
                preserveState: true,
                replace: true,
            });
        },
        getStatusTag(status) {
            switch (status) {
                case 'Pagada': return 'success';
                case 'Pendiente': return 'warning';
                case 'Vencida': return 'danger';
                case 'Cancelada': return 'info';
                default: return 'primary';
            }
        },
        getPaidAmount(invoice) {
            if (!invoice.payments || invoice.payments.length === 0) {
                return 0;
            }
            return invoice.payments.reduce((total, payment) => total + parseFloat(payment.amount), 0);
        },
        getPendingAmount(invoice) {
            const totalAmount = parseFloat(invoice.amount);
            const paidAmount = this.getPaidAmount(invoice);
            return totalAmount - paidAmount;
        },
        openPaymentModal(invoice) {
            this.selectedInvoice = invoice;
            this.paymentForm.reset();
            this.paymentForm.amount = this.getPendingAmount(invoice);
            this.paymentModalVisible = true;
        },
        submitPayment() {
            if (!this.selectedInvoice) return;

            this.paymentForm.post(route('invoices.payments.store', this.selectedInvoice.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.paymentModalVisible = false;
                    this.paymentForm.reset();
                    ElMessage.success('Pago registrado correctamente');
                },
                onError: (errors) => {
                    console.log(errors);
                    ElMessage.error('Hubo un error al registrar el pago.');
                }
            });
        }
    }
};
</script>
