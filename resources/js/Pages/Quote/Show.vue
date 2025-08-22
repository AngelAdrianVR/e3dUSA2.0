<template>
    <Head :title="tabTitle" />
    <div class="bg-gray-100 font-sans print:bg-white">
        <div class="container mx-auto p-4 sm:p-8 print:p-1">
            <!-- Contenedor principal de la cotización -->
            <div class="bg-white rounded-lg shadow-lg p-6 sm:p-10 text-gray-800 relative print:shadow-none print:rounded-none print:p-0">

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
                        <h1 class="text-2xl sm:text-3xl font-bold text-sky-700 uppercase">{{ quote.is_spanish_template ? 'Cotización' : 'Quote' }}</h1>
                        <p class="text-sm font-semibold text-gray-600 mt-1">
                            <span class="text-lg">COT-{{ quote.id.toString().padStart(4, '0') }}</span>
                        </p>
                        <p class="text-sm text-gray-500 mt-2">
                            {{ quote.is_spanish_template ? 'Fecha' : 'Date' }}: {{ formatDate(quote.created_at) }}
                        </p>
                         <!-- Navegación entre cotizaciones (solo en pantalla) -->
                        <div v-show="showAdditionalElements" class="flex items-center justify-end space-x-4 mt-4">
                            <button @click="$inertia.visit(route('quotes.show', prev_quote))" title="Anterior"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 size-7 rounded-full transition-colors">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <button @click="$inertia.visit(route('quotes.show', next_quote))" title="Siguiente"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 size-7 rounded-full transition-colors">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
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
                    <!-- Lógica de Autorización -->
                    <div v-if="quote.authorized_by_user_id && showAdditionalElements">
                        <div class="inline-flex items-center bg-green-100 text-green-700 font-semibold px-4 py-2 rounded-lg">
                            <i class="fa-solid fa-check-circle mr-2"></i>
                            Autorizado
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ quote.is_spanish_template ? 'Por' : 'By' }}: {{ quote.authorized_by?.name }}
                        </p>
                    </div>

                    <div class="relative inline-block" v-else>
                        <button @click="authorize" v-show="showAdditionalElements" :disabled="!quote.user"
                            class="bg-blue-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors shadow disabled:bg-gray-400 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-shield-halved mr-2"></i>
                            Autorizar Cotización
                        </button>

                        <!-- Tooltip -->
                        <div v-if="!quote.user" 
                            class="absolute left-1/2 -translate-x-1/2 mt-2 w-max bg-gray-700 text-white text-xs rounded-md px-3 py-1 shadow-lg">
                            Para autorizar cotización es necesario editarla y guardarla.
                            clic <span @click="$inertia.visit(route('quotes.edit', quote.id))" class="hover:underline text-blue-200 cursor-pointer"> aqui </span>
                        </div>
                    </div>
                </div>

                </section>

                <!-- Mensaje introductorio -->
                 <p class="my-6 text-sm text-gray-700">
                    {{ quote.is_spanish_template
                        ? 'Por medio de la presente reciba un cordial saludo y a su vez le proporciono la cotización que nos solicitó, con base en la plática sostenida con ustedes y sabiendo de sus condiciones del producto a aplicar:'
                        : 'Through this letter, receive a cordial greeting, and at the same time, I provide you with the quote you requested, based on our conversation and understanding the conditions of the product to be applied:'
                    }}
                </p>

                <!-- Descuento por pago anticipado -->
                <section v-if="$page.props.auth.user.permissions.includes('Descuentos cotizaciones')">
                     <figure v-if="quote.early_payment_discount && !quote.early_paid_at" class="mb-6 relative text-center">
                        <img draggable="false" class="w-[370px] h-12 inline-block" src="@/../../public/images/earlyPaymentButton.webp" alt="Descuento por pago anticipado">
                        <div class="absolute inset-0 flex items-center justify-center">
                             <p class="text-[#005660] text-xs text-center ml-10">
                                PAGA ESTA COTIZACIÓN POR ADELANTADO <br> Y RECIBE UN <span class="font-bold">{{ quote.discount }}% DE DESCUENTO</span> EXCLUSIVO
                            </p>
                        </div>
                    </figure>
                    <div v-else-if="quote.early_payment_discount && showAdditionalElements" class="my-4 flex items-center gap-3 p-3 rounded-lg bg-green-100 border border-green-200 text-green-800 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 flex-shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm font-semibold">
                            ¡Descuento por pago anticipado del {{ quote.discount }}% aplicado correctamente!
                        </p>
                    </div>
                </section>

                <!-- Tarjetas de Productos -->
                <section>
                    <h2 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2">{{ quote.is_spanish_template ? 'Conceptos de la Cotización' : 'Quote Concepts' }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="(item, productIndex) in quote.products" :key="item.id"
                             class="bg-white border rounded-lg overflow-hidden transition-shadow hover:shadow-md flex flex-col">
                            
                            <!-- Imagen y Selector -->
                            <div class="bg-gray-200 p-2 relative group">
                                <img class="rounded-md w-full h-44 object-contain mx-auto"
                                     :src="item.media[item.activeImageIndex || 0]?.original_url"
                                     :alt="item.name">
                                <!-- Aprobado Badge -->
                                <span v-if="item.pivot.customer_approval_status === 'Aprobado'"
                                      class="absolute top-2 right-2 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow">
                                      {{ quote.is_spanish_template ? 'ACEPTADO' : 'APPROVED' }}
                                </span>
                                <!-- Botones de Navegación de Imagen -->
                                <div v-if="item.media?.length > 1" v-show="showAdditionalElements">
                                    <button @click="prevImage(productIndex)" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-20 text-white size-8 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </button>
                                     <button @click="nextImage(productIndex)" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-20 text-white size-8 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Contenido de la tarjeta -->
                            <div class="p-4 flex flex-col flex-grow">
                                <h3 class="font-bold text-base text-gray-800 uppercase">{{ item.name }}</h3>
                                <p v-if="item.pivot.notes" class="text-xs text-gray-600 mt-1 flex-grow italic">"{{ item.pivot.notes }}"</p>
                                
                                <div v-if="item.large || item.height || item.width || item.diameter" class="mt-3 pt-3 border-t">
                                    <p class="text-xs uppercase text-gray-500 font-semibold">{{ quote.is_spanish_template ? 'Dimensiones' : 'Dimensions' }}:</p>
                                    <div class="text-xs text-gray-700 mt-1 space-y-1">
                                        <p v-if="item.large">Largo: <span class="font-semibold">{{ item.large }}mm</span></p>
                                        <p v-if="item.height">Alto: <span class="font-semibold">{{ item.height }}mm</span></p>
                                        <p v-if="item.width">Ancho: <span class="font-semibold">{{ item.width }}mm</span></p>
                                        <p v-if="item.diameter">Diámetro: <span class="font-semibold">{{ item.diameter }}mm</span></p>
                                    </div>
                                </div>

                                <!-- Precios -->
                                <div class="mt-4 pt-4 border-t text-xs space-y-2">
                                     <div class="flex justify-between items-center">
                                        <span class="text-gray-500">{{ !labelChanged ? (quote.is_spanish_template ? 'Unidades' : 'Units') : 'MOQ' }}</span>
                                        <span class="font-semibold text-gray-800">{{ item.pivot.quantity }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500">{{ quote.is_spanish_template ? '$ Unitario' : 'Unit $' }}</span>
                                        <span class="font-semibold text-gray-800">{{ formatNumber(item.pivot.unit_price) }} {{ quote.currency }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm mt-2 pt-2 border-t">
                                        <span class="font-bold text-gray-600">{{ quote.is_spanish_template ? 'Total' : 'Total' }}</span>
                                        <span class="font-bold text-sky-700">{{ formatNumber(item.pivot.quantity * item.pivot.unit_price) }} {{ quote.currency }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button v-show="showAdditionalElements" @click="labelChanged = !labelChanged"
                        class="mt-4 text-xs bg-gray-700 text-white rounded-full px-3 py-1 hover:bg-black transition-colors">
                        <i class="fa-solid fa-shuffle mr-1"></i>
                        {{ !labelChanged ? (quote.is_spanish_template ? 'Cambiar a MOQ' : 'Change to MOQ') : (quote.is_spanish_template ? 'Cambiar a Unidades' : 'Change to Units') }}
                    </button>
                </section>
                
                <!-- Sección de Totales -->
                <section v-if="quote.show_breakdown" class="mt-8 flex justify-end">
                    <div class="w-full sm:w-1/2 lg:w-1/3 space-y-2 text-sm">
                         <div class="flex justify-between p-2 rounded-md bg-gray-100">
                            <span class="font-semibold text-gray-600">{{ quote.is_spanish_template ? 'Subtotal (Aprobados)' : 'Subtotal (Approved)' }}:</span>
                            <span class="font-bold text-gray-800">{{ formatNumber(approvedSubtotal) }} {{ quote.currency }}</span>
                        </div>
                        <div v-if="!quote.is_freight_cost_stroked" class="flex justify-between p-2">
                            <span class="text-gray-600">{{ quote.is_spanish_template ? 'Costo de Flete' : 'Freight Cost' }}:</span>
                            <span class="font-semibold text-gray-800">{{ formatNumber(quote.freight_cost) }} {{ quote.currency }}</span>
                        </div>
                         <div v-if="!quote.is_tooling_cost_stroked" class="flex justify-between p-2">
                            <span class="text-gray-600">{{ quote.is_spanish_template ? 'Costo de Herramental' : 'Tooling Cost' }}:</span>
                            <span class="font-semibold text-gray-800">{{ formatNumber(quote.tooling_cost) }} {{ quote.currency }}</span>
                        </div>
                        <div class="flex justify-between p-3 rounded-md bg-gray-800 text-white">
                            <span class="text-base font-bold">{{ quote.is_spanish_template ? 'Total sin IVA' : 'Total before taxes' }}:</span>
                            <span class="text-base font-bold">{{ formatNumber(total) }} {{ quote.currency }}</span>
                        </div>
                    </div>
                </section>

                <!-- Notas Importantes -->
                <section class="w-full border-t-4 border-amber-400 bg-amber-50 px-5 py-4 mt-10 rounded-b-lg text-gray-700" style="font-size: 11px;">
                    <h2 class="text-center font-extrabold text-base uppercase mb-3">{{ quote.is_spanish_template ? 'Información Importante' : 'Important Information' }}</h2>
                    <ol class="list-decimal list-inside space-y-2">
                        <li v-if="quote.notes" class="font-bold text-blue-600 whitespace-pre-line">{{ quote.notes }}</li>
                        <li>{{ quote.is_spanish_template ? 'Precios antes de IVA' : 'Prices before taxes' }}.</li>
                        <li>{{ quote.is_spanish_template ? 'Costo de herramental' : 'Tooling cost' }}: <span class="font-bold text-blue-600" :class="{ 'line-through': quote.is_tooling_cost_stroked }">{{ formatNumber(quote.tooling_cost) }} {{ quote.currency }}</span>.</li>
                        <li>{{ quote.is_spanish_template ? 'Tiempo de entrega para la primer producción' : 'Lead time for first production' }}: <span class="font-bold text-blue-600">{{ quote.first_production_days }}</span>.</li>
                        <li>{{ quote.is_spanish_template ? 'Fletes y acarreos' : 'Freight & handling' }}: <span class="font-bold text-blue-600">{{ quote.freight_option }}</span> <span :class="{ 'line-through': quote.is_freight_cost_stroked }">({{ formatNumber(quote.freight_cost) }} {{ quote.currency }})</span>.</li>
                        <li>{{ quote.is_spanish_template ? 'Precios en' : 'Prices in' }}: <span class="font-bold text-blue-600">{{ quote.currency }}</span>.</li>
                        <li>{{ quote.is_spanish_template ? 'Cotización válida por 21 días' : 'Quote valid for 21 days' }}.</li>
                    </ol>
                </section>
                
                <!-- Despedida -->
                <section class="mt-9 text-sm text-gray-700">
                     <p>
                        {{ quote.is_spanish_template ? 'Sin más por el momento y en espera de su preferencia, quedo a sus órdenes para cualquier duda o comentario.' : 'Without further ado and awaiting your preference, I remain at your service for any questions or comments.' }}
                    </p>
                    <div class="mt-4">
                        <p v-if="quote.user">{{ quote.is_spanish_template ? 'Creado por' : 'Created by' }}: <span class="font-semibold">{{ quote.user?.name }}</span> ({{ quote.user?.email }})</p>
                        <p v-else class="bg-orange-200 px-2 py-1">{{ quote.is_spanish_template ? 'Creado por cliente desde portal de clientes' : 'Created by customer' }}</p>
                    </div>
                </section>

            </div>
             <!-- Footer fuera del contenedor blanco principal -->
            <footer class="text-center text-gray-500 pt-8 pb-4 text-xs print:text-[9px]">
                <!-- Información Bancaria -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-px text-xs text-white text-center font-semibold rounded-lg overflow-hidden max-w-4xl mx-auto mb-6 print:grid-cols-2">
                    <div class="bg-sky-700 p-2 break-words">
                        <p><b>BANORTE M.N.</b> | {{ quote.is_spanish_template ? 'CUENTA' : 'ACCOUNT' }}: 1180403344 | CLABE: 072 320 011804033446</p>
                    </div>
                    <div class="bg-red-700 p-2 break-words">
                        <p><b>BANORTE USD</b> | {{ quote.is_spanish_template ? 'CUENTA' : 'ACCOUNT' }}: 1181103856 | CLABE: 072 320 011811038560</p>
                    </div>
                </div>

                <!-- Contacto -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-600 mb-4">
                    <div class="font-bold text-sm">
                         www.emblemas<b class="text-sky-600">3</b><b class="text-red-600">d</b>.com
                    </div>
                    <div>
                        <i class="fas fa-mobile-alt"></i> 333 46 46 485 <br>
                        <i class="fas fa-phone-alt"></i> (33) 38 33 82 09
                    </div>
                     <div>
                        <i class="fas fa-envelope"></i> j.sherman@emblemas<b class="text-sky-600">3</b><b class="text-red-600">d</b>.com
                    </div>
                </div>
                 <!-- Descripción de la empresa -->
                <p class="leading-tight max-w-4xl mx-auto mt-4 print:text-gray-500" style="font-size: 10px;">
                    {{ quote.is_spanish_template
                        ? 'Emblemas de alta calidad, somos los mejores fabricantes. Ramo automotriz, electrodomésticos, electrónica, textil, calzado, muebles y juguetes. En división electrónica, somos desarrolladores de tecnología. Conoce nuestras nuevas memorias USB personalizadas desde el molde, son exclusivos. En división automotriz somos fabricantes especialistas en emblemas cromados, porta placas, llaveros, porta documentos, placas de estireno. Lo nuevo, LLAVERO USB, diseño original y personalizado, todo con molde único para tu empresa (personalización total y exclusiva).'
                        : 'High-quality emblems, we are the best manufacturers. Automotive, home appliances, electronics, textile, footwear, furniture, and toys sectors. In the electronics division, we are technology developers. Discover our new custom USB drives from the mold, they are exclusive. In the automotive division, we are specialist manufacturers of chrome emblems, license plate holders, keychains, document holders, styrene plates. What\'s new, USB KEYCHAIN, original and personalized design, all with a unique mold for your company (total and exclusive customization).'
                    }}
                </p>
            </footer>
        </div>
    </div>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { ElMessage } from 'element-plus';
import { Head } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        return {
            showAdditionalElements: true,
            labelChanged: false,
        }
    },
    components: {
        AppLayout,
        ApplicationLogo,
        Head,
    },
    props: {
        quote: Object,
        next_quote: Number,
        prev_quote: Number,
    },
    computed: {
        tabTitle() {
            return `${this.quote.is_spanish_template ? 'Cotización' : 'Quote'} ${this.quote.id} - ${this.quote.branch.name}`;
        },
        approvedSubtotal() {
            return this.quote.products.reduce((acc, item) => {
                if (item.pivot.customer_approval_status === 'Aprobado') {
                    return acc + (item.pivot.quantity * item.pivot.unit_price);
                }
                return acc;
            }, 0);
        },
        total() {
            let currentTotal = this.approvedSubtotal;
            if (!this.quote.is_tooling_cost_stroked) {
                currentTotal += parseFloat(this.quote.tooling_cost);
            }
            if (!this.quote.is_freight_cost_stroked) {
                currentTotal += parseFloat(this.quote.freight_cost);
            }
            return currentTotal;
        },
    },
    methods: {
        printQuote() {
        this.showAdditionalElements = false;

            this.$nextTick(() => {
                setTimeout(() => {
                window.print();
                }, 200);
            });
        },
        handleBeforePrint() {
            this.showAdditionalElements = false;
        },
        handleAfterPrint() {
            this.showAdditionalElements = true;
        },
        setActiveImage(productIndex, imageIndex) {
            this.quote.products[productIndex].activeImageIndex = imageIndex;
        },
        nextImage(productIndex) {
            const product = this.quote.products[productIndex];
            const mediaCount = product.media.length;
            let currentIndex = product.activeImageIndex || 0;
            currentIndex = (currentIndex + 1) % mediaCount;
            product.activeImageIndex = currentIndex;
        },
        prevImage(productIndex) {
            const product = this.quote.products[productIndex];
            const mediaCount = product.media.length;
            let currentIndex = product.activeImageIndex || 0;
            currentIndex = (currentIndex - 1 + mediaCount) % mediaCount;
            product.activeImageIndex = currentIndex;
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            if (isNaN(num)) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        async authorize() {
            if (this.quote.authorized_by) return;

            try {
                const response = await axios.put(route('quotes.authorize', this.quote.id));

                if (response.status === 200) {
                    window.location.reload(); // salida facil.
                    // this.quote.authorized_by_user_id = response.data.authorized_by.id;
                    // this.quote.authorized_by_user = response.data.authorized_by;

                    ElMessage({
                        title: 'Éxito',
                        message: response.data.message,
                        type: 'success'
                    });
                } else {
                    ElMessage({
                        title: 'Error',
                        message: response.data.message || 'No se pudo completar la autorización.',
                        type: 'error'
                    });
                }
            } catch (err) {
                ElMessage({
                    title: 'Algo salió mal',
                    message: err.response?.data?.message || err.message,
                    type: 'error'
                });
                console.error(err);
            }
        },
    },
    mounted() {
        window.addEventListener('beforeprint', this.handleBeforePrint);
        window.addEventListener('afterprint', this.handleAfterPrint);
    },
    beforeDestroy() {
        window.removeEventListener('beforeprint', this.handleBeforePrint);
        window.removeEventListener('afterprint', this.handleAfterPrint);
    }
};
</script>
