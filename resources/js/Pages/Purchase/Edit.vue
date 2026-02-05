<template>
    <AppLayout title="Editar Orden de Compra">
        <!-- Productos a favor -->
        <SupplierFavoredProducts v-if="form.supplier_id" :supplier-id="form.supplier_id" />

        <!-- Encabezado de la página -->
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('purchases.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar órden de compra #{{ purchase.id }}
                </h2>
            </div>
        </div>

        <!-- Contenedor del formulario -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="update">
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
                                    <el-date-picker v-model="form.expected_delivery_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" />
                                    <InputError :message="form.errors.expected_delivery_date" />
                                </div>

                                <div class="col-span-full mt-5">
                                    <div v-if="form.current_media.length" class="col-span-full mb-3">
                                        <label class="text-gray-700 dark:text-white text-sm mb-2 inline-block" for="">Archivos adjuntos actuales</label>
                                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                                            <FileView v-for="file in form.current_media" :key="file.id" :file="file" :deletable="true" @delete-file="deleteFile(file.id)" />
                                        </div>
                                    </div>
                                    <div v-if="form.current_media.length < 3">
                                        <label class="text-gray-700 dark:text-gray-100 text-sm ml-1 mb-1" for="currency">Agregar más archivos (max. {{ 3 - form.current_media.length }})</label>
                                        <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="todo" :max-files="3 - form.current_media.length" />
                                        <InputError :message="form.errors.media" class="mt-2" />
                                    </div>
                                    <p v-else class="text-amber-600 text-sm mt-2 col-span-full">*Has alcanzado el límite de archivos.</p>
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
                                    <div v-if="productForm.type === 'Venta'" class="md:col-span-full">
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
                                            <el-checkbox @change="!productForm.needs_mold ? productForm.mold_price = 0 : ''" v-model="productForm.needs_mold">¿Requiere molde?</el-checkbox>
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
                                            <i :class="isEditing ? 'fa-solid fa-check' : 'fa-solid fa-plus'" class="mr-2"></i> {{ isEditing ? 'Actualizar producto' : 'Agregar producto' }}
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
                                        <el-table-column prop="mold_price" label="Molde" width="120">
                                            <template #default="scope">
                                                <p v-if="scope.row.needs_mold">{{  scope.row.mold_price ? formatCurrency(scope.row.mold_price) : (form.is_spanish_template ? 'Por definir' : 'To be defined') }}</p>
                                                <p v-else>No</p>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Total" width="150" align="right">
                                            <template #default="scope">
                                                <span v-if="scope.row.unit_price && scope.row.unit_price > 0">{{ formatCurrency((scope.row.quantity * scope.row.unit_price) + (parseFloat(scope.row.mold_price) || 0)) }}</span>
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
                            <SecondaryButton @click="update" :loading="form.processing" :disabled="form.items.length === 0">
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
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import SupplierFavoredProducts from "@/Components/MyComponents/SupplierFavoredProducts.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';
import { parse } from 'date-fns';

export default {
    data() {
        // Mapea los items para el formulario, asegurando que tengan `product_name`.
        const formItems = this.purchase.items.map(item => ({
            ...item,
            product_name: item.product.name // Agrega el nombre para la tabla
        }));
        
        const form = useForm({
            ...this.purchase,
            items: formItems,
            media: [], // Para nuevos archivos
            current_media: this.purchase.media, // Archivos existentes
            _method: 'put',
        });

        // Objeto para el producto que se está agregando/editando
        const productForm = {
            product_id: null,
            product_name: '',
            quantity: 1,
            unit_price: 0,
            additional_stock: 0,
            plane_stock: 0,
            ship_stock: 0,
            needs_mold: false,
            mold_price: null,
            type: 'Venta',
            notes: '',
        };

        return {
            form,
            productForm,
            supplierContacts: [],
            supplierBankAccounts: [],
            availableProducts: [],
            editProductIndex: null,
            tax_percent: this.purchase.tax > 0 ? (this.purchase.tax / this.purchase.subtotal) * 100 : 16, // Calcula el porcentaje o usa 16 por defecto
        };
    },
    components: {
        Back,
        AppLayout,
        TextInput,
        InputError,
        FileUploader,
        FileView,
        PrimaryButton,
        SecondaryButton,
        SupplierFavoredProducts,
    },
    props: {
        purchase: Object,
        suppliers: Array,
    },
    mounted() {
        // Cargar datos del proveedor al iniciar el componente
        this.fetchSupplierDetails();
    },
    computed: {
        totals() {
            const hasUndefinedPrice = this.form.items.some(item => !item.unit_price || item.unit_price <= 0);
            const subtotal = this.form.items.reduce((acc, item) => acc + (Number(item.quantity) * (Number(item.unit_price) || 0)), 0);
            const molds = this.form.items.reduce((acc, item) => acc + (Number(item.mold_price) || 0), 0);
            const tax = subtotal * ((Number(this.tax_percent) || 0) / 100);
            const total = subtotal + molds + tax;
            
            return { subtotal, tax, molds, total, hasUndefinedPrice };
        },
        isEditing() {
            return this.editProductIndex !== null;
        },
        stockRemaining() {
            const totalStock = (this.productForm.plane_stock || 0) +
                               (this.productForm.ship_stock || 0) +
                               (this.productForm.additional_stock || 0);
            return (this.productForm.quantity || 0) - totalStock;
        },
    },
    methods: {
        update() {
            // Asigna los totales al formulario antes de enviarlo
            this.form.subtotal = this.totals.subtotal;
            this.form.tax = this.totals.tax;
            this.form.total = this.totals.total;

            // Prepara los IDs de los medios actuales para el backend
            this.form.current_media_ids = this.form.current_media.map(file => file.id);

            this.form.post(route("purchases.update", this.purchase.id), {
                onSuccess: () => {
                    ElMessage.success('Órden de compra actualizada');
                },
                onError: (errors) => {
                    console.log(errors);
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        async fetchSupplierDetails() {
            if (!this.form.supplier_id) return;
            
            try {
                const response = await axios.get(route('suppliers.get-details', { supplier: this.form.supplier_id }));
                const data = response.data;
                this.supplierContacts = data.contacts;
                this.supplierBankAccounts = data.bankAccounts;
                // CORRECCIÓN 1: Agregar ...product para traer los datos del pivot (precio)
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
        onProductSelect(productId) {
            const product = this.availableProducts.find(p => p.value === productId);
            if (product) {
                this.productForm.product_name = product.label;
                
                // CORRECCIÓN 2: Lógica para asignar precio del proveedor
                if (product.pivot && product.pivot.last_price) {
                    this.productForm.unit_price = parseFloat(product.pivot.last_price);
                } else {
                    // Si no tiene precio específico, usa el costo general
                    this.productForm.unit_price = product.cost ?? 0;
                }
            }
        },
        addProduct() {
            if (!this.productForm.product_id || !this.productForm.quantity || this.productForm.unit_price === null) {
                ElMessage.warning('Completa todos los campos del producto');
                return;
            }

            if (this.productForm.type === 'Venta' && this.stockRemaining !== 0) {
                ElMessage.error('La suma del stock (Avión, Barco, Favor) debe ser igual a la Cantidad total.');
                return;
            }

            const existingProductIndex = this.form.items.findIndex(item => item.product_id === this.productForm.product_id);
            if (existingProductIndex !== -1 && existingProductIndex !== this.editProductIndex) {
                 ElMessage.warning('Este producto ya ha sido agregado a la órden');
                return;
            }

            if (this.isEditing) {
                this.form.items[this.editProductIndex] = { ...this.productForm };
            } else {
                this.form.items.push({ ...this.productForm });
            }
            this.resetProductForm();
        },
        removeProduct(index) {
            if (this.editProductIndex === index) {
                this.cancelEdit();
            }
            this.form.items.splice(index, 1);
        },
        editProduct(index) {
            this.editProductIndex = index;
            // Clonación profunda para evitar reactividad no deseada
            this.productForm = this.form.items[index];
            this.productForm.additional_stock = parseInt(this.form.items[index].additional_stock) || 0;
            this.productForm.plane_stock = parseInt(this.form.items[index].plane_stock) || 0;
            this.productForm.ship_stock = parseInt(this.form.items[index].ship_stock) || 0;
            this.productForm.needs_mold = !! this.form.items[index].needs_mold;
            // this.productForm = JSON.parse(JSON.stringify(this.form.items[index]));
            this.$refs.formProduct.scrollIntoView({ behavior: 'smooth' });
        },
        cancelEdit() {
            this.editProductIndex = null;
            this.resetProductForm();
        },
        resetProductForm() {
            this.productForm = {
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_price: 0,
                additional_stock: 0,
                plane_stock: 0,
                ship_stock: 0,
                needs_mold: false,
                mold_price: null,
                type: 'Venta',
                notes: '',
            };
            this.editProductIndex = null;
        },
        deleteFile(fileId) {
            this.form.current_media = this.form.current_media.filter(file => file.id !== fileId);
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.000';

            return Number(value).toLocaleString('es-MX', { 
                style: 'currency', 
                currency: this.form.currency,
                minimumFractionDigits: 3,
                maximumFractionDigits: 3
            });
        }
    }
};
</script>