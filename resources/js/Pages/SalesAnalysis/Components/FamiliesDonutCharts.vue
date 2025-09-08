<template>
  <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
    <h2 class="text-lg font-semibold dark:text-white mb-6">Ventas por Familia de Productos ({{ currency }})</h2>
    <LoadingIsoLogo v-if="isLoading" class="my-3" />
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <div v-for="family in productFamiliesSales" :key="family.name" class="flex flex-col items-center">
        <!-- MODIFIED: Changed type to radialBar and adjusted dimensions -->
        <apexchart type="radialBar" height="220" :options="getFamilyDonutOptions(family)" :series="[family.percentage]"></apexchart>
        <p class="font-semibold dark:text-white -mt-4 text-center">{{ family.name }}</p>
        <p class="text-base text-green-500 dark:text-green-400 font-mono text-center">{{ formatCurrency(family.total) }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import VueApexCharts from 'vue3-apexcharts';

export default {
  name: 'FamiliesDonutCharts',
  components: {
    LoadingIsoLogo,
    apexchart: VueApexCharts,
  },
  props: {
    productFamiliesSales: Array,
    isLoading: Boolean,
    getFamilyDonutOptions: Function,
    formatCurrency: Function,
    currency: String,
  }
}
</script>
