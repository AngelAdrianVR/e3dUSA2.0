<template>
    <Head :title="'Reporte de Precios'" />
    <div class="bg-gray-100 dark:bg-gray-900 min-h-screen p-4 sm:p-6 lg:p-8 font-sans">

        <!-- Botón de Imprimir Flotante -->
        <button @click="print" title="Imprimir Cotización"
            class="fixed bottom-6 right-6 bg-sky-600 text-white rounded-full size-14 shadow-lg hover:bg-sky-700 transition-all z-50 flex items-center justify-center">
            <i class="fa-solid fa-print text-2xl"></i>
        </button>

        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <header class="bg-white dark:bg-slate-800 shadow-md rounded-lg p-4 mb-6 flex flex-col sm:flex-row justify-between items-center">
                <ApplicationLogo />
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Reporte de Precios de Catálogo</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fecha de generación: {{ getCurrentDate() }}</p>
                </div>
            </header>
            
            <p class="text-center text-gray-600 dark:text-gray-400 mb-6 print:hidden">
                <i class="fa-solid fa-magnifying-glass mr-1"></i>
                Para buscar un producto, presiona <kbd class="px-2 py-1.5 text-xs font-semibold text-gray-800 bg-gray-100 border border-gray-200 rounded-lg dark:bg-gray-600 dark:text-gray-100 dark:border-gray-500">CTRL</kbd> + <kbd class="px-2 py-1.5 text-xs font-semibold text-gray-800 bg-gray-100 border border-gray-200 rounded-lg dark:bg-gray-600 dark:text-gray-100 dark:border-gray-500">F</kbd>.
            </p>

            <!-- Product List -->
            <div class="space-y-4">
                <div v-for="product in catalog_products" :key="product.id" class="bg-white dark:bg-slate-800 shadow rounded-lg overflow-hidden page-break">
                    <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Product Info -->
                        <div class="md:col-span-1 flex flex-col items-center md:items-start text-center md:text-left">
                            <a :href="product.media[0]?.original_url" target="_blank" rel="noopener" class="group relative block w-28 h-28 mb-4">
                                <img class="object-contain w-full h-full rounded-md border dark:border-slate-700 transition duration-300 group-hover:scale-105" 
                                     :src="product.media[0]?.original_url" 
                                     :alt="product.name"
                                     onerror="this.src='https://placehold.co/300x300/EBF4FF/575757?text=Sin+Imagen'"/>
                                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center rounded-md opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fa-solid fa-magnifying-glass-plus text-white text-3xl"></i>
                                </div>
                            </a>
                            <h2 class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ product.name }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ product.code }}</p>
                            <p class="text-base font-semibold text-amber-600 dark:text-amber-500">
                                Costo: ${{ product.base_price?.toFixed(2) ?? '0.00' }}
                            </p>
                            <p class="text-base font-semibold text-green-600 dark:text-green-500">
                                Precio Base: ${{ product.cost?.toFixed(2) ?? '0.00' }}
                            </p>
                        </div>

                        <!-- Prices List -->
                        <div class="md:col-span-2">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2 pb-2 border-b dark:border-slate-700">Precios Especiales Activos</h3>
                            <div v-if="product.price_history && product.price_history.length > 0" class="max-h-60 overflow-y-auto pr-2">
                                <ul class="space-y-2">
                                    <li v-for="history in product.price_history" :key="history.id" class="flex items-center justify-between p-2 rounded-md bg-gray-50 dark:bg-slate-700/50">
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ history.branch.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Vigente desde: {{ formatDate(history.valid_from) }}</p>
                                        </div>
                                        <div class="text-right flex items-center space-x-3">
                                            <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                                ${{ history.price?.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}
                                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ history.currency }}</span>
                                            </p>
                                            <button @click="handleUpdateProductPrice(history, product)" class="text-gray-400 hover:text-blue-500 transition duration-200 print:hidden">
                                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                            </button>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div v-else class="text-center py-10">
                                <p class="text-gray-500 dark:text-gray-400">No hay precios especiales activos registrados.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal to update price -->
        <DialogModal :show="showUpdatePriceModal" @close="showUpdatePriceModal = false" maxWidth="2xl">
            <template #title>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Actualizar Precio</h2>
                <p v-if="itemToUpdatePrice" class="text-sm text-gray-600 dark:text-gray-400">
                    {{ itemToUpdatePrice.product_name }} para <span class="font-bold">{{ itemToUpdatePrice.branch_name }}</span>
                </p>
            </template>
            <template #content>
                <section class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <InputLabel value="Precio de referencia actual" />
                        <p class="mt-1 px-3 py-2 bg-gray-100 dark:bg-slate-700 rounded-md text-gray-700 dark:text-gray-300">
                            ${{ priceLogic.current_base_price }}
                        </p>
                    </div>
                    <div>
                         <InputLabel value="Aumento en porcentaje*" />
                        <el-input v-model="priceLogic.percentage" type="number" :min="0" step="0.1" @input="updatePriceFromPercentage" placeholder="Ej. 5" class="mt-1">
                            <template #append>%</template>
                        </el-input>
                    </div>
                    <div class="sm:col-span-2 grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Nuevo precio en moneda*" for="amount" />
                            <el-input id="amount" v-model="priceForm.amount" type="text"
                                :formatter="(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                                :parser="(value) => value.replace(/[^\d.]/g, '')" placeholder="Ej. 30.90"
                                @input="updatePriceFromAmount">
                                <template #prepend>$</template>
                            </el-input>
                            <InputError :message="priceForm.errors.amount" class="mt-1" />
                        </div>
                         <div>
                            <InputLabel value="Moneda*" for="currency" />
                            <el-select id="currency" :teleported="false" v-model="priceForm.currency" placeholder="Seleccionar" class="w-full">
                                <el-option v-for="item in currencies" :key="item.value" :label="item.label" :value="item.value" />
                            </el-select>
                            <InputError :message="priceForm.errors.currency" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Fecha de cambio (Vigente desde)*" for="valid_form" />
                        <el-date-picker id="valid_form" v-model="priceForm.valid_form" :teleported="false" type="date" placeholder="Selecciona una fecha" class="w-full" />
                        <InputError :message="priceForm.errors.valid_form" class="mt-1" />
                    </div>
                     <!-- MENSAJE DE ERROR -->
                    <div v-if="isPriceInvalid && priceForm.amount" class="text-red-600 dark:text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md sm:col-span-2">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>
                        El precio debe ser mayor o igual a ${{ priceLogic.min_allowed_price }} (aumento mínimo del 4%).
                    </div>
                </section>
            </template>
            <template #footer>
                <div class="flex justify-end space-x-2">
                    <SecondaryButton @click="showUpdatePriceModal = false" :disabled="priceForm.processing">Cancelar</SecondaryButton>
                    <PrimaryButton @click="updatePrice" :disabled="priceForm.processing || isPriceInvalid">
                        <span v-if="priceForm.processing">Guardando...</span>
                        <span v-else>Actualizar Precio</span>
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </div>
</template>

<script>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ElMessage } from 'element-plus';
import { Head, useForm, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import axios from 'axios';

export default {
    components: {
        Head,
        InputLabel,
        InputError,
        DialogModal,
        PrimaryButton,
        ApplicationLogo,
        SecondaryButton,
    },
    props: {
        catalog_products: Array,
    },
    data() {
        return {
            priceForm: useForm({
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: 0,
                min_allowed_price: 0,
                product_id: null,
                branch_id: null,
            }),
            priceLogic: {
                percentage: 5.0,
                current_base_price: 0,
                min_allowed_price: 0,
            },
            showUpdatePriceModal: false,
            itemToUpdatePrice: null, // Objeto con información del producto y sucursal a actualizar
            currencies: [
                { value: "MXN", label: "MXN" },
                { value: "USD", label: "USD" },
            ],
        };
    },
    computed: {
        isPriceInvalid() {
            if (!this.priceForm.amount || this.priceForm.amount <= 0) return true;
            return parseFloat(this.priceForm.amount) < this.priceLogic.min_allowed_price;
        }
    },
    methods: {
        print() {
            window.print();
        },
        getCurrentDate() {
            return format(new Date(), 'dd \'de\' MMMM, yyyy', { locale: es });
        },
        formatDate(date) {
            if (!date) return 'N/A';
            const dateObj = new Date(date);
            // Ajuste de zona horaria para evitar que la fecha se muestre un día antes
            const userTimezoneOffset = dateObj.getTimezoneOffset() * 60000;
            return format(new Date(dateObj.getTime() + userTimezoneOffset), 'dd MMM, yyyy', { locale: es });
        },
        handleUpdateProductPrice(history, product) {
            this.itemToUpdatePrice = {
                product_name: product.name,
                branch_name: history.branch.name,
            };
            
            const basePrice = history.price ?? product.base_price;
            this.priceLogic.current_base_price = basePrice;
            this.priceLogic.min_allowed_price = basePrice * 1.04; // Regla de aumento del 4%
            
            this.priceForm.product_id = product.id;
            this.priceForm.branch_id = history.branch_id;
            this.priceForm.currency = history.currency || 'MXN';
            this.priceLogic.percentage = 5.0; // reset percentage
            this.updatePriceFromPercentage();
            
            this.showUpdatePriceModal = true;
        },
        updatePriceFromPercentage() {
            if (this.priceLogic.percentage !== null && this.priceLogic.percentage !== '') {
                const newAmount = this.priceLogic.current_base_price * (1 + (this.priceLogic.percentage / 100));
                this.priceForm.amount = newAmount.toFixed(2);
            } else {
                this.priceForm.amount = null;
            }
        },
        updatePriceFromAmount() {
            if (this.priceForm.amount && this.priceLogic.current_base_price > 0) {
                const percentage = ((this.priceForm.amount / this.priceLogic.current_base_price) - 1) * 100;
                this.priceLogic.percentage = percentage.toFixed(2);
            } else {
                this.priceLogic.percentage = null;
            }
        },
        async updatePrice() {
            if (this.isPriceInvalid) {
                ElMessage.error('El precio ingresado no es válido o es menor al permitido.');
                return;
            }
            try {
            // Definimos la ruta y los parámetros para la petición
            const routeName = 'branches.products.price.store';
            const routeParams = { branch: this.priceForm.branch_id, product: this.priceForm.product_id };
            
            const response = await axios.post(route(routeName, routeParams), this.priceForm);

            if (response.status === 200) {
                ElMessage.success('Precio actualizado correctamente.');
                this.showUpdatePriceModal = false;
                this.priceForm.reset();
                router.reload({ only: ['catalog_products'] });
            }
        } catch (error) {
            console.error("Error al actualizar el precio:", error);
            ElMessage.error(error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
        }

            // // La ruta espera 'valid_form', pero el formulario lo tiene como 'valid_form'. Está correcto.
            // this.priceForm.post(route('branches.products.price.store'), {
            //     preserveScroll: true,
            //     onSuccess: () => {
            //         this.showUpdatePriceModal = false;
            //         this.priceForm.reset();
            //         router.reload({ only: ['catalog_products'] });
            //     },
            // });
        },
    },
};
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .max-w-7xl, .max-w-7xl * {
        visibility: visible;
    }
    .max-w-7xl {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .page-break {
        page-break-inside: avoid;
    }
}
</style>
