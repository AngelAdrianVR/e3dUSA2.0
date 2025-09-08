<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
      <h3 class="text-sm font-medium text-gray-400">Ventas Totales</h3>
      <p class="mt-2 text-3xl font-bold text-slate-700 dark:text-white">{{ formatCurrency(salesMetrics.total_sales) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
      <h3 class="text-sm font-medium text-gray-400">Costos Totales</h3>
      <p class="mt-2 text-3xl font-bold text-slate-700 dark:text-white">{{ formatCurrency(salesMetrics.total_costs) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
      <h3 class="text-sm font-medium text-gray-400">Utilidad Bruta</h3>
      <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(salesMetrics.total_profit) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg flex flex-col items-center justify-center">
      <h3 class="text-sm font-medium text-gray-400 mb-2">Margen de Utilidad</h3>
      <apexchart type="radialBar" height="120" :options="marginChartOptions" :series="marginChartSeries"></apexchart>
    </div>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
  name: 'KpiCards',
  components: {
    apexchart: VueApexCharts,
  },
  props: {
    salesMetrics: Object,
    marginChartOptions: Object,
    marginChartSeries: Array,
    formatCurrency: Function,
  }
}
</script>
