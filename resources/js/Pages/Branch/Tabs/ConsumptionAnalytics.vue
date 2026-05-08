<template>
    <div class="mt-2 pr-2 max-h-[60vh] overflow-y-auto custom-scrollbar">
        <!-- Tarjetas de Resumen -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800">
                <p class="text-sm text-blue-600 dark:text-blue-400 font-semibold mb-1">Consumo Anual Estimado</p>
                <p v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')"
                    class="text-2xl font-bold text-blue-800 dark:text-blue-300">
                    ${{ formatNumber(consumptionData?.annual_consumption) }}
                </p>
                <p v-else class="text-2xl font-bold text-blue-800 dark:text-blue-300" title="Información restringida">
                    ***
                </p>
                <p class="text-sm text-blue-700 dark:text-blue-400 mt-2 font-medium">Cantidades estimadas: {{
                    Math.round(totalAnnualItems)?.toLocaleString() }} u.</p>
                <p class="text-xs text-blue-500 mt-1">Basado en los últimos 12 meses</p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800">
                <p class="text-sm text-green-600 dark:text-green-400 font-semibold mb-1">Consumo Mensual Estimado</p>
                <p v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')"
                    class="text-2xl font-bold text-green-800 dark:text-green-300">
                    ${{ formatNumber(consumptionData?.monthly_consumption) }}
                </p>
                <p v-else class="text-2xl font-bold text-green-800 dark:text-green-300" title="Información restringida">
                    ***
                </p>
                <p class="text-sm text-green-700 dark:text-green-400 mt-2 font-medium">Cantidades estimadas: {{
                    Math.round(totalMonthlyItems)?.toLocaleString() }} u.</p>
                <p class="text-xs text-green-500 mt-1">Promedio mensual</p>
            </div>
        </div>

        <!-- Gráfica de ventas por año -->
        <div class="bg-white dark:bg-slate-800 p-4 mx-7 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm mb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-800 dark:text-gray-200">Histórico de Ventas</h3>
                <div class="flex items-center space-x-3">
                    <button @click="changeYear(-1)"
                        class="p-1 hover:bg-gray-100 dark:hover:bg-slate-700 rounded transition"><i
                            class="fa-solid fa-chevron-left text-gray-500"></i></button>
                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ selectedYear }}</span>
                    <button @click="changeYear(1)" :disabled="selectedYear >= currentYear"
                        :class="selectedYear >= currentYear ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100 dark:hover:bg-slate-700'"
                        class="p-1 rounded transition"><i class="fa-solid fa-chevron-right text-gray-500"></i></button>
                </div>
            </div>

            <div class="flex items-end h-56 space-x-2 pt-8">
                <template v-if="hasDataForSelectedYear">
                    <div v-for="(monthData, index) in normalizedYearData" :key="index"
                        class="flex-1 flex flex-col justify-end items-center group relative h-full">
                        
                        <!-- Tooltip Avanzado (Con posicionamiento dinámico) -->
                        <div class="opacity-0 group-hover:opacity-100 absolute bottom-full mb-2 bg-gray-800 text-white text-xs py-2 px-3 rounded-lg shadow-xl transition-all duration-200 whitespace-nowrap z-50 pointer-events-none group-hover:pointer-events-auto min-w-[280px] sm:min-w-[300px]"
                            :class="{
                                'left-1/2 -translate-x-8': index <= 3,
                                'right-1/2 translate-x-8': index >= 8,
                                'left-1/2 -translate-x-1/2': index > 3 && index < 8
                            }">
                            <!-- Mes y Año -->
                            <p class="font-bold border-b border-gray-600 pb-1 mb-1.5 text-center text-gray-200">{{ monthNamesShort[index] }} {{ selectedYear }}</p>
                            
                            <!-- Lista de Productos -->
                            <div v-if="monthData.products.length > 0" class="max-h-32 overflow-y-auto custom-scrollbar pr-2 mb-2">
                                <div v-for="(prod, i) in monthData.products" :key="i" class="flex justify-between items-center gap-4 mb-1.5">
                                    <span class="truncate max-w-[200px] text-gray-300" :title="prod.name">
                                        <b class="text-white">{{ Math.round(prod.quantity)?.toLocaleString() }}x</b> {{ prod.name }}
                                    </span>
                                    <span v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')" class="text-green-400 font-medium">
                                        ${{ formatNumber(prod.total) }}
                                    </span>
                                    <span v-else class="text-green-400 font-medium" title="Información restringida">***</span>
                                </div>
                            </div>
                            <div v-else class="text-gray-400 text-center py-2 mb-1 italic">
                                Sin ventas de catálogo
                            </div>

                            <!-- Total del mes -->
                            <div class="border-t border-gray-600 pt-1.5 mt-1 flex justify-between items-center font-bold text-sm">
                                <span>Total Mensual:</span>
                                <span v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')" class="text-primary-light">
                                    ${{ formatNumber(monthData.total) }}
                                </span>
                                <span v-else class="text-primary-light">***</span>
                            </div>

                            <!-- Flecha apuntando a la barra (Se mueve dinámicamente) -->
                            <div class="absolute -bottom-1.5 w-3 h-3 bg-gray-800 rotate-45 border-r border-b border-gray-800"
                                :class="{
                                    'left-8': index <= 3,
                                    'right-8': index >= 8,
                                    'left-1/2 -translate-x-1/2': index > 3 && index < 8
                                }"></div>
                        </div>

                        <!-- Bar -->
                        <div class="w-full bg-primary/80 hover:bg-primary rounded-t-sm transition-all duration-300"
                            :style="{ height: getBarHeight(monthData.total) + '%' }"></div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ monthNamesShort[index] }}</span>
                    </div>
                </template>
                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-sm">
                    No hay ventas registradas en {{ selectedYear }}
                </div>
            </div>
        </div>

        <!-- Desglose de Productos -->
        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-3">Desglose de Consumo por Producto</h3>
        <div v-if="productBreakdownList.length"
            class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden max-h-[500px] overflow-y-auto custom-scrollbar">
            <table class="w-full text-sm text-left relative">
                <thead
                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-slate-900/50 dark:text-gray-400 border-b dark:border-gray-700 sticky top-0 z-10">
                    <tr>
                        <th scope="col" class="px-4 py-3">Producto</th>
                        <th scope="col" class="px-4 py-3 text-center">Consumo Mensual</th>
                        <th scope="col" class="px-4 py-3 text-center">Consumo Anual</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="prod in productBreakdownList" :key="prod.product_id"
                        class="bg-white dark:bg-slate-800 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                        <td class="px-4 py-3 flex items-center space-x-3">
                            <img v-if="prod.image_url" :src="formatImageUrl(prod.image_url)" 
                                @click="zoomedImage = formatImageUrl(prod.image_url)"
                                class="w-10 h-10 rounded object-cover border dark:border-gray-600 cursor-pointer hover:opacity-80 transition-opacity"
                                title="Ver imagen completa">
                            <div v-else
                                class="w-10 h-10 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-400">
                                <i class="fa-solid fa-image"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ prod.name }}</p>
                                <p class="text-xs text-gray-500">{{ prod.code }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <p class="font-semibold text-gray-700 dark:text-gray-300">{{ Math.round(prod.monthly_quantity)
                                }} u.</p>
                            <p v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')"
                                class="text-xs text-green-600 dark:text-green-400">
                                ${{ formatNumber(prod.monthly_total) }}
                            </p>
                            <p v-else class="text-xs text-green-600 dark:text-green-400">***</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <p class="font-semibold text-gray-700 dark:text-gray-300">{{ Math.round(prod.annual_quantity)
                                }} u.</p>
                            <p v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')"
                                class="text-xs text-blue-600 dark:text-blue-400">
                                ${{ formatNumber(prod.annual_total) }}
                            </p>
                            <p v-else class="text-xs text-blue-600 dark:text-blue-400">***</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else
            class="text-center p-6 text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
            No hay registros de compras de productos en los últimos 12 meses.
        </div>

        <!-- Visor de imagen a pantalla completa -->
        <div v-if="zoomedImage" @click="zoomedImage = null" 
            class="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center p-4 cursor-zoom-out backdrop-blur-sm transition-all">
            <img :src="zoomedImage" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl border-4 border-white/10" @click.stop>
            <button @click="zoomedImage = null" class="absolute top-6 right-6 text-white hover:text-gray-300 bg-black/50 rounded-full w-10 h-10 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ConsumptionAnalytics',
    props: {
        consumptionData: {
            type: Object,
            required: true,
            default: () => ({})
        }
    },
    data() {
        return {
            selectedYear: new Date().getFullYear(), // Inicia en el año actual
            currentYear: new Date().getFullYear(),
            monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            zoomedImage: null, // Controla qué imagen está ampliada
        };
    },
    computed: {
        productBreakdownList() {
            if (!this.consumptionData?.product_breakdown) return [];
            return Array.isArray(this.consumptionData.product_breakdown)
                ? this.consumptionData.product_breakdown
                : Object.values(this.consumptionData.product_breakdown);
        },
        totalAnnualItems() {
            return this.productBreakdownList.reduce((sum, prod) => sum + prod.annual_quantity, 0);
        },
        totalMonthlyItems() {
            return this.productBreakdownList.reduce((sum, prod) => sum + prod.monthly_quantity, 0);
        },
        salesByYearMonth() {
            return this.consumptionData?.sales_by_year_month || {};
        },
        // Adaptada para manejar la nueva estructura de objeto {total, products}
        normalizedYearData() {
            const yearData = this.salesByYearMonth[this.selectedYear];
            if (!yearData) return Array(12).fill({ total: 0, products: [] });

            const normalized = [];
            for (let i = 1; i <= 12; i++) {
                let monthData;
                // Soporta si el backend manda un array indexado desde 0 o un objeto indexado desde 1.
                if (Array.isArray(yearData)) {
                    monthData = yearData[i - 1];
                } else {
                    monthData = yearData[i] || yearData[i.toString()] || yearData[i - 1];
                }

                // Normalización de seguridad
                if (typeof monthData === 'number') {
                    // Fallback en caso de que aún venga formato viejo
                    normalized.push({ total: monthData, products: [] });
                } else if (monthData) {
                    normalized.push(monthData);
                } else {
                    normalized.push({ total: 0, products: [] });
                }
            }
            return normalized;
        },
        hasDataForSelectedYear() {
            // Verifica que al menos un mes tenga ventas totales > 0
            return this.normalizedYearData.some(month => month.total > 0);
        },
        maxAmountInSelectedYear() {
            if (!this.hasDataForSelectedYear) return 0;
            return Math.max(...this.normalizedYearData.map(m => m.total));
        }
    },
    methods: {
        changeYear(step) {
            this.selectedYear += step;
        },
        getBarHeight(amount) {
            if (this.maxAmountInSelectedYear === 0) return 0;
            const percentage = (amount / this.maxAmountInSelectedYear) * 100;
            // Da una altura mínima de 2% para que al menos se vea un punto si hay venta
            return percentage < 2 && amount > 0 ? 2 : percentage;
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            if (isNaN(num)) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        formatImageUrl(url) {
            if (!url) return null;
            return url.replace('http://localhost:8000', 'https://www.intranetemblems3d.dtw.com.mx');
        }
    },
    mounted() {
        // Al montar, verificamos si el año actual tiene datos, si no, nos movemos un año atrás
        if (!this.hasDataForSelectedYear && this.salesByYearMonth[this.currentYear - 1]) {
            this.selectedYear = this.currentYear - 1;
        }
    }
};
</script>