<template>
    <div v-if="product"
        class="border dark:border-slate-700 rounded-lg p-3 flex items-start gap-4 hover:bg-gray-50 dark:hover:bg-slate-800 transition-all duration-300"
        :class="{ 'opacity-70 saturate-50': shipmentStatus === 'Enviada' }">
        <!-- Imagen del Producto -->
        <div class="flex-shrink-0">
            <img @click="$inertia.visit(route('catalog-products.show', product.id))"
                class="size-16 rounded-md object-cover cursor-pointer" :src="product.media[0]?.original_url"
                :alt="product.name"
                onerror="this.onerror=null; this.src='https://placehold.co/100x100/EBF4FF/3B82F6?text=Producto'">
        </div>

        <!-- Detalles del Producto -->
        <div class="flex-grow">
            <p class="font-semibold dark:text-white">{{ product.name }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ product.code }}</p>

            <!-- Información de Cantidad y Stock -->
            <div class="flex items-center justify-between mt-2">
                <div>
                    <span class="text-sm font-bold text-primary dark:text-sky-400 mr-1">{{ shipmentProduct.quantity?.toLocaleString()
                        }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400"> {{ product.measure_unit }} requeridas</span>
                </div>
                <!-- Indicador de Stock Disponible -->
                <div v-if="shipmentStatus !== 'Enviada'"
                    :class="availableStock >= shipmentProduct.quantity ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'"
                    class="px-2 py-1 rounded-full text-xs font-semibold">
                    Disponible: {{ availableStock?.toLocaleString() }}
                </div>
                <!-- Indicador de Enviado -->
                <div v-else
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300">
                    <svg class="size-4 -ml-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    <span>Enviado</span>
                </div>
            </div>
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
        shipmentStatus: String
    },
    computed: {
        product() {
            return this.shipmentProduct?.sale_product?.product;
        },
        // Calcula el stock disponible para este producto
        availableStock() {
            // Cantidad tomada de stock desde la creación de venta que ya fue descontada del stock
            const quantityTakenOfStock = this.shipmentProduct.sale_product.quantity - this.shipmentProduct.sale_product.quantity_to_produce
            if (!this.product?.storages || this.product.storages.length === 0) {
                return 0;
            }
            return this.product.storages.reduce((total, storage) => total + parseFloat(storage.quantity) + quantityTakenOfStock, 0);
        }
    }
}
</script>
