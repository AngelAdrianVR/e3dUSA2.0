<template>
    <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h3 class="font-semibold text-gray-800 dark:text-white">
                <i class="fa-solid fa-list-check mr-2 text-primary"></i>
                Órdenes con seguimiento pendiente
            </h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ sales.length }} folio(s)</span>
                <el-select
                    v-model="selectedSort"
                    size="small"
                    class="!w-40"
                    @change="$emit('sort-change', selectedSort)"
                >
                    <el-option label="Más antiguos" value="oldest" />
                    <el-option label="Más recientes" value="newest" />
                </el-select>
            </div>
        </div>

        <el-table
            v-if="sales.length"
            :data="sales"
            size="small"
            stripe
            max-height="360"
            style="width: 100%"
            class="dark:!bg-slate-900 dark:!text-gray-300"
        >
            <el-table-column label="Folio" width="110">
                <template #default="scope">
                    <button
                        @click="$inertia.visit(route('sales.show', scope.row.id))"
                        class="font-semibold text-primary hover:underline"
                    >
                        {{ scope.row.folio }}
                    </button>
                </template>
            </el-table-column>

            <el-table-column prop="client" label="Cliente" min-width="140" />

            <el-table-column label="Estatus" width="140">
                <template #default="scope">
                    <el-tag :type="statusTagType(scope.row.status)" size="small">
                        {{ scope.row.status }}
                    </el-tag>
                </template>
            </el-table-column>

            <el-table-column label="Creado" width="120">
                <template #default="scope">
                    {{ formatDate(scope.row.created_at) }}
                </template>
            </el-table-column>

            <el-table-column label="Término de producción" width="180">
                <template #default="scope">
                    <el-tooltip
                        v-if="scope.row.estimated_finish_at && dateUrgency(scope.row.estimated_finish_at) !== 'none'"
                        placement="top"
                        :content="dateUrgency(scope.row.estimated_finish_at) === 'overdue'
                            ? 'Fecha de término vencida'
                            : 'Vence en los próximos 3 días'"
                    >
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold border"
                            :class="dateUrgencyClass(scope.row.estimated_finish_at)">
                            <i :class="dateUrgencyIcon(scope.row.estimated_finish_at)"></i>
                            {{ formatDate(scope.row.estimated_finish_at) }}
                        </span>
                    </el-tooltip>
                    <span v-else-if="scope.row.estimated_finish_at" class="text-xs text-gray-600 dark:text-gray-300">
                        {{ formatDate(scope.row.estimated_finish_at) }}
                    </span>
                    <span v-else class="text-xs text-gray-400">Sin fecha</span>
                </template>
            </el-table-column>

            <el-table-column label="Productos" width="150">
                <template #default="scope">
                    <el-tooltip v-if="scope.row.products?.length" placement="top">
                        <template #content>
                            <ul class="list-disc list-inside text-xs">
                                <li v-for="item in scope.row.products" :key="item.id">
                                    ({{ item.quantity }}) {{ item.name }}
                                </li>
                            </ul>
                        </template>
                        <span class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                            {{ scope.row.products.length }} producto(s)
                        </span>
                    </el-tooltip>
                    <span v-else class="text-xs text-gray-400">N/A</span>
                </template>
            </el-table-column>
        </el-table>

        <p v-else class="text-sm text-center text-gray-500 dark:text-gray-400 py-6">
            No tienes órdenes con seguimiento pendiente.
        </p>
    </div>
</template>

<script>
export default {
    emits: ['sort-change'],
    props: {
        sales: {
            type: Array,
            default: () => [],
        },
        // Orden actual de la lista: 'oldest' | 'newest'
        sort: {
            type: String,
            default: 'oldest',
        },
    },
    data() {
        return {
            selectedSort: this.sort,
        };
    },
    watch: {
        sort(value) {
            this.selectedSort = value;
        },
    },
    methods: {
        statusTagType(status) {
            const statusMap = {
                'Pendiente': 'info',
                'Autorizada': 'primary',
                'En Proceso': 'warning',
                'En Producción': 'primary',
                'Preparando Envío': 'success',
            };
            return statusMap[status] || '';
        },
        // Interpreta una fecha YYYY-MM-DD (o ISO) sin desfase de zona horaria.
        parseDateOnly(dateString) {
            const [y, m, d] = String(dateString).split('T')[0].split('-').map(Number);
            if (!y || !m || !d) return null;
            return new Date(y, m - 1, d);
        },
        // Formatea fechas YYYY-MM-DD sin desfase de zona horaria.
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = this.parseDateOnly(dateString);
            if (!date) return dateString;
            return date.toLocaleDateString('es-MX', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },
        // Días restantes respecto a hoy (negativo si ya venció).
        daysUntil(dateString) {
            const due = this.parseDateOnly(dateString);
            if (!due) return null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return Math.round((due - today) / 86400000);
        },
        isOverdue(dateString) {
            const days = this.daysUntil(dateString);
            return days !== null && days < 0;
        },
        // Próxima a vencer: hoy o dentro de los siguientes 3 días.
        isExpiringSoon(dateString) {
            const days = this.daysUntil(dateString);
            return days !== null && days >= 0 && days <= 3;
        },
        // 'overdue' (rojo) | 'soon' (ámbar) | 'none'
        dateUrgency(dateString) {
            if (this.isOverdue(dateString)) return 'overdue';
            if (this.isExpiringSoon(dateString)) return 'soon';
            return 'none';
        },
        dateUrgencyClass(dateString) {
            const urgency = this.dateUrgency(dateString);
            if (urgency === 'overdue') {
                return 'bg-red-100 text-red-700 border-red-300 dark:bg-red-900/40 dark:text-red-300 dark:border-red-700';
            }
            if (urgency === 'soon') {
                return 'bg-amber-100 text-amber-700 border-amber-300 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-700';
            }
            return '';
        },
        dateUrgencyIcon(dateString) {
            const urgency = this.dateUrgency(dateString);
            if (urgency === 'overdue') return 'fa-solid fa-triangle-exclamation';
            if (urgency === 'soon') return 'fa-solid fa-clock';
            return '';
        },
    },
};
</script>
