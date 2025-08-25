<template>
    <AppLayout :title="`Cliente: ${branch.name}`">
        <!-- === ENCABEZADO === -->
        <h1 class="dark:text-white font-bold text-2xl mb-4">{{ branch.name }}</h1>
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-gray-500">
            <div class="w-full lg:w-1/3">
                <el-select @change="$inertia.get(route('branches.show', selectedBranch))"
                    v-model="selectedBranch" filterable placeholder="Buscar otro cliente..."
                    class="!w-full"
                    no-data-text="No hay clientes registrados" no-match-text="No se encontraron coincidencias">
                    <el-option v-for="item in branches" :key="item.id"
                        :label="item.name" :value="item.id" />
                </el-select>
            </div>
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip content="Editar Cliente" placement="top">
                    <Link :href="route('branches.edit', branch.id)">
                        <button class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                            <i class="fa-solid fa-pencil text-sm"></i>
                        </button>
                    </Link>
                </el-tooltip>
                
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('branches.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nuevo Cliente
                        </DropdownLink>
                        <!-- ===== NUEVA OPCIÓN EN EL DROPDOWN ===== -->
                        <DropdownLink @click="showAddProductsModal = true" as="button">
                            <i class="fa-solid fa-tags w-4 mr-2"></i> Agregar Productos
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('branches.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL (Sin cambios) === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Card de Información Clave -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información Clave</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                            <el-tag :type="branch.status === 'Cliente' ? 'success' : 'info'" size="small">{{ branch.status }}</el-tag>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Vendedor:</span>
                            <span>{{ branch.account_manager?.name ?? 'No asignado' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Matriz:</span>
                            <span @click="$inertia.visit(route('branches.show', branch.parent.id))" :class="branch.parent?.id ? 'text-blue-500 hover:underline cursor-pointer' : ''">{{ branch.parent?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">RFC:</span>
                            <span>{{ branch.rfc ?? 'No especificado' }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Contactos -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Contactos</h3>
                    <div v-if="branch.contacts.length" class="space-y-4">
                        <div v-for="contact in branch.contacts" :key="contact.id">
                            <p class="font-semibold">{{ contact.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ contact.charge }}</p>
                            <div class="text-sm mt-1 space-y-1">
                                <p><i class="fa-solid fa-envelope mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'Correo') }}</p>
                                <p><i class="fa-solid fa-phone mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'Teléfono') }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No hay contactos registrados.</p>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg">
                        <el-tabs v-model="activeTab" class="p-6">
                        <el-tab-pane label="Información General" name="general">
                            <ul class="space-y-4 text-sm mt-2">
                                <li><strong class="font-semibold w-32 inline-block">Dirección:</strong> {{ branch.address ?? 'No especificada' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Código Postal:</strong> {{ branch.post_code ?? 'N/A' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Nos conoció por:</strong> {{ branch.meet_way ?? 'No especificado' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Notas:</strong> {{ branch.important_notes ?? 'No hay notas.' }}</li>
                            </ul>
                        </el-tab-pane>
                        <el-tab-pane name="products">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-tags mr-2"></i>
                                    <span>Productos Asignados</span>
                                </div>
                            </template>
                            <div v-if="branch.products.length" class="space-y-4 mt-2 max-h-[60vh] overflow-y-auto pr-2">
                                <Products :products="branch.products" :branchId="branch.id" />
                            </div>
                             <p v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Aún no hay productos asignados a este cliente.</p>
                        </el-tab-pane>
                        <el-tab-pane label="Cotizaciones" name="quotes">
                            <LoadingIsoLogo v-if="loadingQuotes" />
                            <Quotes v-else :quotes="quotes" />
                        </el-tab-pane>
                        <el-tab-pane label="Ventas" name="sales">
                            <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Historial de ventas.</p>
                        </el-tab-pane>
                        <el-tab-pane label="Proyectos" name="projects">
                            <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Proyectos relacionados.</p>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </main>

        <!-- ===== MODAL PARA AGREGAR PRODUCTOS ===== -->
        <DialogModal :show="showAddProductsModal" @close="showAddProductsModal = false">
            <template #title>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa-solid fa-tags mr-2"></i> Asignar Productos a {{ branch.name }}
                </h2>
            </template>
            <template #content>
                <form @submit.prevent="saveProducts">
                    <div class="p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Buscar producto*</label>
                                <!-- ===== SELECT MODIFICADO ===== -->
                                <el-select @change="getProductMedia" :teleported="false" v-model="currentProduct.product_id" placeholder="Selecciona un producto" class="!w-full" filterable>
                                    <el-option v-for="item in availableProducts" 
                                        :key="item.id" 
                                        :label="item.name" 
                                        :value="item.id"
                                        :disabled="isProductInForm(item.id)"
                                    />
                                </el-select>
                            </div>
                            <TextInput label="Precio Especial (Opcional)" v-model="currentProduct.price"
                                :helpContent="'Si no agregas precio especial se tomará en cuenta el precio base del producto'" type="number" :step="0.01" placeholder="Dejar vacío para usar precio base" />
                        </div>

                        <div v-if="loadingProductMedia" class="flex items-center justify-center h-32">
                            <p class="text-gray-500">Cargando imagen...</p>
                        </div>
                        <div v-else-if="currentProduct.media" class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mt-4">
                            <figure class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg">
                                <img v-if="currentProduct.media?.length" :src="currentProduct.media[0]?.original_url" alt="Imagen del producto" class="rounded-2xl w-full h-auto object-cover">
                                <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500 p-2 text-center">
                                    <i class="fa-solid fa-image text-3xl"></i>
                                    <p class="text-xs mt-1">Sin imagen</p>
                                </div>
                            </figure>
                            <div>
                                <p class="text-gray-500 dark:text-gray-300">
                                    Precio Base: <strong>${{ currentProduct.base_price?.toFixed(2) ?? '0.00' }}</strong>
                                </p>
                                <p class="text-gray-500 dark:text-gray-300">
                                    Stock: <strong>{{ currentProduct.current_stock ?? '0' }}</strong> unidades
                                </p>
                                <p class="text-gray-500 dark:text-gray-300">
                                    Ubicación: <strong>{{ currentProduct.location ?? 'No asignado' }}</strong>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-4">
                            <PrimaryButton @click="addProduct" type="button" plain :disabled="!currentProduct.product_id">
                                <i class="fa-solid fa-plus mr-2"></i> Agregar a la lista
                            </PrimaryButton>
                        </div>
                    </div>

                    <div v-if="form.products.length" class="mt-4">
                        <InputError :message="form.errors.products" />
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Productos a asignar:</p>
                        <ul class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2 max-h-48 overflow-y-auto">
                            <li v-for="(product, index) in form.products" :key="index" class="flex justify-between items-center p-2 rounded-md">
                                <span class="text-sm text-gray-800 dark:text-gray-200">
                                    <span class="font-bold text-primary">{{ getProductName(product.product_id) }}</span>
                                </span>
                                <div class="flex items-center space-x-3 text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">
                                        Precio Especial: <strong>${{ product.price ?? 'N/A' }}</strong>
                                    </span>
                                    <button @click="removeProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
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
                    <PrimaryButton @click="saveProducts" :disabled="!form.products.length || form.processing">
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Productos</span>
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- Modal de Confirmación para Eliminar (Sin cambios) -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Cliente
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente este cliente? Todos los datos relacionados (contactos, precios, etc.) se perderán. Esta acción no se puede deshacer.
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
// Pestañas
import Products from "@/Pages/Branch/Tabs/Products.vue";
import Quotes from "@/Pages/Branch/Tabs/Quotes.vue";

// Componentes
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import DialogModal from "@/Components/DialogModal.vue"; // Importar DialogModal
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import TextInput from "@/Components/TextInput.vue"; // Importar TextInput
import InputError from "@/Components/InputError.vue"; // Importar InputError
import { Link, useForm, router } from "@inertiajs/vue3"; // Importar useForm y router
import { ElMessage } from 'element-plus';
import axios from 'axios';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        // --- FORMULARIO PARA AGREGAR PRODUCTOS ---
        const form = useForm({
            products: [],
        });

        return {
            form, // Formulario para el modal
            activeTab: 'general',
            selectedBranch: this.branch.id,
            showConfirmModal: false,
            showAddProductsModal: false, // Estado para el nuevo modal

            // Cotizaciones
            quotes: [],
            loadingQuotes: false,

            // --- LÓGICA PARA ASIGNAR PRODUCTOS (del Create.vue) ---
            currentProduct: {
                product_id: null,
                price: null,
                media: null,
                base_price: null,
                current_stock: null,
                location: null
            },
            loadingProductMedia: false,
        };
    },
    components: {
        // Componentes
        Link,
        Dropdown,
        AppLayout,
        DropdownLink,
        CancelButton,
        PrimaryButton,
        LoadingIsoLogo,
        SecondaryButton,
        ConfirmationModal,
        DialogModal, // Registrar DialogModal
        TextInput,   // Registrar TextInput
        InputError,  // Registrar InputError

        // Pestañas
        Products,
        Quotes
    },
    props: {
        branch: Object,
        branches: Array,
        catalog_products: Array,
    },
    // ===== NUEVA PROPIEDAD COMPUTADA =====
    computed: {
        availableProducts() {
            // Obtiene un array de IDs de los productos que el cliente ya tiene.
            const assignedProductIds = this.branch.products.map(p => p.id);
            // Filtra el catálogo general para excluir los productos que ya están asignados.
            return this.catalog_products.filter(p => !assignedProductIds.includes(p.id));
        }
    },
    methods: {
        // --- MÉTODOS PARA GUARDAR PRODUCTOS ---
        saveProducts() {
            // Se necesita una nueva ruta para manejar esta lógica en el backend
            this.form.post(route('branches.add-products', this.branch.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Productos asignados correctamente.');
                    this.showAddProductsModal = false;
                    this.form.reset();
                    // Inertia recargará los props automáticamente, actualizando la lista de productos.
                },
                onError: () => {
                     ElMessage.error('Ocurrió un error al asignar los productos.');
                }
            });
        },

        // --- MÉTODOS COPIADOS DE Create.vue PARA EL MODAL ---
        async getProductMedia() {
            if (!this.currentProduct.product_id) return;
            
            this.loadingProductMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.product_id));
                if (response.status === 200) {
                    const product = response.data.product;
                    this.currentProduct.media = product.media;
                    this.currentProduct.base_price = product.base_price;
                    this.currentProduct.current_stock = product.current_stock;
                    this.currentProduct.location = product.location;
                }
            } catch (error) {
                console.error("Error al cargar detalles del producto:", error);
                ElMessage.error('No se pudo cargar la información del producto.');
                this.resetCurrentProduct();
            } finally {
                this.loadingProductMedia = false;
            }
        },
        addProduct() {
            if (!this.currentProduct.product_id) {
                ElMessage.warning('Debes seleccionar un producto.');
                return;
            }
            // Evitar duplicados (ya controlado con :disabled, pero es buena práctica tenerlo)
            if (this.isProductInForm(this.currentProduct.product_id)) {
                ElMessage.warning('Este producto ya está en la lista.');
                return;
            }
            this.form.products.push({ ...this.currentProduct });
            this.resetCurrentProduct();
        },
        removeProduct(index) {
            this.form.products.splice(index, 1);
        },
        resetCurrentProduct() {
            this.currentProduct = {
                product_id: null,
                price: null,
                media: null,
                base_price: null,
                current_stock: null,
                location: null
            };
        },
        getProductName(productId) {
            const product = this.catalog_products.find(p => p.id === productId);
            return product ? product.name : `ID: ${productId}`;
        },
        // --- NUEVO MÉTODO PARA DESHABILITAR OPCIONES ---
        isProductInForm(productId) {
            return this.form.products.some(p => p.product_id === productId);
        },
        // --- FIN DE MÉTODOS COPIADOS ---

        getPrimaryDetail(contact, type) {
            const detail = contact.details.find(d => d.type === type);
            return detail ? detail.value : 'No disponible';
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        async deleteItem() {
            try {
                const response = await axios.delete(route('branches.destroy', this.branch.id));
                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Cliente eliminado con éxito.');
                    this.$inertia.visit(route('branches.index'));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar el cliente.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
            }
        },
        async fetchQuotes() {
            try {
                this.loadingQuotes = true;
                const response = await axios.get(route('quotes.branch-quotes', this.branch.id));
                if ( response.status === 200 ) {
                    this.quotes = response.data
                }
            } catch (error) {
                console.error("Error al cargar cotizaciones:", error);
                ElMessage.error('Ocurrió un error al cargar cotizaciones.');
            } finally {
                this.loadingQuotes = false;
            }
        },
    },
    watch: {
        'branch.id'(newId) {
            this.selectedBranch = newId;
            this.activeTab = 'general';
        },
        // Limpiar el formulario del modal cuando se cierra
        showAddProductsModal(value) {
            if (!value) {
                this.form.reset();
                this.resetCurrentProduct();
            }
        }
    },
    mounted() {
        this.fetchQuotes();
    }
};
</script>

<style>
/* Personalización para que las pestañas se vean más limpias */
.el-tabs__header {
    margin-bottom: 24px !important;
}
</style>
