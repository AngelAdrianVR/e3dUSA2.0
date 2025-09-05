<template>
    <AppLayout title="Crear Orden de Compra">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('purchases.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crear nueva órden de compra
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="store">
                        <!-- SECCIÓN DE DATOS GENERALES -->
                        <section class="mb-8">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Datos Generales</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-4">
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="supplier">Proveedor*</label>
                                    <el-select @change="fetchSupplierDetails" v-model="form.supplier_id" filterable placeholder="Selecciona un proveedor" class="!w-full">
                                        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_id" />
                                </div>
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="contact">Contacto</label>
                                    <el-select v-model="form.supplier_contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" :disabled="!form.supplier_id">
                                        <el-option v-for="item in supplierContacts" :key="item.id" :label="item.name" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_contact_id" />
                                </div>
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="currency">Moneda*</label>
                                    <el-select v-model="form.currency" placeholder="Selecciona" class="!w-full">
                                        <el-option label="MXN" value="MXN" />
                                        <el-option label="USD" value="USD" />
                                    </el-select>
                                    <InputError :message="form.errors.currency" />
                                </div>
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="delivery_date">Fecha de entrega esperada*</label>
                                    <el-date-picker v-model="form.expected_delivery_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" :disabled-date="disabledDate" class="!w-full" />
                                    <InputError :message="form.errors.expected_delivery_date" />
                                </div>
                                <div class="col-span-1 md:col-span-2">
                                    <TextInput label="Notas" v-model="form.notes" type="text" :error="form.errors.notes" placeholder="Notas internas o para el proveedor" />
                                </div>
                            </div>
                        </section>

                        <!-- SECCIÓN DE PRODUCTOS -->
                        <section class="mb-6">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Productos</h3>
                            <div v-if="!form.supplier_id" class="text-center text-sm text-gray-500 bg-gray-100 dark:bg-slate-800 rounded-lg p-4">
                                <p>Por favor, selecciona un proveedor para poder agregar productos.</p>
                            </div>
                            <div v-else>
                                <!-- Formulario para agregar producto -->
                                <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg grid grid-cols-1 md:grid-cols-12 gap-x-3 gap-y-3 items-end">
                                    <div class="md:col-span-5">
                                        <label class="text-gray-700 dark:text-gray-100 text-sm ml-1">Producto*</label>
                                        <el-select-v2 v-model="productForm.product_id" filterable placeholder="Busca un producto" :options="availableProducts" @change="onProductSelect" class="!w-full" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <TextInput label="Cantidad*" v-model="productForm.quantity" type="numeric-stepper" placeholder="0" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <TextInput label="Precio Unitario*" v-model="productForm.unit_price" type="text" placeholder="0.00" :formatAsNumber="true">
                                            <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                        </TextInput>
                                    </div>
                                    <div class="md:col-span-3">
                                        <SecondaryButton @click="addProduct" type="button" :disabled="!productForm.product_id || !productForm.quantity > 0">
                                            <i class="fa-solid fa-plus mr-2"></i> Agregar
                                        </SecondaryButton>
                                    </div>
                                </div>

                                <!-- Tabla de productos agregados -->
                                <div class="mt-4 overflow-x-auto">
                                    <el-table :data="form.items" stripe class="!w-full">
                                        <el-table-column prop="product_name" label="Producto" />
                                        <el-table-column prop="quantity" label="Cantidad" width="120" />
                                        <el-table-column prop="unit_price" label="Precio Unitario" width="150">
                                            <template #default="scope">{{ formatCurrency(scope.row.unit_price) }}</template>
                                        </el-table-column>
                                        <el-table-column label="Total" width="150">
                                            <template #default="scope">{{ formatCurrency(scope.row.quantity * scope.row.unit_price) }}</template>
                                        </el-table-column>
                                        <el-table-column label="Acciones" width="100" align="right">
                                             <template #default="scope">
                                                <el-popconfirm confirm-button-text="Sí" cancel-button-text="No" icon-color="#D32F2F" title="¿Remover producto?" @confirm="removeProduct(scope.$index)">
                                                    <template #reference>
                                                        <button type="button" class="text-red-500 hover:text-red-700">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    </template>
                                                </el-popconfirm>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                    <InputError :message="form.errors.items" class="mt-2" />
                                </div>

                                <!-- Totales -->
                                <div class="mt-6 flex justify-end">
                                    <div class="w-full max-w-sm space-y-2 text-gray-700 dark:text-gray-300">
                                        <div class="flex justify-between"><span>Subtotal:</span> <span>{{ formatCurrency(totals.subtotal) }}</span></div>
                                        <div class="flex justify-between"><span>IVA (16%):</span> <span>{{ formatCurrency(totals.tax) }}</span></div>
                                        <div class="flex justify-between font-bold text-lg border-t pt-2"><span>Total:</span> <span>{{ formatCurrency(totals.total) }}</span></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <div class="flex justify-end mt-8 col-span-full">
                            <PrimaryButton :loading="form.processing" :disabled="form.items.length === 0">
                                Crear Órden de Compra
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    // Definición del componente en Options API
    data() {
        const form = useForm({
            supplier_id: null,
            supplier_contact_id: null,
            expected_delivery_date: null,
            currency: 'MXN',
            notes: null,
            items: [],
            subtotal: 0,
            tax: 0,
            total: 0,
        });

        const productForm = {
            product_id: null,
            product_name: '',
            quantity: 1,
            unit_price: 0,
        };

        return {
            form,
            productForm,
            supplierContacts: [],
            availableProducts: [],
        };
    },
    components: {
        AppLayout,
        PrimaryButton,
        SecondaryButton,
        InputError,
        TextInput,
        Back,
    },
    props: {
        suppliers: Array,
        products: Array,
    },
    computed: {
        totals() {
            const subtotal = this.form.items.reduce((acc, item) => acc + (item.quantity * item.unit_price), 0);
            const tax = subtotal * 0.16;
            const total = subtotal + tax;
            return { subtotal, tax, total };
        }
    },
    methods: {
        store() {
            // Asignar los totales calculados al formulario antes de enviar
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
        async fetchSupplierDetails() {
            if (!this.form.supplier_id) return;
            
            this.form.items = []; // Limpiar items si cambia el proveedor
            this.form.supplier_contact_id = null;
            this.productForm.product_id = null;
            
            try {
                const response = await axios.get(route('suppliers.get-details', { supplier: this.form.supplier_id }));
                this.supplierContacts = response.data.contacts;
                // Formatear productos para el-select-v2
                this.availableProducts = response.data.products.map(product => ({
                    value: product.id,
                    label: product.name,
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
                this.productForm.unit_price = product.cost ?? 0;
            }
        },
        addProduct() {
            if (!this.productForm.product_id || !this.productForm.quantity || this.productForm.unit_price === null) {
                ElMessage.warning('Completa todos los campos del producto');
                return;
            }

            // Verificar si el producto ya está en la lista
            const existingProduct = this.form.items.find(item => item.product_id === this.productForm.product_id);
            if (existingProduct) {
                ElMessage.warning('Este producto ya ha sido agregado a la órden');
                return;
            }

            this.form.items.push({ ...this.productForm });

            // Resetear el formulario de producto
            this.productForm = { product_id: null, product_name: '', quantity: 1, unit_price: 0 };
        },
        removeProduct(index) {
            this.form.items.splice(index, 1);
        },
        disabledDate(time) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return time.getTime() < today.getTime();
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            const num = Number(value);
            return num.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        },
    }
};
</script>
