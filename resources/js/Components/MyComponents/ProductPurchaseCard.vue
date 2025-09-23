<template>
    <div class="bg-gray-100 dark:bg-slate-900 border dark:border-slate-700 rounded-lg p-4 flex items-start space-x-4 shadow-sm hover:shadow-md transition-shadow duration-300">
        <!-- Imagen del Producto -->
        <div @click="$inertia.visit(route('catalog-products.show', purchaseItem.product.id))" class="flex-shrink-0 cursor-pointer">
            <img 
                :src="purchaseItem.product?.media[0]?.original_url || 'https://placehold.co/100x100/e2e8f0/cccccc?text=SIN+IMAGEN'"
                :alt="purchaseItem.description"
                class="w-20 h-20 object-cover rounded-md shadow-sm"
                @error="$event.target.src='https://placehold.co/100x100/e2e8f0/cccccc?text=ERROR'"
            />
        </div>

        <!-- Detalles del Producto y Distribución -->
        <div class="flex-grow">
            <p class="font-bold text-lg text-gray-800 dark:text-gray-100">{{ purchaseItem.product.name }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Código: {{ purchaseItem.product?.code }}</p>

            <!-- Distribución de Cantidades -->
            <div v-if="hasDistribution" class="text-sm text-blue-600 dark:text-sky-400 space-y-1 bg-blue-100 dark:bg-sky-900/40 p-2 rounded-md">
                <p v-if="purchaseItem.plane_stock > 0" class="flex items-center">
                    <i class="fa-solid fa-plane w-5 mr-1"></i> 
                    <span>Avión: <span class="font-semibold">{{ purchaseItem.plane_stock.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ purchaseItem.product.measure_unit }}</span></span>
                </p>
                <p v-if="purchaseItem.ship_stock > 0" class="flex items-center">
                    <i class="fa-solid fa-ship w-5 mr-1"></i> 
                    <span>Barco: <span class="font-semibold">{{ purchaseItem.ship_stock.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ purchaseItem.product.measure_unit }}</span></span>
                </p>
                <p v-if="purchaseItem.additional_stock > 0" class="flex items-center">
                    <i class="fa-solid fa-plus-circle w-5 mr-1"></i> 
                    <span>A Favor: <span class="font-semibold">{{ purchaseItem.additional_stock.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ purchaseItem.product.measure_unit }}</span></span>
                </p>
            </div>
        </div>

        <!-- Precios y Cantidades -->
        <div class="flex-shrink-0 text-right space-y-1 text-sm">
            <p class="text-gray-500 dark:text-gray-400">
                {{ purchaseItem.quantity?.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ purchaseItem.product.measure_unit }} x 
                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ formatCurrency(purchaseItem.unit_price) }}</span>
            </p>
            <p class="text-lg font-bold text-blue-700 dark:text-blue-400">
                {{ formatCurrency(purchaseItem.total_price) }}
            </p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ProductPurchaseCard',
    props: {
        purchaseItem: Object,
    },
    computed: {
        hasDistribution() {
            return this.purchaseItem.plane_stock > 0 || this.purchaseItem.ship_stock > 0 || this.purchaseItem.additional_stock > 0;
        }
    },
    methods: {
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return Number(value).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        },
    }
}
</script>
