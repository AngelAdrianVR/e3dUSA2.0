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
                            <i v-else class="fa-regular fa-image text-gray-400 text-6xl"></i>
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
                <section class="space-y-6">
                    <!-- Nombre y Código -->
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ product.name }}</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-mono mt-1">{{ product.code }}</p>
                    </div>

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
                            <div class="font-semibold text-gray-500 dark:text-gray-400">Costo de Producción</div>
                            <div class="font-bold text-green-600 dark:text-green-400">{{ formatCurrency(product.cost) }}</div>
                            <div class="font-semibold text-gray-500 dark:text-gray-400">Precio Base (Cliente)</div>
                            <div>{{ formatCurrency(product.base_price) }}</div>
                        </div>
                        <!-- Precios por Sucursal -->
                        <div v-if="product.branch_pricings?.length" class="mt-4 pt-4 border-t dark:border-slate-700">
                            <h3 class="font-semibold text-sm mb-2">Precios Especiales por Sucursal</h3>
                            <ul class="space-y-1 text-sm">
                                <li v-for="pricing in product.branch_pricings" :key="pricing.id" class="flex justify-between items-center">
                                    <span class="text-gray-600 dark:text-gray-300">{{ pricing.company_branch?.name }}</span>
                                    <span class="font-semibold">{{ formatCurrency(pricing.price) }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tarjeta de Componentes -->
                    <div v-if="product.components?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                        <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Componentes (Materia Prima)</h2>
                        <ul class="space-y-2 text-sm">
                            <li v-for="component in product.components" :key="component.id" class="flex justify-between items-center p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md">
                                <span class="text-gray-700 dark:text-gray-300">{{ component.name }}</span>
                                <span class="font-semibold text-primary">x {{ component.pivot.quantity }}</span>
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
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import { Link } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    // Se mantiene en Options API para mayor claridad como fue solicitado.
    components: {
        AppLayout,
        ConfirmationModal,
        PrimaryButton,
        CancelButton,
        DropdownLink,
        Dropdown,
        Link,
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
        };
    },
    computed: {
        // Propiedad computada para acceder al producto más fácilmente
        product() {
            return this.catalog_product.data;
        },
        // Propiedad computada para formatear las dimensiones de manera legible
        formattedDimensions() {
            if (this.product.is_circular) {
                return `Ø ${this.product.diameter} mm (Diámetro) x ${this.product.width} mm (Grosor)`;
            }
            return `${this.product.large} mm (L) x ${this.product.height} mm (A) x ${this.product.width} mm (G)`;
        }
    },
    methods: {
        // Formatea un número como moneda
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return parseFloat(value).toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
        // Método para clonar el producto
        async clone() {
            try {
                const response = await axios.post(route('catalog-products.clone', {
                    catalog_product_id: this.product.id
                }));
                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Producto clonado con éxito.');
                    this.$inertia.visit(route('catalog-products.show', response.data.newItem.id));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al clonar el producto.');
                console.error(err);
            }
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
