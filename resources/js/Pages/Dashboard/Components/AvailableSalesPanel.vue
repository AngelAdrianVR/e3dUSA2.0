<template>
  <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-md dark:bg-gray-800 dark:border-gray-700">
    <div class="flex items-center justify-between mb-4">
        <h5 class="text-xl font-bold leading-none text-gray-900 dark:text-white">Órdenes de Venta disponibles</h5>
        <p>¿Cero tareas pendientes?. ¡Pide tareas para avanzar!</p>
   </div>
   <div class="flow-root max-h-96 overflow-y-auto">
        <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
            <li v-for="order in orders" :key="order.id" class="py-3 sm:py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate dark:text-white">
                            {{ order.folio }} - {{ order.contact_name }}
                        </p>
                        <p class="text-sm text-gray-500 truncate dark:text-gray-400">
                            Vendedor: {{ order.user_name }}
                        </p>
                    </div>
                    <div class="text-right">
                        <!-- <div class="inline-flex items-center text-base font-semibold text-gray-900 dark:text-white">
                            {{ order.total }}
                        </div> -->
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ order.date }}</p>
                    </div>
                </div>
                <!-- Product details section -->
                <div v-if="order.products && order.products.length" class="mt-3 pl-14">
                    <details class="group">
                        <summary class="flex items-center cursor-pointer text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white list-none">
                            <span class="font-semibold">Ver Productos</span>
                            <svg class="w-4 h-4 ml-1 transition-transform transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </summary>
                        <ul class="mt-2 space-y-3 border-l-2 border-gray-200 dark:border-gray-600 pl-4 ml-1">
                            <li v-for="product in order.products" :key="product.id" class="text-sm relative pt-1">
                               <span class="absolute -left-[9px] top-2.5 h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-500"></span>
                                <div class="flex items-center space-x-2">
                                    <img :src="product.media[0]?.original_url" alt="Imagen del producto" class="w-12 h-12 object-cover rounded" />
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ product.name }}</p>
                                </div>
                                <div class="flex justify-between text-gray-500 dark:text-gray-400 text-xs mt-1">
                                    <span>A producir: <span class="font-bold text-blue-600 dark:text-blue-400">{{ product.quantity_to_produce }}</span></span>
                                    <span>Stock disponible: <span class="font-bold text-green-600 dark:text-green-400">{{ product.stock_available }}</span></span>
                                </div>
                            </li>
                        </ul>
                    </details>
                </div>
            </li>
        </ul>
        <div v-if="!orders.length" class="text-center py-8">
             <p class="text-gray-500 dark:text-gray-400">No hay órdenes de venta por surtir en este momento.</p>
        </div>
   </div>
  </div>
</template>

<script>
export default {
    props: {
        orders: Array,
    }
}
</script>

