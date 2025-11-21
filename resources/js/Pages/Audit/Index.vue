<template>
    <AppLayout title="Historial de Acciones">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Historial de Acciones
        </h2>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    
                    <!-- Pestañas para filtrar por tipo de acción -->
                    <el-tabs v-model="activeTab" @tab-click="handleTabClick">
                        <el-tab-pane label="Todas" name="all"></el-tab-pane>
                        <el-tab-pane label="Creaciones" name="created"></el-tab-pane>
                        <el-tab-pane label="Actualizaciones" name="updated"></el-tab-pane>
                        <el-tab-pane label="Eliminaciones" name="deleted"></el-tab-pane>
                    </el-tabs>

                    <!-- Lista de acciones (Timeline) -->
                    <div class="mt-6 space-y-4 max-h-[500px] overflow-y-auto">
                        <div v-if="audits.data.length > 0">
                            <!-- Se añade un handler de click a cada registro -->
                            <div v-for="audit in audits.data" :key="audit.id" 
                                @click="openDetailsModal(audit)"
                                :class="isClickable(audit) ? 'cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-800' : ''"
                                class="flex items-start space-x-4 my-2 p-4 rounded-lg bg-gray-50 dark:bg-slate-800/50 transition-colors duration-200">
                                
                                <!-- Contenedor para el avatar y el icono de acción -->
                                <div class="flex-shrink-0 flex items-center group transition-all duration-300 ease-in-out">
                                    <img v-if="audit.user" :src="audit.user.profile_photo_url" :alt="audit.user.name"
                                        class="size-12 rounded-full object-cover border-2 border-white dark:border-slate-800 group-hover:translate-x-2 transition-transform duration-300 ease-in-out">
                                    <div class=" -ml-4 size-10 rounded-full flex items-center justify-center ring-4 ring-white dark:ring-slate-800"
                                        :class="getIconInfo(audit.event).bgClass">
                                        <i class="fa-solid" :class="getIconInfo(audit.event).iconClass"></i>
                                    </div>
                                </div>
                                
                                <!-- Contenido del registro -->
                                <div class="flex-grow">
                                    <p class="text-sm text-gray-800 dark:text-gray-200" v-html="formatAuditMessage(audit)"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ formatDateTime(audit.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else>
                            <Empty />
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="audits.total > 0" class="flex justify-center mt-8">
                        <el-pagination v-model:current-page="audits.current_page" :page-size="audits.per_page" :total="audits.total" layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para ver los detalles de los cambios -->
        <DialogModal :show="showDetailsModal" @close="closeDetailsModal">
            <template #title>Detalles del Cambio</template>
            <template #content>
                <div v-if="selectedAudit">
                    <div class="grid grid-cols-2 gap-6">
                        <!-- Columna de Valores Anteriores -->
                        <div>
                            <h3 class="font-bold text-lg mb-2 border-b pb-2 text-gray-700 dark:text-gray-200">Valores Anteriores</h3>
                            <div v-if="Object.keys(selectedAudit.old_values).length > 0" class="space-y-2 text-sm">
                                <div v-for="(value, key) in selectedAudit.old_values" :key="key">
                                    <strong class="text-gray-500 dark:text-gray-400 capitalize">{{ formatKey(key) }}:</strong>
                                    <p class="text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/50 rounded px-2 py-1 break-all">{{ value ?? '-' }}</p>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400 italic">No hay datos anteriores (es una creación).</p>
                        </div>

                        <!-- Columna de Valores Nuevos -->
                        <div>
                            <h3 class="font-bold text-lg mb-2 border-b pb-2 text-gray-700 dark:text-gray-200">Valores Nuevos</h3>
                            <div v-if="Object.keys(selectedAudit.new_values).length > 0" class="space-y-2 text-sm">
                                <div v-for="(value, key) in selectedAudit.new_values" :key="key">
                                    <strong class="text-gray-500 dark:text-gray-400 capitalize">{{ formatKey(key) }}:</strong>
                                    <p class="text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/50 rounded px-2 py-1 break-all">{{ value }}</p>
                                </div>
                            </div>
                            <div v-else class="flex flex-col justify-center items-center mt-12">
                                <i class="fa-solid fa-trash-can text-4xl"></i>
                                <p class="text-sm text-gray-400 italic">Se eliminó el archivo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <CancelButton @click="closeDetailsModal">Cerrar</CancelButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import Empty from '@/Components/MyComponents/Empty.vue';

export default {
    name: 'AuditIndex',
    components: {
        Empty,
        AppLayout,
        DialogModal,
        CancelButton,
    },
    props: {
        audits: Object,
        filters: Object,
    },
    data() {
        return {
            activeTab: this.filters.event || 'all',
            showDetailsModal: false,
            selectedAudit: null,
        };
    },
    methods: {
        // --- Métodos del Modal ---
        openDetailsModal(audit) {
            // Solo abre el modal si el evento es de actualización o eliminación
            if (this.isClickable(audit)) {
                this.selectedAudit = audit;
                this.showDetailsModal = true;
            }
        },
        closeDetailsModal() {
            this.showDetailsModal = false;
            this.selectedAudit = null;
        },
        isClickable(audit) {
            return ['updated', 'deleted'].includes(audit.event);
        },
        formatKey(key) {
            // Reemplaza guiones bajos por espacios y capitaliza la primera letra
            return (key || '').replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase());
        },

        // --- Métodos existentes ---
        handleTabClick(tab) {
            const event = tab.props.name === 'all' ? undefined : tab.props.name;
            this.$inertia.get(route('audits.index'), { event }, { preserveState: true, replace: true });
        },
        handlePageChange(page) {
            const event = this.activeTab === 'all' ? undefined : this.activeTab;
            this.$inertia.get(route('audits.index'), { page, event }, { preserveState: false });
        },
        formatAuditMessage(audit) {
            const userName = `<strong>${audit.user?.name || 'Usuario desconocido'}</strong>`;
            const action = this.getIconInfo(audit.event).actionText;
            const moduleName = this.formatTableName(audit.auditable_type);
            const recordId = `<strong>${audit.auditable_id}</strong>`;
            return `${userName} ${action} el registro con ID: ${recordId} del módulo <strong>${moduleName}</strong>`;
        },
        formatTableName(modelPath) {
            if (!modelPath) return 'desconocido';
            const modelName = modelPath.split('\\').pop();
            const translations = {
                Bonus: 'Bonos',
                Branch: 'Clientes',
                BranchPriceHistory: 'Historial de precios especiales',
                Brand: 'Marcas',
                DesignAuthorization: 'Formato de autorización de diseño',
                DesignOrder: 'Orden de diseños',
                Discount: 'Descuentos',
                Holiday: 'Días festivos',
                Machine: 'Maquinas',
                Maintenance: 'Mantenimientos',
                Manual: 'Manual/Tutorial',
                Permission: 'Permisos',
                Product: 'Productos',
                ProductFamily: 'Familia de productos',
                Production: 'Producción',
                ProductionCost: 'Proceso de producción',
                Purchase: 'Compras',
                Quote: 'Cotización',
                Sale: 'Ventas',
                SampleTracking: 'Seguimiento de muestra',
                Shipment: 'Envíos',
                SparePart: 'Refacciónes',
                Supplier: 'Proveedores',
                User: 'Usuarios',
            };
            return translations[modelName] || modelName;
        },
        formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace('.', '');
        },
        getIconInfo(event) {
            switch (event) {
                case 'created':
                    return { iconClass: 'fa-plus text-green-500', bgClass: 'bg-green-100 dark:bg-green-900', actionText: 'creó' };
                case 'updated':
                    return { iconClass: 'fa-pencil text-blue-500', bgClass: 'bg-blue-100 dark:bg-blue-900', actionText: 'actualizó' };
                case 'deleted':
                    return { iconClass: 'fa-trash-can text-red-500', bgClass: 'bg-red-100 dark:bg-red-900', actionText: 'eliminó' };
                default:
                    return { iconClass: 'fa-question', bgClass: 'bg-gray-100 dark:bg-gray-700', actionText: 'realizó una acción en' };
            }
        },
    }
}
</script>
