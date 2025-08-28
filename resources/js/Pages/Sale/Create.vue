<template>
    <AppLayout title="Crear Órden de Venta">
        <!-- Encabezado -->
        <div class="px-4 sm:px-0 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <Back :href="route('sales.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crear nueva órden de venta
                </h2>
            </div>
        </div>

        <!-- Formulario principal -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-3 md:p-9 relative">
                    <form @submit.prevent="store">
                        <!-- SECCIÓN 1: INFORMACIÓN GENERAL -->
                        <div class="flex justify-between items-center">
                            <el-divider content-position="left" class="flex-grow">
                                <span>Información General</span>
                            </el-divider>
                            <div v-if="form.branch_id" class="ml-4">
                                <SecondaryButton type="button" @click="showClientProductsDrawer = true">
                                    <i class="fa-solid fa-box-open mr-2"></i>
                                    Ver productos del cliente
                                </SecondaryButton>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                            <div class="col-span-full mb-5">
                                <el-radio-group v-model="form.type" size="small">
                                    <el-radio-button label="venta">Orden de Venta</el-radio-button>
                                    <el-radio-button label="stock">Orden de Stock</el-radio-button>
                                </el-radio-group>
                            </div>
                            
                            <div v-if="form.type === 'venta'">
                                <InputLabel value="Cotización relacionada (Opcional)" />
                                <el-select v-model="form.quote_id" filterable clearable placeholder="Selecciona una cotización" class="!w-full">
                                    <el-option v-for="quote in quotes" :key="quote.id" :label="`C-${quote.id} - ${quote.branch?.name}`" :value="quote.id" />
                                </el-select>
                            </div>

                            <div>
                                <InputLabel value="Cliente*" />
                                <el-select v-model="form.branch_id" filterable placeholder="Selecciona un cliente" class="!w-full" @change="handleBranchChange">
                                    <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                </el-select>
                                <InputError :message="form.errors.branch_id" />
                            </div>

                             <div>
                                <InputLabel value="Contacto*" />
                                <el-select v-model="form.contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" no-data-text="Selecciona un cliente primero">
                                    <el-option v-for="contact in availableContacts" :key="contact.id" :label="`${contact.name} (${contact.email})`" :value="contact.id" />
                                </el-select>
                                <InputError :message="form.errors.contact_id" />
                            </div>
                        </div>

                        <!-- COMPONENTE HIJO PARA PRODUCTOS -->
                        <SaleProductManager 
                            v-model="form.products"
                            :branch-id="form.branch_id"
                            :client-products="clientProducts"
                            :products-error="form.errors.products"
                        />
                        
                        <!-- SECCIÓN 3: LOGÍSTICA Y DETALLES -->
                        <el-divider content-position="left" class="!mt-8">
                            <span>Logística y Detalles de la Orden</span>
                        </el-divider>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                            <TextInput label="Fecha promesa" :error="form.errors.promise_date" v-model="form.promise_date" type="date" />
                            <TextInput label="OCE (Orden Compra Externa)" :error="form.errors.oce_name" v-model="form.oce_name" />
                             <div>
                                <InputLabel value="Medio de petición*" />
                                <el-select v-model="form.order_via" placeholder="Medio de petición *">
                                    <el-option v-for="item in orderVias" :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.order_via" />
                            </div>
                            <!-- <TextInput label="Medio de Petición" :error="form.errors.order_via" v-model="form.order_via" /> -->
                            
                            <div>
                                <InputLabel value="Opción de Flete" />
                                <el-select v-model="form.freight_option" placeholder="Selecciona el flete" class="!w-full">
                                    <el-option label="Por cuenta del cliente" value="Por cuenta del cliente" />
                                    <el-option label="Cargo prorrateado en productos" value="Cargo de flete prorrateado en productos" />
                                    <el-option label="La empresa absorbe el costo" value="La empresa absorbe el costo de flete" />
                                    <el-option label="El cliente manda la guia" value="El cliente manda la guia" />
                                </el-select>
                            </div>
                            <TextInput v-if="form.freight_option !== 'El cliente manda la guia'" label="Costo de Flete*" v-model="form.freight_cost" type="number" :formatAsNumber="true">
                                <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                            </TextInput>
                            <div></div> <!-- Espaciador -->

                            <div class="col-span-full">
                                <TextInput label="Notas generales" v-model="form.notes" :isTextarea="true" />
                            </div>
                            <label class="flex items-center">
                                <Checkbox v-model:checked="form.is_high_priority" class="bg-transparent border-gray-500" />
                                <span class="ml-2 text-sm text-gray-500 dark:text-gray-300">Prioridad Alta</span>
                            </label>
                        </div>

                        <!-- Botón de envío -->
                        <div class="flex justify-end mt-8 col-span-full">
                            <PrimaryButton :loading="form.processing" :disabled="!form.products.length">
                                Crear Órden de Venta
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- COMPONENTE HIJO PARA DRAWER -->
        <ClientProductsDrawer 
            :show="showClientProductsDrawer"
            @update:show="showClientProductsDrawer = $event"
            :branch-id="form.branch_id"
            @products-loaded="clientProducts = $event"
            :branches="branches"
            :catalog_products="catalog_products"
        />
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Checkbox from "@/Components/Checkbox.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SaleProductManager from "@/Pages/Sale/Components/SaleProductManager.vue"; // <-- IMPORTADO
import ClientProductsDrawer from "@/Pages/Sale/Components/ClientProductsDrawer.vue"; // <-- IMPORTADO
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";

export default {
    components: {
        Back,
        Checkbox,
        TextInput,
        AppLayout,
        InputError,
        InputLabel,
        PrimaryButton,
        SecondaryButton,
        SaleProductManager,     // <-- REGISTRADO
        ClientProductsDrawer,   // <-- REGISTRADO
    },
    props: {
        branches: Array,
        quotes: Array,
        catalog_products: Array,
    },
    data() {
        return {
            form: useForm({
                branch_id: null,
                quote_id: null,
                contact_id: null,
                type: 'venta',
                promise_date: null,
                oce_name: '',
                order_via: '',
                freight_option: 'Por cuenta del cliente',
                freight_cost: 0,
                notes: '',
                is_high_priority: false,
                products: [],
            }),
            availableContacts: [],
            clientProducts: [],
            showClientProductsDrawer: false,
            orderVias: [
                'Correo electrónico',
                'WhatsApp',
                'Llamada telefónica',
                'Resurtido programado',
                'Otro',
            ],
        };
    },
    methods: {
        store() {
            this.form.post(route("sales.store"), {
                onSuccess: () => {
                    ElMessage.success('Órden de venta creada correctamente');
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario.');
                }
            });
        },
        handleBranchChange(branchId) {
            this.form.contact_id = null;
            this.form.products = [];
            
            const selectedBranch = this.branches.find(b => b.id === branchId);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];

            this.fetchClientProducts();
        },
        async fetchClientProducts() {
            if (!this.form.branch_id) return;
            this.clientProducts = [];
            try {
                const response = await axios.get(route('branches.fetch-products', this.form.branch_id));
                this.clientProducts = response.data;
                this.$emit('products-loaded', this.clientProducts); // Emitir productos al padre
            } catch (error) {
                console.error("Error fetching client products:", error);
                ElMessage.error('No se pudieron cargar los productos del cliente.');
            }
        },
    },
};
</script>
