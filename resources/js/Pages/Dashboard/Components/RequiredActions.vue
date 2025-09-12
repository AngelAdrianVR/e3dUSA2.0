<template>
    <div class="h-full p-6 text-gray-800 transition-colors duration-300 bg-white rounded-2xl shadow-lg dark:bg-gray-800 dark:text-white">
        <h3 class="mb-6 text-xl font-bold">Accesos Rápidos</h3>

        <div v-for="section in sections" :key="section.title" class="mb-6 last:mb-0">
            <h4 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">{{ section.title }}</h4>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                
                <!-- Action Button -->
                <a v-for="button in section.buttons" 
                   :key="button.key"
                   :href="button.routeName" 
                   class="relative p-4 overflow-hidden transition-all duration-300 ease-in-out transform border-2 rounded-xl group hover:shadow-xl hover:-translate-y-1"
                   :class="button.count > 0 ? 'border-red-500/50 bg-red-500/5 dark:bg-red-500/10 hover:border-red-500' : 'border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/20 hover:border-blue-500 dark:hover:border-blue-400'">
                   
                    <!-- Go-to Arrow -->
                    <div class="absolute top-3 right-3 text-gray-400 dark:text-gray-500 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
                    </div>

                    <!-- Icon -->
                    <div class="flex items-center space-x-2">
                        <div class="text-gray-600 dark:text-gray-300" v-html="button.icon"></div>
                        <span class="font-bold text-3xl">{{ button.count }}</span>
                    </div>

                    <!-- Text -->
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ button.label }}</p>
                    </div>
                </a>

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
        sections() {
            return [
                {
                    title: 'Pendientes por autorizar',
                    buttons: [
                        {
                            label: 'Cotizaciones',
                            key: 'quotes_to_authorize',
                            count: this.actions.quotes_to_authorize || 0,
                            routeName: route('quotes.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>`
                        },
                        {
                            label: 'Órdenes de venta',
                            key: 'sales_to_authorize',
                            count: this.actions.sales_to_authorize || 0,
                            routeName: route('sales.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>`
                        },
                        {
                            label: 'Órdenes de compra',
                            key: 'purchases_to_authorize',
                            count: this.actions.purchases_to_authorize || 0,
                            routeName: route('purchases.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>`
                        },
                        {
                            label: 'Órdenes de diseño',
                            key: 'designs_to_authorize',
                            count: this.actions.designs_to_authorize || 0,
                            routeName: route('design-orders.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><path d="M2 2l7.586 7.586"></path><circle cx="11" cy="11" r="2"></circle></svg>`
                        },
                        {
                            label: 'Seguimiento de muestras',
                            key: 'sample_trackings_to_authorize',
                            count: this.actions.sample_trackings_to_authorize || 0,
                            routeName: route('sample-trackings.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>`
                        },
                    ]
                },
                {
                    title: 'Producción',
                    buttons: [
                        {
                            label: 'Ventas sin OV',
                            key: 'sales_without_ov',
                            count: this.actions.sales_without_ov || 0,
                            routeName: route('sales.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>`
                        },
                        {
                            label: 'Tareas sin iniciar',
                            key: 'unstarted_tasks',
                            count: this.actions.unstarted_tasks || 0,
                            routeName: route('productions.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="m9 14 2 2 4-4"></path></svg>`
                        },
                        {
                            label: 'Diseños sin iniciar',
                            key: 'unstarted_design_orders',
                            count: this.actions.unstarted_design_orders || 0,
                            routeName: route('design-orders.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.28 21.28L12 12M2.72 2.72L12 12M12 22V12M12 12V2M22 12H12M12 12H2"></path><circle cx="12" cy="12" r="10"></circle></svg>`
                        },
                        {
                            label: 'Producción pendiente',
                            key: 'pending_productions',
                            count: this.actions.pending_productions || 0,
                            routeName: route('productions.index'),
                            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[30px]"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" /></svg>`
                        },
                    ]
                }
            ];
        }
    }
}
</script>
