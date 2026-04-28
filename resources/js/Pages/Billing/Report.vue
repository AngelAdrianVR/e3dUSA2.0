<template>
    <Head :title="pageTitle" />
    <div class="min-h-screen bg-gray-100 py-8 px-4 sm:px-8 print:bg-white print:py-0 print:px-0">
        <!-- Contenedor Principal -->
        <main class="max-w-6xl mx-auto bg-white rounded-xl shadow-2xl p-10 sm:p-14 print:shadow-none print:p-0 print:max-w-full">
            
            <!-- Acciones (Ocultas al imprimir) -->
            <div class="flex justify-end mb-6 print:hidden">
                <button @click="printReport" class="flex items-center space-x-2 bg-slate-800 text-white px-5 py-2.5 rounded-lg shadow hover:bg-slate-700 transition-colors duration-200">
                    <i class="fa-solid fa-print"></i>
                    <span class="font-medium text-sm">Imprimir / PDF</span>
                </button>
            </div>

            <!-- Encabezado del Reporte -->
            <header class="border-b-2 border-slate-800 pb-6 mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end">
                <div>
                    <!-- Espacio para Logo o Nombre de Empresa -->
                    <ApplicationLogo />
                    <p class="text-sm text-slate-500 font-medium">Reporte de Facturación (OVs)</p>
                </div>
                <div class="text-left sm:text-right mt-4 sm:mt-0">
                    <h1 class="text-xl font-bold text-slate-800">{{ pageTitle }}</h1>
                    <p class="text-sm text-slate-600 mt-1">
                        <span class="font-semibold">Periodo:</span> 
                        {{ report_dates.start ? formatDate(report_dates.start) : 'Inicio' }} - 
                        {{ report_dates.end ? formatDate(report_dates.end) : 'Actualidad' }}
                    </p>
                    <p class="text-xs text-slate-400 mt-1">
                        Generado el: {{ currentDate() }}
                    </p>
                </div>
            </header>

            <!-- Resumen Financiero -->
            <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10 print:mb-8">
                <div class="border border-slate-200 p-6 rounded-lg bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total de Órdenes</h2>
                        <p class="text-3xl font-black text-slate-800">{{ sales.length }}</p>
                    </div>
                    <div class="text-slate-300">
                        <i class="fa-solid fa-file-invoice text-4xl"></i>
                    </div>
                </div>
                <div class="border border-slate-200 p-6 rounded-lg bg-indigo-50/30 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold text-indigo-600 uppercase tracking-wider mb-1">Monto Total del Reporte</h2>
                        <p class="text-3xl font-black text-indigo-700">${{ formatCurrency(summary.totalAmount) }}</p>
                    </div>
                    <div class="text-indigo-200">
                        <i class="fa-solid fa-sack-dollar text-4xl"></i>
                    </div>
                </div>
            </section>

            <!-- Lista de Órdenes de Venta -->
            <section class="space-y-8">
                <div v-if="sales.length > 0" class="avoid-page-break mb-8">
                    
                    <!-- Tabla de OVs -->
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="py-3 px-4 border-b border-slate-200 font-bold text-slate-600 uppercase text-xs tracking-wider">OV</th>
                                    <th class="py-3 px-4 border-b border-slate-200 font-bold text-slate-600 uppercase text-xs tracking-wider">Fecha</th>
                                    <th class="py-3 px-4 border-b border-slate-200 font-bold text-slate-600 uppercase text-xs tracking-wider">Cliente / Sucursal</th>
                                    <th class="py-3 px-4 border-b border-slate-200 font-bold text-slate-600 uppercase text-xs tracking-wider">Pre-factura</th>
                                    <th class="py-3 px-4 border-b border-slate-200 font-bold text-slate-600 uppercase text-xs tracking-wider">Timbrado</th>
                                    <th class="py-3 px-4 border-b border-slate-200 font-bold text-slate-600 uppercase text-xs tracking-wider text-right">Monto Total</th>
                                    <th class="py-3 px-4 border-b border-slate-200 font-bold text-slate-600 uppercase text-xs tracking-wider text-center">Estado Fact.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="sale in sales" :key="sale.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-3 px-4 text-slate-800 font-bold">OV-{{ sale.id }}</td>
                                    <td class="py-3 px-4 text-slate-600 text-xs">{{ formatDate(sale.created_at) }}</td>
                                    <td class="py-3 px-4">
                                        <p class="text-slate-800 font-medium uppercase text-xs">{{ sale.branch?.parent?.name || sale.branch?.name || 'N/A' }}</p>
                                        <p class="text-slate-500 text-xs mt-0.5">{{ sale.branch?.name }}</p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span v-if="sale.pre_invoice_folio" class="font-mono text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded">{{ sale.pre_invoice_folio }}</span>
                                        <span v-else class="text-slate-400 text-xs italic">-</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span v-if="sale.stamped_invoice_folio" class="font-mono text-xs text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-1 rounded">{{ sale.stamped_invoice_folio }}</span>
                                        <span v-else class="text-slate-400 text-xs italic">-</span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-800 font-semibold text-right">
                                        ${{ formatCurrency(sale.total_amount) }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                         <span class="inline-flex items-center justify-center px-2 py-1 text-[10px] font-bold uppercase tracking-wide rounded-full border border-opacity-20 whitespace-nowrap" :class="getStatusClass(sale.billing_status)">
                                            {{ sale.billing_status || 'Pendiente' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Estado Vacío -->
                <div v-else class="text-center py-20 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                    <i class="fa-solid fa-file-invoice text-5xl text-slate-300 mb-4"></i>
                    <h3 class="text-lg font-bold text-slate-700">Sin registros</h3>
                    <p class="text-slate-500 mt-1">No se encontraron órdenes de venta que coincidan con los criterios del reporte.</p>
                </div>
            </section>
            
            <!-- Pie de página (Solo visible al imprimir) -->
            <footer class="hidden print:block mt-12 pt-6 border-t border-slate-200 text-center text-xs text-slate-400 font-medium">
                Documento generado automáticamente por el Módulo de Cobranza. <br>
                Página 1 de 1
            </footer>
        </main>
    </div>
</template>

<script>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { computed } from 'vue';

export default {
    components: {
        Head,
        ApplicationLogo
    },
    props: {
        sales: {
            type: Array,
            default: () => []
        },
        report_dates: {
            type: Object,
            default: () => ({ start: null, end: null })
        },
        report_type: {
            type: String,
            default: 'todas'
        }
    },
    setup(props) {
        // Título dinámico dependiendo del filtro
        const pageTitle = computed(() => {
            const titles = {
                'sin_prefactura': 'Órdenes sin Pre-factura',
                'prefacturadas': 'Órdenes Pre-facturadas',
                'timbradas': 'Órdenes Timbradas',
                'todas': 'Todas las Órdenes de Venta'
            };
            return titles[props.report_type] || 'Reporte de Facturación';
        });

        // Sumatoria segura previniendo errores de undefined
        const summary = computed(() => {
            let totalAmount = 0;
            const safeSales = props.sales || [];
            
            safeSales.forEach(sale => {
                totalAmount += parseFloat(sale.total_amount || 0);
            });

            return { totalAmount };
        });

        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "dd MMM, yyyy", { locale: es }).toUpperCase();
        };

        const currentDate = () => {
            return format(new Date(), "dd 'de' MMMM, yyyy - HH:mm", { locale: es });
        };

        const formatCurrency = (value) => {
            if (value === null || value === undefined || isNaN(value)) return '0.00';
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };
        
        const getStatusClass = (status) => {
            switch (status) {
                case 'Timbrada': return 'bg-emerald-50 text-emerald-700 border-emerald-700';
                case 'Pre-facturada': return 'bg-indigo-50 text-indigo-700 border-indigo-700';
                case 'Pendiente Timbrado': return 'bg-rose-50 text-rose-700 border-rose-700';
                case 'Pendiente Pre-factura': return 'bg-amber-50 text-amber-700 border-amber-700';
                default: return 'bg-slate-50 text-slate-700 border-slate-700';
            }
        };

        const printReport = () => {
            window.print();
        };

        return {
            pageTitle,
            summary,
            formatDate,
            currentDate,
            formatCurrency,
            getStatusClass,
            printReport,
        };
    },
};
</script>

<style scoped>
/* Optimizaciones exclusivas para la impresión del PDF */
@media print {
    @page {
        margin: 1.5cm;
        size: A4 portrait;
    }
    
    body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        background-color: white !important;
    }

    /* Prevenir que las tablas se corten a la mitad en el salto de página */
    .avoid-page-break {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
</style>