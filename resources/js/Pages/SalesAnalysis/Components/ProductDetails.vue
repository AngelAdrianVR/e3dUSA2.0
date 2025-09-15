<template>
  <div class="space-y-4">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold dark:text-white">Detalles del Producto</h2>
            <button @click="$emit('close')" class="text-gray-400 hover:text-red-500 text-2xl transition">&times;</button>
        </div>
        <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-6">
            <img @click="openProductDetail" :src="selectedProduct.image_url || 'https://placehold.co/128x128/1f2937/9ca3af?text=N/A'" class="size-32 cursor-pointer rounded-lg object-cover bg-gray-700">
            <div class="flex-1">
                <h3 class="text-2xl font-bold dark:text-white">{{ selectedProduct.name }}</h3>
                <el-tag v-if="selectedProduct.archived_at" type="warning" class="mb-1">Obsoleto</el-tag>
                <p class="text-sm text-gray-400 font-mono mb-4">{{ selectedProduct.code }}</p>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Costo</p>
                        <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ formatCurrency(selectedProduct.cost) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Precio Base</p>
                        <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ formatCurrency(selectedProduct.base_price) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
        <h2 class="text-lg font-semibold dark:text-white mb-4">Historial de Ventas ({{ currency }})</h2>
        <LoadingIsoLogo v-if="isLoading" class="my-3" />
        <div v-else-if="productSales.length" class="overflow-x-auto max-h-56 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-300/50 dark:bg-gray-700/50 sticky top-0">
                    <tr>
                        <th class="px-6 py-3">Fecha</th>
                        <th class="px-6 py-3">Cliente</th>
                        <th class="px-6 py-3 text-right">Cantidad</th>
                        <th class="px-6 py-3 text-right">Precio</th>
                        <th class="px-6 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="sale in productSales" :key="sale.sale_id" class="border-b dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 dark:text-white">{{ formatDate(sale.created_at) }}</td>
                        <td class="px-6 py-4 dark:text-white">{{ sale.branch_name }}</td>
                        <td class="px-6 py-4 text-right font-mono dark:text-white">{{ sale.quantity }}</td>
                        <td class="px-6 py-4 text-right font-mono dark:text-white">{{ formatCurrency(sale.price) }}</td>
                        <td class="px-6 py-4 text-right font-semibold text-indigo-400 font-mono">{{ formatCurrency(sale.quantity * sale.price) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="text-center py-10"><p class="text-gray-400">No se encontraron ventas para este producto.</p></div>
    </div>
  </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';

export default {
  name: 'ProductDetails',
  components: { LoadingIsoLogo },
  props: {
    selectedProduct: Object,
    productSales: Array,
    isLoading: Boolean,
    formatCurrency: Function,
    formatDate: Function,
    currency: String,
  },
  methods:{
    openProductDetail() {
        const url = route('catalog-products.show', this.selectedProduct.id)
        window.open(url, 'blanck')
    }
  },
  emits: ['close'],
}
</script>
