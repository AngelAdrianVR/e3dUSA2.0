<template>
    <div class="rounded-lg border border-gray-200 dark:border-slate-700 p-3">
        <div class="flex items-center justify-between flex-wrap gap-1">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                <i class="fa-solid fa-layer-group mr-1"></i> Precios especiales por volumen
            </p>
            <span v-if="rows.length" class="text-xs font-medium text-gray-400 dark:text-gray-500">
                {{ rows.length }} rango{{ rows.length === 1 ? '' : 's' }}
            </span>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Define rangos de cantidad con su precio especial. Si dejas <strong>Hasta</strong> vacío, el precio aplica en adelante.
            Los rangos se guardan en la moneda seleccionada en este formulario.
        </p>

        <p v-if="!rows.length" class="mt-2 text-xs italic text-gray-400 dark:text-gray-500">
            Sin rangos registrados.
        </p>

        <div v-for="(row, index) in rows" :key="row._uid" class="mt-3">
            <div class="grid grid-cols-12 gap-2 items-center">
                <div class="col-span-3">
                    <el-input v-model="row.min_quantity" type="number" min="0" step="any" size="small"
                        placeholder="Desde" @input="emitChange" />
                </div>
                <div class="col-span-4">
                    <el-input v-model="row.max_quantity" type="number" min="0" step="any" size="small"
                        placeholder="Hasta (vacío = en adelante)" @input="emitChange" />
                </div>
                <div class="col-span-3">
                    <el-input v-model="row.price" type="number" min="0" step="0.01" size="small"
                        placeholder="Precio" @input="emitChange">
                        <template #prepend>$</template>
                    </el-input>
                </div>
                <div class="col-span-2 flex justify-end">
                    <button type="button" @click="removeRow(index)"
                        class="size-7 flex items-center justify-center rounded-md text-red-500 bg-red-100 hover:bg-red-200 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                </div>
            </div>
            <p v-if="rowError(index)" class="text-red-500 text-xs mt-1">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ rowError(index) }}
            </p>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <button type="button" @click="addRow"
                class="flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                <i class="fa-solid fa-plus mr-1"></i> Agregar rango
            </button>
            <span class="text-xs text-gray-400 dark:text-gray-500">Moneda: {{ currency }}</span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'VolumePricesEditor',
    props: {
        modelValue: {
            type: Array,
            default: () => [],
        },
        currency: {
            type: String,
            default: 'MXN',
        },
    },
    emits: ['update:modelValue', 'change'],
    data() {
        return {
            rows: [],
            initialSnapshot: '[]',
            lastEmitted: null,
            uidCounter: 0,
        };
    },
    computed: {
        // Errores por fila: cantidades y precios válidos, sin traslapes.
        rowErrors() {
            const errors = {};
            const minOf = (row) => this.numberOrNull(row.min_quantity);
            const maxOf = (row) => this.numberOrNull(row.max_quantity);

            this.rows.forEach((row, index) => {
                const min = minOf(row);
                const price = Number(row.price);

                if (min === null || min <= 0) {
                    errors[index] = 'La cantidad "desde" debe ser mayor a 0.';
                } else if (row.price === null || row.price === '' || isNaN(price) || price <= 0) {
                    errors[index] = 'Ingresa un precio mayor a 0 para este rango.';
                } else {
                    const max = maxOf(row);
                    if (max !== null && max < min) {
                        errors[index] = 'La cantidad "hasta" debe ser mayor o igual a la cantidad "desde".';
                    }
                }
            });

            const ordered = this.rows
                .map((row, index) => ({ index, min: minOf(row), max: maxOf(row) }))
                .filter((item) => item.min !== null)
                .sort((a, b) => a.min - b.min);

            for (let i = 1; i < ordered.length; i++) {
                const previous = ordered[i - 1];
                const current = ordered[i];
                if (errors[current.index]) continue;

                if (previous.max === null) {
                    errors[current.index] = 'El rango anterior es abierto (sin "hasta"), por lo que no puede existir otro rango después.';
                } else if (current.min <= previous.max) {
                    errors[current.index] = 'Este rango se traslapa con el rango anterior.';
                }
            }

            return errors;
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(value) {
                // Evita reconstruir las filas cuando el arreglo recibido es el que acabamos de emitir.
                const incoming = JSON.stringify(this.cleanRows(value ?? []));
                if (incoming === this.lastEmitted) return;

                this.rows = (value ?? []).map((row) => ({ ...row, _uid: ++this.uidCounter }));
                this.initialSnapshot = incoming;
                this.lastEmitted = incoming;
            },
        },
    },
    methods: {
        numberOrNull(value) {
            if (value === null || value === undefined || value === '') return null;
            const number = Number(value);
            return isNaN(number) ? null : number;
        },
        cleanRows(rows) {
            return rows.map((row) => ({
                min_quantity: row.min_quantity ?? null,
                max_quantity: this.numberOrNull(row.max_quantity),
                price: row.price ?? null,
                currency: row.currency ?? this.currency,
            }));
        },
        addRow() {
            const last = this.rows[this.rows.length - 1];
            let suggestedMin = 1;

            if (last) {
                const lastMax = this.numberOrNull(last.max_quantity);
                const lastMin = this.numberOrNull(last.min_quantity);
                // Sugiere continuar a partir del rango anterior.
                if (lastMax !== null) suggestedMin = lastMax + 1;
                else if (lastMin !== null) suggestedMin = lastMin + 1;
            }

            this.rows.push({
                min_quantity: suggestedMin,
                max_quantity: null,
                price: null,
                currency: this.currency,
                _uid: ++this.uidCounter,
            });
            this.emitChange();
        },
        removeRow(index) {
            this.rows.splice(index, 1);
            this.emitChange();
        },
        emitChange() {
            // Todos los rangos se guardan con la moneda activa del formulario.
            this.rows.forEach((row) => { row.currency = this.currency; });

            const clean = this.cleanRows(this.rows);
            this.lastEmitted = JSON.stringify(clean);
            this.$emit('update:modelValue', clean);
            this.$emit('change', { dirty: this.lastEmitted !== this.initialSnapshot });
        },
        rowError(index) {
            return this.rowErrors[index] ?? '';
        },
        /**
         * Devuelve el primer error de validación (con número de rango) o null si todo es válido.
         */
        validate() {
            const errors = this.rowErrors;
            const indexes = Object.keys(errors).map(Number).sort((a, b) => a - b);

            if (!indexes.length) return null;

            return `Rango ${indexes[0] + 1}: ${errors[indexes[0]]}`;
        },
    },
};
</script>
