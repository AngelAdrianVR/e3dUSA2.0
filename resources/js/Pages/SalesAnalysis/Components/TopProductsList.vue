<template>
  <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg h-full">
    <h2 class="text-lg font-semibold dark:text-white mb-4">Top 20 Productos Más Vendidos</h2>
    <LoadingIsoLogo v-if="isLoading" class="my-3" />
    <ul v-else-if="topProducts.length" class="space-y-2 max-h-[60vh] overflow-y-auto pr-2">
      <li v-for="(product, index) in topProducts" :key="product.id" @click="$emit('selectProduct', product)"
        :class="['flex items-center space-x-4 p-3 rounded-lg cursor-pointer transition-all duration-200',
          selectedProduct?.id === product.id ? 'dark:bg-indigo-900 bg-blue-200 shadow-lg' : 'dark:hover:bg-gray-700 hover:bg-gray-100']">
        <span :class="['font-bold text-lg w-8 text-center flex-shrink-0', selectedProduct?.id === product.id ? 'text-white' : 'text-secondary dark:text-indigo-400']">{{ index + 1 }}</span>
        <img :src="product.image_url || 'https://placehold.co/40x40/1f2937/9ca3af?text=N/A'" class="w-10 h-10 rounded-md object-cover bg-gray-700">
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm text-gray-600 dark:text-white truncate">{{ product.name }}</p>
          <p class="text-xs text-gray-400">{{ product.code }}</p>
        </div>
        <div class="text-right flex-shrink-0">
            <p class="font-bold text-base text-gray-600 dark:text-white">{{ product.total_quantity.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}</p>
            <p class="text-xs text-gray-500">unidades</p>
        </div>
      </li>
    </ul>
      <div v-else class="text-center py-10"><p class="text-gray-400">No se encontraron productos.</p></div>
  </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';

export default {
  name: 'TopProductsList',
  components: { LoadingIsoLogo },
  props: {
    topProducts: Array,
    isLoading: Boolean,
    selectedProduct: Object,
  },
  emits: ['selectProduct'],
}
</script>
