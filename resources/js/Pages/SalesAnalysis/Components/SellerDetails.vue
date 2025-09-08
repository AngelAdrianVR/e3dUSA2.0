<template>
  <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg relative">
     <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
      <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
    <h3 class="text-lg font-semibold dark:text-white mb-1">Detalle de Vendedor</h3>
    <p class="text-indigo-500 dark:text-indigo-400 mb-4 font-medium">{{ selectedSeller.name }}</p>
    
    <LoadingIsoLogo v-if="isLoading" class="mt-12"/>
    
    <div v-else-if="sellerChartSeries?.length > 0">
      <apexchart type="donut" height="350" :options="sellerChartOptions" :series="sellerChartSeries"></apexchart>
    </div>
    <div v-else class="flex items-center justify-center h-full -mt-12">
        <p class="text-gray-500 dark:text-gray-100">No hay detalles de venta para este vendedor.</p>
    </div>
  </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import VueApexCharts from 'vue3-apexcharts';

export default {
  name: 'SellerDetails',
  components: { LoadingIsoLogo, apexchart: VueApexCharts },
  props: {
      selectedSeller: Object,
      isLoading: Boolean,
      sellerChartOptions: Object,
      sellerChartSeries: Array,
      formatCurrency: Function
  },
  emits: ['close']
}
</script>
