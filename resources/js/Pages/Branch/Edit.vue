<template>
    <AppLayout title="Editar Cliente">
        <!-- Panel Flotante de Notas -->
        <BranchNotes :branch-id="branch.id" />

        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <!-- Botón dinámico que regresa a ventas o al index dependiendo de cómo llegó aquí -->
                <Back :href="backRoute" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar cliente: {{ form.name }}
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="update">
                        <div class="mb-5 p-4 bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-md text-sm">
                            <i class="fa-solid fa-circle-info mr-2"></i>
                            <strong>Nota:</strong> Para que el registro esté completo, los campos <strong>Nombre Comercial (Alias)</strong>, <strong>Razón Social</strong>, <strong>RFC y al menos un contacto</strong> son obligatorios. Esto <em>solo aplica para sucursales matriz</em>; si se asigna una "Sucursal Matriz" (cliente hijo), no son obligatorios.
                        </div>

                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 border-b dark:border-gray-600 pb-2">Datos Generales</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                            <!-- Nuevos campos incorporados -->
                            <TextInput label="Número de Cliente" v-model="form.client_number" type="text" :error="form.errors.client_number" placeholder="Ej. 001" />
                            <TextInput label="Nombre Comercial (Alias)*" v-model="form.name" type="text" :error="form.errors.name" placeholder="Nombre de empresa/sucursal" />
                            
                            <TextInput :label="form.parent_branch_id ? 'Razón Social' : 'Razón Social*'" v-model="form.business_name" type="text" :error="form.errors.business_name" :placeholder="parentBranchData?.business_name || 'Razón social fiscal'" />
                            <TextInput :label="form.parent_branch_id ? 'RFC' : 'RFC*'" v-model="form.rfc" type="text" :error="form.errors.rfc" :placeholder="parentBranchData?.rfc || 'Registro Federal de Contribuyentes'" />
                            
                            <TextInput label="Grupo" v-model="form.group_name" type="text" :error="form.errors.group_name" placeholder="Nombre del grupo (Opcional)" />
                            <TextInput label="Cuenta Bancaria" v-model="form.bank_account" type="text" :error="form.errors.bank_account" placeholder="Ej. 1234 o Cuenta Completa" />

                            <div class="md:col-span-2">
                                <TextInput label="Dirección" v-model="form.address" type="text" :error="form.errors.address" placeholder="Calle, número, colonia" />
                            </div>
                            <TextInput label="Código Postal" v-model="form.post_code" type="text" :error="form.errors.post_code" />
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Estatus*</label>
                                <el-select v-model="form.status" placeholder="Selecciona un estatus" class="!w-full">
                                    <el-option label="Prospecto" value="Prospecto" />
                                    <el-option label="Cliente" value="Cliente" />
                                </el-select>
                                <InputError :message="form.errors.status" />
                            </div>
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Vendedor Asignado</label>
                                <el-select v-model="form.account_manager_id" placeholder="Selecciona un vendedor" class="!w-full" filterable>
                                    <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                                </el-select>
                                <InputError :message="form.errors.account_manager_id" />
                            </div>
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Sucursal Matriz (Opcional)</label>
                                <el-select v-model="form.parent_branch_id" placeholder="Selecciona una matriz" class="!w-full" filterable clearable>
                                    <el-option v-for="item in branches" :key="item.id" :label="item.name" :value="item.id" />
                                </el-select>
                                <InputError :message="form.errors.parent_branch_id" />
                            </div>
                            <div>
                                <InputLabel value="Cómo nos conoció el cliente*" />
                                <el-select v-model="form.meet_way" placeholder="Selecciona">
                                    <el-option v-for="item in meetWays" :key="item" :value="item" :label="item" />
                                </el-select>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center mt-8 mb-4 border-b dark:border-gray-600 pb-2">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Contactos</h3>
                            <el-button @click="addContact" type="primary" plain>
                                <i class="fa-solid fa-plus mr-2"></i> Agregar Contacto
                            </el-button>
                        </div>

                        <div v-for="(contact, index) in form.contacts" :key="index" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4 border dark:border-gray-600 rounded-lg p-4 mb-4 relative">
                            <!-- Selector de Prefijo -->
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Prefijo</label>
                                <el-select v-model="contact.prefix" placeholder="Prefijo" class="!w-full">
                                    <el-option v-for="p in prefixes" :key="p" :label="p" :value="p === 'Sin prefijo' ? '' : p" />
                                </el-select>
                                <InputError :message="form.errors[`contacts.${index}.prefix`]" />
                            </div>
                            
                            <TextInput class="lg:col-span-2" label="Nombre del contacto*" v-model="contact.name" type="text" :error="form.errors[`contacts.${index}.name`]" />
                            <TextInput label="Cargo" v-model="contact.charge" type="text" :error="form.errors[`contacts.${index}.charge`]" />
                            <TextInput label="Teléfono*" v-model="contact.phone" type="text" :error="form.errors[`contacts.${index}.phone`]" />
                            <TextInput label="Email*" v-model="contact.email" type="email" :error="form.errors[`contacts.${index}.email`]" />

                            <div class="md:col-span-2 lg:col-span-3 grid grid-cols-2 gap-x-5">
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Mes de Cumpleaños</label>
                                    <el-select v-model="contact.birth_month" placeholder="Mes" class="!w-full" clearable>
                                        <el-option v-for="month in months" :key="month.value" :label="month.label" :value="month.value" />
                                    </el-select>
                                    <InputError :message="form.errors[`contacts.${index}.birth_month`]" />
                                </div>
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Día de Cumpleaños</label>
                                    <el-select v-model="contact.birth_day" placeholder="Día" class="!w-full" clearable :disabled="!contact.birth_month">
                                        <el-option v-for="day in daysInMonth(contact.birth_month)" :key="day" :label="day" :value="day" />
                                    </el-select>
                                    <InputError :message="form.errors[`contacts.${index}.birth_day`]" />
                                </div>
                            </div>
                            
                            <button @click="removeContact(index)" type="button" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                        <InputError :message="form.errors.contacts" />

                        <div class="flex justify-between items-center mt-8 mb-4 border-b dark:border-gray-600 pb-2">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Productos Asignados</h3>
                        </div>
                        
                        <div class="p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Buscar producto*</label>
                                    <el-select @change="getProductMedia" v-model="currentProduct.product_id" placeholder="Selecciona un producto" class="!w-full" filterable>
                                        <el-option class="!w-96" v-for="item in availableProducts" 
                                            :key="item.id" 
                                            :label="item.name" 
                                            :value="item.id"
                                            :disabled="isProductInForm(item.id)"
                                        />
                                    </el-select>
                                </div>
                                <TextInput label="Precio Especial (Opcional)" v-model="currentProduct.price"
                                    :helpContent="'Si no agregas precio especial se tomará en cuenta el precio base del producto'" type="number" :step="0.01" placeholder="Dejar vacío para usar precio base" />

                                <div>
                                    <label>Moneda*</label>
                                    <el-select v-model="currentProduct.currency" placeholder="Moneda" :teleported="false" class="!w-full mt-1">
                                        <el-option label="MXN" value="MXN" />
                                        <el-option label="USD" value="USD" />
                                    </el-select>
                                </div>
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
                                    <i class="fa-solid fa-plus mr-2"></i> Agregar Producto
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-if="form.products.length" class="mt-4">
                             <InputError :message="form.errors.products" />
                            <ul class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2">
                                <li v-for="(product, index) in form.products" :key="index" class="flex justify-between items-center p-2 rounded-md">
                                    <span class="text-sm text-gray-800 dark:text-gray-200">
                                        <span class="font-bold text-primary">{{ getProductName(product.product_id) }}</span>
                                    </span>
                                    <div class="flex items-center space-x-3 text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">
                                            Precio Especial: <strong>${{ product.price ?? 'N/A' }}</strong>
                                        </span>
                                        <button @click="editProduct(index)" type="button" class="text-gray-500 hover:text-blue-500 transition-colors">
                                            <i class="fa-solid fa-pencil"></i>
                                        </button>
                                        <button @click="removeProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- ================================================== -->
                        <!-- ============== PRODUCTOS SUGERIDOS =============== -->
                        <!-- ================================================== -->
                        <div class="flex justify-between items-center mt-8 mb-4 border-b dark:border-gray-600 pb-2">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Productos Sugeridos (Opcional)</h3>
                        </div>

                        <div>
                            <el-tooltip
                                content="Selecciona productos que podrían interesarle a este cliente en el futuro, aunque no se les asigne un precio especial ahora."
                                placement="top-start"
                            >
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3 flex items-center space-x-2">
                                    <span>Sugerencias de productos</span>
                                    <i class="fa-solid fa-circle-info text-gray-400"></i>
                                </label>
                            </el-tooltip>
                            <el-select
                                v-model="form.suggested_products"
                                placeholder="Buscar y seleccionar productos"
                                class="!w-full mt-1"
                                filterable
                                multiple
                                clearable
                            >
                                <el-option
                                    v-for="item in catalog_products"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                    :disabled="isProductInForm(item.id)"
                                />
                            </el-select>
                            <InputError :message="form.errors.suggested_products" />
                        </div>

                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing">
                                Guardar Cambios
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        return {
            prefixes: ['Ing.', 'Lic.', 'Arq.', 'Dr.', 'C.P.', 'Sin prefijo'], // Arreglo de prefijos
            form: useForm({
                name: this.branch.name,
                rfc: this.branch.rfc || this.branches.find(b => b.id === this.branch.parent_branch_id)?.rfc || null,
                // --- NUEVAS PROPIEDADES PRE-CARGADAS ---
                group_name: this.branch.group_name,
                business_name: this.branch.business_name || this.branches.find(b => b.id === this.branch.parent_branch_id)?.business_name || null,
                bank_account: this.branch.bank_account,
                client_number: this.branch.client_number,

                address: this.branch.address,
                post_code: this.branch.post_code,
                status: this.branch.status,
                parent_branch_id: this.branch.parent_branch_id,
                account_manager_id: this.branch.account_manager_id,
                meet_way: this.branch.meet_way,
                contacts: this.formattedContacts,
                products: this.formattedProducts,
                suggested_products: this.suggestedProductIds ?? [],
            }),
            currentProduct: {
                product_id: null,
                price: null,
                media: null,
                base_price: null,
                currency: 'MXN',
                current_stock: null,
                location: null
            },
            loadingProductMedia: false,
            months: [
                { label: 'Enero', value: 1 }, { label: 'Febrero', value: 2 },
                { label: 'Marzo', value: 3 }, { label: 'Abril', value: 4 },
                { label: 'Mayo', value: 5 }, { label: 'Junio', value: 6 },
                { label: 'Julio', value: 7 }, { label: 'Agosto', value: 8 },
                { label: 'Septiembre', value: 9 }, { label: 'Octubre', value: 10 },
                { label: 'Noviembre', value: 11 }, { label: 'Diciembre', value: 12 },
            ],
            meetWays: [
                'Recomendación',
                'Búsqueda en línea',
                'Publicidad ',
                'Evento o feria comercial',
                'Correo electrónico',
                'Llamada telefónica ',
                'Sitio web de la empresa',
                'Tocamos puerta',
                'Otro',
            ],
        };
    },
    components: {
        Back,
        AppLayout,
        TextInput,
        InputError,
        InputLabel,
        BranchNotes,
        PrimaryButton,
        SecondaryButton,
    },
    props: {
        branch: Object,
        formattedContacts: Array,
        formattedProducts: Array,
        users: Array,
        branches: Array,
        catalog_products: Array,
        suggestedProductIds: Array,
    },
    computed: {
        availableProducts() {
            const assignedProductIds = this.formattedProducts.map(p => p.product_id);
            return this.catalog_products.filter(p => !assignedProductIds.includes(p.id));
        },
        // NUEVO: Computado para saber a dónde debe llevar el botón "Atrás"
        backRoute() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('redirect_to') === 'sales.create') {
                return route('sales.create');
            }
            return route('branches.index');
        },
        parentBranchData() {
            if (!this.form.parent_branch_id) return null;
            return this.branches.find(b => b.id === this.form.parent_branch_id);
        }
    },
    methods: {
        update() {
            // Buscamos si existe el parámetro redirect_to en la URL
            const urlParams = new URLSearchParams(window.location.search);
            const redirectTo = urlParams.get('redirect_to');

            // Preparamos los parámetros para el router de Laravel
            const routeParams = { branch: this.branch.id };
            
            // Si existe la variable en la url, la adjuntamos a la petición
            if (redirectTo) {
                routeParams.redirect_to = redirectTo; 
            }

            this.form.put(route("branches.update", routeParams), {
                onSuccess: () => {
                    ElMessage.success('Cliente actualizado correctamente');
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario.');
                }
            });
        },
        addContact() {
            this.form.contacts.push({
                prefix: 'Ing.', // Valor por defecto
                name: null,
                charge: null,
                phone: null,
                email: null,
                birth_month: null,
                birth_day: null,
            });
        },
        removeContact(index) {
            this.form.contacts.splice(index, 1);
        },
        daysInMonth(month) {
            if (!month) return 31;
            return new Date(2000, month, 0).getDate();
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
                ElMessage.warning('Este producto ya ha sido agregado a la lista.');
                return;
            }

            this.form.products.push({ ...this.currentProduct });
            this.resetCurrentProduct();
        },
        removeProduct(index) {
            this.form.products.splice(index, 1);
        },
        editProduct(index) {
            const productToEdit = this.form.products[index];
            this.currentProduct = { ...productToEdit };
            this.getProductMedia();
            this.removeProduct(index);
        },
        resetCurrentProduct() {
            this.currentProduct = {
                product_id: null,
                price: null,
                media: null,
                currency: 'MXN',
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
        }
    }
};
</script>