<template>
    <Head :title="`OV-${sale.id}`" />
    <div class="bg-gray-100 dark:bg-gray-800 min-h-screen font-sans">
        <!-- Controles de la página (se ocultan al imprimir) -->
        <div class="p-4 bg-white dark:bg-slate-900 shadow-md print:hidden flex justify-between items-center">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Imprimir Orden de Venta</h1>
            <div class="flex items-center gap-4">
                 <a :href="route('sales.show', sale.id)" class="text-sm text-blue-600 hover:underline">
                    &larr; Volver a la Orden de Venta
                </a>
                <button @click="printPage" class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0c1.291-.646 2.122-1.139 2.122-1.631V8.418c0-.492-.83-1.135-2.122-1.631M6.34 18c-1.291-.646-2.122-1.139-2.122-1.631V8.418c0-.492.83-1.135 2.122-1.631M12 12h.008v.008H12V12Z" />
                    </svg>
                    Imprimir
                </button>
            </div>
        </div>

        <!-- Contenido de la página para imprimir -->
        <main class="max-w-4xl mx-auto p-8 bg-white dark:bg-slate-900 my-8 shadow-lg print:shadow-none print:my-0 print:p-3">
            <!-- Encabezado -->
            <header class="flex justify-between items-start pb-6 border-b border-gray-200 dark:border-slate-700">
                <div class="text-gray-800 dark:text-gray-200">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Orden de Venta</h1>
                    <p class="text-lg font-semibold text-blue-600">OV-{{ sale.id.toString().padStart(4, '0') }}</p>
                    <p v-if="sale.quote_id" class="text-sm">Cotización Relacionada: COT-{{ sale.quote_id.toString().padStart(4, '0') }}</p>
                </div>
                <div class="text-right">
                    <!-- Puedes poner tu logo aquí -->
                    <ApplicationLogo />
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Fecha de Emisión: {{ formatDate(sale.created_at) }}</p>
                </div>
            </header>

            <!-- Información General -->
            <section class="grid grid-cols-3 gap-8 my-6">
                <div class="col-span-2">
                    <h2 class="text-sm font-semibold uppercase text-gray-500 dark:text-gray-400 mb-2">CLIENTE</h2>
                    <p class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ sale.branch.name }}</p>
                </div>
                <div class="text-right">
                    <h2 class="text-sm font-semibold uppercase text-gray-500 dark:text-gray-400 mb-2">VENDEDOR</h2>
                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ sale.user.name }}</p>
                    <p class="text-gray-600 dark:text-gray-300">{{ sale.user.email }}</p>
                </div>
            </section>

            <!-- Detalles de la Orden -->
             <section class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg grid grid-cols-2 gap-4 text-sm mb-2">
                <div>
                    <span class="font-semibold text-gray-600 dark:text-gray-400">OCE:</span>
                    <span class="ml-2 text-gray-800 dark:text-gray-200">{{ sale.oce_name || 'N/A' }}</span>
                </div>
                <div>
                    <span class="font-semibold text-gray-600 dark:text-gray-400">Medio de Petición:</span>
                    <span class="ml-2 text-gray-800 dark:text-gray-200">{{ sale.order_via }}</span>
                </div>
                <div>
                    <span class="font-semibold text-gray-600 dark:text-gray-400">Opción de Flete:</span>
                    <span class="ml-2 text-gray-800 dark:text-gray-200">{{ sale.freight_option }}</span>
                </div>
                 <div>
                    <span class="font-semibold text-gray-600 dark:text-gray-400">Prioridad:</span>
                    <span :class="sale.is_high_priority ? 'text-red-500 font-bold' : 'text-gray-800 dark:text-gray-200'" class="ml-2">{{ sale.is_high_priority ? 'Alta' : 'Normal' }}</span>
                </div>
            </section>

            <!-- Tabla de Productos -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-1">Productos</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-100 dark:bg-slate-800 text-sm uppercase text-gray-600 dark:text-gray-400">
                            <tr>
                                <th class="p-3 text-center">Imagen</th>
                                <th class="p-3">Código</th>
                                <th class="p-3">Descripción</th>
                                <th class="p-3 text-center">Cant.</th>
                                <!-- <th class="p-3 text-right">P. Unitario</th>
                                <th class="p-3 text-right">Total</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in sale.sale_products" :key="item.id" class="border-b dark:border-slate-700">
                                <td class="p-3 text-center">
                                    <img :src="item.product.media[0]?.original_url || 'https://placehold.co/80x80/e2e8f0/e2e8f0?text=N/A'" 
                                         alt="Imagen de producto" 
                                         class="size-28 object-cover rounded-md inline-block">
                                </td>
                                <td class="p-3 font-mono text-sm text-gray-600 dark:text-gray-300">{{ item.product.code }}</td>
                                <td class="p-3 font-semibold text-gray-800 dark:text-gray-200">{{ item.product.name }}</td>
                                <td class="p-3 text-center text-gray-700 dark:text-gray-300">{{ item.quantity }} {{ item.product.measure_unit }}</td>
                                <!-- <td class="p-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.price) }}</td>
                                <td class="p-3 text-right font-semibold text-gray-800 dark:text-gray-200">{{ formatCurrency(item.quantity * item.price) }}</td> -->
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Totales -->
            <!-- <section class="flex justify-end mt-6">
                <div class="w-full max-w-xs text-gray-700 dark:text-gray-300">
                    <div class="flex justify-between py-2">
                        <span>Subtotal:</span>
                        <span class="font-semibold dark:text-gray-200">{{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span>Costo de Flete:</span>
                        <span class="font-semibold dark:text-gray-200">{{ formatCurrency(sale.freight_cost) }}</span>
                    </div>
                    <div class="flex justify-between py-3 border-t-2 border-gray-200 dark:border-slate-700 mt-2">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">Total:</span>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(sale.total_amount) }}</span>
                    </div>
                </div>
            </section> -->

             <!-- Envíos / Parcialidades -->
            <section class="mt-4" v-if="sale.shipments && sale.shipments.length">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Plan de Envíos</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="(shipment, index) in sale.shipments" :key="shipment.id" class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border dark:border-slate-700">
                        <h3 class="font-bold text-md mb-2 text-gray-800 dark:text-gray-200">Parcialidad {{ index + 1 }}</h3>
                        <p class="text-sm"><span class="font-semibold text-gray-600 dark:text-gray-400">Fecha Promesa:</span> <span class="dark:text-gray-300">{{ formatDate(shipment.promise_date) }}</span></p>
                        <p class="text-sm"><span class="font-semibold text-gray-600 dark:text-gray-400">Paquetería:</span> <span class="dark:text-gray-300">{{ shipment.shipping_company || 'No especificada' }}</span></p>
                        <p class="text-sm"><span class="font-semibold text-gray-600 dark:text-gray-400">Guía:</span> <span class="dark:text-gray-300">{{ shipment.tracking_guide || 'N/A' }}</span></p>
                    </div>
                </div>
            </section>

            <!-- Notas y Footer -->
            <footer class="mt-7 pt-4 border-t border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-gray-400">
                <div v-if="sale.notes">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Notas Adicionales</h3>
                    <p>{{ sale.notes }}</p>
                </div>
                <!-- <div class="mt-6 text-center text-xs text-gray-400">
                    <p>Gracias por su compra.</p>
                    <p>Nombre de tu Empresa | Dirección de tu Empresa | Teléfono</p>
                </div> -->
            </footer>
        </main>
    </div>
</template>

<script>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    components: {
        ApplicationLogo,
        Head,
    },
    props: {
        sale: Object,
    },
    computed: {
        subtotal() {
            return this.sale.sale_products.reduce((acc, item) => {
                return acc + (item.quantity * item.price);
            }, 0);
        }
    },
    methods: {
        printPage() {
            window.print();
        },
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            // Sumar un día porque a veces el timezone lo interpreta un día antes
            const date = new Date(dateString);
            date.setDate(date.getDate() + 1);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            const num = Number(value);
            return num.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        }
    }
}
</script>

<style>
@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print\:hidden {
        display: none;
    }
    .print\:shadow-none {
        box-shadow: none;
    }
    .print\:my-0 {
        margin-top: 0;
        margin-bottom: 0;
    }
    .print\:p-0 {
        padding: 0;
    }
}
</style>
