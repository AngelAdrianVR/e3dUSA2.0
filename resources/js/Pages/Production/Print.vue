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
                </div>
            </header>

            <!-- Lista de Producciones -->
            <section class="mt-3 space-y-3">
                 <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">Productos a Fabricar</h2>
                <!-- MODIFICACIÓN: Se añade el índice al v-for y clases para controlar los saltos de página -->
                <div v-for="(production, index) in sale.productions" :key="production.id"
                     class="p-4 rounded-lg border dark:border-slate-700 production-item"
                     :class="{ 'page-break-after': (index + 1) % 2 === 0 && (index + 1) < sale.productions.length }">
                    <!-- Detalles del Producto -->
                    <div class="flex flex-col md:flex-row print:flex-row gap-4 relative">
                        <div class="flex-shrink-0">
                             <img :src="production.sale_product.product.media[0]?.original_url || 'https://placehold.co/128x128/e2e8f0/e2e8f0?text=N/A'"
                                 alt="Imagen de producto"
                                 class="size-40 object-contain rounded-lg border border-gray-200 dark:border-slate-700">
                        </div>
                        
                        <!-- Precio del producto discreto -->
                        <div class="absolute bottom-0">
                            <p class="text-md font-bold text-gray-800 dark:text-gray-100 italic">AP-{{ production.sale_product.price.replace('.00', '-00') }}</p>
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
        printPage() {
            window.print();
        },
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
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

    .page-break-after {
        break-after: page; /* Estándar moderno para forzar salto de página */
        page-break-after: always; /* Propiedad antigua para mayor compatibilidad */
    }
}
</style>
