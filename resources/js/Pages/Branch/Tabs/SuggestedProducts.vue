<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        <div v-for="product in products" :key="product.id"
            class="bg-gray-100 dark:bg-slate-900/70 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden transform hover:-translate-y-px transition-transform duration-300 shadow-sm hover:shadow-lg">
            
            <figure class="aspect-square bg-gray-100 dark:bg-slate-800">
                <img v-if="product.media?.length" :src="product.media[0]?.original_url" :alt="product.name"
                    class="w-full h-full object-cover">
                <div v-else class="flex items-center justify-center h-full text-gray-400 dark:text-slate-500">
                    <i class="fa-solid fa-image text-4xl"></i>
                </div>
            </figure>

            <div class="p-4">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 truncate" :title="product.name">
                    {{ product.name }}
                </h3>
                <a :href="route('catalog-products.show', product.id)" target="_blank" class="text-sm text-primary hover:underline mt-2 inline-block">
                    Ver detalles <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                </a>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        products: Array,
    }
}
</script>
