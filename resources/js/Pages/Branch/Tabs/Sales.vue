<template>
<p class="text-xs mb-1 ml-3">Últimas 20</p>
    <div v-if="sales.length">
        <el-table 
            :data="sales" 
            style="width: 100%" 
            max-height="600"
            @row-click="handleRowClick"
            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300"
            stripe>
            
            <el-table-column prop="id" label="Folio" width="120">
                <template #default="scope">
                    <span class="font-semibold">{{ 'OV-' + scope.row.id.toString().padStart(4, '0') }}</span>
                </template>
            </el-table-column>

            <el-table-column label="Productos" width="130">
                <template #default="scope">
                    <el-tooltip v-if="scope.row.sale_products?.length" placement="top">
                        <template #content>
                            <ul class="list-disc list-inside text-xs">
                                <li v-for="item in scope.row.sale_products" :key="item.id">
                                    ({{ item.quantity }}) {{ item.product.name }}
                                </li>
                            </ul>
                        </template>
                        <span class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                            {{ scope.row.sale_products.length }} producto(s)
                        </span>
                    </el-tooltip>
                    <span v-else class="text-xs text-gray-400">N/A</span>
                </template>
            </el-table-column>

            <el-table-column label="Creado por" width="150">
                <template #default="scope">
                    {{ scope.row.user?.name ?? 'N/A' }}
                </template>
            </el-table-column>

            <el-table-column label="Creado el" width="150">
                <template #default="scope">
                    {{ formatDate(scope.row.created_at) }}
                </template>
            </el-table-column>

            <el-table-column prop="status" label="Estatus" width="130">
                <template #default="scope">
                    {{ scope.row.status }}
                </template>
            </el-table-column>

            <el-table-column label="Autorizado" width="100" align="center">
                <template #default="scope">
                    <el-tooltip v-if="scope.row.authorized_at" placement="top">
                        <template #content>
                            Autorizado por: {{ scope.row.authorized_user_name }} <br>
                            Fecha: {{ formatDate(scope.row.authorized_at) }}
                        </template>
                        <i class="fa-solid fa-check-double text-green-500 text-lg"></i>
                    </el-tooltip>
                    <p v-else class="text-xs text-gray-400">Pendiente</p>
                </template>
            </el-table-column>

            <el-table-column label="Monto Total" align="right" width="130">
                <template #default="scope">
                    <span class="font-semibold">{{ formatCurrency(scope.row.total_amount) }}</span>
                </template>
            </el-table-column>

            <el-table-column label="Cotización" width="120" align="center">
                <template #default="scope">
                    <a v-if="scope.row.quote_id" @click.stop
                        :href="route('quotes.show', scope.row.quote_id)" target="_blank"
                        class="text-blue-500 hover:underline">
                        COT-{{ String(scope.row.quote_id).padStart(4, '0') }}
                    </a>
                    <span v-else class="text-gray-400">N/A</span>
                </template>
            </el-table-column>

        </el-table>
    </div>
    <div v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">
        No hay ventas registradas para este cliente.
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    props: {
        sales: Array,
    },
    methods: {
        handleRowClick(row) {
            router.get(route('sales.show', row.id));
        },
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "d MMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            const num = Number(value);
            return num.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
    }
}
</script>
