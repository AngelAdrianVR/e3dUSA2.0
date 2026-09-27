<template>
    <!-- TARIFAS DE ENVÍO SUGERIDAS (mínimo número de cajas por familia) -->
    <div v-if="suggestions.length" class="mt-4 pt-3 border-t dark:border-gray-600">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-sm flex items-center">
                <i class="fa-solid fa-boxes-packing mr-2 text-amber-500"></i>
                Tarifas de envío sugeridas
            </h4>
            <el-tooltip placement="right" effect="dark">
                <template #content>
                    <div class="text-xs max-w-[260px] leading-relaxed">
                        Se calcula, con la ficha de especificaciones de caja de cada familia,
                        la combinación que cubre las piezas de la orden usando el
                        <b>menor número de cajas</b>.
                    </div>
                </template>
                <span class="size-5 rounded-full bg-gray-200 dark:bg-slate-700 text-[10px] flex items-center justify-center cursor-help">
                    <i class="fa-solid fa-info"></i>
                </span>
            </el-tooltip>
        </div>

        <div v-for="suggestion in suggestions" :key="suggestion.product_family_id"
            class="mb-3 last:mb-0 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">

            <!-- Familia y piezas de la orden -->
            <div class="bg-slate-100 dark:bg-slate-700/60 px-3 py-2 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="font-bold text-xs uppercase tracking-wide truncate">{{ suggestion.family_name }}</span>

                    <!-- Tooltip con todas las tarifas registradas de la familia -->
                    <el-tooltip v-if="suggestion.available_rates?.length" placement="top" effect="dark">
                        <template #content>
                            <div class="text-xs">
                                <p class="font-bold mb-2">
                                    Tarifas de {{ suggestion.family_name }}
                                    <span class="font-normal opacity-70">({{ suggestion.available_rates.length }})</span>
                                </p>
                                <ul class="space-y-1 max-h-64 overflow-y-auto pr-1">
                                    <li v-for="rate in suggestion.available_rates" :key="rate.quantity"
                                        class="flex items-center justify-between gap-4 whitespace-nowrap">
                                        <span class="font-semibold text-amber-600 w-20">
                                            {{ rate.quantity }} pza
                                        </span>
                                        <span class="opacity-90">
                                            {{ formatMeasure(rate.length_cm) }} × {{ formatMeasure(rate.width_cm) }} × {{ formatMeasure(rate.height_cm) }} cm
                                        </span>
                                        <span class="opacity-90 w-16 text-right">{{ formatMeasure(rate.weight_kg) }} kg</span>
                                        <i v-if="isSuggestedBox(suggestion, rate.quantity)"
                                            class="fa-solid fa-check text-green-400 w-3" title="Usada en la sugerencia"></i>
                                        <span v-else class="w-3"></span>
                                    </li>
                                </ul>
                            </div>
                        </template>
                        <span class="shrink-0 text-[10px] font-semibold rounded-full px-2 py-0.5 cursor-help border border-gray-300 dark:border-slate-500 bg-white/70 dark:bg-slate-800 text-gray-600 dark:text-gray-300">
                            <i class="fa-solid fa-list-ul mr-1"></i>Ver todas las tarifas
                        </span>
                    </el-tooltip>
                </div>
                <span class="shrink-0 text-[11px] bg-sky-600 text-white rounded-full px-2 py-0.5 font-semibold">
                    {{ suggestion.pieces }} pza
                </span>
            </div>

            <!-- Cajas sugeridas -->
            <ul v-if="suggestion.has_rates" class="divide-y divide-gray-100 dark:divide-gray-700">
                <li v-for="box in suggestion.boxes" :key="box.quantity" class="px-3 py-2">
                    <p class="text-xs font-semibold">
                        <span class="text-amber-600 dark:text-amber-400">{{ box.count }}x</span>
                        caja de {{ box.quantity }} pza
                    </p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        {{ formatMeasure(box.length_cm) }} × {{ formatMeasure(box.width_cm) }} × {{ formatMeasure(box.height_cm) }} cm
                        · {{ formatMeasure(box.weight_kg) }} kg c/u
                    </p>
                </li>
            </ul>

            <!-- La familia aún no tiene fichas de especificaciones de caja -->
            <p v-else class="px-3 py-3 text-[11px] text-amber-600 dark:text-amber-400">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                Este tipo de producto no tiene tarifas registradas en
                <span class="font-semibold">Tarifas de envío</span>.
            </p>

            <!-- Totales de la familia -->
            <div v-if="suggestion.has_rates" class="px-3 py-2 bg-gray-50 dark:bg-slate-800/60 border-t dark:border-gray-700 flex items-center justify-between text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                <span>
                    Total: {{ suggestion.total_boxes }} {{ suggestion.total_boxes === 1 ? 'caja' : 'cajas' }}
                </span>
                <span>
                    Peso: {{ formatMeasure(suggestion.total_weight_kg) }} kg
                </span>
            </div>

            <div v-if="suggestion.sat_code" class="px-3 py-1.5 border-t dark:border-gray-700 text-[11px] text-gray-500 dark:text-gray-400">
                Código SAT: <span class="font-mono">{{ suggestion.sat_code }}</span>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SuggestedShippingRates',
    props: {
        // Sugerencias calculadas por ShippingRateSuggestionService (una por familia de la orden)
        suggestions: {
            type: Array,
            default: () => [],
        },
    },
    methods: {
        // Formatea medidas/pesos sin ceros innecesarios (12.50 -> 12.5, 40.00 -> 40)
        formatMeasure(value) {
            const num = Number(value);
            if (value === null || value === undefined || isNaN(num)) return value ?? '-';
            if (Number.isInteger(num)) return String(num);
            return num.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        },
        // Indica si esa tarifa de la familia forma parte de la sugerencia calculada
        isSuggestedBox(suggestion, quantity) {
            return (suggestion.boxes || []).some(box => box.quantity === quantity);
        },
    },
};
</script>
