<template>
    <AppLayout title="Crear Cotización">
        <!-- Encabezado -->
        <div class="px-4 sm:px-0 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <Back :href="route('quotes.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crear nueva cotización
                </h2>
            </div>
        </div>

        <!-- Formulario principal -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-3 md:p-9 relative">
                    
                    <!-- Botón para ver productos del cliente -->
                    <div v-if="form.branch_id" class="absolute top-4 right-8">
                        <SecondaryButton @click="showClientProductsDrawer = true">
                            <i class="fa-solid fa-box-open mr-2"></i>
                            Ver productos del cliente
                        </SecondaryButton>
                    </div>

                    <form @submit.prevent="store">
                        <!-- SECCIÓN 1: INFORMACIÓN GENERAL -->
                        <el-divider content-position="left">
                            <span>Información General</span>
                        </el-divider>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                            <div>
                                 <InputLabel value="Cliente*" />
                                <el-select v-model="form.branch_id" filterable placeholder="Selecciona un cliente" class="!w-full">
                                    <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                </el-select>
                                <InputError :message="form.errors.branch_id" />
                            </div>
                            <TextInput label="Persona que recibe*" v-model="form.receiver" :error="form.errors.receiver" placeholder="Ej. Juan Pérez" />
                            <TextInput label="Departamento / Puesto*" v-model="form.department" :error="form.errors.department" placeholder="Ej. Gerente de Compras" />
                            <div>
                                <InputLabel value="Moneda general*" />
                                <el-select v-model="form.currency" placeholder="Selecciona la moneda" class="!w-full">
                                    <el-option label="MXN (Peso Mexicano)" value="MXN" />
                                    <el-option label="USD (Dólar Americano)" value="USD" />
                                </el-select>
                                <InputError :message="form.errors.currency" />
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <TextInput label="Notas generales (opcional)"  :isTextarea="true" :withMaxLength="true" :maxLength="255" v-model="form.notes" type="textarea" :error="form.errors.notes" />
                            </div>
                        </div>

                        <!-- SECCIÓN 2: COSTOS DE HERRAMENTAL Y FLETE -->
                        <el-divider content-position="left" class="!mt-8">
                            <span>Costos Adicionales</span>
                        </el-divider>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4 items-start">
                             <TextInput label="Costo de Herramental*" v-model="form.tooling_cost" type="number" :error="form.errors.tooling_cost">
                                <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                             </TextInput>
                             <div class="flex items-center space-x-2 mt-8">
                                <el-checkbox v-model="form.is_tooling_cost_stroked" label="Tachar costo" size="large" />
                             </div>
                             <div></div> <!-- Espaciador -->
                             <div>
                                 <InputLabel value="Opción de flete*" />
                                <el-select v-model="form.freight_option" placeholder="Selecciona el flete" class="!w-full">
                                    <el-option label="Por cuenta del cliente" value="Por cuenta del cliente" />
                                    <el-option label="Cargo prorrateado en productos" value="Cargo de flete prorrateado en productos" />
                                    <el-option label="La empresa absorbe el costo" value="La empresa absorbe el costo de flete" />
                                </el-select>
                                <InputError :message="form.errors.freight_option" />
                            </div>
                            <TextInput label="Costo de Flete*" v-model="form.freight_cost" type="number" :error="form.errors.freight_cost">
                               <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                            </TextInput>
                            <div class="flex items-center space-x-2 mt-8">
                                <el-checkbox v-model="form.is_freight_cost_stroked" label="Tachar flete" size="large" />
                            </div>
                        </div>

                        <!-- SECCIÓN 3: PRODUCTOS -->
                        <el-divider content-position="left" class="!mt-8">
                            <span>Productos a Cotizar</span>
                        </el-divider>

                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <div class="flex items-center space-x-2 mb-3">
                                 <InputLabel value="Producto*" />
                                <el-tooltip content="Refrescar lista de productos" placement="top">
                                    <button @click="fetchCatalogProducts" type="button" class="text-primary hover:opacity-75 transition-opacity duration-200">
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                    </button>
                                </el-tooltip>
                                <a :href="route('catalog-products.create')" target="_blank" class="text-primary hover:opacity-75 transition-opacity duration-200 text-xs ml-2">(+ Nuevo producto)</a>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 items-start">
                                <el-select @change="getProductData" v-model="currentProduct.id" filterable placeholder="Buscar producto" class="lg:col-span-2">
                                    <el-option v-for="product in localCatalogProducts" :key="product.id" :label="`${product.name} (${product.part_number})`" :value="product.id" />
                                </el-select>
                                <TextInput label="Cantidad*" v-model="currentProduct.quantity" type="number" />
                                <TextInput label="Precio Unitario*" v-model="currentProduct.unit_price" type="number" />

                                <!-- Estado de carga -->
                                <LoadingIsoLogo class="col-span-full" v-if="loadingProductData" />

                                <!-- Tarjeta de materia prima seleccionada -->
                                <div class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="currentProduct.id">
                                    <figure 
                                        v-if="currentProduct.media" 
                                        class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
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
                                        <p class="text-gray-500 dark:text-gray-300">
                                            Stock: <strong>{{ currentProduct.storages[0]?.quantity }}</strong> unidades
                                        </p>
                                        <p class="text-gray-500 dark:text-gray-300">
                                            Ubicación: <strong>{{ currentProduct.storages[0]?.location ?? 'No asignado' }}</strong>
                                        </p>
                                        <p class="text-gray-500 dark:text-gray-300">
                                            precio base: <strong>{{ currentProduct.base_price ?? '0.00' }}</strong>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="lg:col-span-5">
                                     <TextInput label="Notas del producto (opcional)" v-model="currentProduct.notes" type="textarea" rows="1" />
                                </div>
                                <div class="pt-2">
                                    <SecondaryButton @click="addProduct" type="button" :disabled="!currentProduct.id || !currentProduct.quantity || !currentProduct.unit_price">
                                        {{ editIndex !== null ? 'Actualizar' : 'Agregar' }}
                                    </SecondaryButton>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.products" class="mt-2" />

                        <!-- Lista de productos agregados -->
                        <div v-if="form.products.length" class="mt-4 border dark:border-gray-600 rounded-lg p-3">
                            <h3 class="font-bold mb-2">Lista de productos</h3>
                            <div v-for="(product, index) in form.products" :key="index" class="flex items-center justify-between p-2 rounded-md" :class="index % 2 === 0 ? 'bg-gray-50 dark:bg-slate-800' : ''">
                                <div>
                                    <p class="font-semibold">{{ getProductName(product.id) }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Cantidad: {{ product.quantity }} | Precio U: ${{ product.unit_price }} | Subtotal: ${{ (product.quantity * product.unit_price).toFixed(2) }}
                                    </p>
                                    <p v-if="product.notes" class="text-xs italic text-gray-500 mt-1">Nota: {{ product.notes }}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <el-button @click="editProduct(index)" type="primary" circle><i class="fa-solid fa-pen"></i></el-button>
                                    <el-button @click="deleteProduct(index)" type="danger" circle><i class="fa-solid fa-trash"></i></el-button>
                                </div>
                            </div>
                        </div>

                        <!-- Botón de envío -->
                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing">
                                Crear Cotización
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Drawer para productos del cliente -->
        <el-drawer 
            v-model="showClientProductsDrawer" 
            title="Productos del Cliente" 
            direction="rtl" 
            :size="drawerSize"
            >
            <div class="md:p-3">
                <p v-if="!clientProducts.length && !loadingClientProducts">
                Este cliente no tiene productos registrados.
                </p>

                <!-- Estado de carga -->
                <LoadingIsoLogo class="col-span-full" v-if="loadingClientProducts" />

                <!-- Lista de productos -->
                <div v-else class="space-y-4">
                    <div 
                        v-for="product in clientProducts" 
                        :key="product.id" 
                        class="relative bg-gray-100 dark:bg-slate-900 shadow-md rounded-2xl p-4 transition hover:shadow-xl duration-300"
                    >
                        <!-- icono para ver producto -->
                        <button @click="openProduct(product.id)" class="absolute top-2 right-2 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full size-8">
                            <i class="fa-solid fa-eye text-gray-500 dark:text-gray-600"></i>
                        </button>

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
                        </div>
                        </div>

                        <!-- Precios -->
                        <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Precio base</p>
                        <p class="font-medium text-secondary">${{ product.base_price }}</p>
                        </div>
                        <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Precio actual</p>
                        <p class="font-semibold text-green-600 dark:text-green-400">
                            ${{ product.price_history?.[0]?.price || product.base_price }}
                        </p>
                        </div>

                        <!-- Último cambio de precio -->
                        <div 
                            v-if="product.price_history?.length" 
                            class="mt-2 text-sm rounded-sm py-1 px-2"
                            :class="getPriceChangeClass(product.price_history[0].valid_from)"
                            >
                            Último cambio de precio: {{ timeSince(product.price_history[0].valid_from) }}
                        </div>

                        <!-- Historial de precios -->
                        <el-collapse v-if="product.price_history?.length" class="mt-4">
                            <el-collapse-item :title="'Historial de precios'" name="history">
                                <ul class="space-y-1 max-h-40 overflow-y-auto pr-2 text-sm">
                                <li 
                                    v-for="(history, idx) in product.price_history" 
                                    :key="idx" 
                                    class="flex justify-between text-gray-600 dark:text-gray-400"
                                >
                                    <span>{{ formatDate(history.valid_from) }}</span>
                                    <span class="font-medium">${{ history.price }}</span>
                                </li>
                                </ul>
                            </el-collapse-item>
                        </el-collapse>
                    </div>
                </div>
            </div>
        </el-drawer>

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    // Usando Options API
    data() {
        return {
            form: useForm({
                branch_id: null,
                receiver: '',
                department: '',
                currency: 'MXN',
                tooling_cost: 0,
                is_tooling_cost_stroked: false,
                freight_cost: 0,
                is_freight_cost_stroked: false,
                freight_option: 'Por cuenta del cliente',
                first_production_days: '3 a 4 días',
                notes: '',
                is_spanish_template: true,
                show_breakdown: true,
                has_early_payment_discount: false,
                early_payment_discount_amount: 0,
                products: [],
            }),
            currentProduct: {
                id: null,
                quantity: null,
                unit_price: null,
                notes: '',
                customization_details: null,
            },
            editIndex: null,
            localCatalogProducts: this.catalogProducts,
            showClientProductsDrawer: false,
            clientProducts: [],
            loadingClientProducts: false,
            loadingProductData: false,
            drawerSize: "30%" // valor inicial
        };
    },
    components: {
        Back,
        TextInput,
        AppLayout,
        InputError,
        InputLabel,
        LoadingIsoLogo,
        SecondaryButton,
    },
    props: {
        catalogProducts: Array,
        branches: Array,
    },
    methods: {
        store() {
            this.form.post(route("quotes.store"), {
                onSuccess: () => {
                    ElMessage.success('Cotización creada correctamente');
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario.');
                }
            });
        },
        addProduct() {
            // Clonar el objeto para evitar reactividad no deseada
            const productToAdd = { ...this.currentProduct };
            
            if (this.editIndex !== null) {
                this.form.products[this.editIndex] = productToAdd;
            } else {
                this.form.products.push(productToAdd);
            }
            this.resetCurrentProduct();
        },
        editProduct(index) {
            this.currentProduct = { ...this.form.products[index] };
            this.editIndex = index;
        },
        deleteProduct(index) {
            this.form.products.splice(index, 1);
        },
        resetCurrentProduct() {
            this.currentProduct = { id: null, quantity: null, unit_price: null, notes: '', customization_details: null };
            this.editIndex = null;
        },
        getProductName(productId) {
            const product = this.localCatalogProducts.find(p => p.id === productId);
            return product ? product.name : 'Producto no encontrado';
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },

        // ------ Metodos para el drawer ( productos del cliente ) -------
        openProduct(id) {
            window.open(`/catalog-products/${id}`, "_blank");
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
        updateDrawerSize() {
            const width = window.innerWidth;
            if (width < 640) {
                this.drawerSize = "90%"; // móvil
            } else if (width < 1024) {
                this.drawerSize = "60%"; // tablet
            } else {
                this.drawerSize = "30%"; // desktop
            }
        },

        // ------- Metodos asíncronos -----------
        async getProductData() {
            // 1. Inicia el estado de carga
            this.loadingProductData = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.id));

                if ( response.status === 200 ) {
                    this.currentProduct.media = response.data.product.media;
                    this.currentProduct.storages = response.data.product.storages;
                    this.currentProduct.base_price = response.data.product.base_price;
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la imagen del componente')
            } finally {
                this.loadingProductData = false;
            }
        },
        async fetchCatalogProducts() {
            try {
                const response = await axios.get(route('api.catalog-products.index')); // Asumiendo que tienes una ruta API
                this.localCatalogProducts = response.data;
                ElMessage.success('Lista de productos actualizada');
            } catch (error) {
                ElMessage.error('No se pudo actualizar la lista de productos.');
            }
        },
        async fetchClientProducts(branchId) {
            if (!branchId) return;
            this.loadingClientProducts = true;
            try {
                const response = await axios.get(route('branches.fetch-products', branchId));
                this.clientProducts = response.data;
            } catch (error) {
                console.error("Error fetching client products:", error);
                ElMessage.error('No se pudieron cargar los productos del cliente.');
            } finally {
                this.loadingClientProducts = false;
            }
        }
    },
    watch: {
        // Observador para cargar productos del cliente cuando se selecciona uno
        'form.branch_id'(newVal) {
            this.clientProducts = []; // Limpiar la lista anterior
            if (newVal) {
                this.fetchClientProducts(newVal);
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
};
</script>
