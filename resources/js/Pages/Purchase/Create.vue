<template>
    <AppLayout title="Crear Orden de Compra">
        <!-- Productos a favor -->
        <SupplierFavoredProducts v-if="form.supplier_id" :supplier-id="form.supplier_id" />

        <!-- Encabezado de la página -->
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('purchases.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crear nueva órden de compra
                </h2>
            </div>
        </div>

        <!-- Contenedor del formulario -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="store">
                        <!-- SECCIÓN DE DATOS GENERALES -->
                        <section class="mb-8">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Datos Generales</h3>
                             <!-- Opciones de Plantilla y Tipo -->
                            <div class="col-span-1 md:col-span-3 grid grid-cols-2 gap-x-5 mb-5">
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1">Idioma de la plantilla</label>
                                    <el-radio-group v-model="form.is_spanish_template" class="!w-full">
                                        <el-radio-button :label="true">Español</el-radio-button>
                                        <el-radio-button :label="false">Inglés</el-radio-button>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-4">
                                <!-- Selector de Proveedor -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="supplier">Proveedor*</label>
                                    <el-select @change="fetchSupplierDetails" v-model="form.supplier_id" filterable placeholder="Selecciona un proveedor" class="!w-full">
                                        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_id" />
                                </div>
                                <!-- Selector de Contacto -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="contact">Contacto</label>
                                    <el-select v-model="form.supplier_contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" :disabled="!form.supplier_id">
                                        <el-option v-for="item in supplierContacts" :key="item.id" :label="item.name" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_contact_id" />
                                </div>
                                <!-- Selector de Cuenta Bancaria -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="bank_account">Información bancaria</label>
                                    <el-select v-model="form.supplier_bank_account_id" filterable placeholder="Selecciona una cuenta" class="!w-full" :disabled="!form.supplier_id">
                                        <el-option v-for="item in supplierBankAccounts" :key="item.id" :label="`${item.bank_name} - ${item.account_number}`" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_bank_account_id" />
                                </div>
                                <!-- Selector de Moneda -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="currency">Moneda*</label>
                                    <el-select v-model="form.currency" placeholder="Selecciona" class="!w-full">
                                        <el-option label="MXN" value="MXN" />
                                        <el-option label="USD" value="USD" />
                                    </el-select>
                                    <InputError :message="form.errors.currency" />
                                </div>
                                <!-- Selector de Fecha de Entrega -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="delivery_date">Fecha de entrega esperada*</label>
                                    <el-date-picker v-model="form.expected_delivery_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" :disabled-date="disabledDate" class="!w-full" />
                                    <InputError :message="form.errors.expected_delivery_date" />
                                </div>

                                <div class="col-span-2 mt-5">
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1 mb-1" for="currency">Archivos extra (max. 3)</label>
                                    <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="todo" :max-files="3" />
                                    <InputError :message="form.errors.media" class="mt-2" />
                                </div>
                                
                                <!-- Campo de Notas -->
                                <div class="col-span-1 md:col-span-3">
                                    <TextInput label="Notas" :withMaxLength="true"
                                        :maxLength="255" 
                                        v-model="form.notes" 
                                        type="text" 
                                        :error="form.errors.notes" 
                                        placeholder="Notas internas o para el proveedor" />
                                </div>
                                
                            </div>
                        </section>

                        <!-- SECCIÓN DE PRODUCTOS -->
                        <section class="mb-5">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Productos</h3>
                            <div v-if="!form.supplier_id" class="text-center text-sm text-gray-500 bg-gray-100 dark:bg-slate-800 rounded-lg p-4">
                                <p>Por favor, selecciona un proveedor para poder agregar productos.</p>
                            </div>
                            <div ref="formProduct" v-else>
                                <!-- Formulario para agregar/editar producto -->
                                <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg grid grid-cols-1 md:grid-cols-4 gap-x-3 gap-y-4 items-end relative">
                                    <div class="md:col-span-full">
                                        <div class="w-1/2">
                                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-1">Producto*</label>
                                            <el-select-v2 v-model="productForm.product_id" filterable placeholder="Busca un producto" :options="availableProducts" @change="onProductSelect" class="!w-full" />
                                        </div>
                                        <LoadingIsoLogo v-if="loadingProductMedia" />

                                        <!-- Tarjeta de materia prima seleccionada -->
                                        <div class="md:absolute top-3 right-3 mt-2 flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="productForm.product_id">
                                            <figure 
                                                v-if="productForm.media" 
                                                class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                                                <img v-if="productForm.media?.length"
                                                    :src="productForm.media[0]?.original_url" 
                                                    alt="" 
                                                    class="rounded-2xl w-full h-auto object-cover transition duration-300 ease-in-out hover:opacity-95"
                                                >
                                                <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                                    </svg>
                                                <p>Sin imagen</p>
                                                </div>
                                                <!-- Overlay degradado sutil -->
                                                <div class="absolute inset-0 bg-gradient-to-t from-black/10 via-transparent to-black/5"></div>
                                            </figure>

                                            <!-- informacion de almacén -->
                                            <div>
                                                <p class="text-gray-500 dark:text-gray-300">
                                                    Stock: <strong>{{ productForm.storages[0]?.quantity.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ productForm.measure_unit }}</strong>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <TextInput label="Cantidad*" v-model.number="productForm.quantity" type="numeric-stepper" placeholder="0" />
                                    </div>
                                    <div>
                                        <TextInput label="Precio Unitario*" v-model="productForm.unit_price" type="text" placeholder="0.00" :formatAsNumber="true">
                                            <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                        </TextInput>
                                    </div>
                                    <div class="md:col-span-2"></div> <!-- Spacer -->

                                    <!-- Distribución de stock -->
                                    <div v-if="form.type === 'Venta'" class="md:col-span-full">
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Distribución de stock</p>
                                        <div class="grid grid-cols-3 gap-x-2 border p-3 rounded-md" :class="{
                                                'border-red-500': stockRemaining < 0,
                                                'border-green-500': stockRemaining === 0,
                                                'border-amber-500': stockRemaining > 0
                                        }">
                                            <TextInput label="Stock Avión" v-model.number="productForm.plane_stock" type="numeric-stepper" placeholder="0" />
                                            <TextInput label="Stock Barco" v-model.number="productForm.ship_stock" type="numeric-stepper" placeholder="0" />
                                            <TextInput label="Stock Favor" v-model.number="productForm.additional_stock" type="numeric-stepper" placeholder="0" />
                                        </div>
                                        <p v-if="stockRemaining !== 0" :class="stockRemaining < 0 ? 'text-red-500' : 'text-amber-500'" class="text-xs mt-1">
                                            <i class="fa-solid fa-circle-info mr-1"></i> Cantidad por asignar: {{ stockRemaining }}
                                        </p>
                                    </div>

                                    <!-- Tipo de compra -->
                                    <div class="col-span-full grid grid-cols-1 md:grid-cols-3">
                                        <div>
                                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-1">Tipo de compra</label>
                                            <el-radio-group v-model="productForm.type" class="!w-full">
                                                <el-radio-button label="Venta">Para Venta</el-radio-button>
                                                <el-radio-button label="Muestra">Para Muestras</el-radio-button>
                                            </el-radio-group>
                                        </div>
                                        <div class="md:col-span-2 flex items-center space-x-3">
                                            <el-checkbox v-model="productForm.needs_mold">¿Requiere molde?</el-checkbox>
                                            <TextInput 
                                                v-if="productForm.needs_mold" 
                                                v-model.number="productForm.mold_price" 
                                                label="Precio del molde" 
                                                helpContent="En caso de no saber el costo, dejarlo vacío o en 0."
                                                type="text" 
                                                placeholder="0.00" 
                                                :formatAsNumber="true"
                                            >
                                                <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                            </TextInput>
                                        </div>
                                    </div>

                                    <!-- Notas del producto -->
                                    <div class="md:col-span-full">
                                        <TextInput
                                            v-model="productForm.notes"
                                            label="Notas del producto"
                                            :withMaxLength="true"
                                            :maxLength="255"
                                        />
                                    </div>
                                    
                                    <div class="md:col-span-full">
                                        <PrimaryButton @click="addProduct" type="button" :disabled="!productForm.product_id || !productForm.quantity > 0">
                                            <i :class="isEditing ? 'fa-solid fa-check' : 'fa-solid fa-plus'" class="mr-2"></i> {{ isEditing ? 'Actualizar' : 'Agregar' }}
                                        </PrimaryButton>
                                        <button v-if="isEditing" @click="cancelEdit" type="button" class="ml-2 text-sm text-gray-500 hover:text-gray-800 dark:hover:text-red-500">Cancelar</button>
                                    </div>
                                </div>

                                <!-- Tabla de productos agregados -->
                                <div class="mt-4 overflow-x-auto">
                                    <el-table :data="form.items" stripe class="!w-full">
                                        <el-table-column prop="product_name" label="Producto" min-width="150" />
                                        <el-table-column prop="notes" label="Notas" min-width="120">
                                            <template #default="scope">
                                                <el-tooltip v-if="scope.row.notes" :content="scope.row.notes" placement="top">
                                                    <span class="truncate">{{ scope.row.notes }}</span>
                                                </el-tooltip>
                                                <span v-else class="text-gray-400">N/A</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="quantity" label="Cantidad" width="100" />
                                        <el-table-column prop="unit_price" label="Precio Unitario" width="120">
                                            <template #default="scope">
                                                <span v-if="scope.row.unit_price && scope.row.unit_price > 0">{{ formatCurrency(scope.row.unit_price) }}</span>
                                                <span v-else>{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="mold_price" label="Molde" width="100">
                                            <template #default="scope">
                                                <p v-if="scope.row.needs_mold">{{  scope.row.mold_price ? formatCurrency(scope.row.mold_price) : (form.is_spanish_template ? 'Por definir' : 'To be defined') }}</p>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Total" width="150">
                                            <template #default="scope">
                                                <span v-if="scope.row.unit_price && scope.row.unit_price > 0">{{ formatCurrency((scope.row.quantity * scope.row.unit_price) + (scope.row.mold_price || 0)) }}</span>
                                                <span v-else>{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Acciones" width="100" align="right">
                                             <template #default="scope">
                                                <div class="flex items-center justify-end space-x-3">
                                                    <button @click="editProduct(scope.$index)" type="button" class="text-blue-500 hover:text-blue-700">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <el-popconfirm confirm-button-text="Sí" cancel-button-text="No" icon-color="#D32F2F" title="¿Remover producto?" @confirm="removeProduct(scope.$index)">
                                                        <template #reference>
                                                            <button type="button" class="text-red-500 hover:text-red-700">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </template>
                                                    </el-popconfirm>
                                                </div>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                    <InputError :message="form.errors.items" class="mt-2" />
                                </div>

                                <!-- Totales -->
                                <div class="mt-6 flex justify-end">
                                    <div class="w-full max-w-sm space-y-2 text-gray-700 dark:text-gray-300">
                                        <div class="flex justify-between">
                                            <span>Subtotal:</span>
                                            <span v-if="totals.hasUndefinedPrice">{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            <span v-else>{{ formatCurrency(totals.subtotal) }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <label class="text-sm">IVA ({{ tax_percent }}%):</label>
                                            <div class="w-28">
                                                <TextInput v-model.number="tax_percent" type="number" placeholder="16">
                                                    <template #icon-right><i class="fa-solid fa-percentage"></i></template>
                                                </TextInput>
                                            </div>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Impuestos:</span>
                                            <span v-if="totals.hasUndefinedPrice">{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            <span v-else>{{ formatCurrency(totals.tax) }}</span>
                                        </div>
                                        <div class="flex justify-between"><span>Moldes:</span> <span>{{ formatCurrency(totals.molds) }}</span></div>
                                        <div class="flex justify-between font-bold text-lg border-t pt-2">
                                            <span>Total:</span>
                                            <span v-if="totals.hasUndefinedPrice">{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            <span v-else>{{ formatCurrency(totals.total) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- Botón de envío -->
                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing" :disabled="form.items.length === 0">
                                Crear Órden de Compra
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
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import SupplierFavoredProducts from "@/Components/MyComponents/SupplierFavoredProducts.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        // Objeto principal del formulario
        const form = useForm({
            supplier_id: null,
            supplier_contact_id: null,
            supplier_bank_account_id: null,
            expected_delivery_date: null,
            currency: 'MXN',
            notes: '',
            is_spanish_template: true, // true para español, false para inglés
            type: 'Venta', // 'Venta' o 'Muestra'
            items: [],
            subtotal: 0,
            tax: 0,
            total: 0,
            media: [],
        });

        // Objeto para el producto que se está agregando/editando
        const productForm = {
            product_id: null,
            product_name: '',
            quantity: 1,
            unit_price: 0,
            additional_stock: 0,
            plane_stock: 0,
            needs_mold: false,
            mold_price: null,
            ship_stock: 0,
            type: 'Venta',
            notes: '',
        };

        return {
            form,
            productForm,
            supplierContacts: [],
            supplierBankAccounts: [],
            availableProducts: [],
            editProductIndex: null, // Índice del producto en edición
            loadingProductMedia: false,
            tax_percent: 16,
        };
    },
    components: {
        Back,
        AppLayout,
        TextInput,
        InputError,
        FileUploader,
        PrimaryButton,
        LoadingIsoLogo,
        SecondaryButton,
        SupplierFavoredProducts,
    },
    props: {
        suppliers: Array,
        products: Array,
    },
    computed: {
        // Calcula los totales de la orden
        totals() {
            const hasUndefinedPrice = this.form.items.some(item => !item.unit_price || item.unit_price <= 0);
            const subtotal = this.form.items.reduce((acc, item) => acc + (item.quantity * (item.unit_price || 0)), 0);
            const tax = subtotal * (this.tax_percent / 100);
            const molds = this.form.items.reduce((acc, item) => acc + (item.mold_price || 0), 0);
            const total = subtotal + tax + molds;

            return { subtotal, tax, molds, total, hasUndefinedPrice };
        },
        // Devuelve true si se está editando un producto
        isEditing() {
            return this.editProductIndex !== null;
        },
        // Calcula la cantidad de stock restante por asignar
        stockRemaining() {
            const totalStock = (this.productForm.plane_stock || 0) +
                               (this.productForm.ship_stock || 0) +
                               (this.productForm.additional_stock || 0);
            return (this.productForm.quantity || 0) - totalStock;
        },
    },
    methods: {
        // Enviar el formulario para crear la orden
        store() {
            this.form.subtotal = this.totals.subtotal;
            this.form.tax = this.totals.tax;
            this.form.total = this.totals.total;

            this.form.post(route("purchases.store"), {
                onSuccess: () => {
                    ElMessage.success('Órden de compra creada');
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        // Obtener detalles del proveedor (contactos, cuentas, productos)
        async fetchSupplierDetails() {
            if (!this.form.supplier_id) return;
            
            this.resetFormOnSupplierChange();
            
            try {
                const response = await axios.get(route('suppliers.get-details', { supplier: this.form.supplier_id }));
                const data = response.data;
                this.supplierContacts = data.contacts;
                this.supplierBankAccounts = data.bankAccounts;
                this.availableProducts = data.products.map(product => ({
                    value: product.id,
                    label: `${product.name} (${product.code})`,
                    ...product
                }));
            } catch (error) {
                console.error("Error al obtener detalles del proveedor:", error);
                ElMessage.error("No se pudieron cargar los datos del proveedor.");
            }
        },
        // Seleccionar un producto del listado
        onProductSelect(productId) {
            const product = this.availableProducts.find(p => p.value === productId);
            if (product) {
                this.productForm.product_name = product.label;
                this.productForm.unit_price = product.cost ?? 0;
            }
            this.getProductMedia();
        },
        async getProductMedia() {
            if (!this.productForm.product_id) return;
            this.loadingProductMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.productForm.product_id));

                if ( response.status === 200 ) {
                    this.productForm.media = response.data.product.media;
                    this.productForm.storages = response.data.product.storages;
                    this.productForm.cost = response.data.product.cost;
                    this.productForm.measure_unit = response.data.product.measure_unit;
                    this.productForm.currency = response.data.product.currency;

                    // Si estamos agregando un nuevo producto (no editando), se asigna el costo como precio.
                    if (this.editingProductIndex === null) {
                        this.productForm.last_price = response.data.product.cost;
                    }
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la información del producto');
            } finally {
                this.loadingProductMedia = false;
            }
        },
        // Agregar o actualizar un producto en la lista
        addProduct() {
            if (!this.productForm.product_id || !this.productForm.quantity || this.productForm.unit_price === null) {
                ElMessage.warning('Completa todos los campos del producto');
                return;
            }

            // Validación de la distribución de stock
            if (this.form.type === 'Venta' && this.stockRemaining !== 0) {
                ElMessage.error('La suma del stock (Avión, Barco, Favor) debe ser igual a la Cantidad total.');
                return;
            }

            const existingProductIndex = this.form.items.findIndex(item => item.product_id === this.productForm.product_id);
            if (existingProductIndex !== -1 && existingProductIndex !== this.editProductIndex) {
                 ElMessage.warning('Este producto ya ha sido agregado a la órden');
                return;
            }

            if (this.isEditing) {
                // Actualizar producto existente
                this.form.items[this.editProductIndex] = { ...this.productForm };
            } else {
                // Agregar nuevo producto
                this.form.items.push({ ...this.productForm });
            }

            this.resetProductForm();
        },
        // Quitar un producto de la lista
        removeProduct(index) {
            if (this.editProductIndex === index) {
                this.cancelEdit();
            }
            this.form.items.splice(index, 1);
        },
        // Preparar formulario para editar un producto
        editProduct(index) {
            this.editProductIndex = index;
            this.productForm = { ...this.form.items[index] };
            this.$refs.formProduct.scrollIntoView({ behavior: 'smooth' });
        },
        // Cancelar la edición de un producto
        cancelEdit() {
            this.editProductIndex = null;
            this.resetProductForm();
        },
        // Limpiar el formulario de producto
        resetProductForm() {
            this.productForm = {
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_price: 0,
                additional_stock: 0,
                plane_stock: 0,
                ship_stock: 0,
                type: 'Venta',
                needs_mold: false,
                mold_price: null,
                notes: '',
            };
            this.editProductIndex = null;
        },
        // Limpiar el formulario cuando cambia el proveedor
        resetFormOnSupplierChange() {
            this.form.items = [];
            this.form.supplier_contact_id = null;
            this.form.supplier_bank_account_id = null;
            this.resetProductForm();
        },
        // Deshabilitar fechas anteriores al día de hoy en el calendario
        disabledDate(time) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return time.getTime() < today.getTime();
        },
        // Formatear un número como moneda
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return Number(value).toLocaleString('es-MX', { style: 'currency', currency: this.form.currency });
        },
    }
};
</script>
