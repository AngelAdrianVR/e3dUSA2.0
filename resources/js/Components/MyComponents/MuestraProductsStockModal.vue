<template>
    <!-- Modal para capturar el stock actual al registrar productos nuevos como Muestra/Regalo -->
    <el-dialog
        :model-value="show"
        title="Registrar productos como Muestra/Regalo"
        width="540px"
        :close-on-click-modal="false"
        :close-on-press-escape="!processing"
        :show-close="!processing"
        @update:model-value="(value) => { if (!value) $emit('close'); }"
    >
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                Captura el <strong>stock actual</strong> (piezas disponibles) de los productos nuevos de la muestra.
                Se registrarán automáticamente en la categoría <strong>"Muestras y regalos"</strong> para poder agregarlos a la orden de venta.
            </p>

            <ul class="space-y-2">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="flex flex-wrap items-center justify-between gap-3 bg-gray-50 dark:bg-slate-800/60 rounded-md px-3 py-2 border border-gray-200 dark:border-slate-700"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ item.name }}</p>
                        <p v-if="item.quantity" class="text-[11px] text-gray-500 dark:text-gray-400">
                            Cantidad en la muestra: {{ item.quantity }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Stock actual</span>
                        <el-input-number
                            v-model="stocks[item.id]"
                            :min="0"
                            size="small"
                            controls-position="right"
                            class="!w-28"
                        />
                    </div>
                </li>
            </ul>

            <p v-if="!items.length" class="text-sm text-gray-500 dark:text-gray-400 italic">
                No hay productos nuevos por registrar.
            </p>
        </div>

        <template #footer>
            <div class="flex justify-end space-x-2">
                <el-button @click="$emit('close')" :disabled="processing">Cancelar</el-button>
                <el-button type="primary" :loading="processing" @click="confirm">
                    {{ confirmText }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script>
/**
 * Modal de captura de stock para los productos nuevos del seguimiento de muestra
 * que se registran automáticamente en la categoría "Muestras y regalos".
 *
 * Emite:
 *  - close
 *  - confirm (payload: [{ new_product_proposal_id, stock }])
 */
export default {
    name: 'MuestraProductsStockModal',
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        // [{ id, name, quantity }]
        items: {
            type: Array,
            default: () => [],
        },
        processing: {
            type: Boolean,
            default: false,
        },
        confirmText: {
            type: String,
            default: 'Registrar y continuar',
        },
    },
    emits: ['close', 'confirm'],
    data() {
        return {
            stocks: {},
        };
    },
    watch: {
        // Al abrir el modal (o cambiar los productos) el stock inicia en 0
        show(value) {
            if (value) {
                this.resetStocks();
            }
        },
        items() {
            if (this.show) {
                this.resetStocks();
            }
        },
    },
    methods: {
        resetStocks() {
            const stocks = {};
            (this.items || []).forEach(item => {
                stocks[item.id] = 0;
            });
            this.stocks = stocks;
        },
        confirm() {
            const payload = (this.items || []).map(item => ({
                new_product_proposal_id: item.id,
                stock: Number(this.stocks[item.id] ?? 0),
            }));

            this.$emit('confirm', payload);
        },
    },
};
</script>
