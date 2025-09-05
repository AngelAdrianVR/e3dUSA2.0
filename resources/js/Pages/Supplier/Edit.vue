<template>
    <AppLayout title="Editar Proveedor">
        <div class="flex justify-between items-center">
            <Back :href="route('suppliers.index')" />
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Editar proveedor
            </h2>
        </div>

        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
                <form @submit.prevent="update">
                    <!-- SECCIÓN DE INFORMACIÓN GENERAL -->
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                        Información del Proveedor
                    </h3>
                    <div ref="formContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-y-1 gap-x-4 mb-7">
                        <TextInput v-model="form.name" label="Nombre del proveedor*" :error="form.errors.name" />
                        <TextInput v-model="form.rfc" label="RFC" :error="form.errors.rfc" />
                        <TextInput v-model="form.nickname" label="Apodo / Nombre corto" :error="form.errors.nickname" />
                        <TextInput v-model="form.phone" label="Teléfono" :error="form.errors.phone" type="tel" />
                        <TextInput v-model="form.email" label="Correo Electrónico" :error="form.errors.email" type="email" />
                        <TextInput v-model="form.address" label="Dirección" :error="form.errors.address" class="sm:col-span-2" />
                        <TextInput v-model="form.post_code" label="Código Postal" :error="form.errors.post_code" />
                        <TextInput v-model="form.notes" label="Notas" :error="form.errors.notes" :isTextarea="true" class="sm:col-span-2" />
                    </div>

                    <!-- SECCIÓN DE CONTACTOS -->
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                        Contactos
                    </h3>
                    <div class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg mb-8">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <TextInput v-model="newContact.name" label="Nombre del contacto*" placeholder="Ej. Juan Pérez" />
                            <TextInput v-model="newContact.position" label="Puesto" placeholder="Ej. Ventas" />
                            <TextInput v-model="newContact.phone" label="Teléfono" type="tel" />
                            <TextInput v-model="newContact.email" label="Email" type="email" class="sm:col-span-2"/>
                            <label class="flex items-center mt-7">
                                <Checkbox v-model:checked="newContact.is_primary" name="is_primary" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">¿Es el contacto principal?</span>
                            </label>
                        </div>
                        <div class="flex justify-end">
                            <SecondaryButton @click="addContact" :disabled="!newContact.name" type="button">
                                <i class="fa-solid fa-plus-circle mr-2"></i> Agregar Contacto
                            </SecondaryButton>
                        </div>
                        <!-- Lista de contactos agregados -->
                        <ul v-if="form.contacts?.length" class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2">
                            <li v-for="(contact, index) in form.contacts" :key="index" class="flex justify-between items-center p-2 rounded-md">
                                <div>
                                    <p class="font-bold text-primary">{{ contact.name }} <span v-if="contact.is_primary" class="text-xs text-green-500 font-normal">(Principal)</span></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ contact.position }}</p>
                                    <p class="text-xs text-gray-500">{{ contact.phone }} | {{ contact.email }}</p>
                                </div>
                                <button @click="removeContact(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- SECCIÓN DE CUENTAS BANCARIAS -->
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                        Cuentas Bancarias
                    </h3>
                    <div class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg mb-8">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <TextInput v-model="newBankAccount.bank_name" label="Banco*" />
                            <TextInput v-model="newBankAccount.account_holder" label="Titular de la cuenta*" />
                            <TextInput v-model="newBankAccount.account_number" label="Número de cuenta*" />
                            <TextInput v-model="newBankAccount.clabe" label="CLABE" />
                            <div>
                               <InputLabel value="Moneda*" />
                               <el-select v-model="newBankAccount.currency" placeholder="Selecciona" class="w-full">
                                   <el-option label="MXN" value="MXN" />
                                   <el-option label="USD" value="USD" />
                               </el-select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <SecondaryButton @click="addBankAccount" :disabled="!newBankAccount.bank_name || !newBankAccount.account_holder || !newBankAccount.account_number" type="button">
                                <i class="fa-solid fa-plus-circle mr-2"></i> Agregar Cuenta
                            </SecondaryButton>
                        </div>
                         <!-- Lista de cuentas agregadas -->
                        <ul v-if="form.bankAccounts?.length" class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2">
                            <li v-for="(account, index) in form.bankAccounts" :key="index" class="flex justify-between items-center p-2 rounded-md">
                                <div>
                                    <p class="font-bold text-primary">{{ account.bank_name }} ({{ account.currency }})</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Titular: {{ account.account_holder }}</p>
                                    <p class="text-xs text-gray-500">Cuenta: {{ account.account_number }} | CLABE: {{ account.clabe }}</p>
                                </div>
                                <button @click="removeBankAccount(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- SECCIÓN DE PRODUCTOS -->
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                        Productos que Suministra
                    </h3>
                    <div class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-1">
                                <InputLabel value="Buscar producto*" />
                                <el-select v-model="newProduct.product_id" @change="getComponentMedia()" filterable placeholder="Selecciona un producto" class="w-full" no-data-text="No hay productos comprables" no-match-text="No se encontraron coincidencias">
                                    <el-option v-for="item in products" :key="item.id" :label="item.name" :value="item.id" :disabled="isProductSelected(item.id)">
                                        <span>{{ item.name }}</span>
                                        <span class="text-xs text-gray-400 ml-2">{{ item.code }}</span>
                                    </el-option>
                                </el-select>
                            </div>
                            
                            <TextInput v-model="newProduct.last_price" label="Último precio de compra*" type="number" placeholder="0.00">
                                 <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                            </TextInput>
                             <TextInput v-model="newProduct.min_quantity" type="numeric-stepper" label="Cantidad mínima de compra" />
                        </div>

                        <LoadingIsoLogo v-if="loadingComponentMedia" />

                            <!-- Tarjeta de materia prima seleccionada -->
                            <div class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="newProduct.product_id">
                                <figure 
                                    v-if="newProduct.media" 
                                    class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                                    <img v-if="newProduct.media?.length"
                                        :src="newProduct.media[0]?.original_url" 
                                        alt="" 
                                        class="rounded-2xl w-full h-auto object-cover transition duration-300 ease-in-out hover:opacity-95"
                                    >
                                    <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    <p>Sin imagen</p>
                                    </div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/10 via-transparent to-black/5"></div>
                                </figure>

                                <div>
                                    <p class="text-gray-500 dark:text-gray-300">
                                        Stock: <strong>{{ newProduct.storages[0]?.quantity.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ newProduct.measure_unit }}</strong>
                                    </p>
                                    <p class="text-gray-500 dark:text-gray-300">
                                        Ubicación: <strong>{{ newProduct.storages[0]?.location ?? 'No asignado' }}</strong>
                                    </p>
                                    <p class="text-gray-500 dark:text-gray-300">
                                        Costo: <strong>${{ newProduct.cost ?? '0.00' }} {{ newProduct.currency }}</strong>
                                    </p>
                                </div>
                            </div>
                         <div class="flex justify-end">
                            <SecondaryButton @click="addProduct" :disabled="!newProduct.product_id || newProduct.last_price === null" type="button">
                                <i class="fa-solid fa-plus-circle mr-2"></i> Agregar Producto
                            </SecondaryButton>
                        </div>
                        <!-- Lista de productos agregados -->
                         <ul v-if="form.products?.length" class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2">
                            <li v-for="(product, index) in form.products" :key="index" class="flex justify-between items-center p-2 rounded-md">
                                <div>
                                    <p class="font-bold text-primary">{{ getProductName(product.product_id) }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Precio: ${{ product.last_price }}</p>
                                    <p class="text-xs text-gray-500">Cantidad mínima: {{ product.min_quantity ?? 'N/A' }} {{ getProductUnit(product.product_id) }}</p>
                                </div>
                                <button @click="removeProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- BOTONES DE ACCIÓN -->
                    <div class="flex justify-end mt-8">
                        <SecondaryButton :loading="form.processing">
                            Guardar Cambios
                        </SecondaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Checkbox from "@/Components/Checkbox.vue";
import { useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

export default {
    components: {
        Back,
        Checkbox,
        AppLayout,
        TextInput,
        InputLabel,
        InputError,
        LoadingIsoLogo,
        SecondaryButton,
    },
    props: {
        supplier: Object,
        products: Array,
    },
    data() {
        return {
            form: useForm({
                name: this.supplier.name,
                rfc: this.supplier.rfc,
                nickname: this.supplier.nickname,
                address: this.supplier.address,
                post_code: this.supplier.post_code,
                phone: this.supplier.phone,
                email: this.supplier.email,
                notes: this.supplier.notes,
                contacts: this.supplier.contacts?.map(contact => ({...contact})), // Clonar para evitar mutación
                bankAccounts: this.supplier.bank_accounts?.map(account => ({...account})),
                products: this.supplier.products?.map(product => ({
                    product_id: product.id,
                    last_price: product.pivot.last_price,
                    min_quantity: product.pivot.min_quantity,
                    supplier_sku: product.pivot.supplier_sku,
                })),
            }),
            newContact: {
                name: '',
                position: '',
                email: '',
                phone: '',
                is_primary: false,
            },
            newBankAccount: {
                bank_name: '',
                account_holder: '',
                account_number: '',
                clabe: '',
                currency: 'MXN',
            },
            newProduct: {
                product_id: null,
                last_price: null,
                supplier_sku: null,
                min_quantity: 1,
                media: [],
                storages: [],
            },
            loadingComponentMedia: false
        };
    },
    methods: {
        update() {
            this.form.put(route("suppliers.update", this.supplier.id), {
                onSuccess: () => {
                    ElMessage.success('Proveedor actualizado con éxito');
                },
                onError: () => {
                    ElMessage.error('Hubo un problema. Revisa los campos del formulario.');
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        // --- Métodos para Contactos ---
        addContact() {
            if (!this.newContact.name) return;
            if (this.newContact.is_primary) {
                this.form.contacts.forEach(contact => contact.is_primary = false);
            }
            this.form.contacts.push({ ...this.newContact });
            this.newContact = { name: '', position: '', email: '', phone: '', is_primary: false };
        },
        removeContact(index) {
            this.form.contacts.splice(index, 1);
        },
        // --- Métodos para Cuentas Bancarias ---
        addBankAccount() {
            if (!this.newBankAccount.bank_name || !this.newBankAccount.account_holder || !this.newBankAccount.account_number) return;
            this.form.bankAccounts.push({ ...this.newBankAccount });
            this.newBankAccount = { bank_name: '', account_holder: '', account_number: '', clabe: '', currency: 'MXN' };
        },
        removeBankAccount(index) {
            this.form.bankAccounts.splice(index, 1);
        },
        // --- Métodos para Productos ---
        addProduct() {
            if (!this.newProduct.product_id) return;
            this.form.products.push({ ...this.newProduct });
            this.newProduct = { product_id: null, last_price: null, supplier_sku: null, min_quantity: 1 };
        },
        removeProduct(index) {
            this.form.products.splice(index, 1);
        },
        isProductSelected(productId) {
            return this.form.products.some(p => p.product_id === productId);
        },
        getProductName(productId) {
            const product = this.products.find(p => p.id === productId);
            return product ? product.name : 'Producto no encontrado';
        },
        getProductUnit(productId) {
            const product = this.products.find(p => p.id === productId);
            return product ? product.measure_unit : '';
        },
        async getComponentMedia() {
            this.loadingComponentMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.newProduct.product_id));
                if (response.status === 200) {
                    this.newProduct.media = response.data.product.media;
                    this.newProduct.storages = response.data.product.storages;
                    this.newProduct.cost = response.data.product.cost;
                    this.newProduct.measure_unit = response.data.product.measure_unit;
                    this.newProduct.currency = response.data.product.currency;
                    this.newProduct.last_price = response.data.product.cost;
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la información del producto');
            } finally {
                this.loadingComponentMedia = false;
            }
        },
    },
};
</script>
