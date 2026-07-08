<template>
    <div class="bg-white border rounded-lg overflow-hidden transition-shadow hover:shadow-xl flex flex-col md:flex-row print:flex-col print:break-inside-avoid"
         :class="{'print:hidden': item.customer_approval_status === 'Rechazado' }">
        
        <!-- Imagen y Selector -->
        <div class="bg-gray-100 relative group md:w-40 print:w-full print:h-36 flex-shrink-0 flex items-center justify-center">
            <img v-if="item.show_image && productMedia?.length" 
                draggable="false"
                class="rounded-md w-full h-40 md:h-full print:h-full object-contain print:object-contain mx-auto"
                :src="productMedia[activeImageIndex]?.original_url"
                :alt="item.product ? item.product.name : item.custom_name"
                @error="handleImageError">

            <!-- Contenedor alternativo si no hay imagen -->
            <div v-else class="flex items-center justify-center w-full h-40 md:h-full print:h-20 rounded-md bg-gray-200 text-gray-500 text-sm print:text-xs font-semibold italic">
                {{ quote.is_spanish_template ? 'Sin imagen' : 'No image' }}
            </div>

            <!-- Status Badge -->
            <span v-if="item.customer_approval_status === 'Aprobado'"
                  class="absolute top-2 right-2 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md print:hidden">
                {{ quote.is_spanish_template ? 'ACEPTADO' : 'APPROVED' }}
            </span>
            <span v-if="item.customer_approval_status === 'Rechazado'"
                  class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md print:hidden">
                {{ quote.is_spanish_template ? 'RECHAZADO' : 'REJECTED' }}
            </span>

            <!-- BOTÓN FLOTANTE PARA HISTORIAL (SOLO PRODUCTOS DE CATÁLOGO) -->
            <div class="absolute bottom-1 right-1 z-20 print:hidden" v-if="item.product">
                 <el-popover placement="right" :width="280" trigger="click">
                    <template #reference>
                        <button class="text-xs bg-sky-600 hover:bg-sky-500 text-white rounded-full shadow-md transition-colors px-2 py-1 flex items-center justify-center ring-2 ring-white" title="Ver historial y actualizar precio">
                            Historial de precios
                        </button>
                    </template>
                    
                    <div class="text-xs text-gray-700">
                         <div class="flex justify-between items-center mb-2 border-b pb-1">
                            <h4 class="font-bold dark:text-gray-300">Historial de Precios</h4>
                            <i class="fa-solid fa-clock-rotate-left" :class="lastUpdateInfo?.colorClass" :title="lastUpdateInfo?.text"></i>
                         </div>
                         
                         <div class="mb-2" v-if="lastUpdateInfo?.text !== 'Sin precio vigente'">
                            <p class="mb-1 text-[10px] dark:text-gray-300">{{ lastUpdateInfo?.text }}</p>
                         </div>

                         <!-- Caso: No hay historial de precios para este cliente -->
                         <div v-if="!item.product?.price_history?.length" class="text-gray-500 italic text-center py-4 bg-gray-50 rounded border border-gray-100">
                            <i class="fa-solid fa-circle-info text-blue-400 mb-2 text-lg block"></i>
                            Este producto no está registrado para este cliente, por lo tanto no cuenta con un historial.
                         </div>

                         <!-- Caso: Hay historial de precios -->
                         <ul v-else class="max-h-32 overflow-y-auto space-y-1 mb-3 pr-1">
                            <li v-for="history in item.product?.price_history" :key="history.id" class="flex justify-between items-center bg-gray-50 p-1.5 rounded border border-gray-100">
                                <span class="text-gray-500 text-[10px]">{{ formatDate(history.valid_from) }}</span>
                                <div class="text-right">
                                    <span class="font-bold block text-gray-800">${{ formatNumber(history.price) }} {{ history.currency }}</span>
                                    <span v-if="!history.valid_to" class="text-[9px] text-green-600 font-bold bg-green-50 px-1 rounded">ACTUAL</span>
                                </div>
                            </li>
                         </ul>

                         <!-- BOTONES DINÁMICOS SEGÚN EL REGISTRO -->
                         <button v-if="!item.product?.price_history?.length" @click="goToBranchProducts" class="w-full mt-3 bg-indigo-500 hover:bg-indigo-600 text-white py-1.5 rounded transition shadow-sm text-center font-bold flex items-center justify-center">
                            <i class="fa-solid fa-plus mr-2"></i> Registrar Producto
                         </button>
                         <button v-else @click="openPriceModal" class="w-full mt-3 bg-amber-500 hover:bg-amber-600 text-white py-1.5 rounded transition shadow-sm text-center font-bold flex items-center justify-center">
                            <i class="fa-solid fa-pen-to-square mr-2"></i> Actualizar Precio
                         </button>
                         
                         <p v-if="!item.product?.price_history?.length" class="text-[9px] text-gray-400 text-center mt-1">Ir a productos del cliente</p>
                    </div>
                 </el-popover>
            </div>

            <!-- Botones de Navegación de Imagen -->
            <div v-if="productMedia?.length > 1 && item.show_image" v-show="showAdditionalElements">
                <button @click="prevImage" 
                        class="absolute left-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-20 text-white size-8 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button @click="nextImage" 
                        class="absolute right-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-20 text-white size-8 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Contenido de la tarjeta -->
        <div class="p-3 print:p-2 flex flex-col flex-grow">
            <h3 class="font-bold text-base text-gray-800 uppercase print:text-[11px] print:leading-tight">
                {{ item.product ? item.product.name : item.custom_name }}
                <span v-if="!item.product" class="text-[10px] ml-1 bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full normal-case tracking-normal print:hidden border border-blue-200">Nuevo</span>
            </h3>
            
            <p v-if="item.notes" class="text-xs text-gray-600 mt-1 flex-grow italic print:text-[9px] print:leading-tight print:mt-0">"{{ item.notes }}"</p>
            
            <ul v-if="item.customization_details?.length" class="text-[10px] text-gray-500 mt-1 print:text-[8px] list-disc list-inside">
                <li v-for="(detail, i) in item.customization_details" :key="i">
                    {{ detail.type }}: <strong>{{ detail.key }}</strong> = {{ detail.value }}
                </li>
            </ul>
            
            <div class="mt-2 pt-2 border-t print:mt-1 print:pt-1 grid grid-cols-2 gap-x-2 text-xs print:text-[9px]">
                <!-- Dimensiones -->
                <div class="space-y-1 print:space-y-0" v-if="item.product && (item.product.large || item.product.height || item.product.width || item.product.diameter)">
                    <p v-if="item.product.large">{{ quote.is_spanish_template ? 'Largo' : 'Length' }}: <span class="font-semibold">{{ item.product.large }}mm</span></p>
                    <p v-if="item.product.height">{{ quote.is_spanish_template ? 'Alto' : 'Height' }}: <span class="font-semibold">{{ item.product.height }}mm</span></p>
                    <p v-if="item.product.width">{{ quote.is_spanish_template ? 'Ancho' : 'Width' }}: <span class="font-semibold">{{ item.product.width }}mm</span></p>
                    <p v-if="item.product.diameter">{{ quote.is_spanish_template ? 'Diámetro' : 'Diameter' }}: <span class="font-semibold">{{ item.product.diameter }}mm</span></p>
                </div>

                <!-- Precios -->
                <div class="space-y-1 print:space-y-0" :class="{ 'col-span-2': !item.product || (!item.product.large && !item.product.height && !item.product.width && !item.product.diameter) }">
                    <div class="flex justify-between">
                        <span>{{ !labelChanged ? (quote.is_spanish_template ? 'Unidades' : 'Units') : 'MOQ' }}</span>
                        <span class="font-semibold">{{ Number(item.quantity).toLocaleString() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ quote.is_spanish_template ? 'P. Unit.' : 'Unit P.' }}</span>
                        <span class="font-semibold">${{ formatNumber(item.unit_price) }}</span>
                    </div>
                </div>
            </div>
            
            <!-- TOTAL CON TOGGLE DE IVA -->
            <div class="mt-1 pt-1 border-t print:border-t-gray-200">
                <div class="flex justify-between items-center text-sm print:text-[10px]">
                    <div class="flex items-center gap-2">
                        <span class="font-bold">{{ quote.is_spanish_template ? 'Total' : 'Total' }}</span>
                        <button v-show="showAdditionalElements" 
                                @click="showIva = !showIva"
                                class="text-[9px] px-1.5 py-0.5 rounded border transition-colors print:hidden outline-none focus:outline-none"
                                :class="showIva ? 'bg-sky-100 text-sky-700 border-sky-300 font-bold' : 'bg-gray-100 text-gray-500 border-gray-300 hover:bg-gray-200'"
                                title="Agregar/Quitar IVA al desglose de esta tarjeta">
                            + IVA
                        </button>
                    </div>
                    
                    <span v-if="!showIva" class="font-bold text-sky-700">
                        ${{ formatNumber(item.quantity * item.unit_price) }}
                    </span>
                </div>

                <div v-if="showIva" class="mt-1 text-[10px] print:text-[9px] leading-tight space-y-0.5">
                    <div class="flex justify-between text-gray-500">
                        <span>Subtotal:</span>
                        <span>${{ formatNumber(item.quantity * item.unit_price) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>IVA (16%):</span>
                        <span>${{ formatNumber((item.quantity * item.unit_price) * 0.16) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-sky-700 pt-0.5 mt-0.5 border-t border-gray-100 text-xs print:text-[10px]">
                        <span>{{ quote.is_spanish_template ? 'Total Neto' : 'Net Total' }}:</span>
                        <span>${{ formatNumber((item.quantity * item.unit_price) * 1.16) }}</span>
                    </div>
                </div>
            </div>

            <!-- ALERTA INTERNA DE PRECIO BAJO (Solo Uso Interno - Oculto al imprimir) -->
            <div v-if="item.has_low_price && showAdditionalElements" class="mt-3 p-2 rounded border print:hidden" :class="!quote.authorized_by_user_id ? 'bg-amber-50 border-amber-200' : 'bg-green-50 border-green-200'">
                <div class="flex items-start gap-2 mb-1">
                    <i class="fa-solid mt-0.5 text-[14px]" :class="!quote.authorized_by_user_id ? 'fa-triangle-exclamation text-amber-500' : 'fa-check-circle text-green-500'"></i>
                    <div class="flex-1">
                        <p class="text-xs font-bold leading-tight" :class="!quote.authorized_by_user_id ? 'text-amber-800' : 'text-green-800'">
                            {{ !quote.authorized_by_user_id ? 'Precio por debajo de lo establecido' : 'Precio especial autorizado' }}
                        </p>
                        <div class="flex justify-between text-[10px] mt-1">
                            <span class="text-gray-500">Precio actual: <strong class="text-gray-700">${{ formatNumber(getEstablishedPrice(item)) }}</strong></span>
                            <span class="text-gray-500">Ofrecido: <strong :class="!quote.authorized_by_user_id ? 'text-red-600' : 'text-green-700'">${{ formatNumber(item.unit_price) }}</strong></span>
                        </div>
                        <p class="text-[10px] text-gray-600 italic mt-1 leading-tight"><span class="font-semibold not-italic">Justificación:</span> {{ item.low_price_reason }}</p>
                    </div>
                </div>
            </div>
            
            <!-- Botones de Aprobación -->
            <div class="mt-auto pt-3 print:hidden flex items-center space-x-2" v-show="showAdditionalElements">
                <button @click="$emit('update-status', item, item.customer_approval_status === 'Aprobado' ? 'Pendiente' : 'Aprobado')"
                    :disabled="item.customer_approval_status === 'Rechazado'"
                    class="w-full text-center py-2 px-4 rounded-lg transition-all duration-300 ease-in-out text-sm font-bold flex items-center justify-center space-x-2 shadow-sm hover:shadow-md disabled:shadow-none disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 transform hover:-translate-y-0.5"
                    :class="{'bg-gradient-to-r from-green-500 to-teal-600 text-white': item.customer_approval_status === 'Aprobado', 'bg-gray-200 text-gray-700 hover:bg-gray-300': item.customer_approval_status === 'Pendiente'}">
                    
                    <div class="w-5 h-5 flex items-center justify-center rounded-full transition-transform duration-300"
                        :class="{'bg-white/30': item.customer_approval_status === 'Aprobado', 'border-2 border-gray-400 rotate-180': item.customer_approval_status === 'Pendiente'}">
                        <i v-if="item.customer_approval_status === 'Aprobado'" class="fa-solid fa-check text-white text-xs"></i>
                    </div>

                    <span v-if="item.customer_approval_status === 'Aprobado'">{{ quote.is_spanish_template ? 'Aprobado' : 'Approved' }}</span>
                    <span v-else>{{ quote.is_spanish_template ? 'Aprobar' : 'Approve' }}</span>
                </button>
                
                <button @click="$emit('update-status', item, item.customer_approval_status === 'Rechazado' ? 'Pendiente' : 'Rechazado')"
                    :title="item.customer_approval_status === 'Rechazado' ? 'Mover a pendiente' : 'Rechazar producto'"
                    class="flex-shrink-0 size-9 rounded-lg transition-colors duration-200 flex items-center justify-center"
                    :class="{'bg-red-100 text-red-600 hover:bg-red-200': item.customer_approval_status !== 'Rechazado', 'bg-gray-200 text-gray-700 hover:bg-gray-300': item.customer_approval_status === 'Rechazado'}">
                    <i class="fa-solid" :class="{'fa-times': item.customer_approval_status !== 'Rechazado', 'fa-undo': item.customer_approval_status === 'Rechazado'}"></i>
                </button>
            </div>
        </div>

        <!-- Modal para actualizar precio especial -->
        <ConfirmationModal :show="showPriceModal" @close="showPriceModal = false">
            <template #title>
                Actualizar precio de <span class="text-blue-500">{{ item.product?.name }}</span>
            </template>
            <template #content>
                <div class="space-y-4 text-sm dark:text-gray-300 text-left">
                    <p v-if="canBypassPriceRule" class="text-green-600 dark:text-green-400 text-xs mt-1 font-semibold p-2 bg-green-50 dark:bg-green-900/20 rounded-md">
                        <i class="fa-solid fa-unlock mr-1"></i> Tienes permisos especiales para asignar cualquier precio sin restricción.
                    </p>
                    <p v-else>El precio de referencia actual es <strong class="font-semibold">${{ priceForm.current_base_price }}</strong>. El nuevo precio no puede ser inferior al actual y el aumento debe ser de al menos 4%.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                        <div>
                            <label class="font-semibold">Aumento en porcentaje*</label>
                                <el-input v-model="priceForm.percentage" @input="updatePriceFromPercentage" placeholder="Ej. 5" class="mt-1">
                                <template #append>%</template>
                            </el-input>
                        </div>
                            <div>
                            <label class="font-semibold">Precio nuevo en moneda*</label>
                                <el-input v-model="priceForm.amount" @input="updatePriceFromAmount" placeholder="Ej. 44.10" class="mt-1">
                                <template #prepend>$</template>
                            </el-input>
                        </div>
                    </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold">Moneda*</label>
                            <el-select v-model="priceForm.currency" placeholder="Moneda" :teleported="false" class="!w-full mt-1">
                                <el-option label="MXN" value="MXN" />
                                <el-option label="USD" value="USD" />
                            </el-select>
                        </div>
                        <div>
                            <label class="font-semibold">Fecha de cambio (Vigente desde)*</label>
                            <el-date-picker v-model="priceForm.valid_from" type="date" :teleported="false" placeholder="Selecciona una fecha" class="!w-full mt-1" />
                        </div>
                    </div>
                    <!-- MENSAJE DE ERROR -->
                    <div v-if="priceForm.amount && isPriceInvalid && !canBypassPriceRule" class="text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>
                        El precio debe ser mayor o igual a ${{ priceForm.min_allowed_price.toFixed(2) }} (aumento mínimo del 4%).
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showPriceModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="submitNewPrice" :disabled="isPriceInvalid" class="!bg-blue-600 hover:!bg-blue-700 disabled:!bg-blue-300 dark:disabled:!bg-slate-600">Actualizar precio</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

    </div>
</template>

<script>
import { format, formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { router } from '@inertiajs/vue3';
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import axios from 'axios';
import { ElMessage } from 'element-plus';

export default {
    name: 'QuoteProductCard',
    components: {
        ConfirmationModal,
        CancelButton,
        PrimaryButton
    },
    props: {
        item: { type: Object, required: true },
        quote: { type: Object, required: true },
        showAdditionalElements: { type: Boolean, default: true },
        labelChanged: { type: Boolean, default: false }
    },
    emits: ['update-status'],
    data() {
        return {
            showIva: false,
            activeImageIndex: 0,
            showPriceModal: false,
            priceForm: {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: 0,
                min_allowed_price: 0,
            },
        }
    },
    computed: {
        productMedia() {
            return this.item.product ? this.item.product.media : this.item.media;
        },
        canBypassPriceRule() {
            return this.$page.props.auth?.user?.permissions?.includes('Cambiar precio especial') || false;
        },
        isPriceInvalid() {
            if (!this.priceForm.amount || this.priceForm.amount <= 0) return true;
            if (this.canBypassPriceRule) return false;
            return this.priceForm.amount < this.priceForm.min_allowed_price;
        },
        lastUpdateInfo() {
            const product = this.item.product;
            if (!product || !product.price_history || product.price_history.length === 0) return { text: 'Sin precio vigente', colorClass: 'text-gray-400' };

            const activePrice = product.price_history.find(h => h.valid_to === null);
            if (!activePrice) return { text: 'Sin precio vigente', colorClass: 'text-gray-400' };

            const fromDate = new Date(activePrice.valid_from);
            const now = new Date();
            const monthsDiff = (now.getFullYear() - fromDate.getFullYear()) * 12 + (now.getMonth() - fromDate.getMonth());

            let colorClass = monthsDiff < 6 ? 'text-green-600' : (monthsDiff < 12 ? 'text-orange-500' : 'text-red-500');
            const relativeTime = formatDistanceToNow(fromDate, { addSuffix: true, locale: es });

            return { text: `Actualizado ${relativeTime}`, colorClass };
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
        goToBranchProducts() {
            router.visit(route('branches.show', { branch: this.quote.branch.id, tab: 'products' }));
        },
        nextImage() {
            const mediaCount = this.productMedia?.length || 0;
            if (mediaCount === 0) return;
            this.activeImageIndex = (this.activeImageIndex + 1) % mediaCount;
        },
        prevImage() {
            const mediaCount = this.productMedia?.length || 0;
            if (mediaCount === 0) return;
            this.activeImageIndex = (this.activeImageIndex - 1 + mediaCount) % mediaCount;
        },
        formatDate(dateString) {
            if (!dateString) return '';
            return format(new Date(dateString), "d 'de' MMMM, yyyy", { locale: es });
        },
        formatNumber(value) {
            if (value === null || value === undefined || value === '') return '0.00';
            const num = Number(value);
            return isNaN(num) ? value : new Intl.NumberFormat('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(num);
        },
        getEstablishedPrice(item) {
            if (!item.product_id) return item.custom_cost || 0;
            
            if (item.product?.price_history && item.product.price_history.length > 0) {
                const validPrice = item.product.price_history.find(h => !h.valid_to);
                if (validPrice) {
                    return validPrice.price;
                }
            }
            return item.product?.base_price || 0;
        },
        openPriceModal() {
            const product = this.item.product;
            const basePrice = product.price_history?.[0]?.price ?? product.base_price;
            
            this.priceForm = {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: basePrice,
                min_allowed_price: basePrice * 1.04, 
            };
            this.showPriceModal = true;
        },
        updatePriceFromAmount() {
            if (this.priceForm.amount && this.priceForm.current_base_price > 0) {
                const percentage = ((this.priceForm.amount / this.priceForm.current_base_price) - 1) * 100;
                this.priceForm.percentage = percentage.toFixed(2);
            } else {
                this.priceForm.percentage = null;
            }
        },
        updatePriceFromPercentage() {
            if (this.priceForm.percentage !== null && this.priceForm.percentage !== '') {
                const newAmount = this.priceForm.current_base_price * (1 + (this.priceForm.percentage / 100));
                this.priceForm.amount = newAmount.toFixed(2);
            } else {
                this.priceForm.amount = null;
            }
        },
        async submitNewPrice() {
            if (this.isPriceInvalid) {
                ElMessage.error('El precio ingresado no es válido o es menor al permitido.');
                return;
            }

            try {
                const routeName = 'branches.products.price.store';
                const routeParams = { branch: this.quote.branch_id, product: this.item.product.id };
                
                const response = await axios.post(route(routeName, routeParams), this.priceForm);

                if (response.status === 200) {
                    ElMessage.success('Precio actualizado correctamente.');
                    this.showPriceModal = false;
                    // Recargamos el componente principal para que se refresque el historial
                    router.reload({ preserveScroll: true });
                }
            } catch (error) {
                console.error("Error al actualizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
            }
        },
    }
}
</script>