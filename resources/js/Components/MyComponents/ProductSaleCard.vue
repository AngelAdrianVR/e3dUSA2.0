<template>
    <div class="bg-white dark:bg-slate-800/80 rounded-2xl shadow-lg p-5 border border-transparent transition-all duration-300 hover:shadow-2xl hover:border-primary/50 relative overflow-hidden">
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

            <div class="flex-1">
                <h3 class="font-bold text-lg text-gray-800 dark:text-gray-100 pr-10">{{ saleProduct.product?.name }}</h3>
                
                <el-tag v-if="saleProduct.is_new_design" type="primary" size="small" effect="light" class="mt-2">
                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Diseño Nuevo
                </el-tag>

                <div class="grid grid-cols-2 gap-x-6 gap-y-3 mt-4 text-sm">
                    <div>
                        <div class="flex items-center space-x-2">
                            <p class="text-gray-500 dark:text-gray-400">Cantidad ordenada</p>
                            <el-tooltip placement="top">
                                <template #content>
                                    <h2 class="text-lg font-bold mb-2">Movimientos de stock</h2>
                                    <p class="text-blue-400">Cantidad tomada de stock: <span class="text-white ml-1">{{ (saleProduct.quantity - saleProduct.quantity_to_produce).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ saleProduct.product?.measure_unit }}</span></p>
                                    <p class="text-blue-400">Cantidad a producir: <span class="text-white ml-1">{{ saleProduct.quantity_to_produce.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ saleProduct.product?.measure_unit }}</span></p>
                                </template>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-amber-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                            </el-tooltip>
                        </div>
                        <p class="font-semibold text-lg">{{ saleProduct.quantity?.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} <span class="text-xs font-normal">{{ saleProduct.product?.measure_unit }}</span></p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Precio Unitario (Venta)</p>
                        <p class="font-semibold text-lg text-green-600 dark:text-green-400">{{ formatCurrency(saleProduct.price) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">{{ currentPriceLabel }}</p>
                        <p class="font-semibold text-base">{{ formatCurrency(currentPrice) }}</p>
                    </div>
                     <div>
                        <p class="text-gray-500 dark:text-gray-400">Importe Total</p>
                        <p class="font-bold text-lg text-primary dark:text-sky-400">{{ formatCurrency(totalAmount) }}</p>
                    </div>
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
            <p v-if="lastUpdateInfo" :class="lastUpdateInfo.colorClass" class="text-xs font-semibold mb-2">
                <i class="fa-solid fa-circle-info mr-1"></i>
                {{ lastUpdateInfo.text }}
            </p>
            
            <el-collapse v-if="saleProduct.product.price_history?.length">
                <el-collapse-item title="Historial de precios especiales del cliente" name="history">
                    <ul class="max-h-32 overflow-y-auto pr-2 text-sm">
                        <li v-for="history in saleProduct.product.price_history" :key="history.id" class="flex justify-between items-center text-gray-600 dark:text-gray-400 p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700/50">
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

    <ConfirmationModal :show="showPriceModal" @close="showPriceModal = false">
        <template #title>
            Actualizar precio de <span class="text-primary dark:text-sky-400">{{ saleProduct.product?.name }}</span>
        </template>
        <template #content>
            <div class="space-y-4 text-sm dark:text-gray-300">
                <p>El precio base actual del catálogo es <strong class="font-semibold">${{ priceForm.current_base_price }}</strong>. El nuevo precio especial se aplicará a este cliente para futuras compras.</p>
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
                <div v-if="priceForm.amount && isPriceInvalid" class="text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    El aumento del precio especial debe ser de al menos un 4% sobre el precio base.
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
import { useForm, router } from "@inertiajs/vue3";
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
    },
    props: {
        saleProduct: {
            type: Object,
            required: true,
        },
        isHighPriority: {
            type: Boolean,
            default: false,
        },
        branchId: {
            type: Number,
            required: true,
        }
    },
    data() {
        return {
            showPriceModal: false,
            showClosePriceConfirmModal: false,
            priceHistoryToClose: null,
            priceForm: useForm({
                amount: null,
                percentage: null,
                currency: 'MXN', // Asumido por defecto
                valid_from: new Date(),
                current_base_price: 0,
            }),
        };
    },
    computed: {
        totalAmount() {
            return (this.saleProduct.quantity * this.saleProduct.price).toFixed(2);
        },
        isPriceInvalid() {
            if (!this.priceForm.amount || this.priceForm.amount <= 0 || !this.priceForm.current_base_price) {
                return true;
            }
            const percentageIncrease = ((this.priceForm.amount / this.priceForm.current_base_price) - 1) * 100;
            return percentageIncrease < 4;
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
            // Calcula la diferencia en meses.
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
            const basePrice = this.saleProduct.product?.base_price ?? 0;
            
            this.priceForm.reset();
            this.priceForm.current_base_price = basePrice;
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
                ElMessage.error('El aumento de precio debe ser de al menos un 4% sobre el precio base.');
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