<template>
    <AppLayout :title="`Proveedor: ${supplier.name}`">
        <SupplierFavoredProducts :supplier-id="supplier.id" />

        <!-- === ENCABEZADO === -->
        <h1 class="dark:text-white font-bold text-2xl mb-4">{{ supplier.name }}</h1>
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-gray-500">
            <div class="w-full lg:w-1/3">
                <el-select @change="$inertia.get(route('suppliers.show', selectedSupplier))"
                    v-model="selectedSupplier" filterable placeholder="Buscar otro proveedor..."
                    class="!w-full"
                    no-data-text="No hay proveedores registrados" no-match-text="No se encontraron coincidencias">
                    <el-option v-for="item in suppliers" :key="item.id"
                        :label="item.name" :value="item.id" />
                </el-select>
            </div>
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip content="Editar Proveedor" placement="top">
                    <Link :href="route('suppliers.edit', supplier.id)">
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
                        <DropdownLink :href="route('suppliers.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nuevo Proveedor
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink @click="showConfirmDeleteSupplierModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                        </DropdownLink>
                    </template>
                </Dropdown>
                <Link :href="route('suppliers.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-3">
                <!-- Card de Información Clave -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información Clave</h3>
                    <ul class="space-y-3 text-sm">
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">RFC:</span>
                            <span>{{ supplier.rfc ?? 'No especificado' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Teléfono:</span>
                            <a :href="'tel:'+supplier.phone" class="text-blue-500 hover:underline">{{ supplier.phone ?? 'No especificado' }}</a>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Email:</span>
                            <a :href="'mailto:'+supplier.email" class="text-blue-500 hover:underline">{{ supplier.email ?? 'No especificado' }}</a>
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
                    <div v-if="supplier.contacts?.length" class="space-y-4">
                        <div v-for="contact in supplier.contacts" :key="contact.id" class="relative">
                             <div class="absolute top-0 right-0 flex space-x-1">
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

                 <!-- Card de Cuentas Bancarias -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <div class="flex justify-between items-center border-b dark:border-gray-600 pb-3 mb-4">
                        <h3 class="text-lg font-semibold">Cuentas Bancarias</h3>
                        <button @click="openBankAccountModal()" class="text-primary hover:underline text-sm font-bold">
                            <i class="fa-solid fa-plus mr-1"></i> Nueva
                        </button>
                    </div>
                    <div v-if="supplier.bank_accounts?.length" class="space-y-4">
                        <div v-for="account in supplier.bank_accounts" :key="account.id" class="relative">
                             <div class="absolute top-0 right-0 flex space-x-1">
                                <el-tooltip content="Editar" placement="top">
                                    <button @click="openBankAccountModal(account)" class="size-6 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-blue-200 dark:hover:bg-blue-900 transition-colors">
                                        <i class="fa-solid fa-pencil text-xs"></i>
                                    </button>
                                </el-tooltip>
                                 <el-tooltip content="Eliminar" placement="top">
                                    <button @click="confirmDeleteBankAccount(account)" class="size-6 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-red-200 dark:hover:bg-red-900 transition-colors">
                                        <i class="fa-regular fa-trash-can text-xs"></i>
                                    </button>
                                </el-tooltip>
                            </div>
                            <p class="font-semibold">{{ account.bank_name }} ({{ account.currency }})</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Titular: {{ account.account_holder }}</p>
                            <div class="text-sm mt-1 space-y-1">
                                <p>
                                <strong class="font-normal">Cuenta:</strong> {{ account.account_number }}
                                </p>

                                <p v-if="account.clabe" class="flex items-center gap-2">
                                <strong class="font-normal">CLABE:</strong> 
                                <span>{{ formatClabe(account.clabe) }}</span>

                                <!-- Botón copiar -->
                                <button
                                    @click="copyClabe(account.clabe)"
                                    class="px-2 py-1 text-xs bg-gray-200 hover:bg-gray-300 dark:bg-gray-500 rounded"
                                >
                                    Copiar
                                </button>
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No hay cuentas registradas.</p>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg max-h-[70vh]">
                    <el-tabs v-model="activeTab" class="p-5">
                        <el-tab-pane label="Información General" name="general">
                            <ul class="space-y-4 text-sm mt-2">
                                <li><strong class="font-semibold w-32 inline-block">Dirección:</strong> {{ supplier.address ?? 'No especificada' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Código Postal:</strong> {{ supplier.post_code ?? 'N/A' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Notas:</strong> {{ supplier.notes ?? 'No hay notas.' }}</li>
                            </ul>
                        </el-tab-pane>
                        <el-tab-pane name="products">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-box-open mr-2"></i>
                                    <span>Productos que Suministra</span>
                                </div>
                            </template>
                             <div class="space-y-4 mt-2 pb-4 max-h-[60vh] overflow-y-auto pr-2">
                                <Products :products="supplier.products" :catalog="catalog_products" :supplierId="supplier.id" />
                             </div>
                        </el-tab-pane>
                        <el-tab-pane label="Órdenes de Compra" name="orders">
                           <div class="text-center text-sm text-gray-500 dark:text-gray-400 p-4">
                                <p></p>
                                <p class="mt-2">Próximamente: Historial de órdenes de compra.</p>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </main>

        <!-- Modals -->
        <ModalCrearEditarContacto :show="showContactModal" :contact="contactToEdit" :contactable-id="supplier.id" :contactable-type="'App\\Models\\Supplier'" @close="showContactModal = false" />
        <BanckAccountModal :show="showBankAccountModal" :bankAccount="bankAccountToEdit" :supplierId="supplier.id" @close="showBankAccountModal = false" />

        <!-- Modal de Confirmación para Eliminar proveedor -->
        <ConfirmationModal :show="showConfirmDeleteSupplierModal" @close="showConfirmDeleteSupplierModal = false">
            <template #title>
                Eliminar Proveedor
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente este proveedor? Todos los datos relacionados se perderán.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmDeleteSupplierModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteSupplier" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Modal de Confirmación para Eliminar contacto -->
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

         <!-- Modal de Confirmación para Eliminar cuenta bancaria -->
        <ConfirmationModal :show="showConfirmDeleteBankAccountModal" @close="showConfirmDeleteBankAccountModal = false">
            <template #title>
                Eliminar Cuenta Bancaria
            </template>
            <template #content>
                 ¿Estás seguro de que deseas eliminar la cuenta de <strong>{{ bankAccountToDelete?.bank_name }}</strong> ({{ bankAccountToDelete?.account_number }})?
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmDeleteBankAccountModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteBankAccount" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Products from './Tabs/Products.vue';
import BanckAccountModal from './Modals/BanckAccountModal.vue';
import SupplierFavoredProducts from "@/Components/MyComponents/SupplierFavoredProducts.vue";
// Se importa el modal de contacto reutilizable
import ModalCrearEditarContacto from "@/Pages/Branch/Modals/ModalCrearEditarContacto.vue";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        return {
            activeTab: 'general',
            selectedSupplier: this.supplier.id,
            showConfirmDeleteSupplierModal: false,
            // Contactos
            showContactModal: false,
            contactToEdit: null,
            showConfirmDeleteContact: { show: false, contactId: null }, // Lógica adaptada de Branch
            // Cuentas
            showBankAccountModal: false,
            bankAccountToEdit: null,
            bankAccountToDelete: null,
            showConfirmDeleteBankAccountModal: false,
        };
    },
    components: {
        Link,
        Products,
        Dropdown,
        AppLayout,
        DropdownLink,
        CancelButton,
        PrimaryButton,
        BanckAccountModal,
        ConfirmationModal,
        SupplierFavoredProducts,
        ModalCrearEditarContacto, // Se registra el nuevo modal
    },
    props: {
        supplier: Object,
        suppliers: Array,
        catalog_products: Array,
    },
    methods: {
        // --- MÉTODOS GENERALES ---
        formatClabe(clabe) {
            if (!clabe) return '';
            return clabe.replace(/(.{4})/g, '$1 ').trim();
        },
        async copyClabe(clabe) {
            try {
                await navigator.clipboard.writeText(clabe);
                ElMessage.success('CLABE copiada');
            } catch (err) {
                console.error('Error al copiar:', err);
                ElMessage.warning('No se pudo copiar la CLABE');
            }
        },
         formatPhone(number) {
            if (!number) return '';
            const digits = number.toString().replace(/\D/g, '');
            return digits.match(/.{1,2}/g)?.join('-') || '';
        },
        formatBirthday(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString + 'T00:00:00'); 
            return format(date, "d 'de' MMMM", { locale: es });
        },

        // --- GESTIÓN DE CONTACTOS (Adaptado de ShowBranch.vue) ---
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
            if (!contact.details) return null;
            const primary = contact.details.find(d => d.type === type && d.is_primary);
            if (primary) return primary.value;
            const first = contact.details.find(d => d.type === type);
            return first ? first.value : null;
        },

        // --- GESTIÓN DE CUENTAS ---
        openBankAccountModal(account = null) {
            this.bankAccountToEdit = account;
            this.showBankAccountModal = true;
        },
        confirmDeleteBankAccount(account) {
            this.bankAccountToDelete = account;
            this.showConfirmDeleteBankAccountModal = true;
        },
        deleteBankAccount() {
             router.delete(route('supplier-bank-accounts.destroy', this.bankAccountToDelete.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Cuenta eliminada');
                    this.showConfirmDeleteBankAccountModal = false;
                }
            });
        },

        // --- ELIMINAR PROVEEDOR ---
        deleteSupplier() {
            router.delete(route('suppliers.destroy', this.supplier.id), {
                onSuccess: () => {
                    ElMessage.success('Proveedor eliminado con éxito.');
                    this.$inertia.visit(route('suppliers.index'));
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar el proveedor.');
                }
            });
        },
    },
};
</script>
