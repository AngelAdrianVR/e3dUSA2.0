<template>
    <AppLayout title="Dashboard de Cobranza">
        <div class="flex justify-between items-center mb-6 max-w-[100em] mx-auto sm:px-6 lg:px-8 pt-7">
            <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                Panel de Control de Facturación
            </h2>

            <!-- Botón de Reportes -->
            <div class="flex items-center space-x-3">
                <el-date-picker
                    v-model="reportDates"
                    type="daterange"
                    range-separator="A"
                    start-placeholder="Inicio"
                    end-placeholder="Fin"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    size="small"
                    style="width: 250px"
                />
                <el-dropdown @command="generateReport" trigger="click">
                    <el-button type="primary" size="small">
                        <i class="fa-solid fa-file-excel mr-2"></i> Generar Reporte <i class="fa-solid fa-chevron-down ml-2 text-xs"></i>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="sin_prefactura">OVs sin pre-factura</el-dropdown-item>
                            <el-dropdown-item command="prefacturadas">OVs pre-facturadas</el-dropdown-item>
                            <el-dropdown-item command="timbradas">OVs timbradas</el-dropdown-item>
                            <el-dropdown-item divided command="todas">Todas las OVs</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>

        <div class="pb-7">
            <div class="max-w-[101rem] mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Sección de KPIs (Clicables) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- KPI 1: Pendientes Pre-factura -->
                    <div @click="filterByKpi('pending_pre_invoice')" 
                         class="bg-white dark:bg-slate-900 rounded-lg shadow-sm p-6 border-l-4 border-amber-500 cursor-pointer hover:shadow-md transition-all relative overflow-hidden group">
                        
                        <!-- Alerta Visual -->
                        <span v-if="kpis.total_pending_pre_invoice > 0" class="absolute top-4 right-4 flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                        </span>

                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 mr-4 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-file-invoice text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wide">Requieren Pre-factura</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ kpis.total_pending_pre_invoice }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 2: Pendientes Timbrado -->
                    <div @click="filterByKpi('pending_stamping')" 
                         class="bg-white dark:bg-slate-900 rounded-lg shadow-sm p-6 border-l-4 border-rose-500 cursor-pointer hover:shadow-md transition-all relative overflow-hidden group">
                        
                         <!-- Alerta Visual -->
                        <span v-if="kpis.total_pending_stamping > 0" class="absolute top-4 right-4 flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                        </span>

                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 mr-4 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-stamp text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wide">Requieren Timbrado</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ kpis.total_pending_stamping }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 3: Timbradas -->
                    <div @click="filterByKpi('stamped_month')" 
                         class="bg-white dark:bg-slate-900 rounded-lg shadow-sm p-6 border-l-4 border-emerald-500 cursor-pointer hover:shadow-md transition-all group">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 mr-4 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-check-double text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wide">Timbradas (Este mes)</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ kpis.total_stamped_month }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenedor Principal (Tabla) -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 border border-gray-100 dark:border-gray-800">
                    
                    <!-- Filtros -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0 gap-4">
                        
                        <!-- Búsqueda General -->
                        <div class="w-full md:w-1/3">
                            <el-input
                                v-model="filters.search"
                                placeholder="Buscar por ID de OV, Razón social o Sucursal..."
                                clearable
                                @input="debouncedSearch"
                                :prefix-icon="'Search'"
                            >
                                <template #prefix>
                                    <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
                                </template>
                            </el-input>
                        </div>

                        <!-- Filtro Estado Facturación -->
                        <div class="!w-72 md:w-auto flex items-center space-x-2">
                            <el-select
                                v-model="filters.billing_status"
                                placeholder="Estatus de Facturación"
                                clearable
                                class="w-56"
                                @change="syncUrl"
                            >
                                <el-option label="Pendiente Pre-factura" value="Pendiente Pre-factura" />
                                <el-option label="Pre-facturada" value="Pre-facturada" />
                                <el-option label="Pendiente Timbrado" value="Pendiente Timbrado" />
                                <el-option label="Timbrada" value="Timbrada" />
                            </el-select>

                            <el-tooltip v-if="hasActiveFilters" content="Limpiar todos los filtros" placement="top">
                                <button 
                                    @click="clearFilters"
                                    class="size-9 flex-shrink-0 flex items-center justify-center rounded-lg bg-red-100 text-red-600 hover:bg-red-200 transition-colors"
                                >
                                    <i class="fa-solid fa-filter-circle-xmark text-sm"></i>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>

                    <!-- Tabla de OVs -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full whitespace-nowrap text-xs">
                            <thead class="bg-gray-50 dark:bg-slate-800">
                                <tr class="text-left font-bold text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-3 px-4">OV</th>
                                    <th class="py-3 px-4">Razón Social</th>
                                    <th class="py-3 px-4">Sucursal</th>
                                    <th class="py-3 px-4">Monto</th>
                                    <th class="py-3 px-4">Estado OV</th>
                                    <th class="py-3 px-4 text-center">Estado Fact.</th>
                                    <th class="py-3 px-4 text-center">Pre-factura</th>
                                    <th class="py-3 px-4 text-center">Timbrado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="sale in salesForBilling.data" :key="sale.id" 
                                    @click="openDetailsModal(sale)"
                                    class="hover:bg-indigo-50/50 cursor-pointer dark:hover:bg-slate-800/50 transition-colors">
                                    
                                    <td class="px-4 py-3">
                                        <span @click.stop="$inertia.visit(route('sales.show', sale.id))" class="font-semibold text-indigo-600 hover:underline hover:text-blue-600 dark:text-indigo-400">OV-{{ sale.id }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-medium">
                                        {{ sale.branch?.parent?.name || sale.branch?.name || 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ sale.branch?.name || 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        ${{ formatCurrency(sale.total_amount) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <el-tag size="small" :type="getGeneralStatusType(sale.status)" effect="plain">{{ sale.status }}</el-tag>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <el-tag size="small" :type="getBillingStatusType(sale.billing_status)">{{ sale.billing_status }}</el-tag>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="sale.pre_invoice_folio" class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-gray-600 dark:text-gray-300 font-mono">{{ sale.pre_invoice_folio }}</span>
                                        <span v-else class="text-gray-400 italic">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="sale.stamped_invoice_folio" class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 px-2 py-1 rounded font-mono">{{ sale.stamped_invoice_folio }}</span>
                                        <span v-else class="text-gray-400 italic">-</span>
                                    </td>
                                </tr>
                                <tr v-if="salesForBilling.data.length === 0">
                                    <td colspan="8" class="text-center py-10">
                                        <div class="flex flex-col items-center text-gray-400">
                                            <i class="fa-solid fa-inbox text-4xl mb-3"></i>
                                            <p class="text-sm">No hay órdenes de venta que coincidan con los filtros.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación Simple -->
                    <div class="mt-6 flex justify-end" v-if="salesForBilling.links && salesForBilling.data.length > 0">
                        <div class="flex gap-1 shadow-sm rounded-md">
                            <template v-for="(link, key) in salesForBilling.links" :key="key">
                                <div v-if="link.url === null" class="px-3 py-1.5 text-xs text-gray-400 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-slate-800" v-html="link.label" />
                                <Link v-else class="px-3 py-1.5 text-xs border border-gray-200 dark:border-gray-700 bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors" :class="{ 'bg-indigo-50 border-indigo-500 text-indigo-600 z-10': link.active }" :href="link.url" v-html="link.label" />
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Importamos el nuevo componente del modal -->
        <SaleDetailsModal 
            v-model:show="detailsModalVisible" 
            :sale="selectedSale" 
            @saved="handleModalSaved"
        />

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SaleDetailsModal from "./Components/SaleDetailsModal.vue";
import { Link, router } from "@inertiajs/vue3";
import { debounce } from 'lodash';

export default {
    name: 'BillingDashboard',
    components: { AppLayout, SaleDetailsModal, Link },
    props: {
        kpis: Object,
        salesForBilling: Object,
        filtersProp: Object,
    },
    data() {
        return {
            filters: {
                billing_status: this.filtersProp?.billing_status || null,
                search: this.filtersProp?.search || '',
                kpi_filter: this.filtersProp?.kpi_filter || null,
            },
            reportDates: null,
            detailsModalVisible: false,
            selectedSale: null,
        };
    },
    computed: {
        hasActiveFilters() {
            return this.filters.billing_status || this.filters.search || this.filters.kpi_filter;
        }
    },
    created() {
        // Inicializamos el debounce en el created para que mantenga la referencia
        this.debouncedSearch = debounce(this.syncUrl, 500);
    },
    methods: {
        formatCurrency(value) {
            return parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        syncUrl() {
            router.get(route('billing.dashboard'), this.filters, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        },
        clearFilters() {
            this.filters = { billing_status: null, search: '', kpi_filter: null };
            this.syncUrl();
        },
        filterByKpi(kpiType) {
            this.filters.kpi_filter = kpiType;
            // Limpiamos los otros filtros al dar clic en un KPI para que sea exacto
            this.filters.billing_status = null; 
            this.filters.search = '';
            this.syncUrl();
        },
        openDetailsModal(sale) {
            this.selectedSale = sale;
            this.detailsModalVisible = true;
        },
        handleModalSaved() {
            // Refrescar los datos actuales sin recargar toda la página
            router.reload({ only: ['salesForBilling', 'kpis'] });
        },
        generateReport(type) {
            let url = route('billing.report', { type: type });
            
            if (this.reportDates && this.reportDates.length === 2) {
                url += `&start_date=${this.reportDates[0]}&end_date=${this.reportDates[1]}`;
            }
            
            // Abrir en nueva pestaña para descargar
            window.open(url, '_blank');
        },
        getGeneralStatusType(status) {
            const types = {
                'Pendiente': 'warning', 'Autorizada': 'primary', 'En Proceso': 'primary',
                'En Producción': 'warning', 'Terminada': 'success', 'Preparando Envío': 'warning',
                'Enviada': 'success', 'Cancelada': 'danger',
            };
            return types[status] || 'info';
        },
        getBillingStatusType(status) {
            const types = {
                'Pendiente Pre-factura': 'warning', 'Pre-facturada': 'primary',
                'Pendiente Timbrado': 'danger', 'Timbrada': 'success',
            };
            return types[status] || 'info';
        }
    }
};
</script>