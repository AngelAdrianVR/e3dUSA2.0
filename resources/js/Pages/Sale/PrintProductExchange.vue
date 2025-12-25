<template>
    <Head :title="`Formato de Cambio #${exchange.id}`" />
    
    <!-- Contenedor Principal -->
    <div class="min-h-screen bg-gray-100 flex justify-center items-start p-6 print:p-0 print:bg-white print:items-start print:min-h-0">
        
        <!-- Hoja de Papel -->
        <div class="bg-white shadow-2xl w-full max-w-4xl p-8 relative overflow-hidden print:shadow-none print:w-full print:max-w-none print:p-0 print:overflow-visible">
            
            <!-- === ENCABEZADO === -->
            <header class="border-b-2 border-gray-800 pb-2 mb-4 relative z-10">
                <section class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <!-- Placeholder Logo -->
                        <ApplicationLogo />
                    </div>
                    <div class="text-right mt-1">
                        <!-- <p class="text-base font-bold text-gray-900">FOLIO: EX-{{ exchange.id.toString().padStart(4, '0') }}</p> -->
                        <p class="text-sm text-gray-600">Fecha: {{ formatDate(exchange.created_at) }}</p>
                        <p class="text-[12px] text-gray-500 mt-0 uppercase">Ref. Venta: {{ exchange.sale.type === 'venta' ? 'OV-' : 'OS-' }}{{ exchange.sale.id.toString().padStart(4, '0') }}</p>
                    </div>
                </section>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 uppercase tracking-wide leading-tight">Cambio de Producto</h1>
                    <p class="text-sm text-gray-500">Comprobante de Movimiento de Inventario</p>
                </div>
            </header>

            <!-- === INFO DEL CLIENTE Y GESTIÓN (Compacto) === -->
            <section class="grid grid-cols-2 gap-4 mb-4 text-sm relative z-10">
                <!-- Cliente -->
                <div class="bg-gray-50 p-3 rounded border border-gray-200">
                    <h3 class="font-bold text-gray-400 uppercase tracking-wider mb-1 text-[10px]">Datos del Cliente</h3>
                    <p class="font-bold text-gray-800 text-sm">{{ exchange.sale.branch?.name || 'Cliente General' }}</p>
                    <p class="text-gray-600 truncate" v-if="exchange.sale.branch?.address">
                        {{ exchange.sale.branch?.address }}
                    </p>
                    <p class="text-gray-600 mt-1" v-if="exchange.sale.contact">
                        <span class="font-semibold">Contacto:</span> {{ exchange.sale.contact?.name }}
                    </p>
                </div>

                <!-- Interno -->
                <div class="bg-gray-50 p-3 rounded border border-gray-200">
                    <h3 class="font-bold text-gray-400 uppercase tracking-wider mb-1 text-[10px]">Gestión Interna</h3>
                    <div class="flex justify-between mb-0.5">
                        <span class="text-gray-600">Solicitado por:</span>
                        <span class="font-medium text-gray-900 truncate pl-2">{{ exchange.user?.name }}</span>
                    </div>
                    <div class="flex justify-between mb-0.5">
                        <span class="text-gray-600">Almacén Origen:</span>
                        <span class="font-medium text-gray-900 truncate pl-2">{{ exchange.sale.branch?.name || 'Matriz' }}</span>
                    </div>
                     <div class="flex justify-between mt-1 pt-1 border-t border-gray-200">
                        <span class="font-bold text-gray-700">Diferencia a pagar/favor:</span>
                        <span class="font-bold text-sm" :class="Number(exchange.price_difference) >= 0 ? 'text-gray-900' : 'text-gray-900'">
                            {{ formatCurrency(exchange.price_difference) }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- === COMPARATIVA DE PRODUCTOS === -->
            <section class="mb-4 relative z-10">
                <div class="grid grid-cols-2 border border-gray-200 rounded overflow-hidden">
                    
                    <!-- Lado Izquierdo: Entrada / Devolución -->
                    <div class="border-r border-gray-200 flex flex-col h-full">
                        <div class="bg-red-50 p-1.5 border-b border-red-100">
                            <h4 class="text-red-800 font-bold text-sm uppercase text-center flex items-center justify-center gap-2">
                                <span class="bg-red-200 text-red-800 rounded-full w-4 h-4 flex items-center justify-center text-[10px]">&larr;</span> 
                                Entrada (Devolución)
                            </h4>
                        </div>
                        <div class="p-3 flex items-start gap-3 flex-grow">
                            <!-- Imagen del Producto devuelto -->
                            <div class="w-20 h-20 bg-gray-100 rounded border border-gray-200 flex-shrink-0 overflow-hidden flex items-center justify-center">
                                <img v-if="exchange.returned_product?.media?.length" 
                                     :src="exchange.returned_product.media[0].original_url" 
                                     class="w-full h-full object-cover" 
                                     alt="Prod. Devuelto">
                                <span v-else class="text-[10px] text-gray-400 text-center px-1">Sin imagen</span>
                            </div>
                            <!-- Info -->
                            <div>
                                <p class="text-2xl font-bold text-gray-800 leading-none mb-1">{{ exchange.returned_quantity }} <span class="text-sm font-normal text-gray-500">pza(s)</span></p>
                                <p class="text-sm font-bold text-gray-900 leading-tight">{{ exchange.returned_product?.name }}</p>
                                <p class="text-[10px] text-gray-500 font-mono mt-0.5">SKU: {{ exchange.returned_product?.code }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Lado Derecho: Salida / Entrega -->
                    <div class="flex flex-col h-full">
                        <div class="bg-green-50 p-1.5 border-b border-green-100">
                            <h4 class="text-green-800 font-bold text-sm uppercase text-center flex items-center justify-center gap-2">
                                Salida (Entrega) 
                                <span class="bg-green-200 text-green-800 rounded-full w-4 h-4 flex items-center justify-center text-[10px]">&rarr;</span>
                            </h4>
                        </div>
                        <div class="p-3 flex items-start gap-3 flex-grow">
                            <!-- Imagen del Producto Nuevo -->
                            <div class="w-20 h-20 bg-gray-100 rounded border border-gray-200 flex-shrink-0 overflow-hidden flex items-center justify-center">
                                <img v-if="exchange.new_product?.media?.length" 
                                     :src="exchange.new_product.media[0].original_url" 
                                     class="w-full h-full object-cover" 
                                     alt="Prod. Nuevo">
                                <span v-else class="text-[10px] text-gray-400 text-center px-1">Sin imagen</span>
                            </div>
                            <!-- Info -->
                            <div>
                                <p class="text-2xl font-bold text-gray-800 leading-none mb-1">{{ exchange.new_quantity }} <span class="text-sm font-normal text-gray-500">pza(s)</span></p>
                                <p class="text-sm font-bold text-gray-900 leading-tight">{{ exchange.new_product?.name }}</p>
                                <p class="text-[10px] text-gray-500 font-mono mt-0.5">SKU: {{ exchange.new_product?.code }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- === RAZÓN Y DETALLES === -->
            <section class="mb-4 relative z-10">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 border-b border-gray-200 pb-0.5">Motivo del Cambio</h3>
                <div class="text-sm text-gray-700 leading-snug bg-gray-50 p-2 rounded border border-gray-200 italic">
                    "{{ exchange.reason }}"
                </div>
            </section>

            <!-- === EVIDENCIA FOTOGRÁFICA (Compacta) === -->
            <section class="mb-6 relative z-10" v-if="exchange.media && exchange.media.length > 0">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 border-b border-gray-200 pb-0.5">Evidencia Fotográfica</h3>
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <div v-for="image in exchange.media" :key="image.id" class="w-24 h-24 flex-shrink-0 rounded border border-gray-200 overflow-hidden">
                        <img :src="image.original_url" class="w-full h-full object-cover grayscale hover:grayscale-0 transition-all print:grayscale-0" />
                    </div>
                </div>
            </section>

            <!-- === FIRMAS === -->
            <section class="grid grid-cols-2 gap-12 mt-6 pt-4 relative z-10">
                <div class="text-center">
                    <div class="border-t border-gray-400 w-3/4 mx-auto pt-1"></div>
                    <p class="text-sm font-bold text-gray-900">Entregó (Almacén)</p>
                    <p class="text-[10px] text-gray-500 truncate">{{ exchange.user?.name }}</p>
                </div>
                <div class="text-center">
                    <div class="border-t border-gray-400 w-3/4 mx-auto pt-1"></div>
                    <p class="text-sm font-bold text-gray-900">Recibí de Conformidad (Cliente)</p>
                    <p class="text-[10px] text-gray-500 truncate">{{ exchange.sale.contact?.name || 'Firma' }}</p>
                </div>
            </section>

            <!-- Footer pequeño -->
            <footer class="mt-8 text-center border-t border-gray-100 pt-2 print:mt-auto print:fixed print:bottom-4 print:left-0 print:w-full print:border-0 relative z-10">
                <p class="text-[9px] text-gray-400">Este documento es un comprobante interno. No es válido como factura fiscal.</p>
                <p class="text-[9px] text-gray-300 mt-0.5">Generado el {{ new Date().toLocaleString() }}</p>
            </footer>

        </div>
    </div>

    <!-- Botón Flotante para imprimir -->
    <div class="fixed bottom-8 right-8 print:hidden z-50">
        <button @click="print" class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-5 rounded-full shadow-xl flex items-center transition-all transform hover:scale-105 border-2 border-white">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
            </svg>
            Imprimir Formato
        </button>
    </div>
</template>

<script>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head } from '@inertiajs/vue3';

export default {
    name: "PrintProductExchange",
    components: {
        ApplicationLogo,
        Head
    },
    props: {
        exchange: Object
    },
    methods: {
        print() {
            window.print();
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return new Intl.DateTimeFormat('es-MX', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        },
        formatCurrency(amount) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(amount);
        }
    }
}
</script>

<style scoped>
@media print {
    body {
        background: white;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    
    /* Forzar márgenes mínimos de impresión para aprovechar la hoja */
    @page {
        margin: 0.5cm;
    }
}
</style>