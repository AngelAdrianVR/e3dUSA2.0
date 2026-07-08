<template>
    <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg overflow-hidden">
        <!-- Encabezado Colapsable -->
        <div @click="isOpen = !isOpen" class="flex justify-between items-center p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-boxes-stacked text-blue-500"></i>
                <h3 class="text-lg font-semibold">Progreso de Envíos</h3>
            </div>
            <i :class="isOpen ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid text-gray-400 transition-transform"></i>
        </div>

        <el-collapse-transition>
            <div v-show="isOpen" class="p-4 pt-0 border-t dark:border-gray-600 mt-2">
                <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                    <div v-for="item in uniqueSaleProducts" :key="item.id" class="flex flex-col space-y-2 border-b dark:border-gray-700 pb-3 last:border-0 last:pb-0">
                        <div class="flex items-center gap-3 mt-2">
                            <!-- Imagen del producto -->
                            <div class="w-12 h-12 flex-shrink-0 bg-gray-100 dark:bg-slate-700 rounded-md overflow-hidden flex items-center justify-center border dark:border-gray-600">
                                <img v-if="item.product.media?.length" :src="item.product.media[0].original_url" @error="handleImageError" alt="img" class="w-full h-full object-cover" />
                                <i v-else class="fa-solid fa-box text-gray-400 text-lg"></i>
                            </div>

                            <!-- Detalles del producto -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate" :title="item.product.name">
                                    {{ item.product.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Cód: {{ item.product.code || 'N/A' }}
                                </p>
                            </div>

                            <!-- Cantidades -->
                            <div class="text-right flex-shrink-0">
                                <p class="text-sm font-bold" :class="item.shipped_quantity >= item.total_quantity ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-300'">
                                    {{ (item.shipped_quantity)?.toLocaleString() }} / {{ (item.total_quantity)?.toLocaleString() }}
                                </p>
                                <p class="text-[10px] uppercase tracking-wider text-gray-500">Enviados</p>
                            </div>
                        </div>

                        <!-- Barra de Progreso -->
                        <div class="w-full mt-1">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] font-semibold text-gray-500">Completado</span>
                                <span class="text-[10px] font-bold" :class="item.percentage === 100 ? 'text-green-500' : 'text-blue-500'">{{ item.percentage }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-slate-700">
                                <div class="h-1.5 rounded-full transition-all duration-500"
                                    :class="item.percentage === 100 ? 'bg-green-500' : 'bg-blue-500'"
                                    :style="{ width: item.percentage + '%' }">
                                </div>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-bold" :class="item.shipped_quantity >= item.total_quantity ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-300'">
                                {{ (item.total_quantity - item.shipped_quantity)?.toLocaleString() }}
                            </p>
                            <p class="text-[10px] uppercase tracking-wider text-gray-500">Restantes</p>
                        </div>
                    </div>
                </div>
            </div>
        </el-collapse-transition>
    </div>
</template>

<script>
export default {
    name: 'ShipmentProgressCard',
    props: {
        uniqueSaleProducts: { type: Array, required: true }
    },
    data() {
        return {
            isOpen: true // Cerrado por defecto
        }
    },
    methods: {
        handleImageError(event) {
            const img = event.target;
            const currentSrc = img.src;
            const prodDomain = 'https://www.intranetemblems3d.dtw.com.mx';

            if (img.dataset.fallbackAttempted || currentSrc.includes(prodDomain)) return;
            img.dataset.fallbackAttempted = "true";

            try {
                const urlObj = new URL(currentSrc);
                img.src = prodDomain + urlObj.pathname;
            } catch (e) {
                img.src = currentSrc.replace(/^https?:\/\/[^\/]+/, prodDomain);
            }
        }
    }
}
</script>