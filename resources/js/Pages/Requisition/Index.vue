<template>
    <AppLayout title="Requisiciones de Compra">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Requisiciones de Material
        </h2>

        <div class="py-7">
            <div class="max-w-[95rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">

                    <!-- Barra superior: altas, filtros y búsqueda -->
                    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
                        <div class="flex flex-wrap gap-2">
                            <el-dropdown v-if="can.create" trigger="click" @command="goToCreate">
                                <PrimaryButton>
                                    <i class="fa-solid fa-plus mr-2"></i> Nueva requisición
                                    <i class="fa-solid fa-chevron-down ml-2 text-xs"></i>
                                </PrimaryButton>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="Local">
                                            <i class="fa-solid fa-store mr-2 text-emerald-500"></i> Requisición Local
                                        </el-dropdown-item>
                                        <el-dropdown-item command="Importación">
                                            <i class="fa-solid fa-ship mr-2 text-sky-500"></i> Requisición de Importación
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <el-select v-model="filtersForm.type" @change="applyFilters" placeholder="Tipo" style="width: 165px;">
                                <el-option label="Todos los tipos" value="" />
                                <el-option label="Local" value="Local" />
                                <el-option label="Importación" value="Importación" />
                            </el-select>

                            <el-select v-model="filtersForm.status" @change="applyFilters" placeholder="Estatus" style="width: 210px;" filterable>
                                <el-option label="Todos los estatus" value="" />
                                <el-option v-for="status in statusOptions" :key="status" :label="status" :value="status" />
                            </el-select>

                            <el-select v-model="filtersForm.origin" @change="applyFilters" placeholder="Vía" style="width: 150px;">
                                <el-option label="Todas las vías" value="" />
                                <el-option label="Vendedor" value="Vendedor" />
                                <el-option label="Almacén" value="Almacén" />
                            </el-select>

                            <el-select v-model="filtersForm.priority" @change="applyFilters" placeholder="Prioridad" style="width: 150px;">
                                <el-option label="Toda prioridad" value="" />
                                <el-option label="Baja" value="Baja" />
                                <el-option label="Media" value="Media" />
                                <el-option label="Alta" value="Alta" />
                                <el-option label="Urgente" value="Urgente" />
                            </el-select>

                            <div v-if="can.view_all"
                                class="flex items-center px-3 py-1.5 bg-gray-100 dark:bg-slate-800 rounded-full shadow-sm border border-gray-200 dark:border-slate-700">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 mr-2">Mías</span>
                                <el-switch v-model="showAll" @change="toggleView"
                                    style="--el-switch-on-color: #10b981; --el-switch-off-color: #3b82f6;" />
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 ml-2">Todas</span>
                            </div>
                        </div>

                        <SearchInput v-model="search" @keyup.enter="applyFilters" @cleanSearch="applyFilters"
                            :searchProps="['Folio', 'Cliente', 'Producto', 'Solicitante', 'Razón social']" />
                    </div>

                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>

                        <el-table :data="requisitions.data" style="width: 100%" stripe @row-click="handleRowClick"
                            :row-class-name="rowClassName"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">
                            <el-table-column label="Folio" width="120">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <el-tooltip :content="scope.row.type" placement="top">
                                            <i :class="scope.row.type === 'Importación'
                                                ? 'fa-solid fa-ship text-sky-500'
                                                : 'fa-solid fa-store text-emerald-500'"></i>
                                        </el-tooltip>
                                        <span class="font-bold">{{ scope.row.folio }}</span>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Vía" width="110">
                                <template #default="scope">
                                    <el-tag size="small" :type="scope.row.origin === 'Vendedor' ? 'primary' : 'warning'">
                                        {{ scope.row.origin }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Solicitante" width="140">
                                <template #default="scope">
                                    {{ scope.row.user?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>

                            <el-table-column label="Cliente" min-width="170">
                                <template #default="scope">
                                    <div v-if="scope.row.branch" class="text-sm min-w-0">
                                        <p class="font-semibold text-gray-700 dark:text-gray-200 truncate"
                                            :title="scope.row.branch.name">{{ scope.row.branch.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate"
                                            :title="scope.row.business_name ?? ''">
                                            {{ scope.row.business_name ?? 'Sin razón social' }}
                                        </p>
                                    </div>
                                    <span v-else class="text-xs text-gray-500 dark:text-gray-400">
                                        N/A - Resurtido
                                    </span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Producto / Cantidad" min-width="210">
                                <template #default="scope">
                                    <el-tooltip v-if="hasProducts(scope.row)" placement="top" effect="dark" :show-after="150">
                                        <template #content>
                                            <div class="space-y-1.5 py-0.5 max-h-72 max-w-md overflow-y-auto">
                                                <p v-if="productRows(scope.row).length > 1"
                                                    class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                                    {{ productRows(scope.row).length }} productos solicitados
                                                </p>
                                                <div v-for="(line, index) in productRows(scope.row)" :key="index"
                                                    class="flex items-start justify-between gap-6 leading-snug py-0.5">
                                                    <span class="text-xs text-gray-200">{{ line.name }}</span>
                                                    <span class="text-xs font-semibold text-gray-100 bg-white/10 rounded px-1.5 py-0.5 shrink-0">
                                                        {{ line.quantity }}
                                                    </span>
                                                </div>
                                            </div>
                                        </template>

                                        <div class="text-sm min-w-0">
                                            <p class="font-semibold text-gray-700 dark:text-gray-200 truncate">
                                                {{ productName(scope.row) }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                <span v-if="extraProductsLabel(scope.row)">
                                                    {{ extraProductsLabel(scope.row) }}
                                                </span>
                                                Cantidad: {{ formatQuantity(productQuantity(scope.row)) }}
                                            </p>
                                        </div>
                                    </el-tooltip>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </template>
                            </el-table-column>

                            <!-- <el-table-column label="Cantidad" width="105">
                                <template #default="scope">
                                    {{ formatQuantity(scope.row.quantity) }}
                                </template>
                            </el-table-column> -->

                            <el-table-column label="Prioridad" width="105">
                                <template #default="scope">
                                    <el-tag v-if="scope.row.priority" size="small" :type="getPriorityTagType(scope.row.priority)">
                                        {{ scope.row.priority }}
                                    </el-tag>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Frecuencia" width="150">
                                <template #default="scope">
                                    <div @click.stop>
                                        <!-- La frecuencia solo aplica a requisiciones de vendedor -->
                                        <el-select v-if="scope.row.can?.edit_frequency" v-model="scope.row.sale_frequency"
                                            size="small" placeholder="Sin definir" clearable class="w-full"
                                            @change="saveFrequency(scope.row)">
                                            <el-option v-for="frequency in salesFrequencies" :key="frequency"
                                                :label="frequency" :value="frequency" />
                                        </el-select>
                                        <span v-else class="text-xs">
                                            {{ frequencyLabel(scope.row) }}
                                        </span>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Estatus" width="205">
                                <template #default="scope">
                                    <el-tag size="small" :type="getStatusTagType(scope.row.status)">{{ scope.row.status }}</el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Arribo" width="130">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-block w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: getArrivalColor(scope.row) }"></span>
                                        <span class="text-xs">{{ scope.row.arrival_status }}</span>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Guía" width="140">
                                <template #default="scope">
                                    <div v-if="scope.row.shipments?.length" class="text-xs">
                                        <p v-for="shipment in scope.row.shipments" :key="shipment.id" class="font-semibold">
                                            {{ shipment.tracking_number }}
                                        </p>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Entrega estimada" width="150">
                                <template #default="scope">
                                    {{ scope.row.expected_delivery_date ? formatDate(scope.row.expected_delivery_date) : '—' }}
                                </template>
                            </el-table-column>

                            <el-table-column label="Monto total" width="170">
                                <template #default="scope">
                                    <div class="text-xs space-y-1">
                                        <p v-if="scope.row.quoted_cost">
                                            <span class="text-gray-500 dark:text-gray-400">Cotizado:</span>
                                            {{ formatCurrency(scope.row.quoted_cost, scope.row.quoted_currency) }}
                                        </p>
                                        <p v-if="scope.row.invoice_amount">
                                            <span class="text-gray-500 dark:text-gray-400">Factura:</span>
                                            {{ formatCurrency(scope.row.invoice_amount, scope.row.invoice_currency) }}
                                        </p>
                                        <p v-if="!scope.row.quoted_cost && !scope.row.invoice_amount" class="text-gray-400">—</p>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Acciones" width="110" fixed="right">
                                <template #default="scope">
                                    <div class="flex items-center gap-1" @click.stop>
                                        <el-tooltip content="Editar" placement="top">
                                            <span>
                                                <el-button v-if="scope.row.can?.edit" size="small" circle
                                                    @click="editRequisition(scope.row)">
                                                    <i class="fa-solid fa-pen-to-square text-blue-600"></i>
                                                </el-button>
                                            </span>
                                        </el-tooltip>
                                        <el-tooltip content="Eliminar" placement="top">
                                            <span>
                                                <el-button v-if="scope.row.can?.delete" size="small" circle
                                                    @click="confirmDelete(scope.row)">
                                                    <i class="fa-solid fa-trash-can text-red-600"></i>
                                                </el-button>
                                            </span>
                                        </el-tooltip>
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <div v-if="requisitions.total > 0" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="requisitions.current_page" :page-size="requisitions.per_page"
                            :total="requisitions.total" layout="prev, pager, next" background
                            @current-change="handlePageChange" />
                    </div>
                    <Empty v-if="requisitions.total === 0"
                        label="No hay requisiciones registradas con los filtros seleccionados." />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import Empty from '@/Components/MyComponents/Empty.vue';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { router } from "@inertiajs/vue3";
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    components: {
        AppLayout,
        PrimaryButton,
        SearchInput,
        LoadingIsoLogo,
        Empty,
    },
    props: {
        requisitions: Object,
        filters: Object,
        statusOptions: {
            type: Array,
            default: () => [],
        },
        salesFrequencies: {
            type: Array,
            default: () => [],
        },
        can: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            loading: false,
            search: this.filters.search ?? '',
            showAll: this.filters.view !== 'mias',
            filtersForm: {
                type: this.filters.type ?? '',
                status: this.filters.status ?? '',
                origin: this.filters.origin ?? '',
                priority: this.filters.priority ?? '',
            },
        };
    },
    methods: {
        buildParams(page = null) {
            const params = {
                view: this.showAll ? 'todas' : 'mias',
                ...this.filtersForm,
            };

            if (this.search) {
                params.search = this.search;
            }
            if (page) {
                params.page = page;
            }
            if (this.filters.filter) {
                params.filter = this.filters.filter;
            }

            // Se limpian los filtros vacíos para no ensuciar la URL.
            return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined));
        },
        applyFilters() {
            router.get(route('requisitions.index', this.buildParams()), {}, {
                preserveState: true,
                replace: true,
                onStart: () => (this.loading = true),
                onFinish: () => (this.loading = false),
            });
        },
        toggleView() {
            this.applyFilters();
        },
        handlePageChange(page) {
            router.get(route('requisitions.index', this.buildParams(page)), {}, {
                preserveState: true,
                replace: true,
            });
        },
        handleRowClick(row) {
            router.get(route('requisitions.show', row.id));
        },
        goToCreate(type) {
            router.visit(route('requisitions.create', { type }));
        },
        editRequisition(row) {
            router.get(route('requisitions.edit', row.id));
        },
        // La frecuencia es el único campo editable en línea desde el listado.
        saveFrequency(row) {
            router.patch(route('requisitions.sale-frequency', row.id), {
                sale_frequency: row.sale_frequency ?? null,
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => ElMessage.success('Frecuencia actualizada.'),
                onError: () => ElMessage.error('No se pudo actualizar la frecuencia.'),
            });
        },
        async confirmDelete(row) {
            try {
                await ElMessageBox.confirm(
                    `¿Eliminar la requisición ${row.folio}? Esta acción no se puede deshacer.`,
                    'Confirmar eliminación',
                    { confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', type: 'warning' },
                );
            } catch (error) {
                return;
            }

            router.delete(route('requisitions.destroy', row.id), {
                preserveScroll: true,
                onSuccess: () => ElMessage.success(`Requisición ${row.folio} eliminada.`),
                onError: () => ElMessage.error('No se pudo eliminar la requisición.'),
            });
        },
        // --- Columna Producto / Cantidad ---------------------------------
        hasProducts(row) {
            return Boolean(row.items?.length) || Boolean(row.product);
        },
        productName(row) {
            const item = (row.items ?? [])[0];

            return item?.product?.name ?? item?.description ?? row.product?.name ?? 'Mercancía';
        },
        productQuantity(row) {
            const item = (row.items ?? [])[0];

            return item ? item.quantity : row.quantity;
        },
        extraProductsLabel(row) {
            const extra = (row.items?.length ?? 0) - 1;

            return extra > 0 ? `+${extra} producto(s)` : '';
        },
        // Detalle de cada partida para el tooltip: nombre y cantidad por renglón.
        productRows(row) {
            const items = row.items ?? [];

            if (!items.length) {
                return [{
                    name: row.product?.name ?? 'Mercancía',
                    quantity: this.formatQuantity(row.quantity),
                }];
            }

            return items.map(item => ({
                name: item.product?.name ?? item.description ?? 'Mercancía',
                quantity: this.formatQuantity(item.quantity),
            }));
        },
        // La frecuencia de venta solo aplica a requisiciones levantadas por vendedor.
        frequencyLabel(row) {
            if (row.sale_frequency) return row.sale_frequency;

            return row.origin === 'Almacén' ? 'N/A' : '—';
        },
        // Filas con incidencia (rojo tenue) y arribo confirmado (verde tenue).
        rowClassName({ row }) {
            if (row.arrival_status === 'Incidencia') return 'req-row-incidencia';
            if (row.arrival_status === 'Arribado') return 'req-row-arribado';

            return '';
        },
        formatDate(dateString) {
            if (!dateString) return '—';
            return format(new Date(dateString), "d 'de' MMM, yyy", { locale: es });
        },
        formatQuantity(quantity) {
            if (quantity === null || quantity === undefined) return '—';
            return Number(quantity).toLocaleString('es-MX');
        },
        formatCurrency(value, currency = 'MXN') {
            if (value === null || value === undefined || value === '') return '—';
            return Number(value).toLocaleString('es-MX', {
                style: 'currency',
                currency: currency || 'MXN',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        getStatusTagType(status) {
            const map = {
                'Pendiente de cotización': 'info',
                'Pendiente de negociación': 'info',
                'Pendiente de autorización': 'warning',
                'Rechazada': 'danger',
                'Pendiente de pago': 'danger',
                'Autorizada': 'success',
                'Aprobada': 'success',
                'Pagada': 'success',
                'Arribo confirmado': 'success',
                'Completada': 'success',
                'Pedido a fábrica': 'primary',
                'En tránsito': 'warning',
            };
            return map[status] ?? 'info';
        },
        getPriorityTagType(priority) {
            const map = { Baja: 'info', Media: 'primary', Alta: 'warning', Urgente: 'danger' };
            return map[priority] ?? 'info';
        },
        getArrivalColor(row) {
            const map = {
                Pendiente: '#9CA3AF',
                'En tránsito': '#F59E0B',
                Incidencia: '#EF4444',
                Arribado: '#22C55E',
            };
            return map[row.arrival_status] ?? '#9CA3AF';
        },
    },
};
</script>

<style>
/* Filas del listado de requisiciones según el estatus de arribo.
   Los estilos de el-table se renderizan fuera del scope del componente,
   por eso el bloque no es scoped. */
.el-table .req-row-incidencia td.el-table__cell {
    background-color: #fee2e2 !important;
}

.el-table .req-row-arribado td.el-table__cell {
    background-color: #dcfce7 !important;
}

.dark .el-table .req-row-incidencia td.el-table__cell {
    background-color: rgba(239, 68, 68, 0.16) !important;
}

.dark .el-table .req-row-arribado td.el-table__cell {
    background-color: rgba(34, 197, 94, 0.16) !important;
}

.el-table .req-row-incidencia:hover > td.el-table__cell {
    background-color: #fecaca !important;
}

.el-table .req-row-arribado:hover > td.el-table__cell {
    background-color: #bbf7d0 !important;
}

.dark .el-table .req-row-incidencia:hover > td.el-table__cell {
    background-color: rgba(239, 68, 68, 0.26) !important;
}

.dark .el-table .req-row-arribado:hover > td.el-table__cell {
    background-color: rgba(34, 197, 94, 0.26) !important;
}
</style>
