<template>
    <AppLayout title="Facturación">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Módulo de Facturación
        </h2>

        <div class="py-7">
            <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- Cabecera con Acciones y Filtro Global -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0">
                        <div class="flex flex-wrap gap-2">
                            <Link :href="route('invoices.create')">
                                <SecondaryButton>
                                    <i class="fa-solid fa-plus mr-2"></i>
                                    Nueva Factura
                                </SecondaryButton>
                            </Link>
                            <SecondaryButton @click="reportModalVisible = true">
                                <i class="fa-solid fa-file-invoice-dollar mr-2"></i>
                                Facturas Pendientes
                            </SecondaryButton>
                        </div>

                        <!-- Filtro por Cliente (Sincronizado con URL) -->
                        <div class="w-full md:w-96 flex items-center space-x-2">
                            <el-select
                                v-model="selectedClientId"
                                placeholder="Filtrar por Cliente"
                                filterable
                                clearable
                                class="flex-1"
                                @change="syncUrl"
                            >
                                <el-option
                                    v-for="item in branches"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                />
                            </el-select>

                            <!-- Botón para quitar filtro (Solo visible si hay un cliente seleccionado) -->
                            <el-tooltip v-if="selectedClientId" content="Ver facturas de todos los clientes" placement="top">
                                <button 
                                    @click="clearFilter"
                                    class="size-9 flex-shrink-0 flex items-center justify-center rounded-lg bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 transition-colors"
                                >
                                    <i class="fa-solid fa-filter-circle-xmark text-sm"></i>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>

                    <!-- Pestañas Modulares -->
                    <el-tabs v-model="activeTab" class="demo-tabs" @tab-change="syncUrl">
                        <el-tab-pane label="Facturas Registradas" name="all_invoices">
                            <AllInvoicesTab 
                                :invoices="invoices" 
                                @page-change="handlePageChange('invoices_page', $event)" 
                            />
                        </el-tab-pane>
                        
                        <el-tab-pane label="OVs por Facturar" name="sales_without_invoice">
                            <SalesToInvoiceTab 
                                :sales="salesWithoutInvoice" 
                                @page-change="handlePageChange('sales_page', $event)" 
                            />
                        </el-tab-pane>

                        <el-tab-pane label="Facturas por Cobrar" name="pending_invoices">
                            <PendingInvoicesTab 
                                :invoices="pendingInvoices" 
                                @register-payment="openPaymentModal"
                                @page-change="handlePageChange('pending_page', $event)" 
                            />
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </div>

        <!-- Modal para Registrar Pago -->
        <el-dialog v-model="paymentModalVisible" title="Registrar Pago de Factura" width="500px">
            <div v-if="selectedInvoice">
                <p class="mb-2"><strong>Folio:</strong> {{ selectedInvoice.folio }}</p>
                <p class="mb-2"><strong>Cliente:</strong> {{ selectedInvoice.sale?.branch?.name }}</p>
                <p class="mb-4"><strong>Saldo Pendiente:</strong> 
                    <span class="font-bold text-lg text-amber-600">${{ formatCurrency(getPendingAmount(selectedInvoice)) }}</span>
                </p>

                <form @submit.prevent="submitPayment">
                    <div class="space-y-4">
                        <div>
                             <label class="text-sm ml-3 text-gray-600 dark:text-gray-400">Monto a pagar*</label>
                             <el-input-number v-model="paymentForm.amount" :precision="2" :step="100" :min="0.01" :max="getPendingAmount(selectedInvoice)" class="!w-full" size="large" />
                             <InputError :message="paymentForm.errors.amount" />
                        </div>
                        <div>
                            <label class="text-sm ml-3 text-gray-600 dark:text-gray-400">Fecha de pago*</label>
                            <el-date-picker v-model="paymentForm.payment_date" type="date" format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" size="large" />
                            <InputError :message="paymentForm.errors.payment_date" />
                        </div>
                        <div>
                           <label class="text-sm ml-3 text-gray-600 dark:text-gray-400">Método de pago*</label>
                            <el-select v-model="paymentForm.payment_method" placeholder="Seleccionar" class="!w-full" size="large">
                                <el-option v-for="item in paymentMethods" :key="item" :label="item" :value="item" />
                            </el-select>
                            <InputError :message="paymentForm.errors.payment_method" />
                        </div>
                        <div>
                             <label class="text-sm ml-3 text-gray-600 dark:text-gray-400">Notas</label>
                             <el-input v-model="paymentForm.notes" :rows="2" type="textarea" placeholder="Añade notas..." />
                        </div>
                        <div>
                            <label class="text-sm ml-3 text-gray-600 dark:text-gray-400">Adjuntar comprobante(s)</label>
                            <el-upload
                                ref="paymentUploaderRef"
                                v-model:file-list="paymentForm.media"
                                multiple
                                :auto-upload="false"
                            >
                                <el-button type="primary" plain size="small"><i class="fa-solid fa-upload mr-2"></i>Seleccionar archivos</el-button>
                            </el-upload>
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

        <!-- Modal para Reporte -->
        <el-dialog v-model="reportModalVisible" title="Generar Reporte" width="500px">
            <div>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Selecciona el rango de fechas de emisión para el reporte.</p>
                <el-date-picker
                    v-model="reportDateRange"
                    type="daterange"
                    range-separator="a"
                    format="YYYY/MM/DD"
                    value-format="YYYY-MM-DD"
                    class="!w-full"
                    size="large"
                />
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="reportModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="generateReport">Generar</el-button>
                </span>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { subMonths } from 'date-fns';
import { ElMessage } from 'element-plus';

import AllInvoicesTab from './Partials/AllInvoicesTab.vue';
import SalesToInvoiceTab from './Partials/SalesToInvoiceTab.vue';
import PendingInvoicesTab from './Partials/PendingInvoicesTab.vue';

export default {
    name: 'InvoiceIndex',
    components: { AppLayout, SecondaryButton, InputError, Link, AllInvoicesTab, SalesToInvoiceTab, PendingInvoicesTab },
    props: {
        invoices: Object,
        salesWithoutInvoice: Object,
        pendingInvoices: Object,
        active_tab_prop: String,
        client_id_prop: [String, Number],
        branches: Array,
    },
    data() {
        return {
            activeTab: this.active_tab_prop || 'all_invoices',
            selectedClientId: this.client_id_prop ? parseInt(this.client_id_prop) : null,
            paymentModalVisible: false,
            reportModalVisible: false,
            reportDateRange: [subMonths(new Date(), 1).toISOString().split('T')[0], new Date().toISOString().split('T')[0]],
            selectedInvoice: null,
            paymentMethods: ['Efectivo', 'Transferencia electrónica de fondos', 'Tarjeta de crédito', 'Tarjeta de débito', 'Cheque nominativo', 'Por definir'],
            paymentForm: useForm({
                amount: null,
                payment_date: new Date().toISOString().split('T')[0],
                payment_method: 'Transferencia electrónica de fondos',
                notes: '',
                media: [],
            })
        };
    },
    methods: {
        syncUrl() {
            router.get(route('invoices.index'), {
                tab: this.activeTab,
                client_id: this.selectedClientId,
            }, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        },
        clearFilter() {
            this.selectedClientId = null;
            this.syncUrl();
        },
        handlePageChange(pageName, page) {
            router.get(route('invoices.index'), {
                [pageName]: page,
                tab: this.activeTab,
                client_id: this.selectedClientId,
            }, { preserveState: true, replace: true, preserveScroll: true });
        },
        formatCurrency(value) {
            return parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        getPendingAmount(invoice) {
            const total = parseFloat(invoice.amount);
            const paid = invoice.payments?.reduce((acc, p) => acc + parseFloat(p.amount), 0) || 0;
            return Math.max(0, total - paid);
        },
        openPaymentModal(invoice) {
            this.selectedInvoice = invoice;
            this.paymentForm.reset();
            this.paymentForm.amount = this.getPendingAmount(invoice);
            this.paymentModalVisible = true;
        },
        submitPayment() {
            this.paymentForm.transform(data => ({
                ...data,
                media: data.media.map(file => file.raw),
            })).post(route('invoices.payments.store', this.selectedInvoice.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.paymentModalVisible = false;
                    ElMessage.success('Pago registrado correctamente');
                }
            });
        },
        generateReport() {
            if (!this.reportDateRange) return ElMessage.warning('Selecciona fechas');
            const [start, end] = this.reportDateRange;
            window.open(route('invoices.pending-report', { start_date: start, end_date: end }), '_blank');
            this.reportModalVisible = false;
        }
    }
};
</script>