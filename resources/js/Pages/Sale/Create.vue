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
import SaleProductManager from "@/Pages/Sale/Components/SaleProductManager.vue";
import ClientProductsDrawer from "@/Pages/Sale/Components/ClientProductsDrawer.vue";
import { ElMessage, ElMessageBox } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import { h } from 'vue'; // <-- NUEVO: Importar 'h' para crear VNodes

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
        SaleProductManager,
        ClientProductsDrawer,
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
    watch: {
        'form.quote_id'(newQuoteId) {
            if (newQuoteId) {
                this.fetchQuoteDetails(newQuoteId);
            } else {
                this.form.reset('branch_id', 'contact_id', 'freight_option', 'freight_cost', 'notes', 'products');
                this.availableContacts = [];
                this.clientProducts = [];
            }
        }
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
        async handleBranchChange(branchId) {
            this.form.contact_id = null;
            
            const selectedBranch = this.branches.find(b => b.id === branchId);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];

            await this.fetchClientProducts();
        },
        async fetchClientProducts() {
            if (!this.form.branch_id) return;
            this.clientProducts = [];
            try {
                const response = await axios.get(route('branches.fetch-products', this.form.branch_id));
                this.clientProducts = response.data;
                this.$emit('products-loaded', this.clientProducts);
            } catch (error) {
                console.error("Error fetching client products:", error);
                ElMessage.error('No se pudieron cargar los productos del cliente.');
            }
        },
        async fetchQuoteDetails(quoteId) {
            try {
                const response = await axios.get(route('quotes.details-for-sale', quoteId));
                const quoteData = response.data;

                this.form.branch_id = quoteData.branch_id;
                this.form.freight_option = quoteData.freight_option;
                this.form.freight_cost = quoteData.freight_cost;
                this.form.notes = quoteData.notes;

                await this.handleBranchChange(quoteData.branch_id);

                this.form.contact_id = quoteData.contact_id;

                this.processQuoteProducts(quoteData.products);

            } catch (error) {
                console.error("Error fetching quote details:", error);
                ElMessage.error('No se pudo cargar la información de la cotización.');
            }
        },
        async processQuoteProducts(quoteProducts) {
            this.form.products = [];
            const clientProductIds = new Set(this.clientProducts.map(p => p.id));

            for (const product of quoteProducts) {
                if (clientProductIds.has(product.id)) {
                    this.addProductToSaleForm(product);
                } else {
                    try {
                        // MODIFICADO: Usamos VNodes para mostrar la imagen y el texto
                        await ElMessageBox.confirm(
                           '', // El mensaje ahora se pasa como VNode en las opciones
                           {
                                title: 'Producto no asignado',
                                message: h('div', { class: 'flex flex-col items-center text-center' }, [ // Diseño en columna y centrado
                                    h('img', {
                                        src: product.image_url || 'https://placehold.co/200x200/e2e8f0/e2e8f0?text=N/A', // Imagen de fallback
                                        class: 'w-48 h-48 rounded-lg object-cover border mb-4', // Imagen más grande y con margen inferior
                                        alt: product.name
                                    }),
                                    h('p', null, `El producto "${product.name}" (P/N: ${product.part_number}) no está asignado a este cliente. ¿Deseas asignarlo y agregarlo a la orden de venta?`)
                                ]),
                                confirmButtonText: 'Sí, asignar y agregar',
                                cancelButtonText: 'No, omitir',
                                type: 'warning',
                           }
                        );
                        await this.associateAndAddProduct(product);
                    } catch (action) {
                        ElMessage.info(`Se omitió el producto: "${product.name}"`);
                    }
                }
            }
        },
        
        async associateAndAddProduct(product) {
            try {
                const payload = {
                    products: [
                        {
                            product_id: product.id,
                            price: null 
                        }
                    ]
                };

                await axios.post(route('branches.add-products', this.form.branch_id), payload);
                
                ElMessage.success(`Producto "${product.name}" asociado al cliente.`);
                
                await this.fetchClientProducts();

                this.addProductToSaleForm(product);

            } catch (error) {
                console.error("Error associating product:", error);
                ElMessage.error(`No se pudo asociar el producto "${product.name}".`);
            }
        },
        
        addProductToSaleForm(product) {
            this.form.products.push({
                id: product.id,
                quantity: product.quantity,
                price: product.unit_price,
                notes: product.notes,
                is_new_design: false,
            });
        }
    },
};
</script>
