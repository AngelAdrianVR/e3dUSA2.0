<template>
    <!-- Contenedor principal para el componente -->
    <div class="fixed top-40 right-0 z-30 flex items-center pointer-events-none">
        <!-- Pestaña para abrir el panel -->
        <transition enter-active-class="transition-opacity duration-300 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="!isOpen" class="pointer-events-auto">
                <button @click="isOpen = true" class="group relative flex items-center justify-center w-8 h-16 bg-slate-800/80 backdrop-blur-sm text-white rounded-l-2xl shadow-2xl hover:bg-slate-700/90 transition-all transform hover:-translate-x-1 focus:outline-none focus:ring-2 focus:ring-amber-400" aria-label="Mostrar productos a favor">
                    <span v-if="favoredProducts.length" class="absolute -top-1 -left-2 flex items-center justify-center size-5 text-xs font-bold text-white bg-amber-500 rounded-full ring-2 ring-amber-800 shadow-md">
                        {{ favoredProducts.length }}
                    </span>
                    <!-- Icono -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    <!-- Tooltip -->
                    <span class="absolute right-full mr-2 w-max px-2 py-1 text-xs font-medium text-white bg-slate-900 rounded-md shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        Productos a Favor
                    </span>
                </button>
            </div>
        </transition>
    </div>

    <!-- Panel de Productos a Favor -->
    <div class="fixed top-40 right-0 bottom-0 z-30 flex items-center pointer-events-none">
        <div class="w-96 h-[calc(80vh-7rem)] bg-slate-900/60 backdrop-blur-xl border border-slate-700 rounded-l-2xl shadow-2xl flex flex-col transition-transform duration-500 ease-in-out pointer-events-auto" :class="isOpen ? 'translate-x-0' : 'translate-x-full'">
            <!-- Encabezado -->
            <div class="flex justify-between items-center p-4 border-b border-slate-700 flex-shrink-0">
                <h3 class="font-bold text-base flex items-center text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-3 text-amber-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    Productos a favor de {{ supplierName }}
                </h3>
                <button @click="isOpen = false" class="text-slate-400 hover:text-white transition-colors" aria-label="Cerrar panel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <!-- Contenido del Panel (Lista de Productos) -->
            <div class="p-4 space-y-3 flex-grow overflow-y-auto">
                <LoadingIsoLogo v-if="isLoading" />
                <div v-else-if="favoredProducts.length > 0" v-for="item in favoredProducts" :key="item.id" class="text-sm bg-slate-800 p-3 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-3">
                            <img v-if="item.product.media && item.product.media.length > 0" :src="item.product.media[0].original_url" :alt="item.product.name" class="w-12 h-12 rounded-md object-cover flex-shrink-0">
                            <div v-else class="w-12 h-12 rounded-md bg-slate-700 flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-200">{{ item.product.name }}</p>
                                <p class="text-xs text-slate-400">{{ item.product.code }}</p>
                            </div>
                        </div>
                    </div>
                        <p class="text-base text-right font-bold text-amber-400 ml-2 mt-1">{{ item.quantity }} pz</p>
                    <!-- Formulario para descontar -->
                    <div class="mt-3 flex items-center space-x-2">
                         <input type="number" v-model="discountQuantities[item.id]" :max="item.quantity" min="0.01" step="0.01" class="w-full text-sm bg-slate-700 text-white border-slate-600 rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500 transition" placeholder="Cantidad">
                         <button @click="discount(item)" :disabled="!discountQuantities[item.id] || discountQuantities[item.id] <= 0" class="px-3 py-2 text-xs font-semibold text-white bg-amber-600 rounded-md hover:bg-amber-500 disabled:bg-slate-700 disabled:text-slate-400 disabled:cursor-not-allowed transition-colors">
                            Descontar
                         </button>
                    </div>
                </div>
                <p v-else class="text-sm text-center text-white py-8">No hay productos a favor para este proveedor.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import { ref, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';

const props = defineProps({
    supplierId: { type: Number, required: true },
});

const isLoading = ref(false);
const favoredProducts = ref([]);
const supplierName = ref('');
const isOpen = ref(false);
const discountQuantities = ref({}); // Objeto para almacenar las cantidades a descontar por ID

onMounted(() => {
    if (props.supplierId) {
        fetchFavoredProducts();
    }
});

watch(() => props.supplierId, (newId) => {
    if (newId) {
        fetchFavoredProducts();
    }
});

const fetchFavoredProducts = async () => {
    if (isLoading.value) return;
    isLoading.value = true;
    try {
        const response = await axios.get(route('suppliers.favored-products.index', props.supplierId));
        favoredProducts.value = response.data.favoredProducts;
        supplierName.value = response.data.supplierName;

        // if (favoredProducts.value.length > 0 && !isOpen.value) {
        //     isOpen.value = true;
        // }
    } catch (error) {
        console.error("Error al recuperar los productos a favor:", error);
        ElMessage.error('No se pudieron recuperar los productos a favor.');
    } finally {
        isLoading.value = false;
    }
};

const discount = async (item) => {
    const quantityToDiscount = discountQuantities.value[item.id];
    if (!quantityToDiscount || quantityToDiscount <= 0) {
        ElMessage.warning('La cantidad a descontar debe ser mayor a cero.');
        return;
    }
    if (quantityToDiscount > item.quantity) {
        ElMessage.error('No se puede descontar más de la cantidad disponible.');
        return;
    }

    try {
        const res = await axios.put(route('favored-products.discount', item.id), {
            quantity: quantityToDiscount
        });

        const index = favoredProducts.value.findIndex(p => p.id === item.id);
        if (index !== -1) {
            if (res.data.quantity > 0) {
                favoredProducts.value[index] = res.data;
            } else {
                // Si la cantidad llega a 0 o menos, lo eliminamos de la lista
                favoredProducts.value.splice(index, 1);
            }
        }
        
        discountQuantities.value[item.id] = null; // Limpiar input
        ElMessage.success('Cantidad descontada correctamente.');
    } catch (error) {
        console.error("Error al descontar la cantidad:", error);
        ElMessage.error(error.response?.data?.message || 'No se pudo descontar la cantidad.');
    }
};
</script>

