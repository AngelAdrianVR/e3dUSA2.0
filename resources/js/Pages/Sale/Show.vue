<template>
    <AppLayout :title="`Detalles de la Órden #${sale.id}`">
        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
                    <Back :href="route('sales.index')" />
                    <h1 class="dark:text-white font-bold text-2xl my-2">
                        <span class="text-gray-500 dark:text-gray-400">Órden de {{ sale.type === 'venta' ? 'Venta' : 'Stock' }}:</span> {{ sale.type === 'venta' ? 'OV-' : 'OS-' }} {{sale.id.toString().padStart(4, '0')}}
                    </h1>
                </div>
                <el-select
                    @change="navigateToSale"
                    v-model="selectedSale"
                    filterable
                    placeholder="Buscar otra órden..."
                    class="!w-full"
                    no-data-text="No hay órdenes registradas"
                    no-match-text="No se encontraron coincidencias"
                    :loading="loadingSales"
                >
                    <el-option 
                        v-for="item in salesList" 
                        :key="item.id"
                        :label="item.name" 
                        :value="item.id" 
                    />
                </el-select>
            </div>
            
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip content="Imprimir Órden" placement="top">
                    <button @click="printOrder" class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <i class="fa-solid fa-print"></i>
                    </button>
                </el-tooltip>

                <el-tooltip content="Editar Órden" placement="top">
                    <Link :href="route('sales.edit', sale.id)">
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
                        <DropdownLink @click="authorize" v-if="sale.authorized_at === null" as="button">
                            <i class="fa-solid fa-check-double w-4 mr-2"></i> Marcar como Autorizada
                        </DropdownLink>
                        <DropdownLink @click="$inertia.visit(route('sales.create'))" as="button">
                            Crear nueva Órden
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar Órden
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('sales.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>


        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-6">
            <!-- === STEPPER DE ESTADO === -->
            <Stepper :currentStatus="sale.status" :steps="['Autorizada', 'En Proceso', 'Completada', 'Enviada']" />
                <!-- Card de Información de la Órden -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Órden</h3>
                    <ul class="space-y-3 text-sm">
                        <!-- Campos para Venta -->
                        <template v-if="sale.type === 'venta'">
                            <li class="flex justify-between items-center">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>

                                <!-- Tooltip Moderno -->
                                <el-tooltip placement="top-start" effect="light" raw-content>
                                <template #content>
                                    <div class="w-72 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                                    <!-- Header -->
                                    <div class="flex justify-between items-center border-b pb-2 mb-3">
                                        <h4 class="font-bold text-lg text-primary dark:text-sky-400">
                                        {{ sale.branch?.name }}
                                        </h4>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300">
                                        {{ sale.branch?.status ?? 'N/A' }}
                                        </span>
                                    </div>

                                    <!-- Datos principales -->
                                    <div class="space-y-1 text-gray-700 dark:text-gray-300">
                                        <p><strong class="font-semibold">RFC:</strong> {{ sale.branch?.rfc ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Dirección:</strong> {{ sale.branch?.address ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">C.P.:</strong> {{ sale.branch?.post_code ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Medio de contacto:</strong> {{ sale.branch?.meet_way ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Última compra:</strong> {{ sale.branch?.last_purchase_date ?? 'Sin registro' }}</p>
                                    </div>

                                    <!-- Footer -->
                                    <div class="mt-4 pt-2 border-t flex justify-between items-center">
                                        <Link :href="route('branches.show', sale.branch?.id)">
                                        <SecondaryButton class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                                            <i class="fa-solid fa-eye"></i> Ver Cliente
                                        </SecondaryButton>
                                        </Link>
                                        <span class="text-[10px] italic text-gray-400">Creado: {{ sale.branch?.created_at?.split('T')[0] }}</span>
                                    </div>
                                    </div>
                                </template>

                                <!-- Nombre clickable -->
                                <span class="text-blue-500 hover:underline cursor-pointer">
                                    {{ sale.branch?.name ?? 'N/A' }}
                                </span>
                                </el-tooltip>
                            </li>

                            <!-- Contacto -->
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                                <span>{{ sale.contact.name ?? 'N/A' }}</span>
                            </li>

                            <!-- Cotización -->
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cotización Rel.</span>
                                <span @click="$inertia.visit(route('quotes.show', sale.quote_id))" class="text-blue-500 hover:underline cursor-pointer">
                                COT-{{ sale.quote_id ?? 'N/A' }}
                                </span>
                            </li>
                            </template>


                        <!-- Campos Comunes -->
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">OCE:</span>
                            <span>{{ sale.oce_name ?? 'No especificado' }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Creado por:</span>
                            <span>{{ sale.user?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Creación:</span>
                            <span>{{ formattedDate }}</span>
                        </li>
                         <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Prioridad:</span>
                            <el-tag v-if="sale.is_high_priority" type="danger" size="small">Alta</el-tag>
                            <el-tag v-else type="info" size="small">Normal</el-tag>
                        </li>
                         <li v-if="sale.type === 'venta'" class="flex justify-between text-base font-bold">
                            <span class="text-gray-700 dark:text-gray-300">Monto Total:</span>
                            <span>${{ parseFloat(sale.total_amount)?.toFixed(2)?.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PRODUCTOS -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px] max-h-[75vh] overflow-auto">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Productos de la Órden</h3>
                    <!-- --- SECCIÓN DE PRODUCTOS --- -->
                    <div v-if="sale.sale_products?.length" class="space-y-4">
                        <ProductSaleCard 
                            v-for="product in sale.sale_products" 
                            :key="product.id"
                            :sale-product="product"
                            :is-high-priority="sale.is_high_priority"
                            :branch-id="sale.branch_id"
                        />
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-boxes-stacked text-3xl mb-3"></i>
                        <p>Esta órden no tiene productos registrados.</p>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ProductSaleCard from "@/Components/MyComponents/ProductSaleCard.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { ElMessage } from 'element-plus';
import { Link } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'SaleShow',
    components: {
        Link,
        Back,
        Stepper,
        Dropdown,
        AppLayout,
        ProductSaleCard,
        DropdownLink,
        SecondaryButton,
    },
    props: {
        sale: Object,
    },
    data() {
        return {
            selectedSale: this.sale.id,
            salesList: [],
            loadingSales: false,
        };
    },
    computed: {
        formattedDate() {
            if (!this.sale.created_at) return 'N/A';
            const date = new Date(this.sale.created_at);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        }
    },
    methods: {
        printOrder() {
            window.open(route('sales.print', this.sale.id), '_blank');
        },
        navigateToSale(saleId) {
            this.$inertia.visit(route('sales.show', saleId));
        },
        async authorize() {
            try {
                const response = await axios.put(route('sales.authorize', this.sale.id));
                if (response.status === 200) {
                    this.sale.authorized_at = response.data.authorized_at;
                    this.sale.status = 'Autorizada';
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar la venta');
                console.error(err);
            }
        },
        async fetchSalesList() {
            this.loadingSales = true;
            try {
                const response = await axios.get(route('sales.fetch-all'));
                this.salesList = response.data;
            } catch (error) {
                console.error('Error fetching sales list:', error);
            } finally {
                this.loadingSales = false;
            }
        }
    },
    mounted() {
        this.fetchSalesList();
    }
};
</script>