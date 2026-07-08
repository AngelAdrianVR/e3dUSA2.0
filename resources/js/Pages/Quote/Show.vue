<template>
    <Head :title="tabTitle" />
    <div class="bg-gray-100 font-sans print:bg-white relative">
        
        <!-- MARCA DE AGUA - NO AUTORIZADA -->
        <div v-if="!quote.authorized_by_user_id" 
             class="fixed inset-0 pointer-events-none flex items-center justify-center z-[100] overflow-hidden">
            <div class="text-red-500 opacity-15 print:opacity-20 font-black text-[80px] md:text-[120px] print:text-[100px] -rotate-45 whitespace-nowrap uppercase select-none tracking-widest">
                {{ quote.is_spanish_template ? 'NO AUTORIZADA' : 'UNAUTHORIZED' }}
            </div>
        </div>

        <div class="container mx-auto p-4 print:max-w-full print:w-full print:p-0">
            <!-- Contenedor principal de la cotización -->
            <div class="bg-white rounded-lg shadow-lg p-5 text-gray-800 relative print:shadow-none print:rounded-none print:p-0">

                <!-- Botón de Imprimir Flotante -->
                <button v-show="showAdditionalElements" @click="printQuote" title="Imprimir Cotización"
                    class="fixed bottom-6 right-6 bg-sky-600 text-white rounded-full size-14 shadow-lg hover:bg-sky-700 transition-all z-50 flex items-center justify-center">
                    <i class="fa-solid fa-print text-2xl"></i>
                </button>

                <!-- Encabezado -->
                <header class="flex justify-between items-start pb-6 mb-6 border-b">
                    <div class="w-1/2">
                        <ApplicationLogo class="h-16 w-auto" />
                        <p class="text-gray-500 mt-2 text-sm">{{ quote.is_spanish_template ? 'Especialistas en Emblemas de alta calidad' : 'Specialists in High-Quality Emblems' }}</p>
                    </div>
                    <div class="text-right w-1/2">
                        <div class="flex items-center justify-end space-x-3 mb-2">
                            <!-- NUEVO BOTÓN PARA IR AL INDEX DE COTIZACIONES -->
                            <button @click="$inertia.visit(route('quotes.index'))"
                                class="flex items-center gap-2 px-3 py-1 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm transition shadow-sm print:hidden">
                                <i class="fa-solid fa-arrow-left"></i>
                                {{ quote.is_spanish_template ? 'Volver' : 'Back' }}
                            </button>

                            <!-- BOTÓN DE EDITAR -->
                            <button @click="$inertia.visit(route('quotes.edit', quote.id))"
                                class="flex items-center gap-2 px-3 py-1 bg-blue-400 text-white rounded-lg hover:bg-blue-500 text-sm transition shadow-sm print:hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487l3.651 3.651M4.5 20.25l4.125-.516a2.25 2.25 0 001.266-.633L19.5 9.75a2.25 2.25 0 000-3.182l-2.568-2.568a2.25 2.25 0 00-3.182 0L4.5 14.25v6z" />
                                </svg>
                                Editar
                            </button>
                            <h1 class="text-2xl sm:text-3xl font-bold text-sky-700 uppercase">{{ quote.is_spanish_template ? 'Cotización' : 'Quote' }}</h1>
                        </div>

                        <div class="text-sm font-semibold text-gray-600 flex items-center space-x-2 justify-end">
                            <span class="text-lg">COT-{{ quote.root_quote_id?.toString()?.padStart(4, '0') }}</span>
                            <span class="ml-2 px-3 py-1 bg-sky-100 text-sky-700 text-xs font-bold rounded-full align-middle">v{{ quote.version }}</span>
                            
                            <!-- Navegación entre VERSIONES -->
                            <div class="flex items-center justify-end space-x-4 pl-4 print:hidden">
                                <button @click="$inertia.visit(route('quotes.show', prev_version_id))" :disabled="!prev_version_id" title="Versión Anterior" class="bg-gray-200 hover:bg-gray-300 text-gray-700 size-7 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"><i class="fa-solid fa-chevron-left"></i></button>
                                <span class="px-2 py-1 !text-xs rounded-md bg-blue-100">Versión</span>
                                <button @click="$inertia.visit(route('quotes.show', next_version_id))" :disabled="!next_version_id" title="Versión Siguiente" class="bg-gray-200 hover:bg-gray-300 text-gray-700 size-7 rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                        </div>

                        <div class="mt-2 print:hidden" v-if="allVersions && allVersions.length > 1">
                            <label for="version_select" class="text-xs text-gray-500 mr-2">Ver versión:</label>
                            <select id="version_select" :value="quote.id" @change="navigateToVersion($event.target.value)" class="text-sm border-gray-300 rounded-md shadow-sm h-8 py-0 focus:border-sky-500 focus:ring-sky-500">
                                <option v-for="version in allVersions" :key="version.id" :value="version.id">Versión {{ version.version }} ({{ formatDate(version.created_at) }})</option>
                            </select>
                        </div>
                        
                        <p class="text-sm text-gray-500 mt-2">{{ quote.is_spanish_template ? 'Fecha' : 'Date' }}: {{ formatDate(quote.created_at) }}</p>
                        
                        <!-- Navegación entre cotizaciones -->
                        <div v-show="showAdditionalElements" class="flex items-center justify-end space-x-4 mt-4">
                            <button @click="$inertia.visit(route('quotes.show', prev_quote))" title="Anterior" class="bg-gray-200 hover:bg-gray-300 text-gray-700 size-7 rounded-full transition-colors"><i class="fa-solid fa-chevron-left"></i></button>
                            <span class="px-2 py-1 text-xs rounded-md bg-blue-100 font-semibold text-gray-600">Cambiar cotización</span>
                            <button @click="$inertia.visit(route('quotes.show', next_quote))" title="Siguiente" class="bg-gray-200 hover:bg-gray-300 text-gray-700 size-7 rounded-full transition-colors"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                </header>

                <!-- Información del Cliente y Autorización -->
                <section class="flex justify-between items-start mb-8">
                    <div class="w-2/3">
                        <p class="text-sm text-gray-500 mb-1">{{ quote.is_spanish_template ? 'Atención a' : 'Attention to' }}:</p>
                        <p class="text-lg font-bold text-gray-900">{{ quote.branch?.name }}</p>
                        <p class="text-gray-700">{{ quote.receiver }} - {{ quote.department }}</p>
                    </div>
                    <div class="text-right relative" v-if="$page.props.auth.user?.permissions?.includes('Autorizar cotizaciones')">
                        <div v-if="quote.authorized_by_user_id && showAdditionalElements">
                            <div class="inline-flex items-center bg-green-100 text-green-700 font-semibold px-4 py-2 rounded-lg"><i class="fa-solid fa-check-circle mr-2"></i>Autorizado</div>
                            <p class="text-xs text-gray-500 mt-1">{{ quote.is_spanish_template ? 'Por' : 'By' }}: {{ quote.authorized_by?.name }}</p>
                        </div>
                        <div class="relative inline-block" v-else>
                            <button @click="authorize" v-show="showAdditionalElements" :disabled="!quote.user" class="bg-blue-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors shadow disabled:bg-gray-400 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-shield-halved mr-2"></i> Autorizar Cotización
                            </button>
                            <div v-if="!quote.user" class="absolute left-1/2 -translate-x-1/2 mt-2 w-max bg-gray-700 text-white text-xs rounded-md px-3 py-1 shadow-lg">
                                Para autorizar cotización es necesario editarla y guardarla. clic <span @click="$inertia.visit(route('quotes.edit', quote.id))" class="hover:underline text-blue-200 cursor-pointer"> aqui </span>
                            </div>
                        </div>
                    </div>
                </section>

                <p class="my-6 text-sm text-gray-700">
                    {{ quote.is_spanish_template ? 'Por medio de la presente reciba un cordial saludo y a su vez le proporciono la cotización que nos solicitó, con base en la plática sostenida con ustedes y sabiendo de sus condiciones del producto a aplicar:' : 'Through this letter, receive a cordial greeting, and at the same time, I provide you with the quote you requested, based on our conversation and understanding the conditions of the product to be applied:' }}
                </p>

                <!-- Promoción -->
                <section v-if="quote.has_early_payment_discount" class="mb-6">
                    <div class="flex items-start space-x-2">
                        <div v-show="showPromotion" class="flex-grow bg-gradient-to-r from-sky-600 to-cyan-500 text-white p-4 rounded-lg shadow-lg flex items-center space-x-4 transition-all duration-300">
                            <i class="fa-solid fa-tags text-3xl opacity-80"></i>
                            <div>
                                <h3 class="font-bold text-lg">{{ quote.is_spanish_template ? '¡Promoción Exclusiva!' : 'Exclusive Promotion!' }}</h3>
                                <p class="text-sm">
                                    {{ quote.is_spanish_template ? 'Paga por adelantado y recibe un' : 'Pay in advance and receive an exclusive' }}
                                    <span class="font-extrabold text-amber-300">{{ quote.early_payment_discount_amount }}% {{ quote.is_spanish_template ? 'de descuento' : 'discount' }}</span>.
                                </p>
                            </div>
                        </div>
                        <button @click="showPromotion = !showPromotion" class="flex-shrink-0 bg-gray-200 text-gray-600 size-9 rounded-full hover:bg-gray-300 transition-colors print:hidden" :title="showPromotion ? (quote.is_spanish_template ? 'Ocultar promoción' : 'Hide promotion') : (quote.is_spanish_template ? 'Mostrar promoción' : 'Show promotion')">
                            <i class="fa-solid" :class="showPromotion ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </section>

                <!-- Componentes de Tarjetas de Productos -->
                <section>
                    <h2 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2">{{ quote.is_spanish_template ? 'Conceptos de la Cotización' : 'Quote Concepts' }}</h2>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 print:grid-cols-2 print:gap-2">
                        
                        <div v-for="(item, index) in quote.quote_products" 
                             :key="item.id"
                             style="page-break-inside: avoid; break-inside: avoid;" 
                             class="print:break-inside-avoid h-full">
                            <!-- Inyectamos nuestro nuevo componente de Tarjeta -->
                            <QuoteProductCard 
                                :item="item" 
                                :quote="quote" 
                                :show-additional-elements="showAdditionalElements"
                                :label-changed="labelChanged"
                                @update-status="updateProductStatus" 
                            />
                        </div>
                        
                    </div>
                    <button v-show="showAdditionalElements" @click="labelChanged = !labelChanged" class="mt-4 text-xs bg-gray-700 text-white rounded-full px-3 py-1 hover:bg-black transition-colors">
                        <i class="fa-solid fa-shuffle mr-1"></i> {{ !labelChanged ? (quote.is_spanish_template ? 'Cambiar a MOQ' : 'Change to MOQ') : (quote.is_spanish_template ? 'Cambiar a Unidades' : 'Change to Units') }}
                    </button>
                </section>
                
                <!-- Totales -->
                <section v-if="quote.show_breakdown" class="mt-8 flex justify-end">
                    <div class="w-full sm:w-1/2 lg:w-1/3 space-y-2 text-sm print:text-xs print:break-inside-avoid" style="page-break-inside: avoid; break-inside: avoid;">
                        <div class="flex items-center justify-end gap-3 mb-2 print:hidden bg-gray-50 p-2 rounded border">
                            <label class="flex items-center gap-2 cursor-pointer text-xs select-none">
                                <input type="checkbox" v-model="showTaxes" class="rounded text-sky-600 focus:ring-sky-500">
                                {{ quote.is_spanish_template ? 'Mostrar precios con IVA' : 'Show prices with Tax' }}
                            </label>
                            <div v-if="showTaxes" class="flex items-center gap-1">
                                <span class="text-xs text-gray-500">IVA %:</span>
                                <input type="number" v-model.number="taxPercentage" class="w-16 h-7 text-xs border-gray-300 rounded focus:ring-sky-500 focus:border-sky-500 text-right" min="0" step="0.1">
                            </div>
                        </div>

                         <div class="flex justify-between p-2 rounded-md bg-gray-100">
                            <span class="font-semibold text-gray-600">{{ quote.is_spanish_template ? 'Subtotal (Aprobados)' : 'Subtotal (Approved)' }}:</span>
                            <span class="font-bold text-gray-800">{{ formatNumber(quote.total_data.subtotal) }} {{ quote.currency }}</span>
                        </div>
                        <div v-if="!quote.is_freight_cost_stroked" class="flex justify-between p-2">
                            <span class="text-gray-600">{{ quote.is_spanish_template ? 'Costo de Flete' : 'Freight Cost' }}:</span>
                            <span class="font-semibold text-gray-800">{{ formatNumber(quote.freight_cost) }} {{ quote.currency }}</span>
                        </div>
                         <div v-if="!quote.is_tooling_cost_stroked" class="flex justify-between p-2">
                            <span class="text-gray-600">{{ quote.is_spanish_template ? 'Costo de Herramental' : 'Tooling Cost' }}:</span>
                            <span class="font-semibold text-gray-800">{{ formatNumber(quote.tooling_cost) }}</span>
                        </div>
                        <div v-if="quote.has_early_payment_discount" class="flex justify-between p-2 text-green-600 border-t border-dashed">
                            <span class="font-semibold">{{ quote.is_spanish_template ? 'Descuento' : 'Discount' }} ({{ quote.early_payment_discount_amount }}%):</span>
                            <span class="font-bold">- {{ formatNumber(quote.total_data.discount_amount) }} {{ quote.currency }}</span>
                        </div>
                        
                        <div v-if="showTaxes" class="flex justify-between p-2 text-gray-600 border-t">
                            <span class="font-semibold">IVA ({{ taxPercentage }}%):<span class="text-[10px] text-gray-400 block font-normal leading-tight">({{ quote.is_spanish_template ? 'Solo productos' : 'Products only' }})</span></span>
                            <span class="font-bold text-gray-700">{{ formatNumber(taxAmount) }} {{ quote.currency }}</span>
                        </div>

                        <div class="flex justify-between p-3 rounded-md bg-gray-800 text-white">
                            <span class="text-base font-bold">
                                {{ showTaxes ? (quote.is_spanish_template ? 'Total Neto' : 'Net Total') : (quote.is_spanish_template ? 'Total sin IVA' : 'Total before taxes') }}:
                            </span>
                            <span class="text-base font-bold">{{ formatNumber(showTaxes ? totalWithTax : quote.total_data.total_after_discount) }} {{ quote.currency }}</span>
                        </div>
                    </div>
                </section>

                <!-- Notas Importantes -->
                <section class="w-full border-t-4 border-amber-400 bg-amber-50 px-5 py-4 mt-10 rounded-b-lg text-gray-700 print:break-inside-avoid" style="page-break-inside: avoid; break-inside: avoid; font-size: 11px;">
                    <h2 class="text-center font-extrabold text-base uppercase mb-3">{{ quote.is_spanish_template ? 'Información Importante' : 'Important Information' }}</h2>
                    <ol class="list-decimal list-inside space-y-2">
                        <li v-if="quote.notes" v-html="quote.notes"></li>
                        <li>{{ quote.is_spanish_template ? 'Precios antes de IVA' : 'Prices before taxes' }}.</li>
                        <li>{{ quote.is_spanish_template ? 'Costo de herramental' : 'Tooling cost' }}: <span class="font-bold text-blue-600" :class="{ 'line-through': quote.is_tooling_cost_stroked }">{{ formatNumber(quote.tooling_cost) }}</span>.</li>
                        <li>{{ quote.is_spanish_template ? 'Tiempo de entrega para la primer producción' : 'Lead time for first production' }}: <span class="font-bold text-blue-600">{{ quote.first_production_days }}</span>.</li>
                        <li>{{ quote.is_spanish_template ? 'Fletes y acarreos' : 'Freight & handling' }}: <span class="font-bold text-blue-600">{{ quote.freight_option }}</span> <span v-if="quote.freight_cost > 0" :class="{ 'line-through': quote.is_freight_cost_stroked }">({{ formatNumber(quote.freight_cost) }} {{ quote.currency }})</span>.</li>
                        <li>{{ quote.is_spanish_template ? 'Precios en' : 'Prices in' }}: <span class="font-bold text-blue-600">{{ quote.currency }}</span>.</li>
                        <li>{{ quote.validity || (quote.is_spanish_template ? 'Cotización válida por 21 días' : 'Quote valid for 21 days') }}.</li>
                    </ol>
                </section>
                
                <section class="mt-9 text-sm text-gray-700 print:break-inside-avoid" style="page-break-inside: avoid; break-inside: avoid;">
                     <p>{{ quote.is_spanish_template ? 'Sin más por el momento y en espera de su preferencia, quedo a sus órdenes para cualquier duda o comentario.' : 'Without further ado and awaiting your preference, I remain at your service for any questions or comments.' }}</p>
                    <div class="mt-4">
                        <p v-if="quote.user">{{ quote.is_spanish_template ? 'Creado por' : 'Created by' }}: <span class="font-semibold">{{ quote.user?.name }}</span> ({{ quote.user?.email }}), ({{ quote.user?.phone }})</p>
                        <p v-else class="bg-orange-200 px-2 py-1">{{ quote.is_spanish_template ? 'Creado por cliente desde portal de clientes' : 'Created by customer' }}</p>
                    </div>
                </section>
            </div>
            
            <!-- Inyectamos nuestro nuevo componente Footer -->
            <QuoteFooter :quote="quote" />
        </div>
    </div>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import QuoteProductCard from './Components/QuoteProductCard.vue';
import QuoteFooter from './Components/QuoteFooter.vue';
import { ElMessage } from 'element-plus';
import { Head, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    components: {
        AppLayout,
        ApplicationLogo,
        Head,
        QuoteProductCard,
        QuoteFooter
    },
    props: {
        quote: Object,
        allVersions: Array,
        next_version_id: Number,
        prev_version_id: Number,
        next_quote: Number,
        prev_quote: Number,
    },
    data() {
        return {
            showAdditionalElements: true,
            labelChanged: false,
            showPromotion: true,
            showTaxes: false,
            taxPercentage: 16,
        }
    },
    computed: {
        tabTitle() {
            return `Cot. ${this.quote.root_quote_id}-v${this.quote.version} - ${this.quote.branch.name}`;
        },
        taxAmount() {
            if (!this.showTaxes) return 0;
            const subtotal = Number(this.quote.total_data.subtotal) || 0;
            const percentage = Number(this.taxPercentage) || 0;
            return subtotal * (percentage / 100);
        },
        totalWithTax() {
            const currentTotal = Number(this.quote.total_data.total_after_discount) || 0;
            return currentTotal + this.taxAmount;
        }
    },
    methods: {
        navigateToVersion(selectedId) {
            if (selectedId != this.quote.id) {
                router.visit(route('quotes.show', selectedId), { preserveScroll: true });
            }
        },
        async updateProductStatus(item, newStatus) {
            try {
                const response = await axios.put(route('quotes.products.updateStatus', item.id), { status: newStatus });
                if (response.status === 200) {
                    router.reload({ 
                        preserveScroll: true,
                        onSuccess: () => {
                             ElMessage({ message: response.data.message || 'Estatus actualizado.', type: 'success' });
                        }
                    });
                }
            } catch (err) {
                ElMessage({ title: 'Error al actualizar', message: err.response?.data?.message || 'No se pudo cambiar el estatus del producto.', type: 'error' });
            }
        },
        printQuote() {
            this.showAdditionalElements = false;
            this.$nextTick(() => { setTimeout(() => { window.print(); }, 200); });
        },
        handleBeforePrint() { this.showAdditionalElements = false; },
        handleAfterPrint() { this.showAdditionalElements = true; },
        formatDate(dateString) {
            if (!dateString) return '';
            return format(new Date(dateString), "d 'de' MMMM, yyyy", { locale: es });
        },
        formatNumber(value) {
            if (value === null || value === undefined || value === '') return '0.00';
            const num = Number(value);
            return isNaN(num) ? value : new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        async authorize() {
            if (this.quote.authorized_by) return;
            try {
                const response = await axios.put(route('quotes.authorize', this.quote.id));
                if (response.status === 200) {
                    router.reload({ preserveScroll: true, preserveState: true });
                    ElMessage({ title: 'Éxito', message: response.data.message, type: 'success' });
                } else {
                    ElMessage({ title: 'Error', message: response.data.message || 'No se pudo completar la autorización.', type: 'error' });
                }
            } catch (err) {
                ElMessage({ title: 'Algo salió mal', message: err.response?.data?.message || err.message, type: 'error' });
            }
        },
    },
    mounted() {
        window.addEventListener('beforeprint', this.handleBeforePrint);
        window.addEventListener('afterprint', this.handleAfterPrint);
    },
    beforeUnmount() {
        window.removeEventListener('beforeprint', this.handleBeforePrint);
        window.removeEventListener('afterprint', this.handleAfterPrint);
    }
};
</script>