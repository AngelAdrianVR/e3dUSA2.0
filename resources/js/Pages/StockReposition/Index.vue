<template>
  <AppLayout title="Reposición de Stock">
    <div class="p-4 font-sans text-gray-800 dark:text-gray-200">
      
      <!-- Header y Buscador -->
      <header class="flex flex-col md:flex-row items-center justify-between pb-6 border-b border-gray-300 dark:border-gray-600 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-red-600 dark:text-red-400">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> Productos con Stock Bajo
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Listado de productos que requieren reposición inmediata (Stock actual < Mínimo).
            </p>
        </div>
        
        <div class="flex flex-col md:flex-row gap-3 items-center w-full md:w-auto">
            <!-- Buscador -->
            <div class="relative w-full md:w-64">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fa-solid fa-search text-gray-400"></i>
                </div>
                <input 
                    v-model="search"
                    type="text" 
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5 dark:bg-slate-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" 
                    placeholder="Buscar por nombre o código..."
                >
            </div>

            <div class="bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300 px-4 py-2 rounded-lg text-sm font-semibold border border-red-200 dark:border-red-800 whitespace-nowrap">
                Total: {{ products.total }} productos
            </div>
        </div>
      </header>

      <!-- Tabla de Productos -->
      <div class="mt-6 overflow-x-auto bg-white dark:bg-slate-900 rounded-lg shadow border border-gray-200 dark:border-slate-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300 uppercase font-bold">
                <tr>
                    <th class="px-4 py-3">Imagen</th>
                    <th class="px-4 py-3">Información</th>
                    <th class="px-4 py-3 text-center">Stock Actual</th>
                    <th class="px-4 py-3 text-center">Mínimo</th>
                    <th class="px-4 py-3 text-center">Faltante</th>
                    <th class="px-4 py-3 text-center">Estatus Reposición</th>
                    <th class="px-4 py-3 text-center">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                    <!-- Imagen (Clickeable) -->
                    <td class="px-4 py-3 w-20">
                        <div 
                            class="h-12 w-12 rounded-md overflow-hidden bg-gray-200 border border-gray-300 flex items-center justify-center group relative cursor-pointer"
                            @click="product.image_url ? openImageModal(product.image_url) : null"
                        >
                            <img v-if="product.image_url" :src="product.image_url" alt="Prod" class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-110">
                            <i v-else class="fa-solid fa-image text-gray-400"></i>
                            
                            <!-- Overlay hover icon -->
                            <div v-if="product.image_url" class="absolute inset-0 bg-black/0 group-hover:bg-black/20 flex items-center justify-center transition-all">
                                <i class="fa-solid fa-magnifying-glass text-white opacity-0 group-hover:opacity-100 drop-shadow-md"></i>
                            </div>
                        </div>
                    </td>

                    <!-- Nombre y Código -->
                    <td class="px-4 py-3">
                        <p class="font-bold text-gray-800 dark:text-gray-100">{{ product.name }}</p>
                        <p class="text-xs text-blue-600 dark:text-blue-400 font-mono">{{ product.code }}</p>
                        <p v-if="product.suggested_supplier_name" class="text-xs text-gray-500 mt-1">
                            <i class="fa-solid fa-truck-field mr-1"></i> {{ product.suggested_supplier_name }}
                        </p>
                    </td>

                    <!-- Stock Actual -->
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            {{ product.current_stock }}
                        </span>
                    </td>

                    <!-- Mínimo -->
                    <td class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-400">
                        {{ product.min_quantity }}
                    </td>

                    <!-- Faltante (Sugerencia) -->
                    <td class="px-4 py-3 text-center font-bold text-orange-600 dark:text-orange-400">
                        +{{ product.missing.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}
                    </td>

                    <!-- Estatus de Reposición -->
                    <td class="px-4 py-3 text-center">
                        <div v-if="product.active_purchase" class="flex flex-col items-center">
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                <i class="fa-solid fa-rotate mr-1"></i> {{ product.active_purchase.status }}
                            </span>
                            <span class="text-[10px] text-gray-400 mt-1">
                                OC #{{ product.active_purchase.id }} - {{ product.active_purchase.created_at }}
                            </span>
                        </div>
                        <div v-else>
                            <span class="text-xs text-gray-400 italic">Sin orden activa</span>
                        </div>
                    </td>

                    <!-- Botones -->
                    <td class="px-4 py-3 text-center">
                        <div v-if="product.active_purchase">
                            <Link :href="route('purchases.show', product.active_purchase.id)">
                                <SecondaryButton class="!py-1 !px-3 text-xs" title="Ver Orden Existente">
                                    Ver OC Existente
                                </SecondaryButton>
                            </Link>
                        </div>
                        <div v-else>
                            <Link :href="route('purchases.create', { 
                                prefill_supplier_id: product.suggested_supplier_id,
                                prefill_product_id: product.id,
                                prefill_quantity: product.missing
                            })">
                                <PrimaryButton class="!py-1 !px-3 text-xs" title="Crear Orden de Compra">
                                    Crear OC <i class="fa-solid fa-arrow-right ml-2"></i>
                                </PrimaryButton>
                            </Link>
                        </div>
                    </td>
                </tr>
                
                <tr v-if="products.data.length === 0">
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        <i class="fa-solid fa-check-circle text-green-500 text-3xl mb-2"></i>
                        <p>No se encontraron productos con stock bajo {{ search ? 'con ese criterio' : '' }}.</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Paginación -->
        <div v-if="products.links.length > 3" class="flex justify-between items-center p-4 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 rounded-b-lg">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Mostrando {{ products.from }} a {{ products.to }} de {{ products.total }} resultados
            </div>
            <div class="flex gap-1">
                <template v-for="(link, key) in products.links" :key="key">
                    <div v-if="link.url === null" 
                         class="mr-1 mb-1 px-3 py-2 text-xs leading-4 text-gray-400 border rounded" 
                         v-html="link.label" 
                    />
                    <Link v-else 
                          class="mr-1 mb-1 px-3 py-2 text-xs leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500 dark:border-slate-600 dark:hover:bg-slate-700" 
                          :class="{ 'bg-blue-600 text-white dark:bg-blue-500': link.active, 'bg-white text-gray-700 dark:bg-slate-800 dark:text-gray-300': !link.active }" 
                          :href="link.url" 
                          preserve-scroll
                          v-html="link.label" 
                    />
                </template>
            </div>
        </div>
      </div>

      <!-- MODAL DE IMAGEN -->
      <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 transition-opacity" @click.self="closeImageModal">
          <div class="relative bg-white dark:bg-slate-800 rounded-lg shadow-2xl overflow-hidden max-w-4xl max-h-[90vh] flex flex-col animate-fade-in-up">
              <!-- Botón cerrar -->
              <button 
                @click="closeImageModal" 
                class="absolute top-2 right-2 z-10 bg-black/50 hover:bg-black/70 text-white rounded-full p-2 transition-colors"
                title="Cerrar"
              >
                  <i class="fa-solid fa-xmark text-xl w-6 h-6 flex items-center justify-center"></i>
              </button>
              
              <!-- Imagen en grande -->
              <img :src="selectedImageUrl" alt="Vista previa" class="max-w-full max-h-[85vh] object-contain w-auto h-auto">
          </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { Link, router } from "@inertiajs/vue3";
import debounce from "lodash/debounce"; 

const props = defineProps({
    products: Object, // Paginator Object
    filters: Object,
});

// --- Lógica de Búsqueda ---
const search = ref(props.filters?.search || '');

watch(search, debounce((value) => {
    router.get(route(route().current()), { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// --- Lógica del Modal de Imagen ---
const showImageModal = ref(false);
const selectedImageUrl = ref('');

const openImageModal = (url) => {
    selectedImageUrl.value = url;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
    setTimeout(() => {
        selectedImageUrl.value = ''; // Limpiar URL después de cerrar para evitar parpadeos al reabrir
    }, 200);
};
</script>

<style scoped>
/* Animación simple para el modal */
.animate-fade-in-up {
    animation: fadeInUp 0.3s ease-out forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>