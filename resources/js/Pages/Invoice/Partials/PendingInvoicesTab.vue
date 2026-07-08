<template>
    <div>
        <el-table :data="invoices.data" style="width: 100%" stripe class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300" @row-click="handleRowClick">
            <el-table-column prop="folio" label="Folio" width="120" />
            <el-table-column prop="sale.branch.name" label="Cliente" min-width="150" />
            <el-table-column label="Monto Total" width="140">
                <template #default="scope">${{ formatCurrency(scope.row.amount) }}</template>
            </el-table-column>
            <el-table-column label="Pendiente" width="140">
                <template #default="scope">
                    <span class="font-bold text-amber-600">${{ formatCurrency(getPending(scope.row)) }}</span>
                </template>
            </el-table-column>
            <el-table-column label="Vencimiento" width="160">
                <template #default="scope">
                    <span :class="{ 'text-red-500 font-bold': isOverdue(scope.row) }">
                        {{ formatDate(scope.row.due_date) }}
                    </span>
                </template>
            </el-table-column>
            <el-table-column align="right" width="160">
                <template #default="scope">
                    <el-button @click.stop="$emit('register-payment', scope.row)" type="success" plain size="small">
                        Pagar
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
        <div v-if="invoices.total > 0" class="flex justify-center mt-6">
            <el-pagination 
                v-model:current-page="currentPage" 
                :page-size="invoices.per_page" 
                :total="invoices.total" 
                layout="prev, pager, next" 
                background 
                @current-change="$emit('page-change', $event)" 
            />
        </div>
    </div>
</template>

<script>
import { router } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    props: { invoices: Object },
    emits: ['register-payment', 'page-change'],
    data() { return { currentPage: this.invoices.current_page }; },
    methods: {
        formatCurrency(v) { return parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2 }); },
        formatDate(d) { return d ? format(new Date(d).setDate(new Date(d).getDate() + 1), "d 'de' MMM, yyyy", { locale: es }) : ''; },
        getPending(inv) {
            const paid = inv.payments?.reduce((acc, p) => acc + parseFloat(p.amount), 0) || 0;
            return Math.max(0, parseFloat(inv.amount) - paid);
        },
        isOverdue(inv) { return new Date(inv.due_date) < new Date().setHours(0,0,0,0); },
        handleRowClick(row) { router.get(route('invoices.show', row.id)); }
    }
}
</script>