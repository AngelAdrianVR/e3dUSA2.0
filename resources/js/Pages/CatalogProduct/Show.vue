<template>
    <AppLayout title="Detalles de producto">
        <div class="p-4 sm:p-6 lg:p-8 dark:text-gray-200">
            <!-- Encabezado con buscador y acciones -->
            <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-slate-700">
                <div class="w-full lg:w-1/3">
                    <el-select @change="$inertia.get(route('catalog-products.show', selectedCatalogProduct))"
                        v-model="selectedCatalogProduct" filterable placeholder="Buscar otro producto..."
                        class="!w-full"
                        no-data-text="No hay productos registrados" no-match-text="No se encontraron coincidencias">
                        <el-option v-for="item in catalog_products" :key="item.id"
                            :label="item.name" :value="item.id" />
                    </el-select>
                </div>
                <div class="flex items-center space-x-2">
                    <el-tooltip content="Registrar Entrada" placement="top">
                        <button @click="openStockModal('Entrada')" class="size-9 flex items-center justify-center rounded-lg bg-green-100 hover:bg-green-200 dark:bg-green-800 dark:hover:bg-green-700 text-green-600 dark:text-green-300 transition-colors">
                            <i class="fa-solid fa-arrow-up"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip content="Registrar Salida" placement="top">
                         <button @click="openStockModal('Salida')" class="size-9 flex items-center justify-center rounded-lg bg-red-100 hover:bg-red-200 dark:bg-red-800 dark:hover:bg-red-700 text-red-600 dark:text-red-300 transition-colors">
                            <i class="fa-solid fa-arrow-down"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip v-if="$page.props.auth.user.permissions.includes('Editar catalogo de productos')" content="Editar Producto" placement="top">
                        <Link :href="route('catalog-products.edit', selectedCatalogProduct)">
                            <button class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                                <i class="fa-solid fa-pencil text-sm"></i>
                            </button>
                        </Link>
                    </el-tooltip>
                    
                    <Dropdown align="right" width="48" v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos') || $page.props.auth.user.permissions.includes('Eliminar catalogo de productos')">
                        <template #trigger>
                            <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                                Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('catalog-products.create')" v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos')">
                                <i class="fa-solid fa-plus w-4 mr-2"></i> Nuevo producto
                            </DropdownLink>
                            <div class="border-t border-gray-200 dark:border-slate-700" />
                            <DropdownLink @click="showConfirmModal = true" as="button" v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos')" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                                <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                            </DropdownLink>
                        </template>
                    </Dropdown>

                    <Link :href="route('catalog-products.index')"
                        class="mt-4 sm:mt-0 flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-slate-700 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                        <i class="fa-solid fa-xmark"></i>
                    </Link>
                </div>
            </header>

            <!-- Contenido Principal del Producto -->
            <main class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-6">
                <!-- Columna Izquierda: Galería de Imágenes -->
                <section>
                    <div class="bg-white dark:bg-slate-800/50 p-4 rounded-xl shadow-lg">
                        <div class="w-full h-80 bg-gray-100 dark:bg-slate-900 rounded-lg flex items-center justify-center overflow-hidden">
                            <img v-if="product.media?.length" :src="product.media[currentImage]?.original_url" :alt="product.name" class="w-full h-full object-contain">
                            <div class="flex flex-col items-center justify-end" v-else>
                                <i class="fa-regular fa-image text-gray-400 text-6xl"></i>
                                <p class="text-center italic text-gray-700 dark:text-gray-400 mt-2">Producto sin imagen</p>
                            </div>
                        </div>
                        <div v-if="product.media?.length > 1" class="flex items-center justify-center space-x-2 mt-3">
                            <div v-for="(image, index) in product.media" :key="index" @click="currentImage = index"
                                class="size-16 rounded-md overflow-hidden cursor-pointer border-2 transition-colors"
                                :class="index === currentImage ? 'border-primary' : 'border-transparent hover:border-gray-300 dark:hover:border-slate-600'">
                                <img :src="image.original_url" :alt="`Thumbnail ${index + 1}`" class="w-full h-full object-cover">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Columna Derecha: Detalles del Producto -->
                <section>
                    <!-- Nombre y Código -->
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ product.name }}</h1>
                        <p class="text-base text-gray-500 dark:text-gray-400 font-mono mt-1">Código: {{ product.code }}</p>
                    </div>

                    <article class="max-h-[600px] overflow-auto space-y-5">
                        <!-- Tarjeta de Detalles Generales -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Información General</h2>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Marca</div>
                                <div>{{ product.brand?.name ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Familia</div>
                                <div>{{ product.product_family?.name ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Material</div>
                                <div>{{ product.material ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400 col-span-2 pt-2">Características</div>
                                <div class="col-span-2 text-gray-600 dark:text-gray-300">{{ product.caracteristics || 'Sin características adicionales.' }}</div>
                            </div>
                        </div>

                        <!-- Tarjeta de Especificaciones y Almacén -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Especificaciones y Almacén</h2>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Dimensiones</div>
                                <div>{{ formattedDimensions }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Unidad de Medida</div>
                                <div>{{ product.measure_unit ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Existencias</div>
                                <div>{{ product.storages?.[0]?.quantity ?? '0' }} {{ product.measure_unit }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Ubicación</div>
                                <div>{{ product.storages?.[0]?.location ?? '--' }}</div>
                            </div>
                        </div>

                        <!-- Tarjeta de Costos y Precios -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Costos y Precios</h2>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div v-if="this.$page.props.auth.user.permissions.includes('Ver costos de produccion')" class="font-semibold text-gray-500 dark:text-gray-400">Costo de Producción</div>
                                <div v-if="this.$page.props.auth.user.permissions.includes('Ver costos de produccion')" class="font-bold text-green-600 dark:text-green-400">{{ formatCurrency(product.cost) }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Precio Base (Cliente)</div>
                                <div>{{ formatCurrency(product.base_price) }}</div>
                            </div>
                            <!-- Precios por Sucursal -->
                            <!-- <div v-if="product.branch_pricings?.length" class="mt-4 pt-4 border-t dark:border-slate-700">
                                <h3 class="font-semibold text-sm mb-2">Precios Especiales por Sucursal</h3>
                                <ul class="space-y-1 text-sm">
                                    <li v-for="pricing in product.branch_pricings" :key="pricing.id" class="flex justify-between items-center">
                                        <span class="text-gray-600 dark:text-gray-300">{{ pricing.branch?.name }}</span>
                                        <span class="font-semibold">{{ formatCurrency(pricing.price) }}</span>
                                    </li>
                                </ul>
                            </div> -->
                        </div>

                        <!-- Tarjeta de Componentes -->
                        <div v-if="product.components?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">
                                Componentes (Materia Prima)
                            </h2>

                            <ul class="space-y-2 text-sm">
                                <li
                                v-for="component in product.components"
                                :key="component.id"
                                class="flex items-center justify-between p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md"
                                >
                                <!-- Imagen o ícono -->
                                <div class="w-10 h-10 flex items-center justify-center bg-gray-200 dark:bg-slate-700 rounded-lg overflow-hidden">
                                    <img
                                    v-if="component.media?.[0]?.original_url"
                                    :src="component.media[0].original_url"
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
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 7a2 2 0 012-2h2l2-3h6l2 3h2a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 13l4-4a2 2 0 012.828 0L15 14l3-3 3 3"
                                    />
                                    </svg>
                                </div>

                                <!-- Nombre -->
                                <span class="text-gray-700 dark:text-gray-300 flex-1 ml-3">
                                    {{ component.name }}
                                </span>

                                <!-- Cantidad -->
                                <span class="font-semibold text-primary">
                                    x {{ component.pivot.quantity }}
                                </span>
                                </li>
                            </ul>
                        </div>


                        <!-- Tarjeta de Procesos de Producción -->
                        <div v-if="product.production_costs?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Procesos de Producción</h2>
                            <ul class="space-y-1 text-sm">
                                <li v-for="process in product.production_costs" :key="process.id" class="flex justify-between items-center p-2">
                                    <span class="text-gray-700 dark:text-gray-300">{{ process.name }}</span>
                                    <span class="text-gray-500 dark:text-gray-400 text-xs">{{ formatCurrency(process.cost) }}</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Tarjeta de movimientos de stock -->
                        <div v-if="product.storages[0]?.stock_movements?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-2 text-gray-800 dark:text-gray-200 border-b dark:border-slate-700 pb-2">
                                Historial de Movimientos
                            </h2>
                            <div class="space-y-1 max-h-56 overflow-y-auto pr-2">
                                <ul class="divide-y divide-gray-200 dark:divide-slate-700">

                                    <li v-for="movement in product.storages[0].stock_movements" :key="movement.id" class="py-3 flex items-center justify-between space-x-3">
                                        
                                        <div class="flex items-center min-w-0">
                                            <div class="flex-shrink-0">
                                                <span class="inline-flex items-center justify-center size-10 rounded-full"
                                                    :class="movement.type === 'Entrada' ? 'bg-green-100 dark:bg-green-900/50' : 'bg-red-100 dark:bg-red-900/50'">
                                                    <i class="fa-solid text-md" 
                                                    :class="movement.type === 'Entrada' ? 'fa-arrow-up text-green-600 dark:text-green-400' : 'fa-arrow-down text-red-600 dark:text-red-400'"></i>
                                                </span>
                                            </div>

                                            <div class="min-w-0 ml-4">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                    {{ movement.type === 'Entrada' ? 'Entrada de Stock' : 'Salida de Stock' }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {{ formatDate(movement.created_at) }} </p>
                                            </div>
                                        </div>

                                        <div class="text-right flex-shrink-0">
                                            <p class="text-sm font-bold"
                                            :class="movement.type === 'Entrada' ? 'text-green-600 dark:text-green-500' : 'text-red-600 dark:text-red-500'">
                                                {{ movement.type === 'Entrada' ? '+' : '-' }} {{ movement.quantity_change }} {{ product.measure_unit }}
                                            </p>
                                            <p v-if="movement.notes" class="text-xs text-gray-500 dark:text-gray-400 italic truncate max-w-xs" :title="movement.notes">
                                                {{ movement.notes }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="!product.storages[0]?.stock_movements?.length" class="text-center py-8">
                                <p class="text-gray-500 dark:text-gray-400">No hay movimientos registrados.</p>
                            </div>
                        </div>
                    </article>
                </section>
            </main>
        </div>

        <!-- Modal de Confirmación para Eliminar -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Producto del Catálogo
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente este producto? Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Stock modal -->
        <Modal :show="showStockModal" @close="showStockModal = false">
            <div class="p-6 dark:bg-slate-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ stockMovementForm.type === 'Entrada' ? 'Registrar Entrada de Stock' : 'Registrar Salida de Stock' }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Ingresa la cantidad y notas para el movimiento de "{{ product.name }}".
                </p>

                <div class="mt-6 space-y-4">
                    <div>
                        <TextInput v-model="stockMovementForm.quantity" 
                            :error="stockMovementForm.errors.quantity" 
                            :label="'Cantidad'" 
                            type="numeric-stepper" 
                            :step="0.1" 
                            class="mt-1 block w-full" 
                            placeholder="0.00" />
                    </div>
                    <div>
                        <TextInput v-model="stockMovementForm.notes" 
                            :label="'Notas (Opcional)'"
                            :error="stockMovementForm.errors.notes" 
                            :isTextarea="true"
                            :withMaxLength="true"
                            class="mt-1 block w-full" 
                            placeholder="Ej. Ajuste de inventario, entrada de proveedor..." />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <CancelButton @click="showStockModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="submitStockMovement" :class="{ 'opacity-25': stockMovementForm.processing }" :disabled="stockMovementForm.processing"
                        :style="{ backgroundColor: stockMovementForm.type === 'Entrada' ? '' : '#DC2626' }">
                        Confirmar {{ stockMovementForm.type === 'Entrada' ? 'Entrada' : 'Salida' }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    // Se mantiene en Options API para mayor claridad como fue solicitado.
    components: {
        Link,
        Modal,
        Dropdown,
        TextInput,
        AppLayout,
        InputLabel,
        InputError,
        CancelButton,
        DropdownLink,
        PrimaryButton,
        SecondaryButton,
        ConfirmationModal,
    },
    props: {
        catalog_product: Object,
        catalog_products: Array, // Es mejor que sea un Array para el select
    },
    data() {
        return {
            selectedCatalogProduct: this.catalog_product.data.id,
            showConfirmModal: false,
            currentImage: 0,

            // movimientos de stock
            showStockModal: false,
            stockMovementForm: useForm({
                quantity: null,
                notes: '',
                type: '', // 'Entrada' o 'Salida'
            }),
        };
    },
    computed: {
        // Propiedad computada para acceder al producto más fácilmente
        product() {
            return this.catalog_product.data;
        },
        // Propiedad computada para formatear las dimensiones de manera legible
        formattedDimensions() {
            const safe = (val) => val ?? "-";

            if (this.product.is_circular) {
                return `Ø ${safe(this.product.diameter)} mm (Diámetro) x ${safe(this.product.width)} mm (Grosor)`;
            }

            return `${safe(this.product.large)} mm (L) x ${safe(this.product.height)} mm (A) x ${safe(this.product.width)} mm (G)`;
        }
    },
    methods: {
        // formatea la fecha
        formatDate(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            // Opciones para un formato más localizado y amigable
            const options = {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            };
            return date.toLocaleDateString('es-MX', options);
        },
        // Formatea un número como moneda
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return parseFloat(value).toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
        openStockModal(type) {
            this.stockMovementForm.reset();
            this.stockMovementForm.type = type;
            this.showStockModal = true;
        },
        submitStockMovement() {
            this.stockMovementForm.post(this.route('products.stock-movement', this.product.id), {
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    this.showStockModal = false;
                    this.stockMovementForm.reset();
                    ElMessage.success('Movimiento de stock realizado');
                },
                onError: () => {
                    ElMessage.error('Hubo un error al realizar el movimiento');
                }
            });
        },
        // Método para eliminar el producto
        async deleteItem() {
            try {
                const response = await axios.delete(route('catalog-products.destroy', this.product.id));
                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Producto eliminado con éxito.');
                    this.$inertia.visit(route('catalog-products.index'));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar el producto.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
            }
        },
    },
    watch: {
        // Observador para resetear la imagen actual si el producto cambia
        'catalog_product.data.id'(newId) {
            this.selectedCatalogProduct = newId;
            this.currentImage = 0;
        }
    }
};
</script>
