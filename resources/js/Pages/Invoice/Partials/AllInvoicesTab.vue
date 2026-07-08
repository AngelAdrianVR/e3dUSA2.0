<template>
    <div>
        <el-table :data="invoices.data" style="width: 100%" stripe @row-click="handleRowClick" class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">
            <el-table-column prop="folio" label="Folio" width="120" />
            <el-table-column label="OV" width="120">
                <template #default="scope">
                    <a @click.stop="" class="text-blue-500 hover:underline" :href="route('sales.show', scope.row.sale_id)" target="_blank">
                        OV-{{ scope.row.sale_id.toString().padStart(4, '0') }}
                    </a>
                </template>
            </el-table-column>
            <el-table-column prop="sale.branch.name" label="Cliente" min-width="150" />
            <el-table-column label="Monto" width="150">
                <template #default="scope">
                    ${{ formatCurrency(scope.row.amount) }}
                </template>
            </el-table-column>
            <el-table-column label="No. Factura" width="150">
                <template #default="scope">
                    {{ scope.row.installment_number }} de {{ scope.row.total_installments }}
                </template>
            </el-table-column>
            <el-table-column label="Vencimiento" width="180">
                <template #default="scope">
                    <span :class="{ 'text-red-500 font-bold': isOverdue(scope.row) }">
                        {{ formatDate(scope.row.due_date) }}
                    </span>
                </template>
            </el-table-column>
            <el-table-column label="Estatus" width="150">
                <template #default="scope">
                    <el-tag :type="getStatusTag(scope.row.status)">{{ scope.row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="Acciones" width="80" align="center">
                <template #default="scope">
                    <el-dropdown trigger="click" @command="handleCommand">
                        <span @click.stop="" class="el-dropdown-link flex items-center justify-center size-7 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-full">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </span>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item v-if="canEdit(scope.row)" :command="{ action: 'edit', invoice: scope.row }">
                                    <i class="fa-solid fa-pencil mr-2"></i> Editar
                                </el-dropdown-item>
                                <el-dropdown-item :command="{ action: 'cancel', invoice: scope.row }">
                                    <i class="fa-solid fa-ban mr-2"></i> Cancelar
                                </el-dropdown-item>
                                <el-dropdown-item :command="{ action: 'delete', invoice: scope.row }">
                                    <i class="fa-solid fa-trash-can mr-2 text-red-500"></i> Eliminar
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
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
import { ElMessageBox, ElMessage } from 'element-plus';

export default {
    props: { invoices: Object },
    emits: ['page-change'],
    data() {
        return { currentPage: this.invoices.current_page };
    },
    methods: {
        formatCurrency(v) { return parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2 }); },
        formatDate(d) { return d ? format(new Date(d).setDate(new Date(d).getDate() + 1), "d 'de' MMM, yyyy", { locale: es }) : ''; },
        isOverdue(inv) { return ['Pendiente', 'Parcialmente pagada'].includes(inv.status) && new Date(inv.due_date) < new Date().setHours(0,0,0,0); },
        getStatusTag(s) {
            const tags = { Pagada: 'success', Pendiente: 'warning', Vencida: 'danger', Cancelada: 'info' };
            return tags[s] || 'primary';
        },
        canEdit(inv) { return !['Pagada', 'Cancelada', 'Vencida'].includes(inv.status); },
        handleRowClick(row) { router.get(route('invoices.show', row.id)); },
        handleCommand({ action, invoice }) {
            if (action === 'edit') router.get(route('invoices.edit', invoice.id));
            if (action === 'cancel') this.confirmAction('cancelar', () => router.put(route('invoices.cancel', invoice.id)));
            if (action === 'delete') this.confirmAction('eliminar', () => router.delete(route('invoices.destroy', invoice.id)));
        },
        confirmAction(text, callback) {
            ElMessageBox.confirm(`¿Estás seguro de ${text} esta factura?`, 'Confirmar', { type: 'warning' })
                .then(callback)
                .catch(() => {});
        }
    }
}
</script>