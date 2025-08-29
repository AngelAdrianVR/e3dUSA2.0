<template>
    <AppLayout :title="form.type === 'venta' ? 'Editar Órden de Venta' : 'Editar Órden de Stock'">
        <!-- Encabezado -->
        <div class="px-4 sm:px-0 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <Back :href="route('sales.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ form.type === 'venta' ? `Editar órden de venta #${sale.id}` : `Editar órden de stock #${sale.id}` }}
                </h2>
            </div>
        </div>

        <!-- Formulario principal -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-3 md:p-9 relative">
                    <form @submit.prevent="update">
                        <!-- SECCIÓN 1: INFORMACIÓN GENERAL -->
                        <div class="flex justify-between items-center">
                            <el-divider content-position="left" class="flex-grow">
                                <span>Información General</span>
                            </el-divider>
                            <div v-if="form.type === 'venta' && form.branch_id" class="ml-4">
                                <SecondaryButton type="button" @click="showClientProductsDrawer = true">
                                    <i class="fa-solid fa-box-open mr-2"></i>
                                    Ver productos del cliente
                                </SecondaryButton>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                            <div class="col-span-full mb-5">
                                <InputLabel value="Tipo de órden*" />
                                <el-radio-group v-model="form.type" size="small">
                                    <el-radio-button label="venta">Orden de Venta</el-radio-button>
                                    <el-radio-button label="stock">Orden de Stock</el-radio-button>
                                </el-radio-group>
                            </div>
                            
                            <!-- Campos exclusivos para 'venta' -->
                            <template v-if="form.type === 'venta'">
                                <div>
                                    <InputLabel value="Cotización relacionada (Opcional)" />
                                    <el-select v-model="form.quote_id" filterable clearable placeholder="Selecciona una cotización" class="!w-full">
                                        <el-option v-for="quote in quotes" :key="quote.id" :label="`COT-${quote.id} - ${quote.branch?.name}`" :value="quote.id" />
                                    </el-select>
                                </div>

                                <div>
                                    <InputLabel value="Cliente*" />
                                    <el-select v-model="form.branch_id" :disabled="form.quote_id ? true : false" filterable placeholder="Selecciona un cliente" class="!w-full" @change="handleBranchChange">
                                        <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                    </el-select>
                                    <InputError :message="form.errors.branch_id" />
                                </div>

                                 <div>
                                    <InputLabel value="Contacto*" />
                                    <el-select v-model="form.contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" no-data-text="Selecciona un cliente primero">
                                        <el-option v-for="contact in availableContacts" :key="contact.id" :label="`${contact.name} (${contact.charge})`" :value="contact.id" />
                                    </el-select>
                                    <InputError :message="form.errors.contact_id" />
                                </div>
                            </template>
                        </div>

                        <!-- COMPONENTE HIJO PARA PRODUCTOS -->
                        <SaleProductManager 
                            v-model="form.products"
                            :branch-id="form.branch_id"
                            :sale-type="form.type"
                            :available-products="productsForManager"
                            :products-error="form.errors.products"
                        />
                        
                        <!-- SECCIÓN 3: LOGÍSTICA (SOLO PARA VENTA) -->
                        <template v-if="form.type === 'venta'">
                            <el-divider content-position="left" class="!mt-8">
                                <span>Logística de la Orden</span>
                            </el-divider>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                                <div>
                                    <InputLabel value="Opciones de envío*" />
                                    <el-select v-model="form.shipping_option"
                                        placeholder="Selecciona">
                                        <el-option v-for="item in shippingOptions" :key="item" :label="item"
                                            :value="item" />
                                    </el-select>
                                    <InputError :message="form.errors.shipping_option" />
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
                                <TextInput v-if="form.freight_option !== 'El cliente manda la guia'" label="Costo de Flete*" :error="form.errors.freight_option" v-model="form.freight_cost" type="number" :formatAsNumber="true">
                                    <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                </TextInput>

                                <!-- ENVÍOS / PARCIALIDADES -->
                                <div v-if="form.products.length" class="col-span-full">
                                    <div v-if="!form.shipping_option" class="text-sm text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-slate-800 p-3 rounded-lg">
                                        <p>Por favor, selecciona una opción de envío para configurar las parcialidades.</p>
                                    </div>
                                    <div v-else class="space-y-6">
                                        <div v-for="(shipment, s_index) in form.shipments" :key="s_index" class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border dark:border-slate-700">
                                            <h3 class="font-bold text-lg mb-3 text-gray-800 dark:text-gray-200">Parcialidad {{ s_index + 1 }}</h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                                                <div>
                                                    <InputLabel value="Fecha promesa de embarque" />
                                                    <el-date-picker
                                                        v-model="shipment.promise_date"
                                                        type="date"
                                                        placeholder="Selecciona una fecha"
                                                        format="YYYY-MM-DD"
                                                        value-format="YYYY-MM-DD"
                                                        :disabled-date="disabledBeforeToday"
                                                    />
                                                    <div v-if="form.errors[`shipments.${s_index}.promise_date`]" class="text-red-500 text-sm mt-1">
                                                    {{ form.errors[`shipments.${s_index}.promise_date`] }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <InputLabel value="Paquetería" />
                                                    <el-select v-model="shipment.shipping_company" filterable clearable placeholder="Selecciona" class="!w-full">
                                                        <el-option v-for="company in shippingCompanies" :key="company" :label="company" :value="company" />
                                                    </el-select>
                                                    <InputError :message="form.errors[`shipments.${s_index}.shipping_company`]" />
                                                </div>
                                                <TextInput label="Guía de rastreo" :error="form.errors[`shipments.${s_index}.tracking_guide`]" v-model="shipment.tracking_guide" />
                                                <div class="col-span-full my-2">
                                                    <InputLabel :value="`Acuse de envío para parcialidad ${s_index + 1}`" />
                                                    <FileUploader @files-selected="shipment.acknowledgement_file = $event[0]" :multiple="false" acceptedFormat="Todo" />
                                                    <InputError :message="form.errors[`shipments.${s_index}.acknowledgement_file`]" />
                                                </div>
                                            </div>
                                            <!-- Productos de la parcialidad -->
                                            <div class="mt-4">
                                                <h4 class="font-semibold text-md mb-2 text-gray-700 dark:text-gray-300">Productos en esta parcialidad</h4>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="text-left text-gray-600 dark:text-gray-400">
                                                                <th class="font-semibold p-2">Producto</th>
                                                                <th class="font-semibold p-2">Total en Venta</th>
                                                                <th class="font-semibold p-2">Cantidad en este envío</th>
                                                                <th class="font-semibold p-2">Restantes por asignar</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(product, p_index) in shipment.products" :key="p_index" class="border-t dark:border-slate-700">
                                                                <td class="p-2">
                                                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ product.name }}</p>
                                                                </td>
                                                                <td class="p-2 text-center dark:text-white">{{ getProductTotalQuantity(product.product_id) }}</td>
                                                                <td class="p-2">
                                                                    <el-input-number 
                                                                        v-model="product.quantity" 
                                                                        :min="0" 
                                                                        :max="getMaxShippableQuantity(product.product_id, s_index)" 
                                                                        :disabled="form.shipments.length === 1"
                                                                        size="small" 
                                                                        controls-position="right" 
                                                                        class="!w-28" />
                                                                </td>
                                                                <td class="p-2 text-center" :class="getRemainingQuantity(product.product_id) < 0 ? 'text-red-500 font-bold' : 'text-green-600'">
                                                                    {{ getRemainingQuantity(product.product_id) }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- SECCIÓN 4: DETALLES GENERALES DE LA ORDEN -->
                        <el-divider content-position="left" class="!mt-8 col-span-full">
                            <span>Detalles generales</span>
                        </el-divider>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                            <TextInput v-if="form.type === 'venta'" label="OCE (Orden Compra Externa)" :error="form.errors.oce_name" v-model="form.oce_name" />
                            
                            <div v-if="form.type === 'venta'">
                                <InputLabel value="Medio de petición*" />
                                <el-select v-model="form.order_via" placeholder="Medio de petición *">
                                    <el-option v-for="item in orderVias" :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.order_via" />
                            </div>

                            <!-- Archivos de OCE -->
                            <div v-if="form.type === 'venta'" class="col-span-full my-2">
                                <InputLabel value="Archivos de OCE (máx. 3 archivos)" />
                                <FileUploader @files-selected="form.oce_media = $event" :multiple="true" acceptedFormat="Todo" :max-files="3" />
                            </div>
                            <div></div> <!-- Espaciador -->

                            <div class="col-span-full">
                                <TextInput label="Notas generales" v-model="form.notes" :error="form.errors.notes" :isTextarea="true" />
                            </div>
                            
                            <label v-if="form.type === 'venta'" class="flex items-center">
                                <Checkbox v-model:checked="form.is_high_priority" class="bg-transparent border-gray-500" />
                                <span class="ml-2 text-sm text-gray-500 dark:text-gray-300">Prioridad Alta</span>
                            </label>
                        </div>

                        <!-- Botón de envío -->
                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing" :disabled="!form.products.length">
                                Crear Órden
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- COMPONENTE HIJO PARA DRAWER (SOLO PARA VENTA) -->
        <ClientProductsDrawer v-if="form.type === 'venta'"
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
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import SaleProductManager from "@/Pages/Sale/Components/SaleProductManager.vue";
import ClientProductsDrawer from "@/Pages/Sale/Components/ClientProductsDrawer.vue";
import { ElMessage, ElMessageBox } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import { h } from 'vue';

export default {
    components: {
        Back,
        Checkbox,
        TextInput,
        AppLayout,
        InputError,
        InputLabel,
        FileUploader,
        PrimaryButton,
        SecondaryButton,
        SaleProductManager,
        ClientProductsDrawer,
    },
    props: {
        sale: Object,
        branches: Array,
        quotes: Array,
        catalog_products: Array,
    },
    data() {
        return {
            form: useForm({
                _method: 'put',
                type: this.sale.type,
                branch_id: this.sale.branch_id,
                contact_id: this.sale.contact_id,
                quote_id: this.sale.quote_id,
                oce_name: this.sale.oce_name,
                order_via: this.sale.order_via,
                freight_option: this.sale.freight_option,
                freight_cost: this.sale.freight_cost,
                notes: this.sale.notes,
                is_high_priority: this.sale.is_high_priority,
                products: this.sale.sale_products.map(p => ({
                    id: p.product_id,
                    quantity: p.quantity,
                    price: p.price,
                    notes: p.notes,
                    customization_details: p.customization_details || [],
                    is_new_design: false,
                })),
                shipping_option: null, // Deberás cargar esto si lo guardas en la BD
                shipments: [], // Cargar y mapear los shipments existentes si es necesario
            }),
            availableContacts: [],
            clientProducts: [],
            showClientProductsDrawer: false,
            orderVias: ['Correo electrónico', 'WhatsApp', 'Llamada telefónica', 'Resurtido programado', 'Otro'],
            shippingOptions: ['Entrega única', '2 parcialidades', '3 parcialidades', '4 parcialidades'],
        };
    },
    computed: {
        productsForManager() {
            // Devuelve los productos de cliente para 'venta' o todo el catálogo para 'stock'
            return this.form.type === 'venta' ? this.clientProducts : this.catalog_products;
        }
    },
    watch: {
        'form.type'(newType) {
            // Limpia el formulario al cambiar de tipo para evitar enviar datos incorrectos
            if (newType === 'stock') {
                this.form.reset(
                    'branch_id', 'quote_id', 'contact_id', 'order_via', 
                    'freight_option', 'freight_cost', 'shipping_option', 'products', 'shipments'
                );
                this.availableContacts = [];
                this.clientProducts = [];
            } else {
                 this.form.reset('products');
            }
        },
        'form.quote_id'(newQuoteId) {
            if (newQuoteId) {
                this.fetchQuoteDetails(newQuoteId);
            } else if (this.form.type === 'venta') {
                this.form.reset('branch_id', 'contact_id', 'freight_option', 'freight_cost', 'notes', 'products');
                this.availableContacts = [];
                this.clientProducts = [];
            }
        },
        'form.shipping_option'(newValue) {
            this.generateShipmentPartials(newValue);
        },
        'form.products': {
            handler() {
                if (this.form.type === 'venta') {
                    this.generateShipmentPartials(this.form.shipping_option);
                }
            },
            deep: true
        }
    },
    methods: {
        update() {
            // Lógica de validación de parcialidades (si aplica)
            this.form.post(route("sales.update", this.sale.id), {
                onSuccess: () => {
                    ElMessage.success('Órden actualizada correctamente');
                },
                onError: (errors) => {
                    console.error(errors);
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario.');
                }
            });
        },
        generateShipmentPartials(option) {
            if (!option || !this.form.products.length || this.form.type !== 'venta') {
                this.form.shipments = [];
                return;
            }

            const count = parseInt(option.split(' ')[0]) || 1;
            const newShipments = [];

            for (let i = 0; i < count; i++) {
                const existingShipment = this.form.shipments[i] || {};
                
                newShipments.push({
                    promise_date: existingShipment.promise_date || null,
                    shipping_company: existingShipment.shipping_company || null,
                    tracking_guide: existingShipment.tracking_guide || '',
                    acknowledgement_file: existingShipment.acknowledgement_file || null,
                    products: this.form.products.map(p => {
                        const existingProduct = existingShipment.products?.find(sp => sp.product_id === p.id);
                        const productInfo = this.getProductInfo(p.id);
                        
                        let quantity = 0;
                        if (count === 1) {
                            quantity = p.quantity;
                        } else {
                            quantity = existingProduct?.quantity || 0;
                        }

                        return {
                            product_id: p.id,
                            quantity: quantity,
                            name: productInfo?.name,
                            part_number: productInfo?.part_number,
                        };
                    })
                });
            }
            this.form.shipments = newShipments;
        },
        getProductInfo(productId) {
            const source = this.form.type === 'venta' ? this.clientProducts : this.catalog_products;
            return source.find(p => p.id === productId);
        },
        getProductTotalQuantity(productId) {
            const productInSale = this.form.products.find(p => p.id === productId);
            return productInSale ? productInSale.quantity : 0;
        },
        getRemainingQuantity(productId) {
            const totalQuantity = this.getProductTotalQuantity(productId);
            const assignedQuantity = this.form.shipments.reduce((sum, shipment) => {
                const productInShipment = shipment.products.find(p => p.product_id === productId);
                return sum + (productInShipment ? productInShipment.quantity : 0);
            }, 0);
            return totalQuantity - assignedQuantity;
        },
        getMaxShippableQuantity(productId, currentShipmentIndex) {
            const totalQuantity = this.getProductTotalQuantity(productId);
            let assignedInOtherShipments = 0;

            this.form.shipments.forEach((shipment, s_index) => {
                if (s_index !== currentShipmentIndex) {
                    const productInShipment = shipment.products.find(p => p.product_id === productId);
                    if (productInShipment) {
                        assignedInOtherShipments += productInShipment.quantity;
                    }
                }
            });

            return totalQuantity - assignedInOtherShipments;
        },
        disabledBeforeToday(date) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return date < today;
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
                        await ElMessageBox.confirm(
                           '',
                           {
                                title: 'Producto no asignado',
                                message: h('div', { class: 'flex flex-col items-center text-center' }, [
                                    h('img', {
                                        src: product.image_url || 'https://placehold.co/200x200/e2e8f0/e2e8f0?text=N/A',
                                        class: 'w-48 h-48 rounded-lg object-cover border mb-4',
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
                    products: [{ product_id: product.id, price: null }]
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
                customization_details: product.customization_details || [],
                is_new_design: false,
            });
        }
    },
    async mounted() {
        if (this.form.type === 'venta' && this.form.branch_id) {
            const selectedBranch = this.branches.find(b => b.id === this.form.branch_id);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];
            await this.fetchClientProducts();
        }
    }
};
</script>
