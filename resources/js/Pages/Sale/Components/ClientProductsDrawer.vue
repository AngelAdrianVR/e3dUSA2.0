<template>
    <el-drawer 
        :model-value="show" 
        @update:modelValue="$emit('update:show', $event)"
        title="Productos del Cliente" 
        direction="rtl" 
        :size="drawerSize"
        >
        <div class="md:p-3 relative">
            <p class="dark:text-gray-500 text-center my-4" v-if="!clientProducts.length && !loading">
            Este cliente no tiene productos registrados.
            </p>

            <!-- Estado de carga de productos del cliente -->
            <LoadingIsoLogo class="col-span-full" v-if="loading" />

            <!-- Lista de productos -->
            <div v-else class="space-y-4">
                <div 
                    v-for="product in clientProducts" 
                    :key="product.id" 
                    class="relative bg-gray-100 dark:bg-slate-900 shadow-md rounded-2xl p-4 transition hover:shadow-xl duration-300"
                >
                    <!-- BOTONES DE ACCIÓN -->
                    <div class="absolute top-2 right-2 flex items-center space-x-1">
                        <el-tooltip content="Actualizar precio especial" placement="top">
                            <button @click="openPriceModal(product)" class="flex items-center justify-center hover:bg-gray-200 dark:bg-slate-800 rounded-full size-8 transition-colors">
                                <i class="fa-solid fa-dollar-sign text-sm text-gray-500 dark:text-gray-600"></i>
                            </button>
                        </el-tooltip>
                        <el-tooltip content="Ver producto" placement="top">
                            <button @click="openProduct(product.id)" class="flex items-center justify-center hover:bg-gray-200 dark:bg-slate-800 rounded-full size-8 transition-colors">
                                <i class="fa-solid fa-eye text-gray-500 dark:text-gray-600"></i>
                            </button>
                        </el-tooltip>
                    </div>

                    <!-- Imagen -->
                    <div class="flex items-center gap-4">
                        <img 
                            v-if="product.media?.length" 
                            :src="product.media[0].original_url" 
                            alt="Imagen del producto" 
                            class="w-20 h-20 object-cover rounded-xl border dark:border-gray-700"
                        />
                        <div class="flex-1">
                            <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            {{ product.name }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                            Código: {{ product.code }}
                            </p>
                            <el-tag v-if="product.archived_at" type="warning">Obsoleto</el-tag>
                        </div>
                    </div>

                    <!-- Precios -->
                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Precio base <small>(Para clientes que no tienen precio asignado)</small></p>
                        <p class="font-medium text-blue-400">${{ product.base_price }} {{ product.currency }}</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Precio actual <small>(Precio al que se vende actualmente a este cliente)</small></p>
                        <p class="font-semibold text-green-600 dark:text-green-400">
                        ${{ !product.price_history?.[0]?.valid_to && product.price_history?.[0]?.price 
                                ? product.price_history[0].price + ' ' + product.price_history[0].currency
                                : product.base_price + ' ' + product.currency }}
                        </p>
                    </div>

                    <!-- Último cambio de precio -->
                    <div 
                        v-if="product.price_history?.length" 
                        class="mt-2 text-sm rounded-sm py-1 px-2"
                        :class="getPriceChangeClass(product.price_history[0].valid_from)"
                        >
                        <span class="text-gray-700">Último cambio de precio: {{ timeSince(product.price_history[0].valid_from) }}</span>
                    </div>

                    <!-- Historial de precios -->
                    <el-collapse v-if="product.price_history?.length" class="mt-4">
                        <el-collapse-item :title="'Historial de precios'" name="history">
                            <ul class="space-y-3 max-h-40 overflow-y-auto pr-2 text-sm">
                            <li 
                                v-for="(history, idx) in product.price_history" 
                                :key="idx" 
                                class="flex flex-col border-b dark:border-gray-700 pb-2 last:border-0 last:pb-0"
                            >
                                <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center space-x-2">
                                        <span>{{ formatDate(history.valid_from) }}</span>
                                        <!-- ETIQUETAS DE ESTADO -->
                                        <span v-if="!history.valid_to" class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">Actual</span>
                                        <span v-else class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">Cerrado</span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <span class="font-medium">${{ history.price }} {{ history.currency }}</span>
                                        <!-- BOTÓN PARA FINALIZAR PRECIO ACTIVO -->
                                        <el-tooltip v-if="!history.valid_to" content="Finalizar vigencia de este precio" placement="top">
                                            <button @click="confirmCloseSpecialPrice(history.id)" class="size-7 flex items-center justify-center rounded-md text-red-500 bg-red-100 hover:bg-red-200 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                                                <i class="fa-solid fa-calendar-xmark text-sm"></i>
                                            </button>
                                        </el-tooltip>
                                    </div>
                                </div>
                                <!-- NUEVO: Usuario -->
                                <div class="text-xs text-gray-400 mt-1">
                                    Registrado por: <span v-if="history.user">{{ history.user.name }}</span><span v-else class="italic">Sistema</span>
                                </div>
                            </li>
                            </ul>
                        </el-collapse-item>
                    </el-collapse>

                    <p class="text-sm text-gray-600 dark:text-gray-500 italic mt-3" v-else>No cuenta con precio especial, así que se toma el precio base del producto</p>
                </div>
                
                <!-- BOTÓN MODIFICADO CON ESTADO DE CARGA -->
                <div @click="openAddProductsModal"
                    class="border-2 border-dashed border-gray-400 dark:border-gray-600 h-40 rounded-2xl flex items-center justify-center 
                            cursor-pointer group transition transform duration-300 ease-in-out"
                    :class="loadingAddModal ? 'opacity-50 pointer-events-none' : 'hover:scale-105 hover:shadow-lg hover:border-indigo-500 dark:hover:border-indigo-400'"
                >
                    <span class="flex items-center text-gray-600 dark:text-gray-300 font-medium text-lg transition"
                          :class="loadingAddModal ? '' : 'group-hover:text-indigo-500 dark:group-hover:text-indigo-400'">
                        <i v-if="loadingAddModal" class="fa-solid fa-circle-notch fa-spin mr-2"></i>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ loadingAddModal ? 'Preparando...' : 'Agregar producto al cliente' }}
                    </span>
                </div>
            </div>
        </div>
    </el-drawer>

    <!-- ESTADO DE CARGA PANTALLA COMPLETA CON BLUR (Bloquea clicks) -->
    <div v-if="loadingAddModal" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-gray-500/30 dark:bg-gray-900/50 backdrop-blur-sm">
        <LoadingIsoLogo />
        <span class="text-lg font-semibold text-gray-800 dark:text-gray-200 mt-4">Preparando catálogo de productos...</span>
    </div>

    <!-- ===== MODAL PARA AGREGAR PRODUCTOS ===== -->
    <DialogModal :show="showAddProductsModal" @close="showAddProductsModal = false">
        <template #title>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa-solid fa-tags mr-2"></i> Asignar Productos a {{ branches?.find(b => b.id == branchId)?.name }}
            </h2>
        </template>
        <template #content>
            <form @submit.prevent="saveProducts" class="min-h-96">
                <div class="p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                    <!-- MODIFICADO: Sistema de grid a 12 columnas para integrar la moneda -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-x-5 gap-y-4 items-end">
                        <div class="md:col-span-5">
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Buscar producto*</label>
                            <el-select @change="getProductMedia" :teleported="false" v-model="currentCatalogProduct.product_id" placeholder="Selecciona un producto" class="!w-full mt-1" filterable>
                                <el-option v-for="item in availableProducts" 
                                    :key="item.id" 
                                    :label="item.name" 
                                    :value="item.id"
                                    :disabled="isBranchProductForm(item.id)"
                                />
                            </el-select>
                        </div>
                        <!-- NUEVO: Selector de Moneda -->
                        <div class="md:col-span-3">
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Moneda*</label>
                            <el-select v-model="currentCatalogProduct.currency" :teleported="false" class="!w-full mt-1" placeholder="Moneda">
                                <el-option label="MXN" value="MXN" />
                                <el-option label="USD" value="USD" />
                            </el-select>
                        </div>
                    </div>
                    <div class="md:col-span-4 mt-2">
                        <TextInput label="Precio Especial (Opcional)" v-model="currentCatalogProduct.price"
                            :helpContent="'Si no agregas precio se tomará el precio base'" type="number" :step="0.01" placeholder="Ej. 150.00" />
                    </div>

                    <div v-if="loadingCatalogProductMedia" class="flex items-center justify-center h-32">
                        <p class="text-gray-500">Cargando imagen...</p>
                    </div>
                    <div v-else-if="currentCatalogProduct.media" class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mt-4">
                        <figure class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg">
                            <img v-if="currentCatalogProduct.media?.length" :src="currentCatalogProduct.media[0]?.original_url" alt="Imagen del producto" class="rounded-2xl w-full h-auto object-cover">
                            <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500 p-2 text-center">
                                <i class="fa-solid fa-image text-3xl"></i>
                                <p class="text-xs mt-1">Sin imagen</p>
                            </div>
                        </figure>
                        <div>
                            <p class="text-gray-500 dark:text-gray-300">
                                Precio Base: <strong>${{ currentCatalogProduct.base_price?.toFixed(2) ?? '0.00' }}</strong>
                            </p>
                            <p class="text-gray-500 dark:text-gray-300">
                                Stock: <strong>{{ currentCatalogProduct.current_stock ?? '0' }}</strong> unidades
                            </p>
                            <p class="text-gray-500 dark:text-gray-300">
                                Ubicación: <strong>{{ currentCatalogProduct.location ?? 'No asignado' }}</strong>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end my-5">
                        <PrimaryButton @click="addBranchProduct" type="button" plain :disabled="!currentCatalogProduct.product_id">
                            <i class="fa-solid fa-plus mr-2"></i> Agregar a la lista
                        </PrimaryButton>
                    </div>
                </div>

                <div v-if="newBranchProductForm.products.length" class="mt-4">
                    <InputError :message="newBranchProductForm.errors.products" />
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Productos a asignar:</p>
                    <ul class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2 max-h-48 overflow-y-auto">
                        <li v-for="(product, index) in newBranchProductForm.products" :key="index" class="flex justify-between items-center p-2 rounded-md">
                            <span class="text-sm text-gray-800 dark:text-gray-200">
                                <span class="font-bold text-primary">{{ getCatalogProductName(product.product_id) }}</span>
                            </span>
                            <div class="flex items-center space-x-3 text-sm">
                                <span class="text-gray-600 dark:text-gray-400">
                                    Precio Especial: <strong>${{ product.price ?? 'N/A' }} {{ product.price ? product.currency : '' }}</strong>
                                </span>
                                <button @click="removeBranchProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Aún no has agregado productos a la lista.</p>
            </form>
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="showAddProductsModal = false">Cancelar</CancelButton>
                <PrimaryButton @click="saveProducts" :disabled="!newBranchProductForm.products.length || newBranchProductForm.processing">
                    <span v-if="newBranchProductForm.processing">Guardando...</span>
                    <span v-else>Guardar Productos</span>
                </PrimaryButton>
            </div>
        </template>
    </DialogModal>

    <!-- Modal para actualizar precio especial -->
    <ConfirmationModal :show="showPriceModal" @close="showPriceModal = false">
        <template #title>
            Actualizar precio de <span class="text-blue-500">{{ productForUpdate?.name }}</span>
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
                <!-- MENSAJE DE ERROR MODIFICADO -->
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

    <!-- Confirmación para Finalizar Precio -->
    <ConfirmationModal :show="showClosePriceConfirmModal" @close="showClosePriceConfirmModal = false">
        <template #title>
            Finalizar Precio Especial
        </template>
        <template #content>
            ¿Estás seguro de que deseas finalizar la vigencia de este precio especial? El producto volverá a su precio base para este cliente.
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
import PrimaryButton from "@/Components/PrimaryButton.vue";
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import DialogModal from "@/Components/DialogModal.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    name: 'ClientProductsDrawer',
    components: {
        LoadingIsoLogo,
        PrimaryButton,
        DialogModal,
        InputError,
        TextInput,
        CancelButton,
        ConfirmationModal,
    },
    props: {
        show: Boolean,
        branchId: Number,
        branches: Array,
        catalog_products: Array,
    },
    emits: ['update:show', 'products-loaded'],
    data() {
        const newBranchProductForm = useForm({
            products: [],
        });

        return {
            newBranchProductForm,
            loading: false,
            loadingAddModal: false, // <-- NUEVO: Estado de carga para evitar doble clic
            clientProducts: [],
            drawerSize: "35%", 
            showAddProductsModal: false,

            showClosePriceConfirmModal: false,
            priceHistoryToClose: null,

            showPriceModal: false,
            productForUpdate: null,
            priceForm: {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: 0,
                min_allowed_price: 0,
            },
            
            // MODIFICADO: Agregada moneda por defecto
            currentCatalogProduct: {
                product_id: null,
                price: null,
                currency: 'MXN',
                media: null,
                base_price: null,
                current_stock: null,
                location: null
            },
            loadingCatalogProductMedia: false,
        };
    },
    computed: {
        // NUEVO: Permiso "Cambiar precio especial"
        canBypassPriceRule() {
            return this.$page.props.auth?.user?.permissions?.includes('Cambiar precio especial') || false;
        },
        // MODIFICADO
        isPriceInvalid() {
            if (!this.priceForm.amount || this.priceForm.amount <= 0) return true;
            if (this.canBypassPriceRule) return false;
            return this.priceForm.amount < this.priceForm.min_allowed_price;
        },

        availableProducts() {
            const assignedProductIds = this.clientProducts.map(p => p.id);
            return this.catalog_products?.filter(p => !assignedProductIds.includes(p.id));
        }
    },
    methods: {
        // <-- NUEVO: Método para mostrar carga e invocar al modal asíncronamente
        openAddProductsModal() {
            if (this.loadingAddModal) return; // Evita clics múltiples
            this.loadingAddModal = true;
            
            // Usamos setTimeout para ceder el hilo al navegador para que pinte el overlay
            // antes de empezar el costoso proceso de renderizar el el-select de los productos.
            setTimeout(() => {
                this.showAddProductsModal = true;
                this.loadingAddModal = false;
            }, 100);
        },

        async fetchClientProducts() {
            if (!this.branchId) return;
            this.loading = true;
            this.clientProducts = [];
            try {
                const response = await axios.get(route('branches.fetch-products', this.branchId));
                this.clientProducts = response.data;
                this.$emit('products-loaded', this.clientProducts);
            } catch (error) {
                console.error("Error fetching client products:", error);
                ElMessage.error('No se pudieron cargar los productos del cliente.');
            } finally {
                this.loading = false;
            }
        },
        openProduct(id) {
            window.open(`/catalog-products/${id}`, "_blank");
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        timeSince(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMonths = diffMs / (1000 * 60 * 60 * 24 * 30);

            if (diffMonths < 1) return "menos de un mes";
            if (diffMonths < 12) {
                const months = Math.floor(diffMonths);
                return `hace ${months} mes${months > 1 ? "es" : ""}`;
            }
            const years = Math.floor(diffMonths / 12);
            return `hace ${years} año${years > 1 ? "s" : ""}`;
            },
        getPriceChangeClass(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMonths = diffMs / (1000 * 60 * 60 * 24 * 30);

            if (diffMonths <= 6) return "bg-green-200";
            if (diffMonths > 6 && diffMonths < 12) return "bg-amber-300";
            return "bg-red-400";
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            if (isNaN(num)) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        openPriceModal(product) {
            const basePrice = product.price_history?.[0]?.price ?? product.base_price;
            
            this.productForUpdate = product;
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
                    this.fetchClientProducts(this.branchId);
                }
            } catch (error) {
                console.error("Error al finalizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'No se pudo finalizar el precio.');
            } finally {
                this.showClosePriceConfirmModal = false;
                this.priceHistoryToClose = null;
            }
        },

        async submitNewPrice() {
            if (this.isPriceInvalid) {
                ElMessage.error('El precio ingresado no es válido o es menor al permitido.');
                return;
            }

            try {
                const routeName = 'branches.products.price.store';
                const routeParams = { branch: this.branchId, product: this.productForUpdate.id };
                
                const response = await axios.post(route(routeName, routeParams), this.priceForm);

                if (response.status === 200) {
                    ElMessage.success('Precio actualizado correctamente.');
                    this.showPriceModal = false;
                    this.fetchClientProducts(this.branchId);
                }
            } catch (error) {
                console.error("Error al actualizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
            }
        },

        saveProducts() {
            this.newBranchProductForm.post(route('branches.add-products', this.branchId), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Productos asignados correctamente.');
                    this.showAddProductsModal = false;
                    this.newBranchProductForm.reset();
                    this.fetchClientProducts(this.branchId);
                },
                onError: () => {
                     ElMessage.error('Ocurrió un error al asignar los productos.');
                }
            });
        },
        addBranchProduct() {
            if (!this.currentCatalogProduct.product_id) {
                ElMessage.warning('Debes seleccionar un producto.');
                return;
            }
            if (this.isBranchProductForm(this.currentCatalogProduct.product_id)) {
                ElMessage.warning('Este producto ya está en la lista.');
                return;
            }
            this.newBranchProductForm.products.push({ ...this.currentCatalogProduct });
            this.resetCurrentBranchProduct();
        },
        removeBranchProduct(index) {
            this.newBranchProductForm.products.splice(index, 1);
        },
        // MODIFICADO
        resetCurrentBranchProduct() {
            this.currentCatalogProduct = {
                product_id: null,
                price: null,
                currency: 'MXN',
                media: null,
                base_price: null,
                current_stock: null,
                location: null
            };
        },
        getCatalogProductName(productId) {
            const product = this.catalog_products.find(p => p.id === productId);
            return product ? product.name : `ID: ${productId}`;
        },
        isBranchProductForm(productId) {
            return this.newBranchProductForm.products.some(p => p.product_id === productId);
        },
        async getProductMedia() {
            if (!this.currentCatalogProduct.product_id) return;
            
            this.loadingCatalogProductMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentCatalogProduct.product_id));
                if (response.status === 200) {
                    const product = response.data.product;
                    this.currentCatalogProduct.media = product.media;
                    this.currentCatalogProduct.base_price = product.base_price;
                    this.currentCatalogProduct.current_stock = product.current_stock;
                    this.currentCatalogProduct.location = product.location;
                }
            } catch (error) {
                console.error("Error al cargar detalles del producto:", error);
                ElMessage.error('No se pudo cargar la información del producto.');
                this.resetCurrentBranchProduct(); // Cambiado a su nombre correcto
            } finally {
                this.loadingCatalogProductMedia = false;
            }
        },
        updateDrawerSize() {
            const width = window.innerWidth;
            if (width < 640) {
                this.drawerSize = "90%";
            } else if (width < 1024) {
                this.drawerSize = "60%";
            } else {
                this.drawerSize = "35%";
            }
        },
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.fetchClientProducts();
            }
        }
    },
    mounted() {
        this.updateDrawerSize();
        window.addEventListener("resize", this.updateDrawerSize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updateDrawerSize);
    },
}
</script>