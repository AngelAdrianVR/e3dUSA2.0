<template>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg h-full transition-colors duration-300">
        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Acciones requeridas</h3>
        <div>
            <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase">Pendientes por autorizar</h4>
            <div class="space-y-2">
                <div v-for="item in authorizations" :key="item.name" class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ item.name }}</span>
                    <span class="font-bold text-lg" :class="item.count > 0 ? 'text-red-500' : 'text-gray-400 dark:text-gray-500'">{{ item.count }}</span>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <div class="space-y-2">
                 <div v-for="item in otherActions" :key="item.name" class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ item.name }}</span>
                    <span class="font-bold text-lg" :class="item.count > 0 ? 'text-yellow-500' : 'text-gray-400 dark:text-gray-500'">{{ item.count }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'RequiredActions',
    props: {
        actions: {
            type: Object,
            default: () => ({})
        }
    },
    computed: {
        authorizations() {
            return [
                { name: 'Cotizaciones', count: this.actions.quotes_to_authorize || 0 },
                { name: 'Órdenes de venta', count: this.actions.sales_to_authorize || 0 },
                { name: 'Órdenes de diseño', count: this.actions.designs_to_authorize || 0 },
                { name: 'Órdenes de compra', count: this.actions.purchases_to_authorize || 0 },
            ];
        },
        otherActions() {
            return [
                 { name: 'Producción pendiente', count: this.actions.pending_productions || 0 },
                 { name: 'Tareas asignadas sin iniciar', count: this.actions.unstarted_tasks || 0 },
                 { name: 'Órdenes de diseño sin iniciar', count: this.actions.unstarted_design_orders || 0 },
            ];
        }
    }
}
</script>
