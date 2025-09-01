<template>
    <AppLayout title="Producción">
        <!-- Encabezado dinámico -->
        <div>
            <div class="flex items-center justify-between space-x-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    <i :class="headerIcon" class="mr-2"></i> {{ headerTitle }}
                </h2>

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
            
            <!-- Controles para el manager -->
            <div v-if="viewType === 'manager'" class="flex items-center justify-start space-x-3 w-full">
                 <!-- Filtro por Estatus -->
                <el-select
                    v-model="selectedStatus"
                    @change="filterByStatus"
                    placeholder="Filtrar por Estatus"
                    clearable
                    class="!w-64"
                >
                    <el-option label="Mostrar Todos" value="" />
                    <el-option
                        v-for="status in productionStatuses"
                        :key="status.id"
                        :label="status.label"
                        :value="status.id"
                    />
                </el-select>

                <!-- Botón para crear producción -->
                <Link :href="route('productions.create')">
                     <SecondaryButton>
                        <i class="fa-solid fa-plus mr-2"></i>
                        Crear
                    </SecondaryButton>
                </Link>

                <!-- Componente de Paginación -->
                <div v-if="viewType === 'manager' && sales.links.length > 3" class="flex justify-center mt-6">
                    <div class="flex flex-wrap gap-2">
                        <template v-for="(link, key) in sales.links" :key="key">
                        <!-- Botón deshabilitado -->
                        <div
                            v-if="link.url === null"
                            class="px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg cursor-not-allowed select-none"
                            v-html="link.label"
                        />
                        
                        <!-- Botón activo o normal -->
                        <Link
                            v-else
                            class="px-4 py-2 text-sm font-medium rounded-lg border transition-all duration-200
                                hover:shadow-md hover:scale-105
                                focus:outline-none focus:ring-2 focus:ring-primary/50"
                            :class="{
                            'bg-primary text-white border-primary shadow-md': link.active,
                            'bg-white dark:bg-slate-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800': !link.active
                            }"
                            :href="link.url"
                            v-html="link.label"
                        />
                        </template>
                    </div>
                </div>


            </div>
        </div>
        
        <!-- Vista del Manager -->
        <ManagerView 
            v-if="viewType === 'manager'" 
            :sales="sales.data"
            :active-view="managerView.activeView"
        />
        <!-- Vista del Operador -->
        <OperatorView v-else-if="viewType === 'operator'" :tasks="tasks" />

        <!-- Fallback -->
        <div v-else class="p-4 text-center text-gray-500">
            <p>No tienes una vista de producción asignada.</p>
        </div>

    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import ManagerView from './Partials/ManagerView.vue';
import OperatorView from './Partials/OperatorView.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link, router } from '@inertiajs/vue3';

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
        sales: { // Ahora recibe el objeto paginado completo
            type: Object, 
            required: false 
        },
        tasks: { 
            type: Array, 
            required: false 
        },
        filters: {
            type: Object,
            required: false
        }
    },
    data() {
        return {
            managerView: {
                activeView: 'kanban'
            },
            // v-model para el nuevo filtro de estatus
            selectedStatus: this.filters?.status || '',
            // Opciones para el dropdown de filtro
            productionStatuses: [
                { id: 'Pendiente', label: 'Pendiente' },
                { id: 'En Proceso', label: 'En Proceso' },
                { id: 'Sin material', label: 'Sin material' },
                { id: 'Pausada', label: 'Pausada' },
                { id: 'Terminada', label: 'Terminada' },
            ]
        };
    },
    computed: {
        headerTitle() {
            return this.viewType === 'manager' 
                ? 'Panel de Producción' 
                : 'Mis Tareas de Producción';
        },
        headerIcon() {
            return this.viewType === 'manager' 
                ? 'fa-solid fa-layer-group' 
                : 'fa-solid fa-list-check';
        }
    },
    methods: {
        // Método para recargar la página con el filtro de estatus
        filterByStatus() {
            router.get(route('productions.index'),
                { status: this.selectedStatus },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }
            );
        }
    }
};
</script>

