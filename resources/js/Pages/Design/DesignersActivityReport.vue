<template>
    <Head title="Reporte de Actividades de Diseñadores" />
    <div class="bg-gray-100 font-sans">
        <!-- Botón de Imprimir Flotante -->
        <button @click="printReport" title="Imprimir Reporte"
            class="fixed bottom-7 right-7 bg-sky-600 print:hidden text-white rounded-full size-14 shadow-lg hover:bg-sky-700 transition-all z-50 flex items-center justify-center">
            <i class="fa-solid fa-print text-2xl"></i>
        </button>

        <div class="max-w-5xl mx-auto p-8 bg-white shadow-lg my-10 rounded-lg">
            <header class="flex justify-between items-center pb-6 border-b-2 border-gray-200">
                <ApplicationLogo />
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Reporte de Actividades</h1>
                    <h2 class="text-xl text-gray-600">Mes: <span class="font-semibold">{{ monthName }}, {{ year }}</span></h2>
                </div>
            </header>

            <main class="mt-8">
                <div v-if="reportData && reportData.length > 0">
                    <div v-for="(data, index) in reportData" :key="index" class="mb-10 p-6 border border-gray-200 rounded-xl bg-gray-50/50">
                        <h3 class="text-2xl font-semibold text-gray-800 border-b pb-2 mb-4">{{ data.designer_name }}</h3>
                        
                        <div v-if="data.total_orders > 0">
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white rounded-lg">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Folio</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Nombre de la Orden</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Inicio</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Término</th>
                                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Tiempo Neto</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <template v-for="order in data.orders" :key="order.id">
                                            <!-- Fila Principal de la Orden -->
                                            <tr class="hover:bg-gray-50 font-medium">
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">OD-{{ String(order.id).padStart(4, '0') }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-800 font-bold max-w-[250px] truncate" :title="order.order_title">
                                                    {{ order.order_title }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">{{ order.started_at || 'N/A' }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">{{ order.finished_at || 'N/A' }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-blue-700 font-bold text-right">{{ order.duration }}</td>
                                            </tr>
                                            
                                            <!-- Historial de Pausas (Lista Vertical) -->
                                            <tr v-if="order.pauses && order.pauses.length > 0" class="bg-amber-50/30 print:bg-white">
                                                <td colspan="5" class="px-10 py-3 border-b border-gray-100">
                                                    <div class="flex flex-col">
                                                        <div class="flex items-center gap-2 mb-2">
                                                            <i class="fa-solid fa-clock-rotate-left text-amber-500 text-[10px]"></i>
                                                            <span class="text-[10px] font-bold text-amber-600 uppercase tracking-widest">Detalle de Interrupciones</span>
                                                        </div>
                                                        
                                                        <div class="space-y-2 border-l-2 border-amber-200 ml-1.5">
                                                            <div v-for="(pause, pIdx) in order.pauses" :key="pIdx" class="relative pl-5 py-0.5">
                                                                <!-- Indicador visual -->
                                                                <div class="absolute left-[-5px] top-1.5 size-2 bg-amber-400 rounded-full border border-white"></div>
                                                                
                                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[10px]">
                                                                    <div class="text-gray-500">
                                                                        <span class="font-semibold text-gray-700">Pausado:</span> {{ pause.paused_at }}
                                                                    </div>
                                                                    <div class="text-gray-500">
                                                                        <span class="font-semibold text-gray-700">Reanudado:</span> {{ pause.resumed_at }}
                                                                    </div>
                                                                    <div class="text-gray-800 font-bold sm:text-right">
                                                                        <i class="fa-regular fa-hourglass-half mr-1"></i> Duración: {{ pause.duration }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-6 text-right bg-gray-100 p-5 rounded-lg border border-gray-200">
                                <p class="text-sm text-gray-600">
                                    Total de Órdenes Completadas: <span class="font-bold text-gray-800">{{ data.total_orders }}</span>
                                </p>
                                <p class="text-md text-gray-800 mt-1">
                                    Tiempo Total Invertido (Neto): <span class="font-bold text-xl text-blue-600">{{ data.total_duration }}</span>
                                </p>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 px-4 bg-gray-100 rounded-lg">
                            <p class="text-gray-500">No se encontraron órdenes terminadas para este diseñador en el mes seleccionado.</p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-10">
                    <p class="text-gray-500">No hay datos para mostrar con los diseñadores seleccionados.</p>
                </div>
            </main>
        </div>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

export default {
    name: 'DesignersActivityReport',
    layout: null,
    components: {
        Head,
        ApplicationLogo
    },
    props: {
        reportData: Array,
        monthName: String,
        year: Number,
    },
    methods: {
        printReport() {
            window.print();
        }
    },
}
</script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    .font-sans {
        font-family: 'Inter', sans-serif;
    }
    @media print {
        .no-print, .no-print * {
            display: none !important;
        }
        body {
            background-color: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .max-w-5xl {
            box-shadow: none !important;
            margin: 0 !important;
            max-width: 100% !important;
            padding: 0 !important;
        }
        tr {
            page-break-inside: avoid;
        }
    }
</style>