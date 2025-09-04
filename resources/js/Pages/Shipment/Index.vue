<template>
    <AppLayout title="Envíos">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Módulo de Envíos
        </h2>

        <div class="py-7">
            <div class="max-w-[70rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-2">
                        <span></span>
                        <!-- Input de búsqueda (a futuro) -->
                        <SearchInput v-model="search" :searchProps="SearchProps" placeholder="Buscar envío..." />
                    </div>

                    <!-- Overlay de carga -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <!-- Tabla de Envíos agrupada por Venta -->
                        <el-table 
                            max-height="550" 
                            :data="sales.data"
                            style="width: 100%" 
                            stripe
                            @row-click="handleRowClick"
                            class="dark:!bg-slate-900 cursor-pointer dark:!text-gray-300">

                            <el-table-column prop="id" label="Folio Venta" width="120">
                                <template #default="scope">
                                    <span class="font-bold">{{ 'OV-' + scope.row.id.toString().padStart(4, '0') }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Cliente" width="200">
                                <template #default="scope">
                                    {{ scope.row.branch?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>

                            <!-- COLUMNA DE ENVÍOS -->
                            <el-table-column label="Envíos programados">
                                <template #default="scope">
                                    <!-- Iterar sobre los envíos de cada venta -->
                                    <div v-if="scope.row.shipments.length" class="space-y-3">
                                        <div v-for="shipment in scope.row.shipments" :key="shipment.id" class="border dark:border-slate-700 rounded-lg p-3 grid grid-cols-4 gap-x-4 gap-y-2 items-center">
                                            <!-- Estatus -->
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Estatus</p>
                                                <el-tag :type="getStatusTagType(shipment.status)" size="small">{{ shipment.status }}</el-tag>
                                            </div>
                                            <!-- Compañía -->
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Paquetería</p>
                                                <p class="font-semibold">{{ shipment.shipping_company ?? 'N/A' }}</p>
                                            </div>
                                            <!-- Guía -->
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Guía de rastreo</p>
                                                <p>{{ shipment.tracking_guide ?? 'N/A' }}</p>
                                            </div>
                                            <!-- Fecha Promesa -->
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Fecha Promesa</p>
                                                <div class="flex items-center">
                                                    <span>{{ formatDate(shipment.promise_date) ?? 'N/A' }}</span>
                                                    <!-- Indicador de Alerta -->
                                                    <el-tooltip v-if="isOverdue(shipment.promise_date, shipment.status)" content="La fecha promesa ha pasado" placement="top">
                                                        <i class="fa-solid fa-triangle-exclamation text-red-500 ml-2 animate-pulse"></i>
                                                    </el-tooltip>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-center text-gray-400">
                                        <p>No hay envíos registrados para esta venta.</p>
                                    </div>
                                </template>
                            </el-table-column>

                            <!-- Menú de acciones por fila -->
                            <el-table-column align="right" width="100">
                                <template #default="scope">
                                    <el-dropdown trigger="click">
                                        <button @click.stop
                                            class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item @click="$inertia.visit(route('shipments.show', scope.row.id))">
                                                     <i class="fa-solid fa-truck-fast mr-2"></i>Gestionar Envío
                                                </el-dropdown-item>
                                                <el-dropdown-item @click="$inertia.visit(route('sales.show', scope.row.id))">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                    Ver venta
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="sales.total > sales.per_page" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="sales.current_page"
                            :page-size="sales.per_page" :total="sales.total"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { router } from "@inertiajs/vue3";

export default {
    data() {
        return {
            loading: false,
            search: '',
            SearchProps: ['ID', 'Cliente', 'Estatus'],
        };
    },
    components: {
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
    },
    props: {
        sales: Object,
    },
    methods: {
        handleRowClick(row) {
            router.get(route('shipments.show', row.id));
        },
        handlePageChange(page) {
            router.get(route('shipments.index', { page }), {
                preserveState: true,
                replace: true,
            });
        },
        formatDate(dateString) {
            if (!dateString) return '';
            // Se agrega un reemplazo para asegurar compatibilidad con distintos formatos de fecha
            const cleanDateString = dateString.split(' ')[0].replace(/-/g, '/');
            const date = new Date(cleanDateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'warning',
                'Enviado': 'success',
            };
            return statusMap[status] || 'info';
        },
        isOverdue(promiseDate, status) {
            // No hay alerta si no hay fecha o si el envío ya se completó
            if (!promiseDate || status === 'Enviado') {
                return false;
            }

            // Crear fecha de hoy a medianoche para una comparación justa
            const today = new Date();
            today.setHours(0, 0, 0, 0); 
            
            // Crear fecha promesa a partir del string
            // Se agrega un reemplazo para asegurar compatibilidad con distintos formatos de fecha
            const cleanDateString = promiseDate.split(' ')[0].replace(/-/g, '/');
            const promise = new Date(cleanDateString);

            // Retorna true si la fecha promesa es anterior a hoy
            return promise < today;
        }
    }
};
</script>

<style>
/* Estilos para la paginación en modo oscuro */
.dark .el-pagination button,
.dark .el-pager li {
    background-color: #1f2937 !important;
    color: #d1d5db !important;
}
.dark .el-pager li.is-active {
    color: #ffffff !important;
    background-color: #3b82f6 !important;
}
</style>
