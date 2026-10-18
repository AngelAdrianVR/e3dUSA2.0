<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div>
                <p class="text-gray-500 dark:text-gray-400">Moneda</p>
                <p class="font-semibold text-gray-800 dark:text-gray-100">{{ currency ?? 'No definida' }}</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Partidas cotizadas</p>
                <p class="font-semibold text-gray-800 dark:text-gray-100">{{ items.length }}</p>
            </div>
            <div v-if="quotationFile">
                <p class="text-gray-500 dark:text-gray-400">Archivo de la cotización</p>
                <div class="mt-1">
                    <FileView :file="quotationFile" />
                </div>
            </div>
        </div>

        <el-table :data="items" style="width: 100%" stripe max-height="320"
            class="dark:!bg-slate-900 dark:!text-gray-300">
            <el-table-column label="Producto" min-width="220">
                <template #default="scope">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">
                        {{ scope.row.product?.name ?? scope.row.description ?? 'Sin producto de catálogo' }}
                    </p>
                    <p v-if="scope.row.product?.code" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ scope.row.product.code }}
                    </p>
                </template>
            </el-table-column>
            <el-table-column label="Cantidad" width="100" align="right">
                <template #default="scope">{{ formatQuantity(scope.row.quantity) }}</template>
            </el-table-column>
            <el-table-column label="Precio unitario" width="150" align="right">
                <template #default="scope">
                    <span v-if="hasUnitCost(scope.row)">{{ formatCurrency(scope.row.unit_cost) }}</span>
                    <span v-else class="text-xs text-gray-400">—</span>
                </template>
            </el-table-column>
            <el-table-column label="Importe" width="150" align="right">
                <template #default="scope">
                    <span v-if="hasUnitCost(scope.row)">{{ formatCurrency(itemAmount(scope.row)) }}</span>
                    <span v-else class="text-xs text-gray-400">—</span>
                </template>
            </el-table-column>
        </el-table>

        <div class="flex justify-end">
            <dl class="w-full sm:w-96 space-y-1.5 text-sm">
                <div v-if="taxEnabled" class="flex items-center justify-between text-gray-700 dark:text-gray-300">
                    <dt>Subtotal</dt>
                    <dd class="font-semibold">{{ formatCurrency(subtotal) }}</dd>
                </div>
                <div v-if="taxEnabled" class="flex items-center justify-between text-amber-700 dark:text-amber-400">
                    <dt>IVA ({{ taxRateLabel }}%)</dt>
                    <dd class="font-semibold">{{ formatCurrency(taxAmount) }}</dd>
                </div>
                <div class="flex items-center justify-between border-t border-gray-200 dark:border-slate-700 pt-2 text-base">
                    <dt class="font-bold text-gray-800 dark:text-gray-100">{{ taxEnabled ? 'Total' : 'Total cotizado' }}</dt>
                    <dd class="font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(total) }}</dd>
                </div>
            </dl>
        </div>

        <p v-if="!taxEnabled" class="text-xs text-gray-500 dark:text-gray-400">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Esta cotización no incluye IVA; el total corresponde al costo cotizado.
        </p>
    </div>
</template>

<script>
import FileView from "@/Components/MyComponents/FileView.vue";

export default {
    components: { FileView },
    props: {
        requisition: {
            type: Object,
            required: true,
        },
    },
    computed: {
        items() {
            return this.requisition.items ?? [];
        },
        currency() {
            return this.requisition.quoted_currency ?? null;
        },
        taxEnabled() {
            return Boolean(this.requisition.quoted_tax_enabled);
        },
        taxRate() {
            return Number(this.requisition.quoted_tax_rate ?? 16);
        },
        taxRateLabel() {
            return this.taxRate.toLocaleString('es-MX', { maximumFractionDigits: 2 });
        },
        quotationFile() {
            return (this.requisition.media ?? []).find(file => file.collection_name === 'quotations') ?? null;
        },
        hasStoredSubtotal() {
            const cost = this.requisition.quoted_cost;

            return cost !== null && cost !== undefined && cost !== '';
        },
        // El subtotal guardado es la fuente de verdad; si falta, se suman los importes capturados.
        subtotal() {
            if (this.hasStoredSubtotal) {
                return Number(this.requisition.quoted_cost) || 0;
            }

            return this.items.reduce((sum, item) => sum + (this.itemAmount(item) ?? 0), 0);
        },
        taxAmount() {
            if (!this.taxEnabled) return 0;

            const stored = Number(this.requisition.quoted_tax_amount);
            if (this.hasStoredSubtotal && Number.isFinite(stored)) {
                return stored;
            }

            return Math.round(this.subtotal * this.taxRate) / 100;
        },
        total() {
            if (!this.taxEnabled) return this.subtotal;

            const stored = Number(this.requisition.quoted_total);
            if (this.hasStoredSubtotal && Number.isFinite(stored)) {
                return stored;
            }

            return Math.round((this.subtotal + this.taxAmount) * 100) / 100;
        },
    },
    methods: {
        hasUnitCost(item) {
            return item.unit_cost !== null && item.unit_cost !== undefined && item.unit_cost !== '';
        },
        itemAmount(item) {
            if (!this.hasUnitCost(item)) return null;

            return (Number(item.unit_cost) || 0) * (Number(item.quantity) || 0);
        },
        formatQuantity(value) {
            return (Number(value) || 0).toLocaleString('es-MX', { maximumFractionDigits: 2 });
        },
        formatCurrency(value) {
            if (value === null || value === undefined || value === '') return 'Sin registrar';

            return Number(value).toLocaleString('es-MX', {
                style: 'currency',
                currency: this.currency || 'MXN',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    },
};
</script>
