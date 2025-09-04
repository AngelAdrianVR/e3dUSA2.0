<template>
    <AppLayout :title="`Producción de la Órden OV-${sale.id.toString().padStart(4, '0')}`">

        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
                    <h1 class="dark:text-white font-bold text-2xl my-2">
                        <span class="text-gray-500 dark:text-gray-400">Producción de Órden de Venta:</span> OV-{{
                            sale.id.toString().padStart(4, '0') }}
                    </h1>
                </div>
            </div>

            <div class="flex items-center space-x-2 dark:text-white">
                <Link :href="route('sales.show', sale.id)"
                    class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                <i class="fa-solid fa-eye mr-2 text-xs"></i> Ver Órden de Venta
                </Link>
                <Link :href="route('productions.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-3 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-5">
                <!-- Card de Información de la Órden -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Órden</h3>
                    <ul class="space-y-3 text-sm">
                        <!-- Campos para Venta -->
                        <template v-if="sale.type === 'venta'">
                            <li class="flex justify-between items-center">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>
                                <el-tooltip placement="top-start" effect="light" raw-content>
                                    <template #content>
                                        <div
                                            class="w-72 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                                            <div class="flex justify-between items-center border-b pb-2 mb-3">
                                                <h4 class="font-bold text-lg text-primary dark:text-sky-400">
                                                    {{ sale.branch?.name }}
                                                </h4>
                                                <span
                                                    class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300">
                                                    {{ sale.branch?.status ?? 'N/A' }}
                                                </span>
                                            </div>
                                            <div class="space-y-1 text-gray-700 dark:text-gray-300">
                                                <p><strong class="font-semibold">RFC:</strong> {{ sale.branch?.rfc ??
                                                    'N/A' }}</p>
                                                <p><strong class="font-semibold">Dirección:</strong> {{
                                                    sale.branch?.address ?? 'N/A' }}</p>
                                                <p><strong class="font-semibold">C.P.:</strong> {{
                                                    sale.branch?.post_code ?? 'N/A' }}</p>
                                                <p><strong class="font-semibold">Medio de contacto:</strong> {{
                                                    sale.branch?.meet_way ?? 'N/A' }}</p>
                                                <p><strong class="font-semibold">Última compra:</strong> {{
                                                    formatRelative(sale.branch?.last_purchase_date) }}</p>
                                            </div>
                                            <div class="mt-4 pt-2 border-t flex justify-between items-center">
                                                <Link :href="route('branches.show', sale.branch?.id)">
                                                <SecondaryButton
                                                    class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                                                    <i class="fa-solid fa-eye"></i> Ver Cliente
                                                </SecondaryButton>
                                                </Link>
                                                <span class="text-[10px] italic text-gray-400">Creado: {{
                                                    sale.branch?.created_at?.split('T')[0] }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    <span class="text-blue-500 hover:underline cursor-pointer">
                                        {{ sale.branch?.name ?? 'N/A' }}
                                    </span>
                                </el-tooltip>
                            </li>
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                                <span>{{ sale.contact.name ?? 'N/A' }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cotización Rel.</span>
                                <span v-if="sale.quote_id"
                                    @click="$inertia.visit(route('quotes.show', sale.quote_id))"
                                    class="text-blue-500 hover:underline cursor-pointer">
                                    COT-{{ sale.quote_id }}
                                </span>
                                <span v-else>N/A</span>
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
                            <span>${{ parseFloat(sale.total_amount)?.toFixed(2)?.replace(/\B(?=(\d{3})+(?!\d))/g, ",")
                                }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Productos en la Órden -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Productos en la Órden</h3>
                    <ul v-if="sale.sale_products?.length"
                        class="space-y-2 text-sm max-h-[calc(100vh-20rem)] overflow-y-auto pr-2">
                        <li v-for="item in sale.sale_products" :key="item.id" @click="selectProduct(item)"
                            class="flex items-center p-3 rounded-lg cursor-pointer" :class="{
                                'bg-blue-100 dark:bg-sky-800/50 border-2 border-blue-400 dark:border-sky-600': selectedSaleProduct?.id === item.id,
                                'hover:bg-gray-100 dark:hover:bg-slate-700/50': selectedSaleProduct?.id !== item.id
                            }">
                            <div
                                class="flex-shrink-0 size-14 bg-gray-100 dark:bg-slate-900/50 rounded-xl flex items-center justify-center relative group">
                                <img v-if="item?.product.media?.length" :src="item.product.media[0].original_url"
                                    alt="Imagen del producto" class="w-full h-full object-contain rounded-xl">
                                <div v-else class="text-gray-300 dark:text-gray-600 text-center">
                                    <i class="fa-regular fa-image text-5xl"></i>
                                </div>
                            </div>
                            <div class="flex-1 ml-4">
                                <p class="font-semibold">{{ item.product.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ item.product.code }}</p>
                            </div>
                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ item.quantity }} pz.</span>
                        </li>
                    </ul>
                    <Empty v-else text="Esta órden no tiene productos." />
                </div>
            </div>

            <!-- COLUMNA DERECHA: KANBAN DEL PRODUCTO SELECCIONADO -->
            <div class="lg:col-span-2">
                <!-- Placeholder when no product is selected -->
                <div v-if="!selectedSaleProduct"
                    class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-10 min-h-[500px] flex flex-col items-center justify-center text-center">
                    <svg class="h-16 w-16 text-gray-400 dark:text-gray-500 mb-4" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1012 10.125A2.625 2.625 0 0012 4.875z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 10.125a2.625 2.625 0 100 5.25 2.625 2.625 0 000-5.25z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 15.375a2.625 2.625 0 100 5.25 2.625 2.625 0 000-5.25z" />
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mt-2">Detalles de Producción</h3>
                    <p class="text-md text-gray-500 dark:text-gray-400 mt-1">Selecciona un producto de la lista para ver
                        su kanban.</p>
                </div>

                <!-- Kanban View when a product is selected -->
                <div v-else
                    class="bg-white dark:bg-slate-800/80 shadow-2xl rounded-xl p-7 transition-all duration-300 ease-in-out animate-fade-in max-h-[77vh]">
                    <!-- Kanban Header -->
                    <div class="flex justify-between items-start pb-4 mb-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h2 class="text-2xl font-bold text-primary dark:text-sky-400">{{
                                selectedSaleProduct.product.name }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ selectedSaleProduct.product.code
                                }}</p>
                        </div>
                        <el-tag v-if="selectedProduction" :type="statusTagType(selectedProduction.status)" size="large"
                            effect="light" class="!text-sm">
                            <i :class="statusIcon(selectedProduction.status) + ' mr-2'"></i>
                            {{ selectedProduction.status }}
                        </el-tag>
                        <el-tag v-else type="info" size="large" effect="light" class="!text-sm">
                            <i class="fa-regular fa-clock mr-2"></i>
                            Sin iniciar
                        </el-tag>
                    </div>

                    <!-- Kanban Body Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                        <!-- Main Column: Timeline Chart -->
                        <div class="lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Línea de Tiempo de Tareas</h3>
                             <div v-if="timelineProps" class="bg-gray-50 dark:bg-slate-900/50 p-4 rounded-lg space-y-4">
                                <!-- Gantt Chart Rows -->
                                <div v-for="task in timelineProps.tasks" :key="task.id" class="group">
                                    <div class="flex items-center text-xs mb-1">
                                         <img :src="task.operator?.profile_photo_url" :alt="task.operator?.name" class="size-5 rounded-full mr-2">
                                        <span class="font-medium text-gray-700 dark:text-gray-300 w-1/3 truncate">{{ task.name }}</span>
                                        <span class="text-gray-500 dark:text-gray-400 ml-auto">{{ getTaskDuration(task.started_at, task.finished_at) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-6 relative overflow-hidden">
                                        <el-tooltip placement="top" effect="dark">
                                            <template #content>
                                                <span>{{ task.operator?.name }} <br> Estado: {{ task.status }}</span>
                                            </template>
                                            <div class="absolute h-full rounded-full transition-all duration-300" 
                                                :class="taskStatusColor(task.status)" 
                                                :style="{ left: task.left + '%', width: task.width + '%' }">
                                            </div>
                                        </el-tooltip>
                                    </div>
                                </div>
                             </div>
                             <Empty v-else text="No hay tareas con seguimiento de tiempo para mostrar." class="!py-12" />
                        </div>

                        <!-- Sidebar Column: Details -->
                        <div class="lg:col-span-1 space-y-5 h-[550px] overflow-y-auto overflow-x-hidden">
                             <!-- Quantities -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3">Cantidades</h3>
                                <div class="space-y-4">
                                    <div class="bg-sky-50 dark:bg-sky-900/40 rounded-lg p-4 text-center">
                                        <p class="text-sm font-medium text-sky-600 dark:text-sky-300">Cantidad a Producir</p>
                                        <p class="text-3xl font-bold text-sky-800 dark:text-sky-100 mt-1">{{ selectedSaleProduct.quantity_to_produce }}</p>
                                    </div>
                                    <div class="bg-emerald-50 dark:bg-emerald-900/40 rounded-lg p-4 text-center">
                                        <p class="text-sm font-medium text-emerald-600 dark:text-emerald-300">Tomado de Stock</p>
                                        <p class="text-3xl font-bold text-emerald-800 dark:text-emerald-100 mt-1">{{ selectedSaleProduct.quantity - selectedSaleProduct.quantity_to_produce }}</p>
                                    </div>
                                </div>
                            </div>
                             <!-- Production Tasks -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3">Tareas y Operadores</h3>
                                <div v-if="selectedProduction?.tasks?.length" class="space-y-3">
                                    <div @click="openLogModal(task.operator)" v-for="task in selectedProduction.tasks" :key="task.id" class="flex items-center space-x-3 bg-gray-50 dark:bg-slate-700/50 p-2 rounded-lg cursor-pointer hover:scale-[1.02] transition-transform ease-linear duration-200">
                                        <img :src="task.operator?.profile_photo_url" :alt="task.operator?.name" class="size-10 rounded-full ring-2 ring-offset-2 dark:ring-offset-slate-800  transition-transform object-cover" :class="taskStatusRingColor(task.status)">
                                        <div>
                                            <p class="font-semibold text-sm text-gray-800 dark:text-gray-200">{{ task.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ task.operator?.name }}</p>
                                        </div>
                                    </div>
                                </div>
                                <Empty v-else text="Sin tareas asignadas." class="!py-8" />
                            </div>
                            <!-- Customizations -->
                             <div>
                                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3">Personalizaciones</h3>
                                <div v-if="Object.keys(groupedCustomizations).length" class="space-y-3">
                                    <div v-for="(details, type) in groupedCustomizations" :key="type" class="bg-gray-100 dark:bg-slate-700/50 p-3 rounded-lg">
                                        <p class="font-semibold text-sm text-gray-800 dark:text-gray-100 mb-2">{{ type }}</p>
                                        <ul class="space-y-1 text-xs list-disc list-inside">
                                            <li v-for="(detail, index) in details" :key="index">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">{{ detail.key }}:</span>
                                                <span class="text-gray-700 dark:text-gray-300 ml-1">{{ detail.value }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <p class="text-sm dark:text-white italic text-center" v-else>Sin personalizaciones</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Log Modal -->
        <el-dialog v-model="logModalVisible" :title="`Historial de acciones ${selectedOperatorName}`" width="500px">
            <div class="space-y-4 max-h-[60vh] overflow-y-auto p-2">
                 <div v-for="log in selectedOperatorLogs" :key="log.id" class="flex items-start space-x-3 text-sm">
                    <div class="flex-shrink-0 size-8 rounded-full flex items-center justify-center" :class="logTypeBgColor(log.type)">
                        <i class="fa-solid text-xs" :class="logTypeIcon(log.type)"></i>
                    </div>
                    <div class="flex-grow">
                        <p class="text-gray-800 dark:text-gray-200">{{ log.notes }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                           {{ formatDistanceToNowWithLocale(log.created_at) }}
                        </p>
                    </div>
                </div>
                <Empty v-if="!selectedOperatorLogs.length" text="No hay eventos registrados para este operador." />
            </div>
        </el-dialog>
    </AppLayout>
</template>

<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Empty from "@/Components/MyComponents/Empty.vue";
import { Link } from "@inertiajs/vue3";
import { ref, computed } from 'vue';
import { format, formatDistanceToNow, differenceInMinutes } from 'date-fns';
import { es } from 'date-fns/locale';

const props = defineProps({
    sale: Object,
});

// --- STATE ---
const selectedSaleProduct = ref(null);
const logModalVisible = ref(false);
const selectedOperatorLogs = ref([]);
const selectedOperatorName = ref('');


// --- COMPUTED ---
const formattedDate = computed(() => {
    if (!props.sale.created_at) return 'N/A';
    const date = new Date(props.sale.created_at);
    return format(date, "d 'de' MMMM, yyyy", { locale: es });
});

const selectedProduction = computed(() => {
    if (!selectedSaleProduct.value) return null;
    return props.sale.productions.find(p => p.sale_product_id === selectedSaleProduct.value.id);
});

const groupedCustomizations = computed(() => {
    if (!selectedSaleProduct.value?.customization_details?.length) return {};
    return selectedSaleProduct.value.customization_details.reduce((acc, current) => {
        const { type, key, value } = current;
        if (!acc[type]) {
            acc[type] = [];
        }
        acc[type].push({ key, value });
        return acc;
    }, {});
});

const timelineProps = computed(() => {
    if (!selectedProduction.value?.tasks?.length || !selectedProduction.value.started_at) return null;

    const validTasks = selectedProduction.value.tasks.filter(t => t.started_at);
    if (!validTasks.length) return null;

    const productionStart = new Date(validTasks.reduce((min, task) => Math.min(min, new Date(task.started_at).getTime()), Infinity));
    const productionEnd = new Date(validTasks.reduce((max, task) => Math.max(max, new Date(task.finished_at || new Date()).getTime()), 0));
    
    const totalDuration = productionEnd.getTime() - productionStart.getTime();

    if (totalDuration <= 0) return null;

    const tasksWithPositions = validTasks.map(task => {
        const taskStart = new Date(task.started_at);
        const taskEnd = new Date(task.finished_at || new Date());
        const offset = taskStart.getTime() - productionStart.getTime();
        const duration = taskEnd.getTime() - taskStart.getTime();

        return {
            ...task,
            left: (offset / totalDuration) * 100,
            width: (duration / totalDuration) * 100,
        };
    });
    
    return {
        tasks: tasksWithPositions,
        startDate: productionStart.toISOString(),
        endDate: productionEnd.toISOString(),
    };
});


// --- METHODS ---
const selectProduct = (product) => {
    selectedSaleProduct.value = product;
};

const openLogModal = (operator) => {
    if (!operator) return;
    selectedOperatorName.value = operator.name;
    selectedOperatorLogs.value = selectedProduction.value?.logs?.filter(log => log.user_id === operator.id) ?? [];
    logModalVisible.value = true;
};

const formatWithLocale = (dateString, formatString) => {
    if (!dateString) return '';
    return format(new Date(dateString), formatString, { locale: es });
};

const formatDistanceToNowWithLocale = (dateString) => {
    if (!dateString) return '';
    return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale: es });
};

const formatRelative = (dateString) => {
    if (!dateString) return "Sin registro";
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    if (diffMs < 0) return "En el futuro";
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
};

const getTaskDuration = (start, end) => {
    if (!start) return 'N/A';
    const startDate = new Date(start);
    const endDate = end ? new Date(end) : new Date();
    
    const minutes = differenceInMinutes(endDate, startDate);
    if (minutes < 1) return '< 1m';
    if (minutes > 60) {
        const hours = Math.floor(minutes / 60);
        const remMinutes = minutes % 60;
        return `${hours}h ${remMinutes}m`;
    }
    return `${minutes}m`;
};

const statusTagType = (status) => {
    const types = {
        'Terminada': 'success', 'En Proceso': 'primary', 'Sin material': 'danger',
        'Pendiente': 'info', 'Pausada': 'warning',
    };
    return types[status] || 'info';
};

const statusIcon = (status) => {
    const icons = {
        'Terminada': 'fa-solid fa-check-double', 'En Proceso': 'fa-solid fa-person-digging',
        'Sin material': 'fa-solid fa-circle-exclamation', 'Pendiente': 'fa-regular fa-clock',
        'Pausada': 'fa-solid fa-pause',
    };
    return icons[status] || 'fa-solid fa-question-circle';
};

const taskStatusRingColor = (status) => {
    const colors = {
        'Terminada': 'ring-green-500', 'En Proceso': 'ring-blue-500', 'Sin material': 'ring-red-500',
        'Pendiente': 'ring-gray-400', 'Pausada': 'ring-yellow-500',
    };
    return colors[status] || 'ring-gray-400';
};

const taskStatusColor = (status) => {
    const colors = {
        'Terminada': 'bg-green-500', 'En Proceso': 'bg-blue-500', 'Sin material': 'bg-red-500',
        'Pendiente': 'bg-gray-400', 'Pausada': 'bg-yellow-500',
    };
    return colors[status] || 'bg-gray-400';
};

const logTypeBgColor = (type) => {
    const colors = {
        'pausa': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
        'reanudacion': 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
        'progreso': 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
        'alerta': 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
    };
    return colors[type] || 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
};

const logTypeIcon = (type) => {
    const icons = {
        'pausa': 'fa-pause', 'reanudacion': 'fa-play', 'progreso': 'fa-check',
        'alerta': 'fa-triangle-exclamation',
    };
    return icons[type] || 'fa-info';
};
</script>

<style>
.animate-fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

