<template>
    <!-- SECCIÓN DE PRODUCTOS -->
    <el-divider content-position="left" class="!mt-8">
        <span>Productos de la Orden</span>
    </el-divider>
    <InputError :message="productsError" class="mt-2" />

    <div ref="formProducts" class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border border-gray-200 dark:border-slate-700">
        <p v-if="saleType === 'venta' && !branchId" class="text-center text-gray-500 py-4">
            <i class="fa-solid fa-arrow-up mr-2"></i>
            Selecciona un cliente para agregar productos.
        </p>
        
        <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-start">
            
            <!-- PRODUCTO DE CATÁLOGO (CON PADRES Y VARIANTES) -->
            <div class="lg:col-span-full bg-white dark:bg-slate-900/50 p-4 rounded-lg border border-gray-200 dark:border-slate-700 shadow-sm mb-3">
                <div class="mb-4">
                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-2 font-semibold">
                        <span class="bg-primary text-white px-2 py-0.5 rounded-full mr-1 text-xs">1</span>
                        Selecciona el producto base
                    </p>
                    <el-select @change="handleBaseProductChange" v-model="selectedBaseProductId" filterable placeholder="Buscar producto base" class="w-full md:w-1/2">
                        <el-option class="!w-96" v-for="product in availableProducts" 
                            :key="product.id" 
                            :label="`${product.name} (${product.code || 'S/C'})`" 
                            :value="product.id"
                             />
                    </el-select>
                </div>

                <!-- Variants Section -->
                <div v-if="selectedBaseProductId && availableVariants.length" class="animate-fade-in-down">
                    <el-divider border-style="dashed" />
                    
                    <!-- Buscador de Variantes -->
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4 gap-3">
                        <p class="text-sm text-gray-700 dark:text-gray-300 font-semibold">
                            <span class="bg-primary text-white px-2 py-0.5 rounded-full mr-1 text-xs">2</span>
                            Selecciona una variante o personalización:
                        </p>
                        <el-input
                            v-model="variantSearchQuery"
                            placeholder="Buscar variante..."
                            clearable
                            class="w-full md:w-72"
                        >
                            <template #prefix>
                                <i class="fa-solid fa-search"></i>
                            </template>
                        </el-input>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 max-h-96 overflow-y-auto pb-2">
                        <!-- Base Product Card (Opción para no usar variante) -->
                        <div v-show="!variantSearchQuery && isBaseProductAllowed" @click="selectVariant(null)"
                             :class="['cursor-pointer border rounded-xl p-2 transition-all shadow-sm flex flex-col items-center justify-between', selectedVariantId === null ? 'border-primary ring-2 ring-primary bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-700 hover:border-primary/50 bg-gray-50 dark:bg-slate-800/50']">
                            <div class="w-full aspect-square bg-gray-200 dark:bg-slate-700 flex items-center justify-center rounded-lg mb-2">
                                <i class="fa-solid fa-box text-gray-400 text-3xl"></i>
                            </div>
                            <p class="text-[11px] font-bold text-center text-gray-700 dark:text-gray-300 leading-tight">Sin personalización</p>
                            <p class="text-[10px] text-gray-500 text-center mt-1">(Usar producto base)</p>
                        </div>

                        <!-- No asignado al cliente (Producto base no permitido) -->
                        <div v-show="!variantSearchQuery && !isBaseProductAllowed" class="cursor-not-allowed opacity-50 border rounded-xl p-2 transition-all shadow-sm flex flex-col items-center justify-between border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
                            <div class="w-full aspect-square bg-gray-200 dark:bg-slate-700 flex items-center justify-center rounded-lg mb-2">
                                <i class="fa-solid fa-box text-gray-400 text-3xl"></i>
                            </div>
                            <p class="text-[11px] font-bold text-center text-gray-700 dark:text-gray-300 leading-tight">Sin personalización</p>
                            <p class="text-[10px] text-red-500 text-center mt-1">(No asignado al cliente)</p>
                        </div>

                        <!-- Mensaje si no hay resultados en la búsqueda -->
                        <div v-if="filteredVariants.length === 0" class="col-span-full py-4 text-center text-gray-500 text-sm">
                            No se encontraron variantes que coincidan.
                        </div>

                        <!-- Tarjetas de Variantes -->
                        <div v-for="variant in filteredVariants" :key="variant.id"
                             @click="selectVariant(variant)"
                             :class="['cursor-pointer border rounded-xl p-2 transition-all shadow-sm hover:shadow-md flex flex-col items-center justify-between', selectedVariantId === variant.id ? 'border-primary ring-2 ring-primary bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-slate-700 hover:border-primary/50 bg-white dark:bg-slate-800']">
                            <div class="w-full aspect-square mb-2 rounded-lg overflow-hidden relative group bg-gray-100 dark:bg-slate-700">
                                <img v-if="variant.media?.length" :src="variant.media[0].original_url" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <i class="fa-solid fa-image text-gray-400 text-3xl"></i>
                                </div>
                            </div>
                            <p class="text-[11px] font-bold text-center text-gray-700 dark:text-gray-300 leading-tight w-full line-clamp-2" :title="variant.name">{{ variant.name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Siempre visibles: Cantidad y Precio -->
            <div class="lg:col-span-2">
                <TextInput label="Cantidad*" v-model="currentProduct.quantity" type="number" />
            </div>
            
            <div class="lg:col-span-2">
                <!-- En orden de stock el precio es opcional, en venta es obligatorio -->
                <TextInput :label="saleType === 'venta' ? 'Precio Unitario (Venta)*' : 'Precio Unitario (Opcional)'" v-model="currentProduct.price" type="number" :formatAsNumber="true">
                    <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                </TextInput>
            </div>

            <!-- ALERTA DE PRECIO BAJO (SOLO VENTAS) -->
            <div v-if="isPriceLow" class="col-span-full mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-md border border-amber-200 dark:border-amber-800 animate-fade-in-down">
                <p class="text-amber-600 dark:text-amber-400 text-xs font-bold mb-2">
                    <i class="fa-solid fa-triangle-exclamation"></i> Precio por debajo del mínimo permitido. Por favor, justifica esta decisión para su validación.
                </p>
                <TextInput 
                    label="Razón del precio*" 
                    v-model="currentProduct.low_price_reason" 
                    :isTextarea="true" 
                    placeholder="Justifica este precio..." 
                />
            </div>

            <!-- Estado de carga -->
            <LoadingIsoLogo class="col-span-full" v-if="loadingProductData" />

            <!-- Tarjeta de Producto Seleccionado (Previsualización de información y stock) -->
            <div v-else-if="currentProduct.id" class="p-4 bg-gray-100 dark:bg-slate-900/50 rounded-lg col-span-full mb-2 border border-gray-200 dark:border-slate-800" >
                <div class="flex items-start space-x-4">
                    <figure 
                        class="relative flex items-center justify-center w-32 h-32 min-w-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl bg-white dark:bg-slate-800">
                        <img v-if="currentProduct.media?.length"
                            :src="currentProduct.media[0]?.original_url" 
                            alt="Imagen del producto" 
                            class="rounded-2xl w-full h-full object-cover transition duration-300 ease-in-out hover:opacity-95"
                        >
                        <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                            <i class="fa-solid fa-image text-3xl mb-2"></i>
                            <p class="text-xs">Sin imagen</p>
                        </div>
                    </figure>

                    <!-- Información de almacén y precios -->
                    <div class="flex-1 text-sm">
                        <span v-if="saleType === 'venta' && currentProduct.isClientProduct" class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full mb-2 inline-block">
                            Producto de cliente
                        </span>
                        
                        <p class="text-gray-500 dark:text-gray-300">
                            Producto Seleccionado: <strong class="text-primary">{{ currentProduct.name }}</strong>
                        </p>

                        <!-- STOCK DE PRODUCTO TERMINADO -->
                        <div class="text-gray-500 dark:text-gray-300 flex items-center flex-wrap mt-1">
                            Stock (P. Terminado):&nbsp;
                            <strong :class="{'text-red-600 flex items-center ml-1': (currentProduct.storages[0]?.quantity ?? 0) == 0}">
                                <i v-if="(currentProduct.storages[0]?.quantity ?? 0) == 0" class="fa-solid fa-circle-exclamation mr-1 text-red-500 animate-pulse"></i>
                                {{ currentProduct.storages[0]?.quantity ?? 0 }}
                            </strong>
                            &nbsp;unidades
                        </div>

                        <p class="text-gray-500 dark:text-gray-300">
                            Ubicación: <strong>{{ currentProduct.storages[0]?.location ?? 'No asignado' }}</strong>
                        </p>
                        <p class="text-gray-500 dark:text-gray-300">
                            Stock mínimo: <strong>{{ currentProduct.min_quantity?.toLocaleString() + ' unidades' ?? 'No definido' }}</strong>
                        </p>
                        
                        <p v-if="saleType === 'venta'" class="text-gray-500 dark:text-gray-300 mt-1">
                            Precio base del catálogo: <strong>${{ formatNumber(currentProduct.base_price) ?? '0.00' }}</strong>
                        </p>
                        <p v-if="saleType === 'venta' && currentProduct.isClientProduct && currentProduct.current_price" class="text-green-600 dark:text-green-400 font-semibold mt-1">
                            Precio actual (para este cliente): <strong>${{ formatNumber(currentProduct.current_price) ?? '0.00' }}</strong>
                        </p>
                    </div>
                </div>

                <!-- ALERTA DE MATERIA PRIMA INSUFICIENTE -->
                <div v-if="showStockWarning" class="col-span-full mt-4 animate-fade-in-down">
                    <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fa-solid fa-triangle-exclamation text-red-500 text-xl mt-0.5"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-bold text-red-800 dark:text-red-400">
                                    Atención: Insuficiencia de Material
                                </h3>
                                <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                    Reportar falta de materia prima a la autoridad correspondiente.
                                </p>
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1 opacity-90">
                                    Solicitado: <strong>{{ Number(currentProduct.quantity)?.toLocaleString() }}</strong> | 
                                    Disponible (Stock + Producción): <strong>{{ totalAvailableForOrder?.toLocaleString() }}</strong> | 
                                    Faltante: <strong>{{ Number(currentProduct.quantity - totalAvailableForOrder)?.toLocaleString() }}</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- --- SECCIÓN DE COMPONENTES --- -->
                <div v-if="currentProduct.components?.length" class="mt-4 pt-4 border-t border-gray-300 dark:border-slate-800">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-3 gap-2">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Componentes para Producción</h4>
                        
                        <!-- INDICADOR DE CAPACIDAD DE PRODUCCIÓN -->
                        <div class="text-xs px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-700 shadow-sm flex items-center">
                            <i class="fa-solid fa-boxes-stacked mr-2"></i>
                            <span>
                                Capacidad Máxima de Producción: 
                                <strong class="text-sm ml-1">{{ formatNumber(maxProducibleQuantity) }}</strong> sets
                            </span>
                        </div>
                    </div>

                    <ul class="space-y-2">
                        <li v-for="component in currentProduct.components" :key="component.id" class="flex items-center justify-between text-sm p-2 rounded-lg bg-white dark:bg-slate-800 shadow-sm">
                            <div class="flex items-center space-x-3">
                                <figure 
                                    class="relative flex items-center justify-center size-12 min-w-12 rounded-lg border border-gray-200 dark:border-slate-900 overflow-hidden shadow-sm bg-gray-50 dark:bg-slate-700">
                                    <img v-if="component.media?.length"
                                        :src="component.media[0]?.original_url" 
                                        alt="Imagen del componente" 
                                        class="rounded-lg w-full h-full object-cover"
                                    >
                                    <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                                        <i class="fa-solid fa-image"></i>
                                    </div>
                                </figure>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ component.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Requerido: {{ component.pivot.quantity }} {{ component.measure_unit }}</p>
                                    <!-- INDICADOR DE STOCK MINIMO DE CADA COMPONENTE -->
                                    <p class="text-[11px] text-gray-400 mt-0.5">Stock Mínimo: {{ component.min_quantity?.toLocaleString() ?? 0 }} {{ component.measure_unit }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 text-right">
                                <!-- Icono de advertencia para stock bajo -->
                                <div v-if="(component.storages[0]?.quantity ?? 0) < component.min_quantity" class="text-yellow-500" title="El stock está por debajo del mínimo requerido">
                                    <i class="fa-solid fa-circle-exclamation text-lg"></i>
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

            <label class="flex items-center mt-2 col-span-full">
                <Checkbox v-model:checked="currentProduct.is_new_design" class="bg-transparent border-gray-500" />
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-300">Diseño nuevo</span>
            </label>
            
            <div class="pt-2 col-span-full">
                <SecondaryButton @click="addProduct" type="button" :disabled="isAddProductDisabled">
                    {{ editIndex !== null ? 'Actualizar producto' : 'Agregar producto a la orden' }}
                </SecondaryButton>
                <button @click="resetCurrentProduct" v-if="editIndex !== null" type="button" class="text-sm text-gray-500 hover:text-red-500 ml-3 transition-colors">
                    Cancelar edición
                </button>
            </div>
        </div>
    </div>

    <!-- Lista de productos agregados -->
    <div v-if="products.length" class="mt-5 mb-8">
        <h3 class="font-bold mb-2 text-gray-800 dark:text-gray-200">Lista de productos en la orden</h3>
        <ul class="rounded-lg bg-gray-100 dark:bg-slate-800 p-3 space-y-2 border border-gray-200 dark:border-slate-700">
            <li v-for="(product, index) in products" :key="index" class="flex justify-between items-center p-3 rounded-md transition-colors bg-white dark:bg-slate-900"
                :class="{ '!bg-blue-50 dark:!bg-blue-900/50': editIndex === index }">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-md overflow-hidden bg-gray-200 dark:bg-slate-700 shrink-0 border dark:border-slate-600">
                        <img v-if="product.media?.length" :src="product.media[0]?.original_url" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-gray-400"><i class="fa-solid fa-image"></i></div>
                    </div>
                    
                    <span class="text-sm text-gray-800 dark:text-gray-200">
                        <p class="font-bold text-primary">{{ product.name || getProductName(product.id) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Cantidad: {{ product.quantity }} 
                            <template v-if="saleType === 'venta'">
                                | P.U: ${{ formatNumber(product.price) }} | Subtotal: ${{ formatNumber(product.quantity * product.price) }}
                            </template>
                        </p>
                        <p v-if="product.has_low_price" class="text-xs text-amber-600 dark:text-amber-400 mt-1 font-semibold">
                            <i class="fa-solid fa-triangle-exclamation"></i> Razón precio bajo: <span class="font-normal italic">{{ product.low_price_reason }}</span>
                        </p>
                        <p v-if="product.notes" class="text-xs italic text-gray-500 mt-1">Nota: {{ product.notes }}</p>
                        
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
                <div class="flex items-center space-x-3 shrink-0">
                    <el-tooltip content="Cancelar edición" placement="top">
                        <button @click="resetCurrentProduct" v-if="editIndex === index" type="button" class="flex items-center justify-center text-gray-500 hover:text-red-500 transition-colors">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </el-tooltip>
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
import axios from 'axios';

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
        availableProducts: { // Estos son los PADRES del catálogo
            type: Array,
            required: true,
        },
        clientProducts: { // Estos son los productos específicos asignados al cliente (con sus precios)
            type: Array,
            default: () => [],
        },
        productsError: String,
    },
    emits: ['update:modelValue'],
    data() {
        return {
            selectedBaseProductId: null,
            selectedVariantId: null,
            availableVariants: [],
            variantSearchQuery: '',
            editIndex: null,
            loadingProductData: false,

            currentProduct: {
                id: null,
                name: '',
                media: null,
                quantity: 1,
                price: null,
                base_price: null,
                current_price: null,
                isClientProduct: false,
                has_low_price: false,
                low_price_reason: '',
                notes: '',
                is_new_design: false,
                storages: [], 
                has_customization: false,
                customization_details: [],
                components: [],
                min_quantity: null,
                max_quantity: null,
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
        },
        filteredVariants() {
            let variants = this.availableVariants;
            if (this.saleType === 'venta') {
                const clientProductIds = new Set(this.clientProducts.map(p => p.id));
                variants = variants.filter(v => clientProductIds.has(v.id));
            }
            if (this.variantSearchQuery) {
                const query = this.variantSearchQuery.toLowerCase().trim();
                variants = variants.filter(v => (v.name && v.name.toLowerCase().includes(query)) || (v.code && v.code.toLowerCase().includes(query)));
            }
            return variants;
        },
        isBaseProductAllowed() {
            if (this.saleType === 'stock') return true;
            return this.clientProducts.some(p => p.id === this.selectedBaseProductId);
        },
        isAddProductDisabled() {
            if (!this.currentProduct.id || !this.currentProduct.quantity) return true;
            // Para ventas, se requiere el precio obligatoriamente
            if (this.saleType === 'venta' && (this.currentProduct.price === null || this.currentProduct.price === '')) return true;
            if (this.isPriceLow && !this.currentProduct.low_price_reason) return true;
            return false;
        },
        isPriceLow() {
            if (this.saleType !== 'venta' || !this.currentProduct.id || !this.currentProduct.price) return false;
            
            let minPrice = this.currentProduct.base_price || 0;
            if (this.currentProduct.isClientProduct && this.currentProduct.current_price !== null && this.currentProduct.current_price !== undefined) {
                minPrice = this.currentProduct.current_price;
            }
            
            const isLow = parseFloat(this.currentProduct.price) < (parseFloat(minPrice) - 0.01);
            this.currentProduct.has_low_price = isLow;
            return isLow;
        },
        maxProducibleQuantity() {
            if (!this.currentProduct.components?.length) return 0;
            
            const limits = this.currentProduct.components.map(component => {
                const stock = Number(component.storages?.[0]?.quantity || 0);
                const required = Number(component.pivot?.quantity || 1);
                if (required === 0) return Infinity;
                return Math.floor(stock / required);
            });

            return Math.min(...limits);
        },
        totalAvailableForOrder() {
             const finishedStock = Number(this.currentProduct.storages?.[0]?.quantity || 0);
             return finishedStock + this.maxProducibleQuantity;
        },
        showStockWarning() {
             if (!this.currentProduct.id || this.currentProduct.quantity <= 0) return false;
             return this.currentProduct.quantity > this.totalAvailableForOrder;
        }
    },
    methods: {
        handleBaseProductChange(productId) {
            const baseProduct = this.availableProducts.find(p => p.id === productId);
            this.availableVariants = baseProduct ? (baseProduct.variants || []) : [];
            this.selectedVariantId = null;
            this.variantSearchQuery = ''; 
            this.currentProduct.id = productId;
            this.getProductData();
        },
        selectVariant(variant) {
            if (!variant) {
                this.selectedVariantId = null;
                this.currentProduct.id = this.selectedBaseProductId;
            } else {
                this.selectedVariantId = variant.id;
                this.currentProduct.id = variant.id;
            }
            this.getProductData();
        },
        addProduct() {
            if (this.saleType === 'stock') {
                this.currentProduct.price = 0;
            }

            // Aseguramos llevar el nombre y la media para que se pinte bonito en la lista
            this.currentProduct.name = this.getProductName(this.currentProduct.id);

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

            // Reconstituir la interfaz buscando de quién es hijo este producto
            let foundBase = this.availableProducts.find(p => p.id === this.currentProduct.id);
            if (foundBase) {
                this.selectedBaseProductId = foundBase.id;
                this.availableVariants = foundBase.variants || [];
                this.selectedVariantId = null;
            } else {
                for (const base of this.availableProducts) {
                    const foundVariant = base.variants?.find(v => v.id === this.currentProduct.id);
                    if (foundVariant) {
                        this.selectedBaseProductId = base.id;
                        this.availableVariants = base.variants || [];
                        this.selectedVariantId = foundVariant.id;
                        break;
                    }
                }
            }

            this.variantSearchQuery = '';
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
                id: null, name: '', media: null, quantity: 1, price: null, base_price: null, current_price: null, 
                has_low_price: false, low_price_reason: '', notes: '', is_new_design: false, storages: [], 
                has_customization: false, customization_details: [], components: [], min_quantity: null, max_quantity: null
            };
            this.selectedBaseProductId = null;
            this.selectedVariantId = null;
            this.availableVariants = [];
            this.variantSearchQuery = '';
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
            for (const parent of this.availableProducts) {
                if (parent.id === productId) return parent.name;
                const v = parent.variants?.find(v => v.id === productId);
                if (v) return v.name;
            }
            return 'Producto no encontrado';
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

                if (response.status === 200) {
                    const productData = response.data.product;
                    this.currentProduct.name = this.getProductName(this.currentProduct.id);
                    this.currentProduct.media = productData.media;
                    this.currentProduct.storages = productData.storages;
                    this.currentProduct.min_quantity = productData.min_quantity;
                    this.currentProduct.max_quantity = productData.max_quantity;

                    // LÓGICA DE HERENCIA DE COMPONENTES DE PADRE A VARIANTE
                    let components = productData.components || [];
                    if (components.length === 0 && this.selectedBaseProductId && this.selectedBaseProductId !== this.currentProduct.id) {
                        try {
                            const parentResponse = await axios.get(route('products.get-media', this.selectedBaseProductId));
                            components = parentResponse.data.product.components || [];
                        } catch(e) {
                            console.error("No se pudieron cargar los componentes del padre.");
                        }
                    }
                    this.currentProduct.components = components;
                    
                    // Lógica de precios
                    if (this.saleType === 'venta') {
                        this.currentProduct.base_price = productData.base_price;
                        
                        if (this.editIndex === null && !this.currentProduct.price) {
                            this.currentProduct.price = productData.base_price;
                        }

                        // Validamos el precio que tiene el cliente (si es que lo tiene)
                        const clientProduct = this.clientProducts.find(p => p.id === this.currentProduct.id);
                        if (clientProduct) { 
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