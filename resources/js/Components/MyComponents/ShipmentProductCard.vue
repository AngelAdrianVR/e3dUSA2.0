<template>
    <div v-if="product" class="border dark:border-slate-700 rounded-lg p-3 flex items-center space-x-4 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors duration-200">
        <!-- Imagen del Producto -->
        <div class="flex-shrink-0">
            <img class="size-16 rounded-md object-cover" :src="product.media[0]?.original_url" :alt="product.name" 
                 onerror="this.onerror=null; this.src='https://placehold.co/100x100/EBF4FF/3B82F6?text=Producto'">
        </div>
        <!-- Detalles del Producto -->
        <div class="flex-grow">
            <p class="font-semibold dark:text-white">{{ product.name }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ product.code }}</p>
        </div>
        <!-- Cantidad -->
        <div class="text-right">
            <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ shipmentProduct.quantity }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">PZA(S)</p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ShipmentProductCard',
    props: {
        shipmentProduct: {
            type: Object,
            required: true,
        },
    },
    computed: {
        // Accedemos al producto a través de la relación anidada
        product() {
            return this.shipmentProduct?.sale_product?.product;
        }
    }
}
</script>
