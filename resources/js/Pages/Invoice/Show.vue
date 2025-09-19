<template>
    <AppLayout :title="`Detalles de la Factura ${invoice.folio}`">
        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
                    <Back :href="route('invoices.index')" />
                    <h1 class="dark:text-white font-bold text-2xl my-2">
                        <span class="text-gray-500 dark:text-gray-400">Factura:</span> {{ invoice.folio }}
                    </h1>
                </div>
            </div>
            
            <div class="flex items-center space-x-2 dark:text-white">
                 <el-tooltip content="Imprimir Factura (Próximamente)" placement="top">
                    <button class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fa-solid fa-print"></i>
                    </button>
                </el-tooltip>
                <Link :href="route('invoices.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-3 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-4">
                <!-- Card de Información de la Factura -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Factura</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                            <el-tag :type="getStatusTag(invoice.status)" disable-transitions>
                                {{ invoice.status }}
                            </el-tag>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">OV Relacionada:</span>
                             <Link :href="route('sales.show', invoice.sale.id)" class="text-blue-500 hover:underline cursor-pointer">
                                OV-{{ invoice.sale.id.toString().padStart(4, '0') }}
                            </Link>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">No. de Factura:</span>
                            <span>{{ invoice.installment_number }} de {{ invoice.total_installments }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Creado por:</span>
                            <span>{{ invoice.user?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Emisión:</span>
                            <span>{{ formatDate(invoice.issue_date) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Vencimiento:</span>
                            <span>{{ formatDate(invoice.due_date) }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Forma de pago:</span>
                            <span>{{ invoice.payment_option }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Método de pago:</span>
                            <span>{{ invoice.payment_method }}</span>
                        </li>
                        <li class="flex justify-between text-base font-bold pt-3 border-t dark:border-gray-600">
                            <span class="text-gray-700 dark:text-gray-300">Monto Total:</span>
                            <span>{{ formatCurrency(invoice.amount) }} {{ invoice.currency }}</span>
                        </li>
                         <li class="flex justify-between text-base font-bold text-green-600 dark:text-green-400">
                            <span >Monto Pagado:</span>
                            <span>{{ formatCurrency(paidAmount) }} {{ invoice.currency }}</span>
                        </li>
                         <li class="flex justify-between text-base font-bold text-amber-600 dark:text-amber-400">
                            <span >Monto Pendiente:</span>
                            <span>{{ formatCurrency(pendingAmount) }} {{ invoice.currency }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Información del Cliente -->
                <div v-if="invoice.sale.branch" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información del Cliente</h3>
                    <ul class="space-y-3 text-sm">
                       <li class="flex justify-between items-center">
                           <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>
                            <Link :href="route('branches.show', invoice.sale.branch.id)" class="text-blue-500 hover:underline cursor-pointer">
                                {{ invoice.sale.branch.name }}
                            </Link>
                       </li>
                        <li class="flex justify-between">
                           <span class="font-semibold text-gray-600 dark:text-gray-400">RFC:</span>
                           <span>{{ invoice.sale.branch.rfc ?? 'N/A' }}</span>
                       </li>
                       <li class="flex justify-between">
                           <span class="font-semibold text-gray-600 dark:text-gray-400">Dirección:</span>
                           <span class="text-right">{{ invoice.sale.branch.address ?? 'N/A' }}</span>
                       </li>
                       <li v-if="invoice.sale.contact" class="flex justify-between">
                           <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                           <span>{{ invoice.sale.contact.name ?? 'N/A' }}</span>
                       </li>
                    </ul>
                </div>

                 <!-- === NUEVO === Card de Facturas Relacionadas -->
                <div v-if="invoice.sale.invoices?.length > 1" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Otras Facturas de la OV</h3>
                    <ul class="space-y-2">
                        <li v-for="relatedInvoice in invoice.sale.invoices" :key="relatedInvoice.id">
                            <Link :href="route('invoices.show', relatedInvoice.id)"
                                class="flex justify-between items-center p-2 rounded-md transition-colors duration-200"
                                :class="{
                                    'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-bold': relatedInvoice.id === invoice.id,
                                    'hover:bg-gray-100 dark:hover:bg-slate-700': relatedInvoice.id !== invoice.id
                                }">
                                <div class="text-sm">
                                    <p>{{ relatedInvoice.folio }}</p>
                                    <p class="text-xs text-gray-500">{{ relatedInvoice.installment_number }} de {{ relatedInvoice.total_installments }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold">{{ formatCurrency(relatedInvoice.amount) }}</p>
                                    <el-tag :type="getStatusTag(relatedInvoice.status)" size="small" disable-transitions>
                                        {{ relatedInvoice.status }}
                                    </el-tag>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Card de Archivos adjuntos -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Archivos Adjuntos</h3>
                    <div v-if="invoice.media?.length" class="grid grid-cols-2 gap-3 col-span-full mb-3">
                        <FileView v-for="file in invoice.media" :key="file.id" :file="file" />
                    </div>
                     <p v-else class="text-sm text-gray-500 text-center py-4">No hay archivos adjuntos.</p>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PAGOS -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px]">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Historial de Pagos</h3>
                    <div v-if="invoice.payments?.length" class="space-y-3 max-h-[65vh] overflow-auto">
                       <el-table :data="invoice.payments" style="width: 100%" stripe class="dark:!bg-slate-800/50 dark:!text-gray-300">
                           <el-table-column prop="payment_date" label="Fecha de Pago" width="180">
                               <template #default="scope">{{ formatDate(scope.row.payment_date) }}</template>
                           </el-table-column>
                           <el-table-column prop="amount" label="Monto">
                               <template #default="scope">{{ formatCurrency(scope.row.amount) }}</template>
                           </el-table-column>
                            <el-table-column prop="payment_method" label="Método de Pago" />
                           <el-table-column prop="notes" label="Notas" />
                       </el-table>
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-file-invoice-dollar text-3xl mb-3"></i>
                        <p>No se han registrado pagos para esta factura.</p>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import { Link } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'InvoiceShow',
    components: {
        AppLayout,
        Back,
        FileView,
        Link,
    },
    props: {
        invoice: Object,
    },
    computed: {
        paidAmount() {
            if (!this.invoice.payments || this.invoice.payments.length === 0) {
                return 0;
            }
            return this.invoice.payments.reduce((total, payment) => total + parseFloat(payment.amount), 0);
        },
        pendingAmount() {
            return parseFloat(this.invoice.amount) - this.paidAmount;
        }
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            date.setDate(date.getDate() + 1); // Ajuste de zona horaria
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    },
};
</script>

