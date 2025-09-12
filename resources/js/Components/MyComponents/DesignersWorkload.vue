<template>
    <!-- Contenedor principal para el componente de carga de trabajo -->
    <div class="fixed top-20 right-0 z-30 flex items-center pointer-events-none">
        <!-- Pestaña para abrir el panel -->
        <transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="!isOpen" class="pointer-events-auto">
                <button 
                    @click="togglePanel"
                    class="group relative flex items-center justify-center w-8 h-16 bg-slate-800/80 backdrop-blur-sm text-white rounded-l-2xl shadow-2xl hover:bg-slate-700/90 transition-all transform hover:-translate-x-1 focus:outline-none focus:ring-2 focus:ring-amber-400"
                    aria-label="Mostrar carga de trabajo de diseñadores"
                >
                    <!-- Icono -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                    </svg>
                    <!-- Tooltip -->
                    <span class="absolute right-full mr-2 w-max px-2 py-1 text-xs font-medium text-white bg-slate-900 rounded-md shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        Carga de trabajo
                    </span>
                </button>
            </div>
        </transition>
    </div>

    <!-- Panel de Carga de Trabajo -->
    <div class="fixed top-1/2 right-0 -translate-y-1/2 z-30 flex items-center pointer-events-none">
        <div class="w-96 h-[calc(80vh-7rem)] bg-slate-900/60 backdrop-blur-xl border border-slate-700 rounded-l-2xl shadow-2xl flex flex-col transition-transform duration-500 ease-in-out pointer-events-auto"
             :class="isOpen ? 'translate-x-0' : 'translate-x-full'">
            
            <!-- Encabezado del Panel -->
            <div class="flex justify-between items-center p-4 border-b border-slate-700 flex-shrink-0">
                <h3 class="font-bold text-lg flex items-center text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                    Carga de trabajo Diseñadores
                </h3>
                <button @click="isOpen = false" class="text-slate-400 hover:text-white transition-colors" aria-label="Cerrar panel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <!-- Contenido del Panel -->
            <div class="p-4 space-y-3 flex-grow overflow-y-auto">
                <div v-if="isLoading" class="flex justify-center items-center h-full">
                    <p class="text-slate-400">Cargando diseñadores...</p>
                </div>
                <div v-else-if="designers.length === 0" class="flex justify-center items-center h-full">
                    <p class="text-slate-400">No se encontraron diseñadores activos.</p>
                </div>
                
                <!-- Lista de Diseñadores -->
                <div v-else v-for="designer in designers" :key="designer.id" class="bg-slate-800/50 p-3 rounded-lg">
                    <div @click="toggleDesigner(designer.id)" class="flex justify-between items-center cursor-pointer">
                        <p class="font-semibold text-white">{{ designer.name }}</p>
                        <div class="flex items-center space-x-3">
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full" :class="getWorkloadClass(designer.assigned_design_orders.length)">
                                {{ designer.assigned_design_orders.length }} órdenes
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 text-slate-400 transition-transform" :class="{'rotate-180': expandedDesigners.includes(designer.id)}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                    <!-- Órdenes asignadas (colapsable) -->
                    <div v-if="expandedDesigners.includes(designer.id)" class="mt-3 pt-3 border-t border-slate-700 space-y-2">
                        <p v-if="designer.assigned_design_orders.length === 0" class="text-sm text-slate-400 italic">Sin órdenes asignadas.</p>
                        <div v-else v-for="order in designer.assigned_design_orders" :key="order.id" class="text-sm bg-slate-900/70 p-2 rounded-md">
                            <p class="text-slate-200 truncate font-medium" :title="order.order_title">{{ order.order_title }}</p>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs text-slate-400">Cliente: {{ order.branch?.name ?? 'Cliente no especificado' }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full" :class="getStatusClass(order.status)">{{ order.status }}</span>
                            </div>
                            <p class="text-amber-200 text-xs">Categoría: {{ order.design_category?.name }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    data() {
        return {
            designers: [],
            isLoading: false,
            isOpen: false,
            expandedDesigners: [],
        };
    },
    methods: {
        async fetchDesigners() {
            if (this.isLoading) return;
            this.isLoading = true;
            try {
                const response = await axios.get(route('design-orders.get-designers'));
                this.designers = response.data;
            } catch (error) {
                console.error("Error fetching designers:", error);
                ElMessage.error('No se pudo cargar la lista de diseñadores.');
            } finally {
                this.isLoading = false;
            }
        },
        togglePanel() {
            this.isOpen = !this.isOpen;
            if (this.isOpen && this.designers.length === 0) {
                this.fetchDesigners();
            }
        },
        toggleDesigner(designerId) {
            const index = this.expandedDesigners.indexOf(designerId);
            if (index > -1) {
                this.expandedDesigners.splice(index, 1);
            } else {
                this.expandedDesigners.push(designerId);
            }
        },
        getWorkloadClass(count) {
            if (count < 3) return 'bg-green-400 text-green-900';
            if (count < 4) return 'bg-yellow-400 text-yellow-900';
            return 'bg-red-400 text-red-900';
        },
        getStatusClass(status) {
            const statusClasses = {
                'Pendiente': 'bg-gray-400 text-white',
                'Autorizada': 'bg-yellow-200 text-yellow-700',
                'En proceso': 'bg-blue-400 text-blue-900',
                'Terminada': 'bg-green-400 text-green-900',
                'Cancelado': 'bg-red-400 text-red-900',
            };
            return statusClasses[status] || 'bg-gray-400 text-black';
        }
    }
}
</script>

