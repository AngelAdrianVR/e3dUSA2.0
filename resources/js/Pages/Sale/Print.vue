<template>
    <Head :title="'OV-' + sale.id.toString().padStart(4, '0')" />
    <div class="bg-gray-100 dark:bg-gray-800 min-h-screen font-sans">
        <!-- Controles de la página (se ocultan al imprimir) -->
        <div class="p-4 bg-white dark:bg-slate-900 shadow-md print:hidden flex justify-between items-center">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Imprimir Orden de Venta</h1>
            <div class="flex items-center gap-4">
                 <a :href="route('sales.show', sale.id)" class="text-sm text-blue-600 hover:underline">
                    &larr; Volver a la Orden de Venta
                </a>
                <button @click="printPage" class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                    Imprimir
                </button>
            </div>
        </div>

        <!-- Contenido de la página para imprimir -->
        <main class="max-w-4xl mx-auto p-4 bg-white dark:bg-slate-900 my-8 shadow-lg print:shadow-none print:my-0 print:p-3">
            <!-- Encabezado -->
            <header class="flex justify-between items-start pb-5 border-b border-gray-200 dark:border-slate-700">
                <div class="text-gray-800 dark:text-gray-200">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ sale.type === 'venta' ? 'Orden de Venta' : 'Orden de stock'}}</h1>
                    <p v-if="sale.type === 'venta'" class="text-lg font-semibold text-blue-600">OV-{{ sale.id.toString().padStart(4, '0') }}</p>
                    <p v-else class="text-lg font-semibold text-blue-600">OS-{{ sale.id.toString().padStart(4, '0') }}</p>
                    <p v-if="sale.quote_id" class="text-sm">Cotización Relacionada: COT-{{ sale.quote_id.toString().padStart(4, '0') }}</p>
                </div>
                <div class="text-right">
                    <!-- Puedes poner tu logo aquí -->
                    <ApplicationLogo />
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Fecha de Emisión: {{ formatDate(sale.created_at) }}</p>
                </div>
            </header>

            <!-- Información General -->
            <section class="grid grid-cols-3 gap-8 my-3">
                <div v-if="sale.type === 'venta'" class="col-span-2">
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
             <section v-if="sale.type === 'venta'" class="bg-gray-50 dark:bg-slate-800 p-3 rounded-lg grid grid-cols-2 gap-4 text-sm mb-2">
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
                                        @error="handleImageError"
                                        alt="Imagen de producto" 
                                        class="size-24 object-cover rounded-md inline-block">
                                </td>
                                <td class="p-3 font-mono text-sm text-gray-600 dark:text-gray-300">{{ item.product.code }}</td>
                                <td class="p-3 font-semibold text-gray-800 dark:text-gray-200">{{ item.product.name }}</td>
                                <td class="p-3 text-center text-gray-700 dark:text-gray-300">{{ item.quantity.toLocaleString() }} {{ item.product.measure_unit }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

             <!-- Envíos / Parcialidades Súper Compacto -->
            <section
                class="mt-6 border-t-2 pt-4 dark:border-slate-700"
                v-if="sale.shipments && sale.shipments.length > 1 && sale.type === 'venta'"
            >
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-3 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18" />
                    </svg>
                    Plan de Envíos
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 print:grid-cols-2 gap-3">
                    <div
                        v-for="(shipment, index) in sale.shipments"
                        :key="shipment.id"
                        class="bg-white dark:bg-slate-800/50 rounded-lg p-2.5 border shipment-item transition-all"
                        :class="shipment.status === 'Enviado' ? 'border-green-400 dark:border-green-600' : 'border-gray-200 dark:border-slate-700'"
                    >
                        <!-- Encabezado de la parcialidad -->
                        <div class="flex justify-between items-center mb-2 pb-1.5 border-b dark:border-slate-600">
                            <h3 class="font-bold text-gray-800 dark:text-gray-100 text-sm flex items-center gap-2">
                                Parcialidad {{ index + 1 }}
                                <span v-if="shipment.status === 'Enviado'" class="px-1.5 py-0.5 bg-green-100 text-green-800 text-[9px] font-bold rounded uppercase border border-green-300 dark:bg-green-800 dark:text-green-100 dark:border-green-700">Enviado</span>
                                <span v-else class="px-1.5 py-0.5 bg-yellow-100 text-yellow-800 text-[9px] font-bold rounded uppercase border border-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:border-yellow-700">{{ shipment.status || 'Pendiente' }}</span>
                            </h3>
                        </div>

                        <!-- Banner Compacto de Enviado -->
                        <div v-if="shipment.status === 'Enviado'" class="bg-green-50 dark:bg-green-900/30 rounded p-1.5 mb-2 flex items-center gap-1.5 border border-green-200 dark:border-green-800">
                            <svg class="w-3.5 h-3.5 text-green-600 dark:text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <p class="text-[10px] text-green-700 dark:text-green-400 leading-tight">
                                Por: <strong>{{ shipment.sent_by || 'N/A' }}</strong> el {{ formatDateTime(shipment.sent_at) }}
                            </p>
                        </div>

                        <!-- Info Grid con Iconos Súper Compacto (Wrap) -->
                        <div class="flex flex-wrap gap-x-3 gap-y-1.5 mb-2 bg-gray-50 dark:bg-slate-700/30 p-1.5 rounded border dark:border-slate-600 text-[10px]">
                            <div class="flex items-center gap-1 text-gray-700 dark:text-gray-300">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="font-semibold text-gray-500 dark:text-gray-400">Fecha:</span> {{ formatDate(shipment.promise_date) }}
                            </div>
                            <div class="flex items-center gap-1 text-gray-700 dark:text-gray-300">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                                <span class="font-semibold text-gray-500 dark:text-gray-400">Paquetería:</span> {{ shipment.shipping_company || "N/A" }}
                            </div>
                            <div class="flex items-center gap-1 text-gray-700 dark:text-gray-300">
                                <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                <span class="font-semibold text-gray-500 dark:text-gray-400">Guía:</span> <span class="font-mono font-bold text-blue-600 dark:text-blue-400">{{ shipment.tracking_guide || "N/A" }}</span>
                            </div>
                        </div>

                        <!-- Productos de la Parcialidad tipo Tags Compactos -->
                        <div v-if="sale.sale_products">
                            <p class="font-medium text-gray-500 dark:text-gray-400 mb-1.5 text-[9px] uppercase tracking-wider flex items-center gap-1">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                Productos
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <div
                                    v-for="shipmentProduct in shipment.shipment_products"
                                    :key="shipmentProduct.id"
                                    class="flex items-center gap-1 bg-white dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600 rounded px-1.5 py-0.5 text-[10px]"
                                >
                                    <span class="bg-indigo-100 text-indigo-800 dark:bg-indigo-900/80 dark:text-indigo-200 px-1 py-0.5 rounded text-[9px] font-bold leading-none">
                                        x{{ shipmentProduct.quantity.toLocaleString() }}
                                    </span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200 truncate max-w-[120px]" :title="sale.sale_products.find((sp) => sp.id === shipmentProduct.sale_product_id)?.product?.name">
                                        {{ sale.sale_products.find((sp) => sp.id === shipmentProduct.sale_product_id)?.product?.name }}
                                    </span>
                                    <span class="text-gray-400 font-mono text-[9px]">
                                        ({{ sale.sale_products.find((sp) => sp.id === shipmentProduct.sale_product_id)?.product?.code }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Notas y Footer -->
            <footer class="mt-7 pt-4 border-t border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-gray-400">
                <div v-if="sale.notes">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Notas Adicionales</h3>
                    <div class="prose prose-sm" v-html="sale.notes"></div>
                </div>
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
        handleImageError(event) {
            const img = event.target;
            const currentSrc = img.src;
            const prodDomain = 'https://www.intranetemblems3d.dtw.com.mx';
            
            if (img.dataset.fallbackAttempted || currentSrc.includes(prodDomain)) return;
            img.dataset.fallbackAttempted = "true";

            try {
                const urlObj = new URL(currentSrc);
                img.src = prodDomain + urlObj.pathname;
            } catch (e) {
                img.src = currentSrc.replace(/^https?:\/\/[^\/]+/, prodDomain);
            }
        },
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
        formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', { 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit', 
                hour12: true 
            }).replace('.', '');
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
    
    .shipment-item {
        break-inside: avoid-page;
        page-break-inside: avoid;
    }
}
</style>