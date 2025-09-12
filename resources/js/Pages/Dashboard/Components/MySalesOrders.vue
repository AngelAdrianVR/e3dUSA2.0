<template>
    <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
        <h3 class="mb-3 font-semibold text-gray-800 dark:text-white">Órdenes creadas por mí</h3>
        <div class="space-y-2 max-h-72 overflow-y-auto">
            <div v-for="order in orders" :key="order.id"
                :class="['p-3 border rounded-lg flex justify-between items-center transition-all', order.requires_follow_up ? 'border-red-500 bg-red-500/10' : 'dark:border-gray-700 border-gray-200']">
                <div class=" w-full mr-2">
                    <div class="flex items-center justify-between w-full">
                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ order.folio }}</p>
                        <el-tag :type="getStatusTagType(order.status)">
                            {{ order.status }}
                        </el-tag>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Creado: {{ new Date(order.created_at).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
                </div>
                <div class="flex items-center space-x-2">
                     <p v-if="order.requires_follow_up" class="px-2 py-1 text-xs text-center text-white bg-red-500 rounded-full w-full">
                        Prioridad
                    </p>
                    <button @click="$inertia.visit(route('sales.show', order.id))" class="flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-full size-10 dark:hover:bg-slate-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
             <p v-if="!orders.length" class="text-sm text-center text-gray-500 dark:text-gray-400">No tienes órdenes pendientes.</p>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        orders: Array,
    },
    methods:{
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'info',
                'Autorizada': 'primary',
                'En Proceso': 'warning',
                'En Producción': 'primary',
                'Stock Terminado': 'success',
                'Preparando Envío': 'success',
                'Enviada': 'success',
            };
            return statusMap[status] || '';
        }
    }
};
</script>
