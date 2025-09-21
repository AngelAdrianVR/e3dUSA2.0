<template>
    <AppLayout
        :title="`Detalles de la Órden ${sale.type === 'venta' ? 'OV-' : 'OS-'}${sale.id.toString().padStart(4, '0')}`"
    >
        <!-- Panel Flotante de Notas -->
        <BranchNotes v-if="sale.branch?.id" :branch-id="sale.branch?.id" />

        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
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
                <el-tooltip v-if="sale.authorized_at === null && $page.props.auth.user.permissions.includes('Autorizar ordenes de venta')" content="Autorizar Órden" placement="top">
                    <button @click="authorize" class="size-9 flex items-center justify-center rounded-lg bg-green-300 hover:bg-green-400 dark:bg-green-800 dark:hover:bg-green-700 transition-colors">
                        <i class="fa-solid fa-check-double"></i>
                    </button>
                </el-tooltip>

                <el-tooltip v-if="sale.status === 'Preparando Envío'" content="Ver detalles de envío" placement="top">
                    <button @click="$inertia.visit(route('shipments.show', sale.id))" class="size-9 flex items-center justify-center rounded-lg bg-blue-300 hover:bg-blue-400 dark:bg-blue-800 dark:hover:bg-blue-700 transition-colors">
                        <i class="fa-solid fa-truck-fast"></i>
                    </button>
                </el-tooltip>

                <el-tooltip content="Imprimir Órden" placement="top">
                    <button @click="printOrder" class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                        </svg>
                    </button>
                </el-tooltip>

                <el-tooltip v-if="$page.props.auth.user.permissions.includes('Editar ordenes de venta')" :content="sale.authorized_at ? 'No puedes editarla una vez autorizada' : 'Editar Órden'" placement="top">
                    <Link :href="sale.authorized_at ? '' : route('sales.edit', sale.id)">
                        <button :disabled="sale.authorized_at" 
                            class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                            <i class="fa-solid fa-pencil text-sm"></i>
                        </button>
                    </Link>
                </el-tooltip>
                
                <Dropdown v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta') || $page.props.auth.user.permissions.includes('Eliminar ordenes de venta')" align="right" width="48">
                    <template #trigger>
                        <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta')" @click="$inertia.visit(route('sales.create'))" as="button">
                           <i class="fa-solid fa-plus w-4 mr-2"></i> Crear nueva Órden
                        </DropdownLink>
                        <DropdownLink v-if="sale?.sale_products?.some(item => item.product?.code.includes('EM'))" as="button">
                            <a class="inline-block" :href="route('sales.quality-certificate', sale.id)" target="_blank">
                                <p>Ver certificado de calidad</p>
                            </a>
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Eliminar ordenes de venta')" @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar Órden
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('sales.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>


        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-3 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-4">
            <!-- === STEPPER DE ESTADO === -->
            <Stepper :currentStatus="sale.status" :steps="sale.type === 'venta' ? saleSteps : stockSteps" />
                <!-- Card de Información de la Órden -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Órden</h3>
                    <ul class="space-y-3 text-sm">
                        <!-- Campos para Venta -->
                        <template v-if="sale.type === 'venta'">
                            <li class="flex justify-between items-center">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>

                                <!-- Tooltip de cliente -->
                                <el-tooltip v-if="sale.branch" placement="top-start" effect="light" raw-content>
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
                                            <p><strong class="font-semibold">Última compra:</strong> {{ formatRelative(sale.branch?.last_purchase_date) }}</p>
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
                                <span v-else class="font-semibold text-gray-600 dark:text-gray-400">N/A</span>
                            </li>

                            <!-- Contacto -->
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                                
                                <el-tooltip
                                    v-if="sale.contact"
                                    placement="right"
                                    effect="dark"
                                >
                                    <template #content>
                                    <div class="space-y-2 text-sm">
                                        <p v-if="getPrimaryDetail(sale.contact, 'Correo')" class="flex items-center gap-2">
                                        <i class="fa-solid fa-envelope text-blue-400"></i>
                                        <span>{{ getPrimaryDetail(sale.contact, 'Correo') }}</span>
                                        </p>
                                        <p v-if="getPrimaryDetail(sale.contact, 'Teléfono')" class="flex items-center gap-2">
                                        <i class="fa-solid fa-phone text-green-400"></i>
                                        <span>{{ getPrimaryDetail(sale.contact, 'Teléfono') }}</span>
                                        </p>
                                    </div>
                                    </template>

                                    <span
                                    class="text-blue-500 font-medium hover:underline cursor-default transition-colors duration-200"
                                    >
                                    {{ sale.contact?.name ?? 'N/A' }}
                                    </span>
                                </el-tooltip>

                                <span v-else class="text-gray-400 italic">N/A</span>
                            </li>

                            <!-- Cotización -->
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cotización Rel.</span>
                                <span v-if="sale.quote_id" @click="$inertia.visit(route('quotes.show', sale.quote_id))" class="text-blue-500 hover:underline cursor-pointer">
                                    COT-{{ sale.quote_id?.toString().padStart(4, '0') }}
                                </span>
                                <span v-else>N/A</span>
                            </li>
                        </template>

                        <!-- Campos Comunes -->
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Tipo:</span>
                            <span>{{ sale.type === 'venta' ? 'Venta' : 'Stock' }}</span>
                        </li>
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
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Notas generales:</span>
                            <span>{{ sale.notes ?? '-' }}</span>
                        </li>
                         <li v-if="sale.type === 'venta'" class="flex justify-between text-base font-bold">
                            <span class="text-gray-700 dark:text-gray-300">Monto Total:</span>
                            <span>${{ parseFloat(sale.total_amount)?.toFixed(2)?.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Resumen de Producción -->
                <div v-if="sale.production_summary && sale.production_summary.total_productions > 0"
                    class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4">
                    <div class="flex justify-between items-center border-b dark:border-gray-600 pb-2 mb-3">
                        <h3 class="text-lg font-semibold">Resumen de Producción</h3>
                        <!-- Estadísticas Detalladas -->
                        <div class="grid grid-cols-2 text-center text-sm">
                            <div>
                                <p class="font-bold text-lg">{{ sale.production_summary.completed_productions }} / {{ sale.production_summary.total_productions }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">Productos</p>
                            </div>
                            <div>
                                <p class="font-bold text-lg">{{ sale.production_summary.completed_tasks }} / {{ sale.production_summary.total_tasks }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">Tareas</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <!-- Estado General -->
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estado:</span>
                            <span class="font-bold px-2 py-1 rounded-md text-xs"
                                :class="{
                                    'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300': sale.production_summary.status === 'Terminada',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300': sale.production_summary.status === 'En Proceso',
                                    'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300': sale.production_summary.status === 'Sin material',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300': sale.production_summary.status === 'Pendiente',
                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300': sale.production_summary.status === 'Pausada',
                                }">
                                {{ sale.production_summary.status }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-semibold text-amber-600 dark:text-amber-400">Inicio:</span>
                            <span class="font-bold px-2 py-1 rounded-md text-xs">
                                {{ sale.production_summary.started_at ? formatDateTime(sale.production_summary.started_at) : 'No iniciada' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-semibold text-amber-600 dark:text-amber-400">Fin:</span>
                            <span v-if="['Preparando Envío', 'Terminada'].includes(sale.status)" class="font-bold px-2 rounded-md text-xs">
                                {{ sale.production_summary.finished_at ? formatDateTime(sale.production_summary.finished_at) : 'No finalizada' }}
                            </span>
                            <span class="text-xs" v-else>No finalizada</span>
                        </div>

                        <!-- Barra de Progreso Futurista -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Progreso General</span>
                                <span class="text-xs font-bold text-green-500">{{ sale.production_summary.percentage }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-slate-700">
                                <div class="bg-gradient-to-r from-emerald-600 to-green-500 h-3 rounded-full transition-all duration-500 ease-out"
                                    :style="{ width: sale.production_summary.percentage + '%' }">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Card de Archivos adjuntos de Órden -->
                <div v-if="sale.media?.length" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Archivos de la Órden</h3>

                    <div v-if="sale.media?.length" label="Archivos adjuntos" class="grid grid-cols-2 gap-3 col-span-full mb-3">
                        <FileView v-for="file in sale.media" :key="file" :file="file" :deletable="true"
                            @delete-file="deleteFile($event)" />
                    </div>

                    <Empty v-else />
                </div>
            </div>

            <!-- COLUMNA DERECHA: PRODUCTOS -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px]">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Productos de la Órden</h3>
                    <!-- --- SECCIÓN DE PRODUCTOS --- -->
                    <div v-if="sale.sale_products?.length" class="space-y-3 max-h-[65vh] overflow-auto">
                        <ProductSaleCard 
                            v-for="product in sale.sale_products" 
                            :key="product.id"
                            :sale-product="product"
                            :is-high-priority="sale.is_high_priority"
                            :branch-id="sale.branch_id"
                            :saleCurrency="sale.currency"
                        />
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-boxes-stacked text-3xl mb-3"></i>
                        <p>Esta órden no tiene productos registrados.</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal de Confirmación para Eliminar (Sin cambios) -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Orden de {{ sale.type === 'venta' ? 'Venta' : 'Stock' }} {{ sale.type === 'venta' ? 'OV-' : 'OS-' }} {{ sale.id.toString().padStart(4, '0') }}
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente esta Orden? Todos los datos relacionados se perderán. Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</SecondaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import Empty from "@/Components/MyComponents/Empty.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ProductSaleCard from "@/Components/MyComponents/ProductSaleCard.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { ElMessage } from 'element-plus';
import { Link } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import axios from 'axios';

export default {
    name: 'SaleShow',
    components: {
        Link,
        Empty,
        Stepper,
        FileView,
        Dropdown,
        AppLayout,
        BranchNotes,
        DropdownLink,
        CancelButton,
        SecondaryButton,
        ProductSaleCard,
        ConfirmationModal,
    },
    props: {
        sale: Object,
    },
    data() {
        return {
            selectedSale: this.sale.id,
            salesList: [],
            loadingSales: false,
            showConfirmModal: false,
            saleSteps: ['Autorizada', 'En Proceso', 'En Producción', 'Preparando Envío', 'Enviada'],
            stockSteps: ['Autorizada', 'En Proceso', 'En Producción', 'Stock Terminado'],
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
        getPrimaryDetail(contact, type) {
            if (!contact.details) return 'No disponible';
            const detail = contact.details.find(d => d.type === type && d.is_primary);
            return detail ? detail.value : 'No disponible';
        },
        navigateToSale(saleId) {
            this.$inertia.visit(route('sales.show', saleId));
        },
        formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace('.', '');
        },
        deleteFile(fileId) {
            this.sale.media = this.sale.media.filter(m => m.id !== fileId);
        },
        formatRelative(dateString) {
            if (!dateString) return "Sin registro";

            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date; // Diferencia en milisegundos

            if (diffMs < 0) {
                return "En el futuro"; // por si la fecha viene futura
            }

            const seconds = Math.floor(diffMs / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            const months = Math.floor(days / 30);
            const years = Math.floor(months / 12);

            if (seconds < 60) return `Hace ${seconds} segundos`;
            if (minutes < 60) return `Hace ${minutes} minutos`;
            if (hours < 24) return `Hace ${hours} horas`;
            if (days < 30) return `Hace ${days} días`;
            if (months < 12) return `Hace ${months} mes${months > 1 ? "es" : ""}`;
            return `Hace ${years} año${years > 1 ? "s" : ""}`;
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
        async deleteItem() {
            try {
                const response = await axios.post(route('sales.destroy', this.sale.id), {
                _method: 'DELETE'
                });

                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Venta eliminada con éxito.');
                    this.$inertia.visit(route('sales.index'));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar el Venta.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
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