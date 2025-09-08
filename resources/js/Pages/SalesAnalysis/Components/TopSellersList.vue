<template>
  <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
    <h3 class="text-lg font-semibold dark:text-white mb-4">Top Vendedores</h3>
    <LoadingIsoLogo v-if="isLoading" />
    <ul v-else-if="topSellers.length > 0" class="space-y-3 h-96 overflow-y-auto pr-2">
      <li v-for="(seller, index) in topSellers" :key="seller.id"
          @click="$emit('select-seller', seller)"
          :class="['p-3 rounded-lg cursor-pointer transition-all duration-200',
                   selectedSeller?.id === seller.id ? 'bg-indigo-100 dark:bg-indigo-900' : 'hover:bg-gray-100 dark:hover:bg-gray-700']">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <p class="font-bold">{{ index + 1 }}</p>
            <p class="font-medium dark:text-gray-200 truncate">{{ seller.name }}</p>
          </div>
          <span class="text-base font-bold text-green-500 dark:text-green-400 ml-4 whitespace-nowrap">{{ formatCurrency(seller.total_sold) }}</span>
        </div>
      </li>
    </ul>
    <div v-else class="flex items-center justify-center h-96">
        <p class="text-gray-500 dark:text-gray-400">No hay datos de vendedores.</p>
    </div>
  </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
export default {
  name: 'TopSellersList',
  components: { LoadingIsoLogo },
  props: {
      topSellers: Array,
      isLoading: Boolean,
      selectedSeller: Object,
      formatCurrency: Function
  },
  emits: ['select-seller']
}
</script>
