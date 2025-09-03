<template>
    <!-- SECCIÓN DE PRODUCTOS -->
    <el-divider content-position="left" class="!mt-8">
        <span>Productos de la Orden</span>
    </el-divider>
    <InputError :message="productsError" class="mt-2" />

    <div ref="formProducts" class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
        <p v-if="saleType === 'venta' && !branchId" class="text-center text-gray-500">
            <i class="fa-solid fa-arrow-up mr-2"></i>
            Selecciona un cliente para agregar productos.
        </p>
        <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-start">
            <!-- Formulario para agregar/editar producto -->
            <div class="lg:col-span-2">
                <InputLabel value="Producto*" />
                <el-select @change="getProductData" v-model="currentProduct.id" filterable placeholder="Buscar producto" class="w-full" 
                           :no-data-text="saleType === 'venta' ? 'No hay productos para este cliente' : 'No hay productos en el catálogo'">
                    <el-option v-for="product in availableProducts" 
                        :key="product.id" 
                        :label="product.name" 
                        :value="product.id"
                        :disabled="products.some(p => p.id === product.id) && product.id !== this.products[editIndex]?.id" />
                </el-select>
            </div>
            <TextInput label="Cantidad*" v-model="currentProduct.quantity" type="number" />
            
            <!-- El precio solo es para 'venta' -->
            <TextInput v-if="saleType === 'venta'" label="Precio Unitario*" v-model="currentProduct.price" type="number" :formatAsNumber="true">
                <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
            </TextInput>

            <!-- Estado de carga -->
            <LoadingIsoLogo class="col-span-full" v-if="loadingProductData" />

            <!-- Tarjeta de producto seleccionado -->
            <div v-else-if="currentProduct.id" class="p-4 bg-gray-100 dark:bg-slate-900/50 rounded-lg col-span-full mb-2 border border-gray-200 dark:border-slate-800" >
                
                <!-- Sección de Información Principal del Producto -->
                <div class="flex items-start space-x-4">
                    <figure 
                        v-if="currentProduct.media" 
                        class="relative flex items-center justify-center w-32 h-32 min-w-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                        <img v-if="currentProduct.media?.length"
                            :src="currentProduct.media[0]?.original_url" 
                            alt="Imagen del producto" 
                            class="rounded-2xl w-full h-auto object-cover transition duration-300 ease-in-out hover:opacity-95"
                        >
                        <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <p>Sin imagen</p>
                        </div>
                    </figure>

                    <!-- Información de almacén y precios -->
                    <div>
                        <span v-if="saleType === 'venta' && currentProduct.isClientProduct" class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full mb-2 inline-block">
                            Producto de cliente
                        </span>
                        <p class="text-gray-500 dark:text-gray-300">
                            Stock (P. Terminado): <strong>{{ currentProduct.storages[0]?.quantity ?? 0 }}</strong> unidades
                        </p>
                        <p class="text-gray-500 dark:text-gray-300">
                            Ubicación: <strong>{{ currentProduct.storages[0]?.location ?? 'No asignado' }}</strong>
                        </p>
                        <p v-if="saleType === 'venta'" class="text-gray-500 dark:text-gray-300">
                            Precio base: <strong>${{ formatNumber(currentProduct.base_price) ?? '0.00' }}</strong>
                        </p>
                        <p v-if="saleType === 'venta' && currentProduct.isClientProduct" class="text-green-600 dark:text-green-400 font-semibold mt-1">
                            Precio actual (especial): <strong>${{ formatNumber(currentProduct.current_price) ?? '0.00' }}</strong>
                        </p>
                    </div>
                </div>

                <!-- --- INICIO DE LA NUEVA SECCIÓN DE COMPONENTES --- -->
                <div v-if="currentProduct.components?.length" class="mt-4 pt-4 border-t border-gray-300 dark:border-slate-800">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">Componentes para Producción</h4>
                    <ul class="space-y-2">
                        <li v-for="component in currentProduct.components" :key="component.id" class="flex items-center justify-between text-sm p-2 rounded-lg bg-white dark:bg-slate-800 shadow-sm">
                            <div class="flex items-center space-x-3">
                                <figure 
                                    v-if="component.media" 
                                    class="relative flex items-center justify-center size-12 min-w-12 rounded-lg border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                                    <img v-if="component.media?.length"
                                        :src="component.media[0]?.original_url" 
                                        alt="Imagen del producto" 
                                        class="rounded-lg w-full h-auto object-cover transition duration-300 ease-in-out hover:opacity-95"
                                    >
                                    <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                        <p>Sin imagen</p>
                                    </div>
                                </figure>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ component.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Requerido: {{ component.pivot.quantity }} {{ component.measure_unit }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 text-right">
                                <!-- Icono de advertencia para stock bajo -->
                                <div v-if="(component.storages[0]?.quantity ?? 0) < component.min_quantity" class="text-yellow-500" title="El stock está por debajo del mínimo requerido">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.21 3.03-1.742 3.03H4.42c-1.532 0-2.492-1.696-1.742-3.03l5.58-9.92zM10 13a1 1 0 110-2 1 1 0 010 2zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <!-- Información de stock con colores condicionales -->
                                <span class="font-bold text-base" :class="{
                                    'text-red-500': (component.storages[0]?.quantity ?? 0) == 0,
                                    'text-yellow-500': (component.storages[0]?.quantity ?? 0) > 0 && (component.storages[0]?.quantity ?? 0) < component.min_quantity,
                                    'text-green-500 dark:text-green-400': (component.storages[0]?.quantity ?? 0) >= component.min_quantity
                                }">
                                    {{ formatNumber(component.storages[0]?.quantity) ?? 0 }}
                                    <span class="font-normal text-xs text-gray-500 dark:text-gray-400">en stock</span>
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            
            <div class="lg:col-span-full">
                 <TextInput label="Notas del producto (opcional)" v-model="currentProduct.notes" type="textarea" :isTextarea="true" />
            </div>

            <!-- INICIO: SECCIÓN DE PERSONALIZACIÓN -->
            <label class="flex items-center col-span-full mt-2">
                <Checkbox v-model:checked="currentProduct.has_customization" class="bg-transparent border-gray-500" />
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-300">Agregar personalización al producto</span>
            </label>

            <div v-if="currentProduct.has_customization" class="lg:col-span-full mt-3 p-4 border border-dashed dark:border-slate-700 rounded-lg">
                <h4 class="font-semibold mb-3 text-gray-700 dark:text-gray-300">Detalles de Personalización</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                    <div>
                        <InputLabel value="Tipo*" />
                        <el-select v-model="newCustomization.type" placeholder="Selecciona" class="!w-full">
                            <el-option v-for="item in customizationTypes" :key="item" :label="item" :value="item" />
                        </el-select>
                    </div>
                    <TextInput label="Concepto*" v-model="newCustomization.key" placeholder="Ej. Teléfono" />
                    <TextInput label="Valor" v-model="newCustomization.value" placeholder="Ej. 3312158856" />
                </div>
                <div class="flex justify-end mt-2">
                    <SecondaryButton @click="addCustomizationDetail" type="button" :disabled="!newCustomization.type || !newCustomization.key">
                        <i class="fa-solid fa-plus mr-2"></i>
                        Agregar Detalle
                    </SecondaryButton>
                </div>

                <div v-if="currentProduct.customization_details.length" class="mt-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Detalles agregados:</p>
                    <div class="flex flex-wrap gap-2">
                        <el-tag
                            v-for="(detail, index) in currentProduct.customization_details"
                            :key="index"
                            closable
                            @close="removeCustomizationDetail(index)"
                            type="info"
                            size="large"
                            class="!h-auto"
                        >
                            <span class="whitespace-normal">
                                <strong>{{ detail.type }}</strong> | {{ detail.key }}: {{ detail.value }}
                            </span>
                        </el-tag>
                    </div>
                </div>
            </div>
            <!-- FIN: SECCIÓN DE PERSONALIZACIÓN -->

            <label class="flex items-center mt-2">
                <Checkbox v-model:checked="currentProduct.is_new_design" class="bg-transparent border-gray-500" />
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-300">Diseño nuevo</span>
            </label>
            <div class="pt-2 col-span-full">
                <SecondaryButton @click="addProduct" type="button" :disabled="!currentProduct.id || !currentProduct.quantity || (saleType === 'venta' && !currentProduct.price)">
                    {{ editIndex !== null ? 'Actualizar producto' : 'Agregar producto' }}
                </SecondaryButton>
                <button @click="resetCurrentProduct" v-if="editIndex !== null" type="button" class="text-sm text-gray-500 hover:text-red-500 ml-3">
                    Cancelar edición
                </button>
            </div>
        </div>
    </div>

    <!-- Lista de productos agregados -->
    <div v-if="products.length" class="mt-5">
        <h3 class="font-bold mb-2 text-gray-800 dark:text-gray-200">Lista de productos agregados</h3>
        <ul class="rounded-lg bg-gray-100 dark:bg-slate-800 p-3 space-y-2">
            <li v-for="(product, index) in products" :key="index" class="flex justify-between items-center p-3 rounded-md transition-colors"
                :class="{ 'bg-blue-100 dark:bg-blue-900/50': editIndex === index }">
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-800 dark:text-gray-200">
                        <p class="font-bold text-primary">{{ getProductName(product.id) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Cantidad: {{ product.quantity }} 
                            <template v-if="saleType === 'venta'">
                                | P.U: ${{ formatNumber(product.price) }} | Subtotal: ${{ formatNumber(product.quantity * product.price) }}
                            </template>
                        </p>
                        <small v-if="product.notes" class="text-xs text-gray-500 dark:text-gray-400">{{ product.notes }}</small>
                        
                        <div v-if="product.customization_details && product.customization_details.length" class="mt-2">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">Personalización:</p>
                            <ul class="list-disc list-inside pl-1">
                                <li v-for="(detail, i) in product.customization_details" :key="i" class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ detail.type }} - {{ detail.key }}: {{ detail.value }}
                                </li>
                            </ul>
                        </div>
                    </span>
                </div>
                <div class="flex items-center space-x-3">
                    <el-tooltip content="Editar" placement="top">
                        <button @click="editProduct(index)" type="button" class="text-gray-500 hover:text-blue-500 transition-colors">
                            <i class="fa-solid fa-pencil"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip content="Eliminar" placement="top">
                        <button @click="deleteProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </el-tooltip>
                </div>
            </li>
        </ul>
    </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Checkbox from "@/Components/Checkbox.vue";
import { ElMessage } from 'element-plus';

export default {
    name: 'SaleProductManager',
    components: {
        SecondaryButton,
        LoadingIsoLogo,
        InputError,
        InputLabel,
        TextInput,
        Checkbox,
    },
    props: {
        modelValue: Array,
        branchId: Number,
        saleType: {
            type: String,
            required: true,
        },
        availableProducts: {
            type: Array,
            required: true,
        },
        productsError: String,
    },
    emits: ['update:modelValue'],
    data() {
        return {
            currentProduct: {
                id: null,
                quantity: 1,
                price: null,
                notes: '',
                is_new_design: false,
                storages: [], 
                has_customization: false,
                customization_details: [],
            },
            newCustomization: {
                type: null,
                key: '',
                value: ''
            },
            customizationTypes: [
                'Grabado de medallón',
                'Estampado',
                'Bordado',
                'Impresión digital',
                'Otro'
            ],
            editIndex: null,
            loadingProductData: false,
        };
    },
    computed: {
        products: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            }
        }
    },
    methods: {
        addProduct() {
            // para orden de stock, el precio siempre es 0
            if (this.saleType === 'stock') {
                this.currentProduct.price = 0;
            }

            const productToAdd = { ...this.currentProduct };
            let updatedProducts = [...this.products];

            if (this.editIndex !== null) {
                updatedProducts[this.editIndex] = productToAdd;
            } else {
                updatedProducts.push(productToAdd);
            }
            this.products = updatedProducts;
            this.resetCurrentProduct();
        },
        async editProduct(index) {
            this.currentProduct = JSON.parse(JSON.stringify(this.products[index]));
            
            if (!this.currentProduct.customization_details) {
                this.currentProduct.customization_details = [];
            }
            this.currentProduct.has_customization = this.currentProduct.customization_details.length > 0;

            this.editIndex = index;
            this.$refs.formProducts.scrollIntoView({ behavior: 'smooth' });
            
            await this.getProductData();
        },
        deleteProduct(index) {
            let updatedProducts = [...this.products];
            updatedProducts.splice(index, 1);
            this.products = updatedProducts;
            ElMessage.info('Producto eliminado de la lista');
        },
        resetCurrentProduct() {
            this.currentProduct = { 
                id: null, 
                quantity: 1, 
                price: null, 
                notes: '', 
                is_new_design: false,
                storages: [],
                has_customization: false,
                customization_details: [],
            };
            this.editIndex = null;
            this.newCustomization = { type: null, key: '', value: '' };
        },
        addCustomizationDetail() {
            if (!this.newCustomization.type || !this.newCustomization.key || !this.newCustomization.value) {
                ElMessage.warning('Completa todos los campos de personalización.');
                return;
            }
            this.currentProduct.customization_details.push({ ...this.newCustomization });
            this.newCustomization = { type: null, key: '', value: '' };
        },
        removeCustomizationDetail(index) {
            this.currentProduct.customization_details.splice(index, 1);
            ElMessage.info('Detalle de personalización eliminado.');
        },
        getProductName(productId) {
            const product = this.availableProducts.find(p => p.id === productId);
            return product ? product.name : 'Producto no encontrado';
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            if (isNaN(num)) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        async getProductData() {
            if (!this.currentProduct.id) return;
            this.loadingProductData = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.id));

                if ( response.status === 200 ) {
                    const productData = response.data.product;
                    this.currentProduct.media = productData.media;
                    this.currentProduct.storages = productData.storages;
                    this.currentProduct.components = productData.components;
                    
                    // La lógica de precios solo aplica para 'venta'
                    if (this.saleType === 'venta') {
                        this.currentProduct.base_price = productData.base_price;
                        
                        if (this.editIndex === null) {
                            this.currentProduct.price = productData.base_price;
                        }

                        const clientProduct = this.availableProducts.find(p => p.id === this.currentProduct.id);
                        if (clientProduct && clientProduct.price_history) { // Se asume que si tiene historial es producto de cliente
                            this.currentProduct.isClientProduct = true;
                            this.currentProduct.current_price = 
                            (!clientProduct.price_history?.[0]?.valid_to && clientProduct.price_history?.[0]?.price) 
                                ? clientProduct.price_history[0].price 
                                : clientProduct.base_price;

                            if (this.editIndex === null) {
                                this.currentProduct.price = this.currentProduct.current_price;
                            }
                        } else {
                            this.currentProduct.isClientProduct = false;
                            this.currentProduct.current_price = null;
                        }
                    }
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la información del producto')
            } finally {
                this.loadingProductData = false;
            }
        },
    }
}
</script>
