<template>
    <!-- SECCIÓN DE PRODUCTOS -->
    <el-divider content-position="left" class="!mt-8">
        <span>Productos de la Orden</span>
    </el-divider>
    <InputError :message="productsError" class="mt-2" />

    <div ref="formProducts" class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
        <p v-if="!branchId" class="text-center text-gray-500">
            <i class="fa-solid fa-arrow-up mr-2"></i>
            Selecciona un cliente para agregar productos.
        </p>
        <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-start">
            <!-- Formulario para agregar/editar producto -->
            <div class="lg:col-span-2">
                <InputLabel value="Producto*" />
                <el-select @change="getProductData" v-model="currentProduct.id" filterable placeholder="Buscar producto" class="w-full" no-data-text="No hay productos para este cliente">
                    <el-option v-for="product in clientProducts" 
                        :key="product.id" 
                        :label="product.name" 
                        :value="product.id"
                        :disabled="products.some(p => p.id === product.id) && product.id !== this.products[editIndex]?.id" />
                </el-select>
            </div>
            <TextInput label="Cantidad*" v-model="currentProduct.quantity" type="number" />
            <TextInput label="Precio Unitario*" v-model="currentProduct.price" type="number" :formatAsNumber="true">
                <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
            </TextInput>

            <!-- Estado de carga -->
            <LoadingIsoLogo class="col-span-full" v-if="loadingProductData" />

            <!-- Tarjeta de producto seleccionado -->
            <div class="flex items-start space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="currentProduct.id && currentProduct.storages">
                <figure 
                    v-if="currentProduct.media" 
                    class="relative flex items-center justify-center w-32 h-32 min-w-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                    <img v-if="currentProduct.media?.length"
                        :src="currentProduct.media[0]?.original_url" 
                        alt="" 
                        class="rounded-2xl w-full h-auto object-cover transition duration-300 ease-in-out hover:opacity-95"
                    >
                    <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    <p>Sin imagen</p>
                    </div>
                </figure>

                <!-- informacion de almacén -->
                <div>
                    <!-- Etiqueta de producto de cliente -->
                    <span v-if="currentProduct.isClientProduct" class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full mb-2 inline-block">
                        Producto de cliente
                    </span>
                    <p class="text-gray-500 dark:text-gray-300">
                        Stock: <strong>{{ currentProduct.storages[0]?.quantity ?? 0 }}</strong> unidades
                    </p>
                    <p class="text-gray-500 dark:text-gray-300">
                        Ubicación: <strong>{{ currentProduct.storages[0]?.location ?? 'No asignado' }}</strong>
                    </p>
                    <p class="text-gray-500 dark:text-gray-300">
                        Precio base: <strong>${{ formatNumber(currentProduct.base_price) ?? '0.00' }}</strong>
                    </p>
                    <!-- Precio actual del cliente -->
                    <p v-if="currentProduct.isClientProduct" class="text-green-600 dark:text-green-400 font-semibold mt-1">
                        Precio actual: <strong>${{ formatNumber(currentProduct.current_price) ?? '0.00' }}</strong>
                    </p>
                </div>
            </div>
            
            <div class="lg:col-span-full">
                 <TextInput label="Notas del producto (opcional)" v-model="currentProduct.notes" type="textarea" :isTextarea="true" />
            </div>
            <label class="flex items-center">
                <Checkbox v-model:checked="currentProduct.is_new_design" class="bg-transparent border-gray-500" />
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-300">Diseño nuevo</span>
            </label>
            <div class="pt-2 col-span-full">
                <SecondaryButton @click="addProduct" type="button" :disabled="!currentProduct.id || !currentProduct.quantity || !currentProduct.price">
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
                            Cantidad: {{ product.quantity }} | P.U: ${{ formatNumber(product.price) }} | Subtotal: ${{ formatNumber(product.quantity * product.price) }}
                        </p>
                        <small v-if="product.notes" class="text-xs text-gray-500 dark:text-gray-400">{{ product.notes }}</small>
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
        modelValue: Array, // Para v-model:products
        branchId: Number,
        clientProducts: Array,
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
                storages: null, // Inicializar para evitar errores
            },
            editIndex: null,
            loadingProductData: false,
        };
    },
    computed: {
        // Hacemos una propiedad computada para poder modificar el array internamente y emitir el cambio
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
            const productToAdd = { ...this.currentProduct };
            let updatedProducts = [...this.products];

            if (this.editIndex !== null) {
                updatedProducts[this.editIndex] = productToAdd;
            } else {
                updatedProducts.push(productToAdd);
            }
            this.products = updatedProducts; // Esto activará el 'set' de la propiedad computada
            this.resetCurrentProduct();
        },
        async editProduct(index) {
            // Clonamos el producto de la lista para llenar el formulario
            this.currentProduct = JSON.parse(JSON.stringify(this.products[index]));
            this.editIndex = index;
            this.$refs.formProducts.scrollIntoView({ behavior: 'smooth' });
            
            // CORRECCIÓN: Llamamos a getProductData para obtener los detalles completos
            // del producto (como 'storages' y 'media') que no están en la lista principal.
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
                storages: null,
            };
            this.editIndex = null;
        },
        getProductName(productId) {
            const product = this.clientProducts.find(p => p.id === productId);
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
            // 1. Inicia el estado de carga
            this.loadingProductData = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.id));

                if ( response.status === 200 ) {
                    const productData = response.data.product;
                    this.currentProduct.media = productData.media;
                    this.currentProduct.storages = productData.storages;
                    this.currentProduct.base_price = productData.base_price;
                    
                    // Si no estamos editando, asignamos el precio base.
                    // Si estamos editando, el precio ya se copió del producto de la lista.
                    if (this.editIndex === null) {
                        this.currentProduct.price = productData.base_price;
                    }

                    // --- NUEVA LÓGICA ---
                    // Revisa si es un producto registrado por el cliente para obtener su precio especial
                    const clientProduct = this.clientProducts.find(p => p.id === this.currentProduct.id);
                    if (clientProduct) {
                        this.currentProduct.isClientProduct = true;
                        // Obtiene el precio actual del historial o usa el precio base como fallback
                        this.currentProduct.current_price = 
                        (!clientProduct.price_history?.[0]?.valid_to && clientProduct.price_history?.[0]?.price) 
                            ? clientProduct.price_history[0].price 
                            : clientProduct.base_price;

                        // Si no estamos editando, asigna el precio del cliente como precio por defecto
                        if (this.editIndex === null) {
                            this.currentProduct.price = this.currentProduct.current_price;
                        }
                    } else {
                        this.currentProduct.isClientProduct = false;
                        this.currentProduct.current_price = null;
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
