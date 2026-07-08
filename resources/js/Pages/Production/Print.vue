<template>
    <Head :title="'OP-' + sale.id.toString().padStart(4, '0')" />
    <div class="min-h-screen font-sans">
        <!-- Controles de la página (se ocultan al imprimir) -->
        <div class="p-4 bg-white dark:bg-slate-900 shadow-md print:hidden flex justify-between items-center">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Imprimir Orden de Producción</h1>
            <div class="flex items-center gap-4">
                 <a :href="route('productions.show', sale.id)" class="text-sm text-blue-600 hover:underline">
                    &larr; Volver a Producción
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
        <main class="max-w-3xl mx-auto p-5 bg-white dark:bg-slate-900 my-8 shadow-lg print:shadow-none print:my-0 print:p-3">
            <!-- Encabezado -->
            <header class="flex justify-between items-start pb-3 border-b border-gray-200 dark:border-slate-700">
                <div class="text-gray-800 dark:text-gray-200">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Orden de Producción</h1>
                    <p class="text-md font-semibold text-blue-600">
                        {{ sale.type === 'venta' ? 'OV-' : 'OS-' }}{{ sale.id.toString().padStart(4, '0') }}
                    </p>
                </div>
                <div class="text-right flex-shrink-0 ml-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Emisión: {{ formatDate(sale.created_at) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Prioridad:
                        <span :class="sale.is_high_priority ? 'text-red-500 font-bold' : ''">{{ sale.is_high_priority ? 'Alta' : 'Normal' }}</span>
                    </p>
                    <p class="text-xs font-bold text-gray-700 dark:text-gray-400">Cliente:
                        <span>{{ sale.branch.name }}</span>
                    </p>
                    <p class="text-xs font-bold text-gray-700 dark:text-gray-400">Razón Social:
                        <span>{{ sale.branch.parent?.name || sale.branch.name }}</span>
                    </p>
                </div>
            </header>

            <!-- Lista de Producciones -->
            <section class="mt-3 space-y-3">
                 <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">Productos a Fabricar</h2>
                <div v-for="(production, index) in sale.productions" :key="production.id"
                     class="p-4 rounded-lg border dark:border-slate-700 production-item"
                     :class="{ 'page-break-after': (index + 1) % 2 === 0 && (index + 1) < sale.productions.length }">
                    <!-- Detalles del Producto -->
                    <div class="flex flex-col md:flex-row print:flex-row gap-4 relative">
                        <div class="flex-shrink-0">
                             <img :src="production.sale_product.product.media[0]?.original_url || 'https://placehold.co/128x128/e2e8f0/e2e8f0?text=N/A'"
                                @error="handleImageError"
                                alt="Imagen de producto"
                                class="size-40 object-contain rounded-lg border border-gray-200 dark:border-slate-700">
                        </div>
                        
                        <!-- Precio del producto discreto -->
                        <div class="absolute bottom-0">
                            <p class="text-md font-bold text-gray-800 dark:text-gray-100 italic">AP-{{ production.sale_product.price?.replace('.00', '-00') || '0-00' }}</p>
                        </div>

                        <div class="flex-grow">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white">{{ production.sale_product.product.name }}</h3>
                            <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ production.sale_product.product.code }}</p>

                            <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="font-semibold text-gray-600 dark:text-gray-300 text-xs">Cantidad total de la venta:</p>
                                    <p class="text-md font-bold text-gray-800 dark:text-gray-100">{{ production.sale_product.quantity }} {{ production.sale_product.product.measure_unit }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-600 dark:text-gray-300 text-xs">Cantidad tomada de stock:</p>
                                    <p class="text-md font-bold text-gray-800 dark:text-gray-100">{{ production.sale_product.quantity - production.sale_product.quantity_to_produce }} {{ production.sale_product.product.measure_unit }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-600 dark:text-gray-300 text-xs">Cantidad a Producir:</p>
                                    <p class="text-md font-bold text-gray-800 dark:text-gray-100">{{ production.quantity_to_produce }} {{ production.sale_product.product.measure_unit }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-600 dark:text-gray-300 text-xs">Tiempo Estimado:</p>
                                    <p class="text-md font-bold text-gray-800 dark:text-gray-100">{{ formatMinutes(production.total_estimated_time_minutes) }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-600 dark:text-gray-300 text-xs">Operadores Asignados:</p>
                                    <p class="text-sm text-gray-800 dark:text-gray-100">{{ getOperatorsForProduction(production) }}</p>
                                </div>
                                <div>
                                        <p class="font-semibold text-gray-600 dark:text-gray-300 text-xs">Solicitado por:</p>
                                    <p class="text-sm text-gray-800 dark:text-gray-100">{{ sale.user.name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Tareas de Producción -->
                    <div class="mt-4 border-t pt-4">
                        <h4 class="font-semibold text-gray-700 dark:text-gray-200 mb-2 text-sm">Tareas a Realizar</h4>
                        <ul class="space-y-2">
                            <li v-for="task in production.tasks" :key="task.id" class="flex justify-between items-center bg-gray-50 dark:bg-slate-900 p-2 rounded-md">
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">{{ task.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Asignado a: {{ task.operator?.name || 'No asignado' }}</p>
                                </div>
                                <div class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    {{ formatMinutes(task.estimated_time_minutes) }}
                                </div>
                            </li>
                        </ul>
                    </div>
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

            <!-- Notas -->
            <footer v-if="sale.notes" class="mt-7 pt-4 border-t border-gray-200 text-sm text-gray-600 dark:text-gray-400">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Notas Generales de la Orden</h3>
                <div class="prose prose-sm" v-html="sale.notes"></div>
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
            const date = new Date(dateString);
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
        formatMinutes(minutes) {
            if (minutes === null || minutes === undefined) return '0m';
            if (minutes < 60) {
                return `${minutes}m`;
            }
            const hours = Math.floor(minutes / 60);
            const remainingMinutes = minutes % 60;
            return `${hours}h ${remainingMinutes}m`;
        },
        getOperatorsForProduction(production) {
            if (!production.tasks || production.tasks.length === 0) {
                return 'Ninguno';
            }
            // Usamos un Set para obtener operadores únicos por nombre
            const operatorNames = new Set(
                production.tasks
                    .map(task => task.operator?.name)
                    .filter(name => name) // Filtramos nombres nulos o undefined
            );

            return [...operatorNames].join(', ') || 'No asignado';
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
    .print\:p-3 {
        padding: 0.75rem;
    }
    .print\:flex-row {
        flex-direction: row;
    }

    /* --- NUEVAS REGLAS PARA IMPRESIÓN --- */
    .production-item {
        break-inside: avoid-page; /* Estándar moderno para evitar cortes */
        page-break-inside: avoid; /* Propiedad antigua para mayor compatibilidad */
    }

    .shipment-item {
        break-inside: avoid-page; /* Evitar que las tarjetas de envío se corten a la mitad de hoja */
        page-break-inside: avoid;
    }

    .page-break-after {
        break-after: page; /* Estándar moderno para forzar salto de página */
        page-break-after: always; /* Propiedad antigua para mayor compatibilidad */
    }
}
</style>