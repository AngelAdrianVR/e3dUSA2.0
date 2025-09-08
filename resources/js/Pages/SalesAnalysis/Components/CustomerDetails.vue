<template>
  <div class="space-y-8">
      <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
          <div class="flex items-center justify-between mb-4">
              <div>
                  <h2 class="text-lg font-semibold dark:text-white">Detalles del Cliente</h2>
                  <h3 class="text-2xl font-bold dark:text-white">{{ selectedCustomer.name }}</h3>
              </div>
              <button @click="$emit('close')" class="text-gray-400 hover:text-red-500 text-2xl transition">&times;</button>
          </div>
          <LoadingIsoLogo v-if="isLoading" class="my-3" />
          <div v-else-if="customerAmountSeries.length">
              <div class="mb-6">
                  <h4 class="text-md font-semibold dark:text-white mb-2">Ventas por Monto ({{ currency }}) por Familia</h4>
                  <apexchart type="area" height="300" :options="customerChartOptions" :series="customerAmountSeries"></apexchart>
              </div>
              <div>
                  <h4 class="text-md font-semibold dark:text-white mb-2">Ventas por Piezas (unidades) por Familia</h4>
                  <apexchart type="area" height="300" :options="customerChartOptions" :series="customerQuantitySeries"></apexchart>
              </div>
          </div>
          <div v-else class="text-center py-10"><p class="text-gray-400">Sin ventas para este cliente en el período.</p></div>
      </div>
  </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import VueApexCharts from 'vue3-apexcharts';

export default {
  name: 'CustomerDetails',
  components: {
    LoadingIsoLogo,
    apexchart: VueApexCharts,
  },
  props: {
    selectedCustomer: Object,
    customerChartOptions: Object,
    customerAmountSeries: Array,
    customerQuantitySeries: Array,
    isLoading: Boolean,
    currency: String,
  },
  emits: ['close'],
}
</script>
