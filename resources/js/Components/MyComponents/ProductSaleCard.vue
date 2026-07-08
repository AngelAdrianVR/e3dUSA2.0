<template>
    <div class="bg-gray-50 dark:bg-slate-800/80 rounded-2xl shadow-lg p-5 border transition-all duration-300 hover:shadow-2xl hover:border-primary/50 relative overflow-hidden"
         :class="saleProduct.has_low_price && !isSaleAuthorized ? 'border-amber-300 dark:border-amber-700/50' : 'border-transparent'">
        
        <div v-if="isHighPriority" class="absolute top-0 right-0 bg-red-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl z-10">
            <i class="fa-solid fa-star text-yellow-300 mr-1"></i>
            PRIORIDAD ALTA
        </div>

        <div class="flex flex-col md:flex-row gap-5">
            <div @click="$inertia.visit(route('catalog-products.show', saleProduct.product.id))" class="flex-shrink-0 w-full cursor-pointer md:w-40 h-40 bg-gray-100 dark:bg-slate-900/50 rounded-xl flex items-center justify-center relative group">
                <img v-if="saleProduct.product?.media?.length" :src="saleProduct.product.media[0].original_url" alt="Imagen del producto" class="w-full h-full object-contain rounded-xl">
                <div v-else class="text-gray-300 dark:text-gray-600 text-center">
                    <i class="fa-regular fa-image text-5xl"></i>
                    <p class="text-xs mt-2">Sin imagen</p>
                </div>
                <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-xl">
                     <span class="text-white text-xs font-semibold">CÓDIGO: {{ saleProduct.product?.code }}</span>
                </div>
            </div>

            <div class="flex-1 relative">
                <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 pr-10">{{ saleProduct.product?.name }}</h3>
                <el-tag v-if="saleProduct.product.archived_at" type="warning">Obsoleto</el-tag>
                
                <el-tag v-if="saleProduct.is_new_design" type="primary" size="small" effect="light" class="mt-2">
                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Diseño Nuevo
                </el-tag>

                <div class="grid grid-cols-2 gap-x-6 gap-y-4 mt-4 text-sm">
                    <div>
                        <div class="flex items-center space-x-2">
                            <p class="text-gray-500 dark:text-gray-400">Cantidad ordenada</p>
                            <el-tooltip v-if="branchId" placement="top">
                                <template #content>
                                    <h2 class="text-lg font-bold mb-2">Movimientos de stock</h2>
                                    <p class="text-blue-400">Cantidad tomada de stock: <span class="text-white dark:text-gray-500 ml-1">{{ (saleProduct.quantity - saleProduct.quantity_to_produce).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ saleProduct.product?.measure_unit }}</span></p>
                                    <p class="text-blue-400">Cantidad a producir: <span class="text-white dark:text-gray-500 ml-1">{{ saleProduct.quantity_to_produce.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ saleProduct.product?.measure_unit }}</span></p>
                                </template>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-amber-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                            </el-tooltip>
                        </div>
                        <p class="font-semibold text-lg">{{ saleProduct.quantity?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} <span class="text-xs font-normal">{{ saleProduct.product?.measure_unit }}</span></p>
                    </div>
                    
                    <!-- PRECIO DE VENTA Y TOOLTIP -->
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Precio Unitario (Venta)</p>
                        <div class="flex items-center space-x-2 mt-0.5">
                            <p class="font-semibold text-lg" :class="saleProduct.has_low_price && !isSaleAuthorized ? 'text-amber-500 dark:text-amber-400' : 'text-green-600 dark:text-green-400'">
                                {{ formatCurrency(saleProduct.price) }} {{ activeSpecialPrice ? this.activeSpecialPrice.currency : saleProduct.product.currency }}
                            </p>
                            
                            <el-tooltip v-if="saleProduct.has_low_price && !isSaleAuthorized" placement="top" effect="dark">
                                <template #content>
                                    <div class="w-64 text-xs leading-relaxed">
                                        El precio de venta es menor al establecido para el cliente.<br><br>
                                        Si no autoriza este precio, <b>comuníquese con el vendedor</b> para que lo edite.<br>
                                        Si autoriza el precio, solo tiene que <b>Autorizar la orden de venta</b> desde el botón principal.
                                    </div>
                                </template>
                                <div class="bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 rounded-full size-6 flex items-center justify-center animate-pulse cursor-help">
                                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                </div>
                            </el-tooltip>

                            <el-tooltip v-else-if="saleProduct.has_low_price && isSaleAuthorized" content="Precio Bajo Autorizado" placement="top" effect="dark">
                                <div class="bg-green-100 dark:bg-green-900/50 text-green-600 dark:text-green-400 rounded-full size-6 flex items-center justify-center cursor-help">
                                    <i class="fa-solid fa-check-double text-xs"></i>
                                </div>
                            </el-tooltip>
                        </div>
                    </div>

                    <div>
                        <p class="text-gray-500 dark:text-gray-400">{{ currentPriceLabel }}</p>
                        <p class="font-semibold text-base">{{ formatCurrency(currentPrice) }} {{ activeSpecialPrice ? this.activeSpecialPrice.currency : saleProduct.product.currency }}</p>
                    </div>
                     <div>
                        <p class="text-gray-500 dark:text-gray-400">Importe Total</p>
                        <p class="font-bold text-lg text-primary dark:text-sky-400">{{ formatCurrency(totalAmount) }} {{ activeSpecialPrice ? this.activeSpecialPrice.currency : saleProduct.product.currency }}</p>
                    </div>
                </div>

                <!-- SECCIÓN DE STOCK (CONDICIONAL: COMPUESTO VS SIMPLE) -->
                <div class="mt-4 border-t dark:border-slate-700/50 pt-4">
                    <!-- VISTA PARA PRODUCTOS COMPUESTOS (COLAPSABLE) -->
                    <div v-if="isComposite" class="bg-white dark:bg-slate-800/50 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm transition-all duration-300">
                        <div @click="showComponents = !showComponents" class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 cursor-pointer group">
                            <div class="flex items-center space-x-2">
                                <h2 class="font-bold text-lg group-hover:text-primary transition-colors">
                                    Componentes
                                </h2>
                                <el-tooltip v-if="stockStatus !== 'green'" placement="top" :content="stockStatus === 'red' ? 'Sin stock suficiente de componentes' : 'Los sets armables están por debajo del mínimo'" effect="dark">
                                    <i class="fa-solid fa-circle-exclamation" :class="stockStatus === 'red' ? 'text-red-500' : 'text-amber-500'"></i>
                                </el-tooltip>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <!-- Badge Sets armables -->
                                <div class="text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-3 py-1 rounded-full font-medium border border-indigo-100 dark:border-indigo-800 flex items-center shrink-0 w-max" title="Cantidad máxima de productos que se pueden armar basándose en el inventario actual de sus componentes.">
                                    <i class="fa-solid fa-boxes-stacked mr-2"></i> Posibles sets a armar: <span class="font-bold" :class="{'text-red-500 ml-1': stockStatus === 'red', 'text-amber-500 ml-1': stockStatus === 'amber', 'text-green-600 dark:text-green-400 ml-1': stockStatus === 'green'}">{{ calculatedSets?.toLocaleString() ?? '0' }}</span>
                                </div>
                                <!-- Botón colapsar/desplegar -->
                                <button type="button" class="text-gray-400 group-hover:text-primary transition-colors w-6 h-6 flex items-center justify-center">
                                    <i class="fa-solid" :class="showComponents ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Lista de componentes -->
                        <div v-show="showComponents" class="mt-4 pt-4 border-t dark:border-slate-700/50">
                            <ul class="space-y-2 text-sm">
                                <li
                                v-for="comp in componentsStockInfo"
                                :key="comp.id"
                                class="flex items-center justify-between p-2 bg-gray-50 dark:bg-slate-900/50 border border-gray-100 dark:border-slate-700 rounded-lg"
                                >
                                    <div class="flex items-center flex-1">
                                        <!-- Imagen o ícono como enlace -->
                                        <Link :href="route('catalog-products.show', comp.id)" title="Ver detalles del componente" class="w-12 h-12 flex-shrink-0 flex items-center justify-center bg-white dark:bg-slate-800 border dark:border-slate-700 rounded-md overflow-hidden hover:ring-2 hover:ring-primary transition-all">
                                            <img
                                            v-if="comp.media?.[0]?.original_url"
                                            :src="comp.media[0].original_url"
                                            alt="Componente"
                                            class="w-full h-full object-cover"
                                            />
                                            <svg
                                            v-else
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="w-6 h-6 text-gray-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h2l2-3h6l2 3h2a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13l4-4a2 2 0 012.828 0L15 14l3-3 3 3"/>
                                            </svg>
                                        </Link>

                                        <!-- Nombre como enlace -->
                                        <Link :href="route('catalog-products.show', comp.id)" class="text-gray-800 dark:text-gray-200 font-medium ml-3 mr-2 leading-tight hover:text-primary hover:underline transition-colors line-clamp-2">
                                            {{ comp.name }}
                                        </Link>
                                    </div>

                                    <!-- Requerimiento y Stock -->
                                    <div class="text-right flex flex-col justify-center min-w-[90px]">
                                        <span class="font-bold text-primary">
                                            Requerido: {{ comp.requiredPerSet }}
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">
                                            Stock actual: <strong :class="comp.currentStock > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500'">{{ comp.currentStock }}</strong>
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-0.5">
                                            Mínimo: <strong>{{ comp.min_quantity?.toLocaleString() || 0 }}</strong>
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- VISTA PARA PRODUCTOS SIMPLES (SIN COMPONENTES) -->
                    <div v-else class="grid grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <div class="flex items-center space-x-2">
                                <p class="text-gray-500 dark:text-gray-400">Stock actual</p>
                                <el-tooltip v-if="stockStatus !== 'green'" placement="top" :content="stockStatus === 'red' ? 'Sin stock o inventario negativo' : 'El stock está por debajo del mínimo permitido'" effect="dark">
                                    <i class="fa-solid fa-circle-exclamation cursor-help" :class="stockStatus === 'red' ? 'text-red-500' : 'text-amber-500'"></i>
                                </el-tooltip>
                            </div>
                            <div class="flex items-center space-x-2 mt-0.5">
                                <p class="font-bold text-lg" :class="{'text-red-600 dark:text-red-400': stockStatus === 'red', 'text-amber-600 dark:text-amber-400': stockStatus === 'amber', 'text-green-600 dark:text-green-400': stockStatus === 'green'}">
                                    {{ currentStock.toLocaleString() }} <span class="text-xs font-normal">{{ saleProduct.product?.measure_unit }}</span>
                                </p>
                                <!-- Semáforo de Colores -->
                                <span class="flex w-3 h-3 rounded-full relative" 
                                      :class="{
                                          'bg-red-500 shadow-[0_0_5px_rgba(239,68,68,0.8)]': stockStatus === 'red',
                                          'bg-amber-400 shadow-[0_0_5px_rgba(251,191,36,0.8)]': stockStatus === 'amber',
                                          'bg-green-500 shadow-[0_0_5px_rgba(34,197,94,0.8)]': stockStatus === 'green'
                                      }">
                                </span>
                            </div>
                        </div>

                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Stock mínimo</p>
                            <p class="font-bold text-lg dark:text-gray-100">{{ saleProduct.product.min_quantity?.toLocaleString() }} <span class="text-xs font-normal">{{ saleProduct.product?.measure_unit }}</span></p>
                        </div>
                        <!-- <div>
                            <p class="text-gray-500 dark:text-gray-400">Stock máximo</p>
                            <p class="font-bold text-lg dark:text-gray-100">{{ saleProduct.product.max_quantity?.toLocaleString() }} <span class="text-xs font-normal">{{ saleProduct.product?.measure_unit }}</span></p>
                        </div> -->
                    </div>
                </div>

                <!-- === JUSTIFICACIÓN DEL PRECIO BAJO === -->
                <div v-if="saleProduct.has_low_price && saleProduct.low_price_reason" class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-400 dark:border-amber-600 rounded-r-md">
                    <p class="text-[11px] text-amber-700 dark:text-amber-400 font-bold mb-1 uppercase tracking-wider flex items-center">
                        <i class="fa-solid fa-comment-dots mr-1"></i> Razón del precio bajo:
                    </p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 italic pr-2">"{{ saleProduct.low_price_reason }}"</p>
                </div>
            </div>
        </div>
        
        <div
            v-if="saleProduct.customization_details?.length"
            class="mt-4 text-xs bg-blue-50 dark:bg-blue-900/30 p-4 rounded-xl border border-blue-300 dark:border-blue-800 shadow-sm"
            >
            <p class="font-semibold text-blue-800 dark:text-blue-300 flex items-center mb-2">
                <i class="fa-solid fa-pen-ruler mr-2"></i>
                Detalles de Personalización:
            </p>

            <ul class="space-y-2">
                <li
                v-for="(item, index) in parsedCustomization"
                :key="index"
                class="bg-white dark:bg-slate-800/50 rounded-lg p-2 border border-blue-200 dark:border-slate-700 shadow-sm"
                >
                <p class="text-gray-700 dark:text-gray-300 text-[11px]">
                    <span class="font-semibold text-blue-700 dark:text-blue-400">Tipo:</span>
                    {{ item.type }}
                </p>
                <p class="text-gray-700 dark:text-gray-300 text-[11px]">
                    <span class="font-semibold text-blue-700 dark:text-blue-400 capitalize">{{ item.key }}:</span>
                    {{ item.value }}
                </p>
                </li>
            </ul>
        </div>

        <div v-if="saleProduct.notes" class="mt-4 text-xs italic bg-yellow-50 dark:bg-yellow-900/30 p-2 rounded-lg border border-dashed border-yellow-300 dark:border-yellow-800">
            <strong class="font-semibold not-italic">Notas:</strong> {{ saleProduct.notes }}
        </div>

        <div class="mt-4 w-full">
            <p v-if="lastUpdateInfo" :class="lastUpdateInfo.colorClass" class="text-sm font-semibold mb-2">
                <i class="fa-solid fa-circle-info mr-1"></i>
                {{ lastUpdateInfo.text }}
            </p>
            
            <el-collapse v-if="saleProduct.product.price_history?.length">
                <el-collapse-item title="Historial de precios especiales del cliente" name="history">
                    <ul class="max-h-32 overflow-y-auto pr-2 text-sm">
                        <li v-for="history in saleProduct.product.price_history" :key="history.id" class="flex flex-col text-gray-600 dark:text-gray-400 p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700/50 border-b dark:border-slate-700/50 last:border-0">
                            <div class="flex justify-between items-center w-full">
                                <div class="flex items-center space-x-2">
                                    <span>{{ formatDate(history.valid_from) }}</span>
                                    <el-tag v-if="!history.valid_to" type="success" size="small" effect="dark" round>Actual</el-tag>
                                    <el-tag v-else type="info" size="small" effect="light" round>Cerrado</el-tag>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="font-medium text-black dark:text-white">${{ history.price }} {{ history.currency }}</span>
                                    <el-tooltip v-if="!history.valid_to" content="Finalizar vigencia de este precio" placement="top">
                                        <button @click="confirmCloseSpecialPrice(history.id)" class="size-7 flex items-center justify-center rounded-md text-red-500 bg-red-100 hover:bg-red-200 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                                            <i class="fa-solid fa-calendar-xmark text-sm"></i>
                                        </button>
                                    </el-tooltip>
                                </div>
                            </div>
                            <div class="text-[11px] text-gray-400 mt-1">
                                Registrado por: <span v-if="history.user" class="font-medium text-gray-500 dark:text-gray-300">{{ history.user.name }}</span><span v-else class="italic">Sistema</span>
                            </div>
                        </li>
                    </ul>
                </el-collapse-item>
            </el-collapse>
        </div>


        <div v-if="branchId" class="absolute top-5 right-4">
            <el-tooltip content="Actualizar precio especial para este cliente" placement="top">
                <button @click="openPriceModal" class="flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 rounded-full size-9 transition-colors">
                    <i class="fa-solid fa-dollar-sign text-sm text-gray-500 dark:text-gray-400"></i>
                </button>
            </el-tooltip>
        </div>
    </div>

    <!-- Modal para actualizar precio especial -->
    <ConfirmationModal :show="showPriceModal" @close="showPriceModal = false">
        <template #title>
            Actualizar precio de <span class="text-primary dark:text-sky-400">{{ saleProduct.product?.name }}</span>
        </template>
        <template #content>
            <div class="space-y-4 text-sm dark:text-gray-300">
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 h-48">
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
                <div v-if="priceForm.amount && isPriceInvalid && !canBypassPriceRule" class="text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    El precio debe ser mayor o igual a ${{ priceForm.min_allowed_price?.toFixed(2) }} (aumento mínimo del 4%).
                </div>
            </div>
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="showPriceModal = false">Cancelar</CancelButton>
                <PrimaryButton @click="submitNewPrice" :disabled="isPriceInvalid || priceForm.processing" class="!bg-blue-600 hover:!bg-blue-700 disabled:!bg-blue-300 dark:disabled:!bg-slate-600">
                    <span v-if="priceForm.processing">Guardando...</span>
                    <span v-else>Actualizar Precio</span>
                </PrimaryButton>
            </div>
        </template>
    </ConfirmationModal>

    <ConfirmationModal :show="showClosePriceConfirmModal" @close="showClosePriceConfirmModal = false">
        <template #title>
            Finalizar Precio Especial
        </template>
        <template #content>
            ¿Estás seguro de que deseas finalizar la vigencia de este precio especial? El producto volverá a su precio base para este cliente en futuras compras.
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="showClosePriceConfirmModal = false">Cancelar</CancelButton>
                <PrimaryButton @click="closeSpecialPrice" class="!bg-red-600 hover:!bg-red-700">Sí, finalizar</PrimaryButton>
            </div>
        </template>
    </ConfirmationModal>
</template>

<script>
import axios from 'axios';
import { useForm, router, Link } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import { format, formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

export default {
    name: 'ProductCard',
    components: {
        ConfirmationModal,
        CancelButton,
        PrimaryButton,
        Link,
    },
    props: {
        saleProduct: {
            type: Object,
            required: true,
        },
        saleCurrency: {
            type: String,
            required: true,
        },
        isHighPriority: {
            type: Boolean,
            default: false,
        },
        branchId: {
            type: Number,
            required: true,
        },
        isSaleAuthorized: {
            type: Boolean,
            default: false,
        }
    },
    data() {
        return {
            showComponents: false,
            showPriceModal: false,
            showClosePriceConfirmModal: false,
            priceHistoryToClose: null,
            priceForm: useForm({
                amount: null,
                percentage: null,
                currency: 'MXN', 
                valid_from: new Date(),
                current_base_price: 0,
                min_allowed_price: 0,
            }),
        };
    },
    computed: {
        // === NUEVA LÓGICA DE COMPONENTES Y STOCK ===
        actualComponents() {
            const p = this.saleProduct.product;
            // Si el producto en sí tiene componentes (padre compuesto o directo)
            if (p?.components?.length > 0) {
                return p.components;
            }
            // Si es un hijo (variante) y hereda componentes del padre
            if (p?.parent?.components?.length > 0) {
                return p.parent.components;
            }
            return [];
        },
        isComposite() {
            return this.actualComponents.length > 0;
        },
        componentsStockInfo() {
            if (!this.isComposite) return [];
            return this.actualComponents.map(comp => {
                // Sumamos el stock de todos los almacenes de este componente en específico
                const stock = comp.storages ? comp.storages.reduce((sum, s) => sum + Number(s.quantity), 0) : 0;
                // Cantidad de este componente necesaria para crear 1 set (viene del pivot)
                const requiredPerSet = Number(comp.pivot?.quantity) || 1;
                // Sets que se pueden armar exclusivamente basados en el stock de ESTE componente
                const possibleSets = Math.floor(stock / requiredPerSet);
                
                return {
                    ...comp,
                    currentStock: stock,
                    requiredPerSet,
                    possibleSets
                };
            });
        },
        calculatedSets() {
            if (!this.isComposite) return 0;
            // La cantidad máxima de sets armables es la cantidad del componente limitante (el que alcanza para menos sets)
            const setsArray = this.componentsStockInfo.map(c => c.possibleSets);
            return setsArray.length ? Math.min(...setsArray) : 0;
        },
        // === CÁLCULO DEL STOCK TOTAL ADAPTADO ===
        currentStock() {
            if (this.isComposite) {
                // Si es compuesto, el stock general es la cantidad de "sets" armables
                return this.calculatedSets;
            }
            
            // Si es un producto simple (sin componentes)
            if (!this.saleProduct.product?.storages?.length) return 0;
            return this.saleProduct.product.storages.reduce((total, storage) => {
                return total + Number(storage.quantity);
            }, 0);
        },
        stockStatus() {
            const stock = this.currentStock;
            const minAllowed = this.saleProduct.product?.min_quantity || 0;
            
            if (stock <= 0) return 'red'; // Sin stock o inventario insuficiente para armar sets
            if (stock < minAllowed) return 'amber'; // Por debajo del mínimo permitido
            return 'green'; // Stock ideal
        },
        
        canBypassPriceRule() {
            return this.$page.props.auth?.user?.permissions?.includes('Cambiar precio especial') || false;
        },
        totalAmount() {
            return (this.saleProduct.quantity * this.saleProduct.price).toFixed(2);
        },
        isPriceInvalid() {
            if (!this.priceForm.amount || Number(this.priceForm.amount) <= 0) {
                return true;
            }
            if (this.canBypassPriceRule) return false;
            
            return Number(this.priceForm.amount) < this.priceForm.min_allowed_price;
        },
        parsedCustomization() {
            try {
                return typeof this.saleProduct.customization_details === "string"
                ? JSON.parse(this.saleProduct.customization_details)
                : this.saleProduct.customization_details;
            } catch (e) {
                return [];
            }
        },
        activeSpecialPrice() {
            if (!this.saleProduct.product?.price_history?.length) {
                return null;
            }
            return this.saleProduct.product.price_history.find(h => h.valid_to === null);
        },
        currentPrice() {
            return this.activeSpecialPrice
                ? this.activeSpecialPrice.price
                : this.saleProduct.product?.base_price;
        },
        currentPriceLabel() {
            return this.activeSpecialPrice
                ? 'Precio Actual (Especial)'
                : 'Precio Actual (Base)';
        },
        lastUpdateInfo() {
            if (!this.activeSpecialPrice) {
                return null;
            }

            const fromDate = new Date(this.activeSpecialPrice.valid_from);
            const now = new Date();
            const monthsDiff = (now.getFullYear() - fromDate.getFullYear()) * 12 + (now.getMonth() - fromDate.getMonth());

            let colorClass = '';
            if (monthsDiff < 6) {
                colorClass = 'text-green-600 dark:text-green-400';
            } else if (monthsDiff >= 6 && monthsDiff < 12) {
                colorClass = 'text-orange-500 dark:text-orange-400';
            } else {
                colorClass = 'text-red-500 dark:text-red-400';
            }

            const relativeTime = formatDistanceToNow(fromDate, { addSuffix: true, locale: es });

            return {
                text: `Precio especial actualizado ${relativeTime}`,
                colorClass: colorClass
            };
        },
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            const num = Number(value);
            if (isNaN(num)) return '$0.00';
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
        },
        openPriceModal() {
            const basePrice = Number(this.activeSpecialPrice?.price ?? this.saleProduct.product?.base_price ?? 0);
            
            this.priceForm.reset();
            this.priceForm.current_base_price = basePrice;
            this.priceForm.min_allowed_price = basePrice * 1.04;
            this.showPriceModal = true;
        },
        updatePriceFromAmount() {
            const amount = Number(this.priceForm.amount);
            if (amount && this.priceForm.current_base_price > 0) {
                const percentage = ((amount / this.priceForm.current_base_price) - 1) * 100;
                this.priceForm.percentage = percentage.toFixed(2);
            } else {
                this.priceForm.percentage = null;
            }
        },
        updatePriceFromPercentage() {
            const percentage = Number(this.priceForm.percentage);
            if (this.priceForm.percentage !== null && this.priceForm.percentage !== '') {
                const newAmount = this.priceForm.current_base_price * (1 + (percentage / 100));
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
                const routeParams = { branch: this.branchId, product: this.saleProduct.product.id };
                
                const response = await axios.post(route(routeName, routeParams), this.priceForm);

                if (response.status === 200) {
                    ElMessage.success('Precio actualizado correctamente.');
                    this.showPriceModal = false;
                    router.reload({ 
                        preserveScroll: true,
                        preserveState: true 
                    });
                }
            } catch (error) {
                console.error("Error al actualizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
            }
        },
        confirmCloseSpecialPrice(historyId) {
            this.priceHistoryToClose = historyId;
            this.showClosePriceConfirmModal = true;
        },
        async closeSpecialPrice() {
            if (!this.priceHistoryToClose) return;
            try {
                const response = await axios.patch(route('branch-price-history.close', this.priceHistoryToClose));
                if (response.status === 200) {
                    ElMessage.success('El precio especial ha sido finalizado.');
                    const historyItem = this.saleProduct.product.price_history.find(h => h.id === this.priceHistoryToClose);
                    if (historyItem) {
                        historyItem.valid_to = new Date().toISOString();
                    }
                }
            } catch (error) {
                console.error("Error al finalizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'No se pudo finalizar el precio.');
            } finally {
                this.showClosePriceConfirmModal = false;
                this.priceHistoryToClose = null;
            }
        },
    }
};
</script>