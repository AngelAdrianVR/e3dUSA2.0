<template>
    <Head :title="tabTitle" />
    <div class="text-xs p-4 print:p-0">
        <!-- Encabezado y Logo -->
        <header class="text-center mb-4">
            <ApplicationLogo class="h-20 w-auto inline-block" />
        </header>

        <!-- Contenido Principal -->
        <main>
            <section v-if="$page.props.auth.user.permissions.includes('Descuentos cotizaciones')">
                <!-- boton de descuento de pago anticipado -->
                <figure v-if="quote.early_payment_discount && !quote.early_paid_at" class="ml-3 md:ml-16 my-2 w-[370px] relative">
                    <svg class="absolute top-3 left-3" width="16" height="19" viewBox="0 0 16 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7.15527 0C9.57271 0.953908 11.3524 2.23003 12.2705 7.08301C12.6323 7.25786 13.0912 6.56184 13.9756 5.11523C18.2995 13.5451 15.0251 18.2319 10.4346 18.3633H5.97461C2.30572 18.3633 -0.842483 14.5589 0.203125 8.52539C1.11282 9.8512 1.48847 9.99154 2.04004 9.70605C0.85957 5.11534 8.598 4.06604 7.15527 0ZM10.5732 6.05664C10.3949 5.99143 10.1997 6.06337 10.1035 6.21875L10.0684 6.29102C9.30024 8.39053 8.70097 10.0296 8.10156 11.668L6.13379 17.0459C6.05912 17.25 6.16407 17.4761 6.36816 17.5508C6.54675 17.616 6.74192 17.5436 6.83789 17.3877L6.87305 17.3164C7.64129 15.2165 8.24131 13.5771 8.84082 11.9385L10.8076 6.56055C10.8817 6.35675 10.7769 6.13129 10.5732 6.05664ZM10.7002 12.0654C9.68635 12.0656 8.86447 12.8885 8.86426 13.9023C8.86438 14.9163 9.6863 15.7381 10.7002 15.7383C11.7141 15.7381 12.537 14.9163 12.5371 13.9023C12.5369 12.8885 11.714 12.0656 10.7002 12.0654ZM10.7002 12.8525C11.2794 12.8528 11.7498 13.3231 11.75 13.9023C11.7499 14.4816 11.2795 14.951 10.7002 14.9512C10.1209 14.951 9.65149 14.4816 9.65137 13.9023C9.65158 13.3231 10.121 12.8528 10.7002 12.8525ZM6.50293 7.60645C5.48915 7.60667 4.6673 8.42863 4.66699 9.44238C4.66699 10.4564 5.48897 11.2791 6.50293 11.2793C7.5169 11.2791 8.33984 10.4564 8.33984 9.44238C8.33954 8.42862 7.51671 7.60666 6.50293 7.60645ZM6.50293 8.39355C7.08207 8.39377 7.55243 8.86326 7.55273 9.44238C7.55273 10.0218 7.08226 10.492 6.50293 10.4922C5.9236 10.492 5.4541 10.0218 5.4541 9.44238C5.45441 8.86326 5.92379 8.39378 6.50293 8.39355Z" fill="#BC0B0B"/>
                    </svg>
                    <button class="flex items-center justify-center absolute top-[8px] right-3 bg-[#F2F2F2] rounded-full size-7 cursor-default">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3 text-black">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                        </svg>
                    </button>
                    <p class="text-[#005660] absolute left-12 top-2 text-xs text-center">PAGA ESTA COTIZACIÓN POR ADELANTADO <br> Y RECIBE UN {{ quote.discount }}% DE DESCUENTO EXCLUSIVO</p>
                    <img draggable="false" class="w-[370px] h-12" src="@/../../public/images/earlyPaymentButton.webp" alt="">
                </figure>
                
                <!-- descuento por pago anticipado aplicado -->
                <div v-else-if="quote.early_payment_discount && showAdditionalElements" class="my-2 flex items-center gap-3 p-4 rounded-xl bg-green-100 border border-green-300 text-green-800 shadow-md max-w-lg mx-auto mt-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 flex-shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <p class="text-sm font-semibold">
                        ¡Descuento por pago anticipado del {{ quote.discount }}% aplicado correctamente!
                    </p>
                </div>
            </section>
            <!-- Información General -->
            <section class="mb-4">
                <!-- botones para cambiar de cot -->
                <div v-show="showAdditionalElements" class="flex items-center justify-center space-x-8 mt-5 dark:text-white">
                    <button @click="$inertia.visit(route('quotes.show', prev_quote))"
                        class="bg-gray-300 text-gray-800 size-5 text-[9px] rounded-full">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="text-xs">COT-{{ quote.id.toString().padStart(4, '0') }}</span>
                    <button @click="$inertia.visit(route('quotes.show', next_quote))"
                        class="bg-gray-300 text-gray-800 size-5 text-[9px] rounded-full">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
                <p class="flex justify-end font-bold text-gray-700 ">
                    Guadalajara, Jalisco {{ formatDate(quote.created_at) }}
                    <i v-show="showAdditionalElements" @click="authorize"
                        :title="quote.authorized_by_user_id ? 'Cotización autorizada' : 'Autorizar cotización'"
                        v-if="$page.props.auth.user?.permissions?.includes('Autorizar cotizaciones')"
                        class="fa-solid fa-check ml-3"
                        :class="quote.authorized_by_user_id ? 'text-green-500' : 'hover:text-green-500 cursor-pointer'">
                    </i>
                </p>
                <div class="w-11/12 mx-auto mt-4">
                    <p class="text-lg font-bold text-gray-700 ">{{ quote.branch?.name }}</p>
                    <p class="font-bold mt-2 text-gray-700 ">
                        {{ quote.is_spanish_template ? 'Estimado(a)' : 'Dear' }} {{ quote.receiver }} - {{ quote.department }}
                    </p>
                    <p class="my-2 pb-2 text-gray-700 ">
                        {{ quote.is_spanish_template 
                            ? 'Por medio de la presente reciba un cordial saludo y a su vez le proporciono la cotización que nos solicitó, con base en la plática sostenida con ustedes y sabiendo de sus condiciones del producto a aplicar:' 
                            : 'Through this letter, receive a cordial greeting, and at the same time, I provide you with the quote you requested, based on our conversation and understanding the conditions of the product to be applied:'
                        }}
                    </p>
                </div>
            </section>

            <!-- Tabla de Productos -->
            <section class="w-11/12 mx-auto">
                <table class="w-full bg-gray-300 text-gray-800" style="font-size: 10.2px;">
                    <thead class="rounded-t-lg">
                        <tr class="text-left border-b-2 border-gray-400">
                            <th class="px-2 py-1">{{ quote.is_spanish_template ? 'Concepto' : 'Concept' }}</th>
                            <th class="px-2 py-1">{{ quote.is_spanish_template ? 'Notas' : 'Notes' }}</th>
                            <th class="px-2 py-1">{{ quote.is_spanish_template ? '$ unitario' : 'Unit $' }}</th>
                            <th class="px-2 py-1">
                                <span v-if="quote.is_spanish_template">{{ !labelChanged ? 'Unidades' : 'MOQ' }}</span>
                                <span v-else>{{ !labelChanged ? 'Units' : 'MOQ' }}</span>
                                <button v-show="showAdditionalElements" @click="labelChanged = !labelChanged"
                                    class="rounded-full size-4 bg-black text-white">
                                    <i class="fa-solid fa-shuffle"></i>
                                </button>
                            </th>
                            <th class="px-2 py-1 text-right">{{ quote.is_spanish_template ? 'Total sin IVA' : 'Total before taxes' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in quote.products" :key="item.id" class="text-gray-700 uppercase"
                            :class="item.customer_approval_status === 'Aprobado' ? 'bg-green-200' : 'bg-gray-200'">
                            <td class="px-2 py-1">
                                <b v-if="item.pivot.customer_approval_status === 'Aprobado'">{{ quote.is_spanish_template ? '(ACEPTADO)' : '(APPROVED)' }} </b>
                                {{ item.name }}
                            </td>
                            <td class="px-2 py-1">{{ item.pivot.notes ?? '--' }}</td>
                            <td class="px-2 py-1">{{ formatNumber(item.pivot.unit_price) }} {{ quote.currency }}</td>
                            <td class="px-2 py-1">{{ item.pivot.quantity }}</td>
                            <td class="px-2 py-1 text-right">{{ formatNumber(item.pivot.quantity * item.pivot.unit_price) }} {{ quote.currency }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="quote.show_breakdown">
                        <tr>
                            <td class="text-right pr-2 py-px" colspan="5">
                                {{ quote.is_spanish_template ? 'SUBTOTAL (PRODUCTOS APROBADOS)' : 'SUBTOTAL (APPROVED PRODUCTS)' }}: {{ formatNumber(approvedSubtotal) }} {{ quote.currency }}
                            </td>
                        </tr>
                        <tr v-if="!quote.is_freight_cost_stroked">
                            <td class="text-right pr-2 py-px" colspan="5">
                                {{ quote.is_spanish_template ? 'COSTO DE FLETE' : 'FREIGHT COST' }}: {{ formatNumber(quote.freight_cost) }} {{ quote.currency }}
                            </td>
                        </tr>
                        <tr v-if="!quote.is_tooling_cost_stroked">
                            <td class="text-right pr-2 py-px" colspan="5">
                                {{ quote.is_spanish_template ? 'COSTO DE HERRAMENTAL' : 'TOOLING COST' }}: {{ formatNumber(quote.tooling_cost) }} {{ quote.currency }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-right pr-2 py-px font-bold" colspan="5">
                                {{ quote.is_spanish_template ? 'TOTAL SIN IVA' : 'TOTAL BEFORE TAXES' }}: {{ formatNumber(total) }} {{ quote.currency }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <div class="w-11/12 mx-auto my-3 grid grid-cols-3 gap-4 ">
                <!-- Images de los productos de catalogo -->
                <template v-for="(item, productIndex) in quote.products" :key="item.id">
                    <div v-if="item.pivot.show_image || true" 
                        class="bg-gray-200 dark:bg-gray-500 dark:text-gray-300 rounded-t-xl rounded-b-md border"
                        style="font-size: 9px;">
                        
                        <!-- Imagen actual -->
                        <img class="rounded-t-xl max-h-52 mx-auto"
                            :src="item.media[item.activeImageIndex || 0]?.original_url">

                        <!-- selector de imagen cuando son varias -->
                        <div v-if="item.media?.length > 1" class="my-3 flex items-center justify-center space-x-3">
                        <i v-for="(image, index) in item.media" :key="index"
                            @click="setActiveImage(productIndex, index)"
                            :class="(item.activeImageIndex || 0) === index ? 'text-black' : 'text-white'"
                            class="fa-solid fa-circle text-[7px] cursor-pointer"></i>
                        </div>

                        <p class="py-px px-1 uppercase text-gray-600 dark:text-gray-300">{{ item.name }}</p>

                        <p v-if="item.large || item.height || item.width || item.diameter"
                        class="py-px px-1 uppercase text-gray-600 dark:text-gray-300 font-bold">Dimensiones:</p>
                        <div class="flex items-center space-x-3 *:dark:text-gray-300">
                        <p v-if="item.large" class="py-px px-1 text-gray-600">Largo: {{ item.large }}mm</p>
                        <p v-if="item.height" class="py-px px-1 text-gray-600">Alto: {{ item.height }}mm</p>
                        <p v-if="item.width" class="py-px px-1 text-gray-600">Ancho: {{ item.width }}mm</p>
                        <p v-if="item.diameter" class="py-px px-1 text-gray-600">Diámetro: {{ item.diameter }}mm</p>
                        </div>
                    </div>
                </template>

            </div>

            <!-- Notas Importantes -->
            <section class="w-11/12 mx-auto border border-gray-500 px-3 pb-1 mt-6 rounded-xl text-gray-500 leading-normal uppercase" style="font-size: 10.5px;">
                <h2 class="text-center font-extrabold">{{ quote.is_spanish_template ? 'IMPORTANTE' : 'IMPORTANT' }} <i class="fas fa-exclamation-circle text-amber-500"></i></h2>
                <ol class="list-decimal mx-2 mb-2">
                    <li v-if="quote.notes" class="font-bold text-blue-500 whitespace-pre-line">{{ quote.notes }}</li>
                    <li>{{ quote.is_spanish_template ? 'PRECIOS ANTES DE IVA' : 'PRICES BEFORE TAXES' }}</li>
                    <li>{{ quote.is_spanish_template ? 'COSTO DE HERRAMENTAL' : 'TOOLING COST' }}: <span class="font-bold text-blue-500" :class="{ 'line-through': quote.is_tooling_cost_stroked }">{{ formatNumber(quote.tooling_cost) }} {{ quote.currency }}</span></li>
                    <li>{{ quote.is_spanish_template ? 'TIEMPO DE ENTREGA PARA LA PRIMER PRODUCCIÓN' : 'LEAD TIME FOR FIRST PRODUCTION' }} <span class="font-bold text-blue-500">{{ quote.first_production_days }}</span>.</li>
                    <li>{{ quote.is_spanish_template ? 'FLETES Y ACARREOS' : 'FREIGHT & HANDLING' }}: <span class="font-bold text-blue-500">{{ quote.freight_option }}</span> <span class="font-bold text-blue-500" :class="{ 'line-through': quote.is_freight_cost_stroked }">({{ formatNumber(quote.freight_cost) }} {{ quote.currency }})</span></li>
                    <li>{{ quote.is_spanish_template ? 'PRECIOS EN' : 'PRICES IN' }} <span class="font-bold text-blue-500">{{ quote.currency }}</span></li>
                    <li>{{ quote.is_spanish_template ? 'COTIZACIÓN VÁLIDA POR 21 DÍAS.' : 'QUOTE VALID FOR 21 DAYS.' }}</li>
                </ol>
            </section>

            <!-- Despedida y Firma -->
            <section class="mx-10 mt-9">
                <p class="w-11/12 mx-auto my-2 pb-2 text-gray-700 ">
                    {{ quote.is_spanish_template ? 'Sin más por el momento y en espera de su preferencia, quedo a sus órdenes para cualquier duda o comentario.' : 'Without further ado and awaiting your preference, I remain at your service for any questions or comments.' }}
                    <br>
                    {{ quote.is_spanish_template ? 'Folio de cotización' : 'Quote Folio' }}: <span class="font-bold bg-yellow-100 px-1 rounded">COT-{{ quote.id.toString().padStart(4, '0') }}</span>
                </p>
            </section>

            <!-- MODIFICADO: Información Bancaria con traducción -->
            <section class="grid grid-cols-2 gap-0 text-xs mt-4 font-semibold" style="font-size: 10px;">
                <div class="bg-sky-600 text-white p-1 flex justify-between rounded-l-xl">
                    <span>BANORTE M.N.</span>
                    <span>{{ quote.is_spanish_template ? 'CUENTA' : 'ACCOUNT' }}: 1180403344</span>
                    <span>CLABE: 072 320 011804033446</span>
                </div>
                <div class="bg-red-600 text-white p-1 flex justify-between rounded-r-xl">
                    <span>BANORTE USD</span>
                    <span>{{ quote.is_spanish_template ? 'CUENTA' : 'ACCOUNT' }}: 1181103856</span>
                    <span>CLABE: 072 320 011811038560</span>
                </div>
            </section>

            <!-- MODIFICADO: Creador y Autorización con traducción -->
            <section class="mt-2 text-gray-700 dark:text-white flex justify-around" style="font-size: 11px;">
                <div>
                    {{ quote.is_spanish_template ? 'Creado por' : 'Created by' }}: {{ quote.user.name }} <br>
                    {{ quote.is_spanish_template ? 'Correo' : 'Email' }}: {{ quote.user.email }}
                </div>
                <div>
                    {{ quote.is_spanish_template ? 'Autorizado por' : 'Authorized by' }}:
                    <span v-if="quote.authorized_by_user" class="text-green-600">{{ quote.authorized_by_user.name }}</span>
                    <span v-else class="text-amber-500">{{ quote.is_spanish_template ? 'Sin autorización' : 'Not authorized' }}</span>
                </div>
            </section>

            <!-- MODIFICADO: Pie de Página con traducción -->
            <footer class="text-gray-400 w-11/12 mx-auto mt-6" style="font-size: 11px;">
                 <div class="grid grid-cols-3 gap-x-4">
                    <div class="text-center text-sm font-bold">
                        {{ quote.is_spanish_template ? 'Especialistas en Emblemas de alta calidad' : 'Specialists in High-Quality Emblems' }}
                    </div>
                    <div>
                        <i class="fas fa-mobile-alt"></i> 333 46 46 485 <br>
                        <i class="fas fa-phone-alt"></i> (33) 38 33 82 09
                    </div>
                    <div>
                        <i class="fas fa-globe"></i> www.emblemas<b class="text-sky-600">3</b><b class="text-red-600">d</b>.com
                        <br>
                        <i class="fas fa-envelope"></i> j.sherman@emblemas<b class="text-sky-600">3</b><b class="text-red-600">d</b>.com
                    </div>
                </div>
                <div class="flex mt-3">
                    <p class="leading-tight mr-1" style="font-size: 10px;">
                        {{ quote.is_spanish_template 
                            ? 'Emblemas de alta calidad, somos los mejores fabricantes. Ramo automotriz, electrodomésticos, electrónica, textil, calzado, muebles y juguetes. En división electrónica, somos desarrolladores de tecnología. Conoce nuestras nuevas memorias USB personalizadas desde el molde, son exclusivos. En división automotriz somos fabricantes especialistas en emblemas cromados, porta placas, llaveros, porta documentos, placas de estireno. Lo nuevo, LLAVERO USB, diseño original y personalizado, todo con molde único para tu empresa (personalización total y exclusiva).'
                            : 'High-quality emblems, we are the best manufacturers. Automotive, home appliances, electronics, textile, footwear, furniture, and toys sectors. In the electronics division, we are technology developers. Discover our new custom USB drives from the mold, they are exclusive. In the automotive division, we are specialist manufacturers of chrome emblems, license plate holders, keychains, document holders, styrene plates. What\'s new, USB KEYCHAIN, original and personalized design, all with a unique mold for your company (total and exclusive customization).'
                        }}
                    </p>
                </div>
            </footer>
        </main>
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
    data(){
        return {
            showAdditionalElements: true,
            labelChanged: false,
            currentCatalogProductImages: [], // Array to store current image index for each product
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
        handleBeforePrint() {
            this.showAdditionalElements = false;
        },
        handleAfterPrint() {
            this.showAdditionalElements = true;
        },
        setActiveImage(productIndex, imageIndex) {
            this.quote.products[productIndex].activeImageIndex = imageIndex;
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
            if (!this.quote.authorized_by_user_id) {
                try {
                    const response = await axios.put(route('quotes.authorize', this.quote.id));

                    if (response.status == 200) {
                        ElMessage({
                            title: 'Éxito',
                            message: response.data.message,
                            type: 'success'
                        });
                    } else {
                        ElMessage({
                            title: 'Algo salió mal',
                            message: response.data.message,
                            type: 'error'
                        });
                    }
                } catch (err) {
                    ElMessage({
                        title: 'Algo salió mal',
                        message: err.message,
                        type: 'error'
                    });
                    console.log(err);
                } finally {
                    // this.$inertia.get(route('quotes.index'));
                }
            }
        },
        // Método para procesar la URL de la imagen
        procesarUrlImagen(originalUrl) {
            // Reemplaza la parte inicial de la URL 
            const nuevaUrl = originalUrl?.replace('https://intranetemblems3d.dtw.com.mx', 'https://clientes-emblems3d.dtw.com.mx');
            return nuevaUrl;
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
