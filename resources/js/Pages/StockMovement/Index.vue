<template>
    <AppLayout title="Movimientos de inventario">
        <Head title="Movimientos de inventario" />
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Movimientos de inventario
        </h2>

        <div class="py-7">
            <div class="max-w-[88rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- Filtros -->
                    <div class="mb-5 p-4 rounded-xl bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-slate-700">
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Buscar</label>
                                <el-input v-model="search" placeholder="Código, nombre o notas" clearable
                                    class="!w-64" @keyup.enter="applyFilters" @clear="applyFilters">
                                    <template #prefix>
                                        <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
                                    </template>
                                </el-input>
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Tipo de movimiento</label>
                                <el-select v-model="typeFilter" placeholder="Entradas y salidas" clearable class="!w-48"
                                    @change="applyFilters">
                                    <el-option label="Entradas" value="Entrada" />
                                    <el-option label="Salidas" value="Salida" />
                                </el-select>
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Rango de fechas</label>
                                <el-date-picker v-model="dateRangeFilter" type="daterange" value-format="YYYY-MM-DD"
                                    unlink-panels range-separator="a" start-placeholder="Desde" end-placeholder="Hasta"
                                    class="!w-64" @change="applyFilters" />
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Ordenar por</label>
                                <el-select v-model="sortFilter" class="!w-52" @change="applyFilters">
                                    <el-option label="Más recientes primero" value="recent" />
                                    <el-option label="Más antiguos primero" value="oldest" />
                                </el-select>
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Productos</label>
                                <el-checkbox v-model="withoutComponentsFilter" label="Sin componentes" border
                                    class="!h-8 !px-3" @change="applyFilters" />
                            </div>

                            <el-button type="primary" @click="applyFilters">
                                <i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar
                            </el-button>

                            <el-button v-if="hasActiveFilters" plain @click="clearFilters">
                                <i class="fa-solid fa-broom mr-1"></i> Limpiar
                            </el-button>

                            <span class="ml-auto self-center flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                <span>
                                    <i class="fa-solid fa-list-ul mr-1"></i>
                                    {{ movements.total }} {{ movements.total === 1 ? 'movimiento' : 'movimientos' }}
                                </span>
                                <span class="border-l border-gray-300 dark:border-gray-600 pl-3">
                                    <i class="fa-solid fa-cubes mr-1"></i>
                                    {{ productsCount }} {{ productsCount === 1 ? 'producto distinto' : 'productos distintos' }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Barra de acciones masivas -->
                    <div v-if="canDelete && selectedRows.length"
                        class="mb-3 flex flex-wrap items-center justify-between gap-3 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900">
                        <span class="text-sm font-medium text-red-700 dark:text-red-300">
                            <i class="fa-solid fa-square-check mr-1"></i>
                            {{ selectedRows.length }} {{ selectedRows.length === 1 ? 'movimiento seleccionado' : 'movimientos seleccionados' }}
                        </span>
                        <div class="flex items-center gap-2">
                            <el-button text @click="clearSelection">Quitar selección</el-button>
                            <el-button type="danger" :loading="bulkDeleting" @click="confirmBulkDelete">
                                <i class="fa-solid fa-trash-can mr-1"></i> Eliminar seleccionados
                            </el-button>
                        </div>
                    </div>

                    <!-- Tabla de movimientos -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>

                        <el-table v-if="movements.data.length" ref="movementsTable" :data="movements.data"
                            style="width: 100%" stripe max-height="600" class="dark:!bg-slate-900 dark:!text-gray-300"
                            @selection-change="handleSelectionChange">
                            <el-table-column v-if="canDelete" type="selection" width="48" />
                            <el-table-column label="Imagen" width="90">
                                <template #default="scope">
                                    <figure class="border rounded-md size-14 flex items-center justify-center bg-white p-1">
                                        <el-image v-if="productImage(scope.row)" :src="productImage(scope.row)"
                                            :preview-src-list="[productImage(scope.row)]" fit="contain" preview-teleported
                                            :hide-on-click-modal="true" class="object-contain h-full w-full cursor-zoom-in" />
                                        <span v-else class="text-gray-400 text-[10px] text-center">Sin imagen</span>
                                    </figure>
                                </template>
                            </el-table-column>

                            <el-table-column label="Código" width="140">
                                <template #default="scope">
                                    <span class="font-mono text-sm">{{ scope.row.product?.code || '—' }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Producto" min-width="220">
                                <template #default="scope">
                                    {{ scope.row.product?.name || 'Producto eliminado' }}
                                </template>
                            </el-table-column>

                            <el-table-column label="Tipo" width="110">
                                <template #default="scope">
                                    <el-tag :type="scope.row.type === 'Entrada' ? 'success' : 'danger'" effect="light">
                                        {{ scope.row.type }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column label="Cantidad" width="140" align="right">
                                <template #default="scope">
                                    <span :class="scope.row.type === 'Entrada' ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'"
                                        class="font-semibold">
                                        {{ scope.row.type === 'Entrada' ? '+' : '−' }}{{ formatQuantity(scope.row.quantity_change) }}
                                    </span>
                                    <span class="text-xs text-gray-500 ml-1">{{ scope.row.product?.measure_unit }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Almacén" width="170">
                                <template #default="scope">
                                    <span class="text-gray-600 dark:text-gray-400">{{ scope.row.storage?.location || '—' }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Stock actual" width="140" align="right">
                                <template #default="scope">
                                    <span v-if="scope.row.storage"
                                        :class="Number(scope.row.storage.quantity) <= 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-700 dark:text-gray-300'"
                                        class="font-semibold">
                                        {{ formatQuantity(scope.row.storage.quantity) }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Notas" min-width="220">
                                <template #default="scope">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ scope.row.notes || '—' }}
                                    </span>
                                </template>
                            </el-table-column>

                            <el-table-column label="Fecha" width="180">
                                <template #default="scope">
                                    {{ formatDateTime(scope.row.created_at) }}
                                </template>
                            </el-table-column>

                            <el-table-column v-if="canDelete" align="right" width="70" fixed="right">
                                <template #default="scope">
                                    <button @click.stop="confirmDelete(scope.row)"
                                        class="size-8 inline-flex items-center justify-center rounded-full text-red-500 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                                        title="Eliminar movimiento y ajustar stock">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </template>
                            </el-table-column>
                        </el-table>

                        <template v-else>
                            <Empty v-if="hasActiveFilters" label="No se encontraron movimientos con los filtros aplicados" />
                            <Empty v-else label="Todavía no hay movimientos de inventario" />
                        </template>
                    </div>

                    <!-- Paginación -->
                    <div v-if="movements.total > 0" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="movements.current_page" :page-size="movements.per_page"
                            :total="movements.total" layout="total, prev, pager, next" background
                            @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import Empty from '@/Components/MyComponents/Empty.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import { Head, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    name: 'StockMovementIndex',
    components: {
        AppLayout,
        Empty,
        LoadingIsoLogo,
        Head,
    },
    props: {
        movements: Object,
        productsCount: {
            type: Number,
            default: 0,
        },
        filters: Object,
        canDelete: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            search: this.filters.search ?? '',
            typeFilter: this.filters.type ?? null,
            sortFilter: this.filters.sort ?? 'recent',
            withoutComponentsFilter: !!this.filters.without_components,
            dateRangeFilter: this.filters.date_from && this.filters.date_to
                ? [this.filters.date_from, this.filters.date_to]
                : [],
            loading: false,
            selectedRows: [],
            bulkDeleting: false,
        };
    },
    computed: {
        hasDateRange() {
            return Array.isArray(this.dateRangeFilter) && this.dateRangeFilter.length === 2;
        },
        hasActiveFilters() {
            return !!this.search || !!this.typeFilter || this.hasDateRange || this.sortFilter !== 'recent'
                || this.withoutComponentsFilter;
        },
    },
    methods: {
        productImage(movement) {
            return movement.product?.media?.[0]?.original_url || null;
        },
        formatQuantity(value) {
            return Math.abs(Number(value)).toLocaleString('es-MX', { maximumFractionDigits: 2 });
        },
        formatDateTime(value) {
            if (!value) return '—';
            return new Date(value).toLocaleString('es-MX', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            }).replace('.', '');
        },
        buildParams(extra = {}) {
            const params = { ...extra };
            if (this.search) params.search = this.search;
            if (this.typeFilter) params.type = this.typeFilter;
            if (this.withoutComponentsFilter) params.without_components = 1;
            if (this.hasDateRange) {
                params.date_from = this.dateRangeFilter[0];
                params.date_to = this.dateRangeFilter[1];
            }
            if (this.sortFilter) params.sort = this.sortFilter;
            return params;
        },
        applyFilters() {
            this.fetchData();
        },
        fetchData(page = 1) {
            this.clearSelection();
            this.loading = true;
            router.get(route('stock-movements.index', this.buildParams({ page })), {}, {
                preserveState: true,
                replace: true,
                onFinish: () => {
                    this.loading = false;
                },
            });
        },
        clearFilters() {
            this.search = '';
            this.typeFilter = null;
            this.sortFilter = 'recent';
            this.withoutComponentsFilter = false;
            this.dateRangeFilter = [];
            this.fetchData();
        },
        handlePageChange(page) {
            this.fetchData(page);
        },
        handleSelectionChange(rows) {
            this.selectedRows = rows;
        },
        clearSelection() {
            this.selectedRows = [];
            this.$refs.movementsTable?.clearSelection();
        },
        escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[char]));
        },
        /**
         * Agrupa los movimientos por almacén y calcula el ajuste neto que se aplicará al stock.
         */
        stockImpact(rows) {
            const grouped = new Map();

            rows.forEach((row) => {
                const key = row.storage_id ?? row.storage?.id ?? row.id;
                const quantity = Math.abs(Number(row.quantity_change) || 0);
                const adjustment = row.type === 'Entrada' ? -quantity : quantity;
                const entry = grouped.get(key) ?? {
                    product: row.product?.name ?? 'producto desconocido',
                    unit: row.product?.measure_unit ?? '',
                    stock: Number(row.storage?.quantity ?? 0),
                    hasStorage: !!row.storage,
                    adjustment: 0,
                };
                entry.adjustment += adjustment;
                grouped.set(key, entry);
            });

            const items = [...grouped.values()];

            // Almacenes cuyo ajuste dejaría el stock en negativo (se limitará a 0).
            return items.filter(item => item.hasStorage && item.stock + item.adjustment < 0);
        },
        stockWarningHtml(overflow) {
            if (!overflow.length) return '';

            const details = overflow.map((item) => {
                const unit = item.unit ? ` ${this.escapeHtml(item.unit)}` : '';
                const adjustment = `${item.adjustment < 0 ? '−' : '+'}${this.formatQuantity(item.adjustment)}`;

                return `<li class="mb-1"><strong>${this.escapeHtml(item.product)}</strong>: `
                    + `stock actual ${this.formatQuantity(item.stock)}${unit}, ajuste ${adjustment}${unit} `
                    + `→ quedará en 0${unit}.</li>`;
            }).join('');

            return `<div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-left text-sm text-amber-800 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-300">`
                + `<p class="font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> El ajuste supera el stock disponible</p>`
                + `<p class="mt-1">El stock no puede ser negativo, por lo que se limitará a 0:</p>`
                + `<ul class="mt-1 list-disc pl-5">${details}</ul></div>`;
        },
        showDeletionFeedback(page, successMessage) {
            const warning = page?.props?.flash?.warning;

            if (warning) {
                ElMessage({ type: 'warning', message: warning, duration: 6000, showClose: true });
                return;
            }

            ElMessage.success(successMessage);
        },
        confirmBulkDelete() {
            if (!this.selectedRows.length) return;

            const ids = this.selectedRows.map(row => row.id);
            const entries = this.selectedRows.filter(row => row.type === 'Entrada').length;
            const exits = this.selectedRows.length - entries;
            const breakdown = [
                entries ? `${entries} ${entries === 1 ? 'entrada' : 'entradas'}` : null,
                exits ? `${exits} ${exits === 1 ? 'salida' : 'salidas'}` : null,
            ].filter(Boolean).join(' y ');

            const overflow = this.stockImpact(this.selectedRows);

            ElMessageBox.confirm(
                `<p>Se eliminarán ${ids.length} ${ids.length === 1 ? 'movimiento' : 'movimientos'} (${breakdown}) y el stock se ajustará según su tipo: las entradas se restan y las salidas se devuelven. Esta acción no se puede deshacer.</p>`
                    + this.stockWarningHtml(overflow),
                'Eliminar movimientos',
                {
                    confirmButtonText: overflow.length ? 'Sí, eliminar y ajustar a 0' : 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                    iconColor: overflow.length ? '#F59E0B' : '#EF4444',
                    dangerouslyUseHTMLString: true,
                }
            ).then(() => {
                this.bulkDeleting = true;
                router.delete(route('stock-movements.bulk-destroy'), {
                    data: { ids },
                    preserveScroll: true,
                    onSuccess: (page) => {
                        this.clearSelection();
                        this.showDeletionFeedback(
                            page,
                            `${ids.length} ${ids.length === 1 ? 'movimiento eliminado' : 'movimientos eliminados'} y stock actualizado`
                        );
                    },
                    onError: (errors) => {
                        ElMessage.error(errors?.stock || errors?.ids || 'No se pudieron eliminar los movimientos');
                    },
                    onFinish: () => {
                        this.bulkDeleting = false;
                    },
                });
            }).catch(() => {});
        },
        confirmDelete(movement) {
            const quantity = `${this.formatQuantity(movement.quantity_change)} ${movement.product?.measure_unit ?? ''}`.trim();
            const detail = movement.type === 'Entrada'
                ? `Se eliminará la entrada de ${quantity} y esa cantidad se restará del stock actual del producto.`
                : `Se eliminará la salida de ${quantity} y esa cantidad se devolverá al stock del producto.`;

            const overflow = this.stockImpact([movement]);

            ElMessageBox.confirm(
                `<p>${this.escapeHtml(detail)} Esta acción no se puede deshacer.</p>`
                    + this.stockWarningHtml(overflow),
                'Eliminar movimiento',
                {
                    confirmButtonText: overflow.length ? 'Sí, eliminar y ajustar a 0' : 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                    iconColor: overflow.length ? '#F59E0B' : '#EF4444',
                    dangerouslyUseHTMLString: true,
                }
            ).then(() => {
                router.delete(route('stock-movements.destroy', movement.id), {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        this.clearSelection();
                        this.showDeletionFeedback(page, 'Movimiento eliminado y stock actualizado');
                    },
                    onError: (errors) => {
                        ElMessage.error(errors?.stock || 'No se pudo eliminar el movimiento');
                    },
                });
            }).catch(() => {});
        },
    },
};
</script>

<style>
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
