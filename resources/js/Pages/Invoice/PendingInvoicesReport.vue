<template>
    <Head title="Reporte de facturas pendientes" />
    <div class="bg-gray-100 min-h-screen p-4 sm:p-8">
        <main class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-6 sm:p-10">
            <!-- Header -->
            <header class="flex justify-between items-start mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Reporte de Facturas Pendientes</h1>
                    <p class="text-gray-500">
                        Periodo del {{ formatDate(report_dates.start) }} al {{ formatDate(report_dates.end) }}
                    </p>
                </div>
                <button @click="printReport" class="flex items-center space-x-2 bg-blue-500 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-600 transition-colors duration-200 print:hidden">
                    <i class="fa-solid fa-print"></i>
                    <span>Imprimir</span>
                </button>
            </header>

            <!-- Summary -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 text-center">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h2 class="text-sm font-semibold text-blue-600">Órdenes de Venta</h2>
                    <p class="text-2xl font-bold text-blue-800">{{ sales.length }}</p>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h2 class="text-sm font-semibold text-yellow-600">Total Facturado</h2>
                    <p class="text-2xl font-bold text-yellow-800">${{ formatCurrency(summary.totalBilled) }}</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <h2 class="text-sm font-semibold text-green-600">Total Pagado</h2>
                    <p class="text-2xl font-bold text-green-800">${{ formatCurrency(summary.totalPaid) }}</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <h2 class="text-sm font-semibold text-red-600">Total Pendiente</h2>
                    <p class="text-2xl font-bold text-red-800">${{ formatCurrency(summary.totalPending) }}</p>
                </div>
            </section>

            <!-- Sales Orders List -->
            <section class="space-y-6">
                <div v-if="sales.length > 0" v-for="sale in sales" :key="sale.id" class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 p-4 border-b border-gray-200">
                        <h3 class="font-bold text-lg text-gray-700">OV-{{ sale.id.toString().padStart(4, '0') }}</h3>
                        <p class="text-sm text-gray-500">{{ sale.branch.name }}</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Folio Factura</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Vencimiento</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-600">Monto Total</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-600">Monto Pagado</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-600">Monto Pendiente</th>
                                    <th class="px-4 py-2 text-center font-semibold text-gray-600">Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="invoice in sale.invoices" :key="invoice.id" 
                                    :class="isUnpaid(invoice.status) ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'">
                                    <td class="px-4 py-2 border-t">{{ invoice.folio }}</td>
                                    <td class="px-4 py-2 border-t">{{ formatDate(invoice.due_date) }}</td>
                                    <td class="px-4 py-2 border-t text-right">${{ formatCurrency(invoice.amount) }}</td>
                                    <td class="px-4 py-2 border-t text-right text-green-700">${{ formatCurrency(getPaidAmount(invoice)) }}</td>
                                    <td class="px-4 py-2 border-t text-right font-bold text-red-700">${{ formatCurrency(getPendingAmount(invoice)) }}</td>
                                    <td class="px-4 py-2 border-t text-center">
                                         <span class="px-2 py-1 text-xs font-semibold rounded-full" :class="getStatusClass(invoice.status)">
                                            {{ invoice.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else class="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg">
                    <i class="fa-solid fa-folder-open text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500 font-semibold">No se encontraron facturas pendientes en el periodo seleccionado.</p>
                </div>
            </section>
        </main>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { computed } from 'vue';

export default {
    components: {
        Head,
    },
    props: {
        sales: Array,
        report_dates: Object,
    },
    setup(props) {
        const summary = computed(() => {
            let totalBilled = 0;
            let totalPaid = 0;

            props.sales.forEach(sale => {
                sale.invoices.forEach(invoice => {
                    const invoiceAmount = parseFloat(invoice.amount);
                    if (invoice.status !== 'Cancelada') {
                        totalBilled += invoiceAmount;
                        totalPaid += getPaidAmount(invoice);
                    }
                });
            });
            const totalPending = totalBilled - totalPaid;

            return { totalBilled, totalPaid, totalPending };
        });

        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            date.setDate(date.getDate() + 1); // Ajuste de zona horaria
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        };

        const formatCurrency = (value) => {
            if (value === null || value === undefined) return '0.00';
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const getPaidAmount = (invoice) => {
            if (!invoice.payments || invoice.payments.length === 0) return 0;
            return invoice.payments.reduce((total, payment) => total + parseFloat(payment.amount), 0);
        };

        const getPendingAmount = (invoice) => {
            const totalAmount = parseFloat(invoice.amount);
            const paidAmount = getPaidAmount(invoice);
            const pending = totalAmount - paidAmount;
            return Math.max(0, pending);
        };

        const isUnpaid = (status) => {
            return ['Pendiente', 'Parcialmente pagada', 'Vencida'].includes(status);
        };
        
        const getStatusClass = (status) => {
            switch (status) {
                case 'Pagada': return 'bg-green-100 text-green-800';
                case 'Pendiente': return 'bg-yellow-100 text-yellow-800';
                case 'Parcialmente pagada': return 'bg-yellow-100 text-yellow-800';
                case 'Vencida': return 'bg-red-100 text-red-800';
                case 'Cancelada': return 'bg-gray-100 text-gray-800';
                default: return 'bg-blue-100 text-blue-800';
            }
        };

        const printReport = () => {
            window.print();
        };

        return {
            summary,
            formatDate,
            formatCurrency,
            getPaidAmount,
            getPendingAmount,
            isUnpaid,
            getStatusClass,
            printReport,
        };
    },
};
</script>
