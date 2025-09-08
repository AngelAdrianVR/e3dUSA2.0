<template>
  <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg mb-8">
    <h2 class="text-lg font-semibold dark:text-white mb-4">Ventas vs. Costos a lo largo del tiempo</h2>
    <apexchart type="area" height="350" :options="options" :series="series"></apexchart>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
  name: 'SalesCostsChart',
  components: {
    apexchart: VueApexCharts,
  },
  props: {
    options: Object,
    series: Array,
  }
}
</script>
