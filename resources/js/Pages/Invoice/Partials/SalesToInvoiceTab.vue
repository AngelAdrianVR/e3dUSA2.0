<template>
    <div>
        <el-table :data="sales.data" style="width: 100%" stripe class="dark:!bg-slate-900 dark:!text-gray-300">
            <el-table-column label="Folio OV" width="120">
                <template #default="scope">
                    <a class="text-blue-500 hover:underline" :href="route('sales.show', scope.row.id)" target="_blank">
                        OV-{{ scope.row.id.toString().padStart(4, '0') }}
                    </a>
                </template>
            </el-table-column>
            <el-table-column prop="branch.name" label="Cliente" />
            <el-table-column label="Monto Total OV" width="160">
                <template #default="scope">
                    ${{ formatCurrency(scope.row.total_amount) }}
                </template>
            </el-table-column>
            <el-table-column label="Facturado" width="160">
                <template #default="scope">
                    <span :class="scope.row.total_invoiced > 0 ? 'text-green-500 font-bold' : ''">
                        ${{ formatCurrency(scope.row.total_invoiced) }}
                    </span>
                </template>
            </el-table-column>
            <el-table-column prop="invoices_count" label="Facturas" width="100" align="center" />
            <el-table-column label="Acciones" align="right" width="150">
                <template #default="scope">
                    <Link :href="route('invoices.create', { sale_id: scope.row.id })">
                        <el-button type="primary" plain size="small">Crear Factura</el-button>
                    </Link>
                </template>
            </el-table-column>
        </el-table>
        <div v-if="sales.total > 0" class="flex justify-center mt-6">
            <el-pagination 
                v-model:current-page="currentPage" 
                :page-size="sales.per_page" 
                :total="sales.total" 
                layout="prev, pager, next" 
                background 
                @current-change="$emit('page-change', $event)" 
            />
        </div>
    </div>
</template>

<script>
import { Link } from "@inertiajs/vue3";
export default {
    props: { 
        sales: Object 
    },
    components: { Link },
    emits: ['page-change'],
    data() { 
        return { 
            currentPage: this.sales.current_page 
        }; 
    },
    watch: {
        // Mantiene sincronizada la página actual si los datos cambian (por ejemplo, al aplicar un filtro)
        'sales.current_page'(newVal) {
            this.currentPage = newVal;
        }
    },
    methods: {
        formatCurrency(v) { 
            return parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }); 
        }
    }
}
</script>