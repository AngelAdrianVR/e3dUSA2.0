<template>
    <AppLayout :title="`Cliente: ${branch.name}`">
        <!-- Panel Flotante de Notas -->
        <BranchNotes :branch-id="branch.id" />

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
                <el-tooltip v-if="$page.props.auth.user.permissions.includes('Editar clientes')" content="Editar Cliente" placement="top">
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
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Crear clientes')" :href="route('branches.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nuevo Cliente
                        </DropdownLink>
                        <DropdownLink @click="showAddProductsModal = true" as="button">
                            <i class="fa-solid fa-tags w-4 mr-2"></i> Agregar Productos
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Eliminar clientes')" @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('branches.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Card de Información Clave -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información Clave</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">ID:</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ branch.id }}</span>
                        </li>
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
                            <span @click="branch.parent?.id ? $inertia.visit(route('branches.show', branch.parent.id)) : null" :class="branch.parent?.id ? 'text-blue-500 hover:underline cursor-pointer' : ''">{{ branch.parent?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">RFC:</span>
                            <span>{{ branch.rfc ?? 'No especificado' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Ultima compra:</span>
                            <span>{{ formatRelative(branch.last_purchase_date) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Sucursales -->
                <div v-if="branch.children && branch.children.length > 0" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Sucursales</h3>
                    <ul class="space-y-3 text-sm">
                        <li v-for="child in branch.children" :key="child.id">
                            <Link :href="route('branches.show', child.id)" class="font-semibold text-blue-500 hover:underline">
                                ID:{{ child.id }} - {{ child.name }}
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Card de Contactos -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <div class="flex justify-between items-center border-b dark:border-gray-600 pb-3 mb-4">
                        <h3 class="text-lg font-semibold">Contactos</h3>
                        <button @click="openContactModal()" class="text-primary hover:underline text-sm font-bold">
                            <i class="fa-solid fa-plus mr-1"></i> Nuevo
                        </button>
                    </div>
                    <div v-if="branch.contacts?.length" class="space-y-4">
                        <div v-for="contact in branch.contacts" :key="contact.id" class="relative">
                            <div class="absolute top-0 right-0 flex space-x-1 transition-opacity duration-200">
                                <el-tooltip content="Editar" placement="top">
                                    <button @click="openContactModal(contact)" class="size-6 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-blue-200 dark:hover:bg-blue-900 transition-colors">
                                        <i class="fa-solid fa-pencil text-xs"></i>
                                    </button>
                                </el-tooltip>
                                <el-tooltip content="Eliminar" placement="top">
                                    <button @click="showConfirmDeleteContact = { show: true, contactId: contact.id }" class="size-6 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-red-200 dark:hover:bg-red-900 transition-colors">
                                        <i class="fa-regular fa-trash-can text-xs"></i>
                                    </button>
                                </el-tooltip>
                            </div>

                            <p class="font-semibold">{{ contact.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ contact.charge }}</p>
                            <div class="text-sm mt-1 space-y-1">
                                <p v-if="getPrimaryDetail(contact, 'Correo')"><i class="fa-solid fa-envelope mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'Correo') }}</p>
                                <p v-if="getPrimaryDetail(contact, 'Teléfono')">
                                    <i class="fa-solid fa-phone mr-2 text-gray-400"></i>
                                    {{ formatPhone(getPrimaryDetail(contact, 'Teléfono')) }}
                                </p>
                                <p v-if="contact.birthdate">
                                    <i class="fa-solid fa-cake-candles mr-2 text-gray-400"></i> {{ formatBirthday(contact.birthdate) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No hay contactos registrados.</p>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg max-h-[70vh]">
                        <el-tabs v-model="activeTab" class="p-5">
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
                        
                        <el-tab-pane name="suggested_products">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-lightbulb mr-2"></i>
                                    <span>Sugerencias</span>
                                </div>
                            </template>
                            <div v-if="branch.suggested_products?.length" class="space-y-4 mt-2 max-h-[60vh] overflow-y-auto pr-2">
                                <SuggestedProducts :products="branch.suggested_products" />
                            </div>
                             <p v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">No hay productos sugeridos para este cliente.</p>
                        </el-tab-pane>

                        <el-tab-pane label="Cotizaciones" name="quotes">
                            <LoadingIsoLogo v-if="loadingQuotes" />
                            <Quotes v-else :quotes="quotes" />
                        </el-tab-pane>
                        <el-tab-pane label="Ventas" name="sales">
                            <LoadingIsoLogo v-if="loadingSales" />
                            <Sales v-else :sales="sales" />
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </main>

        <!-- Modals -->
        <ModalCrearEditarContacto :show="showContactModal" :contactable-id="branch.id" :contact="contactToEdit"
    :contactable-type="'App\\Models\\Branch'" @close="showContactModal = false" />
        <AddProductsModal :show="showAddProductsModal" :branch="branch" :catalog_products="catalog_products" @close="showAddProductsModal = false" />

        <ConfirmationModal :show="showConfirmDeleteContact.show" @close="showConfirmDeleteContact.show = false">
            <template #title>
                Eliminar Contacto
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar este contacto? Esta acción es irreversible.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmDeleteContact.show = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteContact" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

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
import Sales from "@/Pages/Branch/Tabs/Sales.vue";
import SuggestedProducts from "@/Pages/Branch/Tabs/SuggestedProducts.vue"; // <-- NUEVO COMPONENTE

// Modals
import AddProductsModal from "@/Pages/Branch/Modals/AddProductsModal.vue";
import ModalCrearEditarContacto from "@/Pages/Branch/Modals/ModalCrearEditarContacto.vue";

// Componentes
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import DialogModal from "@/Components/DialogModal.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import axios from 'axios';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        const form = useForm({
            products: [],
        });

        // Recuperar la pestaña activa de la URL si existe
        const queryParams = new URLSearchParams(window.location.search);
        const tab = queryParams.get('tab');

        return {
            form,
            activeTab: tab || 'general', // Inicializar con el valor de la URL o por defecto
            selectedBranch: this.branch.id,
            showConfirmModal: false,
            showAddProductsModal: false,
            showContactModal: false,
            showConfirmDeleteContact: { show: false, contactId: null },
            quotes: [],
            loadingQuotes: false,
            sales: [],
            loadingSales: false,
            contactToEdit: null,
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
        Link,
        Dropdown,
        AppLayout,
        BranchNotes,
        DropdownLink,
        CancelButton,
        PrimaryButton,
        LoadingIsoLogo,
        SecondaryButton,
        ConfirmationModal,
        DialogModal,
        TextInput,
        InputError,
        Products,
        Quotes,
        Sales,
        SuggestedProducts, // <-- REGISTRAR NUEVO COMPONENTE
        AddProductsModal,
        ModalCrearEditarContacto,
    },
    props: {
        branch: Object,
        branches: Array,
        catalog_products: Array,
    },
    computed: {
        availableProducts() {
            const assignedProductIds = this.branch.products.map(p => p.id);
            return this.catalog_products.filter(p => !assignedProductIds.includes(p.id));
        }
    },
    methods: {
        formatPhone(number) {
            if (!number) return '';
            // Eliminamos todo lo que no sea dígito
            const digits = number.toString().replace(/\D/g, '');
            // Agrupamos cada 2 dígitos y unimos con guiones
            return digits.match(/.{1,2}/g)?.join('-') || '';
        },
        openContactModal(contact = null) {
            this.contactToEdit = contact;
            this.showContactModal = true;
        },
        deleteContact() {
            const contactId = this.showConfirmDeleteContact.contactId;
            router.delete(route('contacts.destroy', contactId), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Contacto eliminado correctamente');
                    this.showConfirmDeleteContact = { show: false, contactId: null };
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar el contacto');
                }
            });
        },
        getPrimaryDetail(contact, type) {
            if (!contact.details) return 'No disponible';

            // Buscar detalle primario
            const primary = contact.details.find(d => d.type === type && d.is_primary);
            if (primary) return primary.value;

            // Si no hay primario, tomar el primero que coincida con el tipo
            const first = contact.details.find(d => d.type === type);
            return first ? first.value : 'No disponible';
        },
        saveProducts() {
            this.form.post(route('branches.add-products', this.branch.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Productos asignados correctamente.');
                    this.showAddProductsModal = false;
                    this.form.reset();
                },
                onError: () => {
                     ElMessage.error('Ocurrió un error al asignar los productos.');
                }
            });
        },
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
        isProductInForm(productId) {
            return this.form.products.some(p => p.product_id === productId);
        },
        formatBirthday(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString + 'T00:00:00'); 
            return format(date, "d 'de' MMMM", { locale: es });
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatRelative(dateString) {
            if (!dateString) return "Sin registro";
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            if (diffMs < 0) return "En el futuro";
            const seconds = Math.floor(diffMs / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            const months = Math.floor(days / 30);
            const years = Math.floor(months / 12);
            if (seconds < 60) return `Hace ${seconds} segundos`;
            if (minutes < 60) return `Hace ${minutes} minutos`;
            if (hours < 24) return `Hace ${hours} horas`;
            if (days < 30) return `Hace ${days} días`;
            if (months < 12) return `Hace ${months} mes${months > 1 ? "es" : ""}`;
            return `Hace ${years} año${years > 1 ? "s" : ""}`;
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
        async fetchSales() {
            try {
                this.loadingSales = true;
                const response = await axios.get(route('sales.branch-sales', this.branch.id));
                this.sales = response.data;
            } catch (error) {
                console.error("Error al cargar las ventas:", error);
                ElMessage.error('Ocurrió un error al cargar las ventas.');
            } finally {
                this.loadingSales = false;
            }
        },
    },
    watch: {
        activeTab(newTab) {
            // Actualizar la URL sin recargar la página cuando cambia la pestaña
            const url = new URL(window.location.href);
            url.searchParams.set('tab', newTab);
            window.history.replaceState({}, '', url);
        },
        'branch.id'(newId, oldId) {
            if (newId !== oldId) {
                this.selectedBranch = newId;
                this.activeTab = 'general';
                this.fetchQuotes();
                this.fetchSales();
            }
        },
    },
    mounted() {
        this.fetchQuotes();
        this.fetchSales();
    }
};
</script>

<style>
/* Personalización para que las pestañas se vean más limpias */
.el-tabs__header {
    margin-bottom: 24px !important;
}
</style>