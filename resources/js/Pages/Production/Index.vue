<template>
    <AppLayout title="Producción">
        <!-- Encabezado dinámico -->
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                <i :class="headerIcon" class="mr-2"></i> {{ headerTitle }}
            </h2>
            
            <!-- Controles para el manager -->
            <div v-if="viewType === 'manager'" class="flex items-center space-x-4">
                <!-- Botón para crear producción -->
                <Link :href="route('productions.create')">
                     <SecondaryButton>
                        <i class="fa-solid fa-plus mr-2"></i>
                        Crear Producción
                    </SecondaryButton>
                </Link>

                <!-- Switch de vistas Kanban/Tabla -->
                <div class="flex items-center space-x-2 p-1 bg-gray-200 dark:bg-slate-800 rounded-full">
                     <button @click="managerView.activeView = 'kanban'" 
                            :class="[managerView.activeView === 'kanban' ? 'bg-primary text-white shadow-lg' : 'text-gray-500 dark:text-gray-400', 'px-4 py-1 rounded-full transition-all duration-300']">
                        <i class="fa-solid fa-grip-vertical mr-1"></i> Kanban
                    </button>
                    <button @click="managerView.activeView = 'table'" 
                            :class="[managerView.activeView === 'table' ? 'bg-primary text-white shadow-lg' : 'text-gray-500 dark:text-gray-400', 'px-4 py-1 rounded-full transition-all duration-300']">
                        <i class="fa-solid fa-table-list mr-1"></i> Tabla
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Renderizado condicional basado en la prop viewType -->
        <ManagerView 
            v-if="viewType === 'manager'" 
            :productions="productions" 
            :kanban-data="kanbanData" 
            :active-view="managerView.activeView"
        />
        <OperatorView v-else-if="viewType === 'operator'" :tasks="tasks" />

        <!-- Fallback por si acaso -->
        <div v-else class="p-8 text-center text-gray-500">
            <p>No tienes una vista de producción asignada.</p>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import ManagerView from './Partials/ManagerView.vue';
import OperatorView from './Partials/OperatorView.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link } from '@inertiajs/vue3';

export default {
    name: 'ProductionIndex',
    components: {
        AppLayout,
        ManagerView,
        OperatorView,
        SecondaryButton,
        Link,
    },
    props: {
        viewType: String,
        productions: { 
            type: Array, 
            required: false 
        },
        kanbanData: { 
            type: Object, 
            required: false 
        },
        tasks: { 
            type: Array, 
            required: false 
        }
    },
    data() {
        return {
            // Estado local para controlar la vista activa del manager
            managerView: {
                activeView: 'kanban'
            }
        };
    },
    computed: {
        // Título dinámico para el encabezado
        headerTitle() {
            return this.viewType === 'manager' 
                ? 'Panel de Producción' 
                : 'Mis Tareas de Producción';
        },
        // Ícono dinámico para el encabezado
        headerIcon() {
            return this.viewType === 'manager' 
                ? 'fa-solid fa-layer-group' 
                : 'fa-solid fa-list-check';
        }
    }
};
</script>
