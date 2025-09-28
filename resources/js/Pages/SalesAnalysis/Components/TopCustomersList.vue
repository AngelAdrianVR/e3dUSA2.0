<template>
  <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg h-full">
      <h2 class="text-lg font-semibold dark:text-white mb-4">Top 20 Clientes</h2>
      <LoadingIsoLogo v-if="isLoading" class="my-3" />
      <ul v-else-if="topCustomers.length" class="space-y-1 max-h-[50vh] overflow-y-auto">
          <li v-for="(customer, index) in topCustomers" :key="customer.id" @click="$emit('selectCustomer', customer)"
            :class="['flex items-center justify-between p-3 rounded-lg cursor-pointer transition-all duration-200',
              selectedCustomer?.id === customer.id ? 'dark:bg-indigo-900 bg-blue-200 shadow-lg' : 'dark:hover:bg-gray-700 hover:bg-gray-100']">
              <div class="flex items-center space-x-4">
                  <span :class="['font-bold text-lg w-8 text-center', selectedCustomer?.id === customer.id ? 'text-white' : 'text-secondary dark:text-indigo-400']">{{ index + 1 }}</span>
                  <p :class="['font-semibold', selectedCustomer?.id === customer.id ? 'dark:text-white text-gray-600' : 'dark:text-white']">{{ customer.name }}</p>
              </div>
              <p class="font-semibold text-lg text-green-600 dark:text-green-400 font-mono">{{ formatCurrency(customer.total_purchased) }}</p>
          </li>
      </ul>
        <div v-else class="text-center py-10"><p class="text-gray-400">No se encontraron clientes.</p></div>
  </div>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';

export default {
  name: 'TopCustomersList',
  components: { LoadingIsoLogo },
  props: {
    topCustomers: Array,
    isLoading: Boolean,
    selectedCustomer: Object,
    formatCurrency: Function,
  },
  emits: ['selectCustomer']
}
</script>
