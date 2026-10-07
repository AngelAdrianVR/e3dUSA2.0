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
                        <el-tab-pane label="Autorizaciones" name="authorized"></el-tab-pane>
                        <el-tab-pane label="Eliminaciones" name="deleted"></el-tab-pane>
                        <!-- Modificaciones de horas en nómina (solo para el usuario autorizado) -->
                        <el-tab-pane v-if="canViewHoursAudits" label="Horas Nómina" name="hours_updated"></el-tab-pane>
                    </el-tabs>

                    <!-- Filtros de búsqueda -->
                    <div class="mt-4 mb-5 p-4 rounded-xl bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-slate-700">
                        <div class="flex flex-wrap items-end gap-3">
                            <!-- Módulo -->
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Módulo</label>
                                <el-select v-model="moduleFilter" placeholder="Todos los módulos" clearable filterable
                                    class="!w-56" @change="applyFilters">
                                    <el-option v-for="module in modules" :key="module" :label="formatTableName(module)" :value="module" />
                                </el-select>
                            </div>

                            <!-- Usuario -->
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Usuario</label>
                                <el-select v-model="userFilter" placeholder="Todos los usuarios" clearable filterable
                                    class="!w-52" @change="applyFilters">
                                    <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                                </el-select>
                            </div>

                            <!-- ID del registro -->
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">ID del registro</label>
                                <el-input v-model="recordIdFilter" placeholder="Ej. 125" clearable
                                    class="!w-36" @keyup.enter="applyFilters" @clear="applyFilters">
                                    <template #prefix>
                                        <i class="fa-solid fa-hashtag text-gray-400"></i>
                                    </template>
                                </el-input>
                            </div>

                            <!-- Rango de fechas -->
                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Rango de fechas</label>
                                <el-date-picker v-model="dateRangeFilter" type="daterange" value-format="YYYY-MM-DD"
                                    unlink-panels range-separator="a" start-placeholder="Desde" end-placeholder="Hasta"
                                    class="!w-64" @change="applyFilters" />
                            </div>

                            <el-button type="primary" @click="applyFilters">
                                <i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar
                            </el-button>

                            <el-button v-if="hasActiveFilters" plain @click="clearFilters">
                                <i class="fa-solid fa-broom mr-1"></i> Limpiar
                            </el-button>

                            <span class="ml-auto self-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="fa-solid fa-list-ul mr-1"></i>
                                {{ audits.total }} {{ audits.total === 1 ? 'registro' : 'registros' }}
                            </span>
                        </div>
                    </div>

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
                                <div class="flex-grow min-w-0">
                                    <p class="text-sm text-gray-800 dark:text-gray-200" v-html="formatAuditMessage(audit)"></p>

                                    <!-- Resumen de los campos afectados -->
                                    <div v-if="getChangesSummary(audit).length" class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span v-for="change in getChangesSummary(audit)" :key="change.key"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-gray-300">
                                            <span class="font-semibold">{{ change.label }}:</span>
                                            <template v-if="change.old !== null">
                                                <span class="text-red-500 dark:text-red-400 line-through">{{ change.old }}</span>
                                                <i v-if="change.new !== null" class="fa-solid fa-arrow-right text-[8px] text-gray-400"></i>
                                            </template>
                                            <span v-if="change.new !== null" class="text-green-600 dark:text-green-400">{{ change.new }}</span>
                                        </span>
                                        <span v-if="hasMoreChanges(audit)" class="text-[11px] italic text-gray-400">
                                            +{{ getExtraChangesCount(audit) }} campo(s) más
                                        </span>
                                    </div>

                                    <!-- Metadatos: fecha relativa y ID del registro -->
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        <el-tooltip :content="formatDateTime(audit.created_at)" placement="top">
                                            <span class="inline-flex items-center gap-1">
                                                <i class="fa-regular fa-clock"></i>{{ formatRelativeTime(audit.created_at) }}
                                            </span>
                                        </el-tooltip>
                                        <button @click.stop="copyRecordId(audit)"
                                            class="inline-flex items-center gap-1 transition-colors hover:text-blue-500"
                                            title="Copiar ID del registro">
                                            <i class="fa-regular fa-copy"></i>ID: {{ audit.auditable_id }}
                                        </button>
                                        <span class="inline-flex items-center gap-1">
                                            <i class="fa-solid fa-user-pen"></i>{{ formatTableName(audit.auditable_type) }}
                                        </span>
                                        <!-- Ir al registro auditado (solo si el módulo tiene vista de detalle) -->
                                        <button v-if="getRecordRoute(audit)" @click.stop="goToRecord(audit)"
                                            class="inline-flex items-center gap-1 font-medium text-blue-500 transition-colors hover:text-blue-600 hover:underline"
                                            title="Ir al registro">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>Ver registro
                                        </button>
                                    </div>
                                </div>

                                <!-- Indicador de detalle disponible -->
                                <i v-if="isClickable(audit)" class="self-center fa-solid fa-chevron-right text-gray-300 dark:text-gray-600"></i>
                            </div>
                        </div>
                        <!-- Sin resultados -->
                        <div v-else-if="hasActiveFilters" class="flex flex-col items-center justify-center gap-2 py-14 text-center">
                            <i class="fa-solid fa-magnifying-glass text-4xl text-gray-300 dark:text-gray-600"></i>
                            <p class="font-medium text-gray-600 dark:text-gray-300">No se encontraron registros con los filtros aplicados</p>
                            <button @click="clearFilters" class="text-sm text-blue-500 hover:underline">Limpiar filtros</button>
                        </div>
                        <div v-else>
                            <Empty label="Todavía no hay acciones registradas" />
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
                <div v-if="selectedAudit" class="space-y-4">
                    <!-- Encabezado con el contexto de la acción -->
                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-slate-800">
                            <i class="fa-solid fa-user-pen"></i>{{ formatTableName(selectedAudit.auditable_type) }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-slate-800">
                            <i class="fa-solid fa-hashtag"></i>ID: {{ selectedAudit.auditable_id }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-slate-800">
                            <i class="fa-regular fa-user"></i>{{ selectedAudit.user?.name || 'Usuario desconocido' }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-slate-800">
                            <i class="fa-regular fa-clock"></i>{{ formatDateTime(selectedAudit.created_at) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Columna de Valores Anteriores -->
                        <div>
                            <h3 class="font-bold text-lg mb-2 border-b pb-2 text-gray-700 dark:text-gray-200">Valores Anteriores</h3>
                            <div v-if="getValueEntries(selectedAudit.old_values).length > 0" class="space-y-2 text-sm">
                                <div v-for="entry in getValueEntries(selectedAudit.old_values)" :key="entry.key">
                                    <strong class="text-gray-500 dark:text-gray-400">{{ entry.label }}:</strong>
                                    <p class="text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/50 rounded px-2 py-1 break-all">{{ entry.value }}</p>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400 italic">No hay datos anteriores (es una creación).</p>
                        </div>

                        <!-- Columna de Valores Nuevos -->
                        <div>
                            <h3 class="font-bold text-lg mb-2 border-b pb-2 text-gray-700 dark:text-gray-200">Valores Nuevos</h3>
                            <div v-if="getValueEntries(selectedAudit.new_values).length > 0" class="space-y-2 text-sm">
                                <div v-for="entry in getValueEntries(selectedAudit.new_values)" :key="entry.key">
                                    <strong class="text-gray-500 dark:text-gray-400">{{ entry.label }}:</strong>
                                    <p class="text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/50 rounded px-2 py-1 break-all">{{ entry.value }}</p>
                                </div>
                            </div>
                            <div v-else class="flex flex-col justify-center items-center mt-12">
                                <i class="fa-solid fa-trash-can text-4xl"></i>
                                <p class="text-sm text-gray-400 italic">Se eliminó el registro.</p>
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
import { ElMessage } from 'element-plus';

// Nombres legibles para los campos más comunes de las auditorías
const FIELD_LABELS = {
    id: 'ID',
    name: 'Nombre',
    code: 'Código',
    folio: 'Folio',
    status: 'Estatus',
    description: 'Descripción',
    comments: 'Comentarios',
    notes: 'Notas',
    quantity: 'Cantidad',
    price: 'Precio',
    cost: 'Costo',
    total_amount: 'Monto total',
    currency: 'Moneda',
    user_id: 'Usuario',
    branch_id: 'Cliente',
    contact_id: 'Contacto',
    product_id: 'Producto',
    sale_id: 'Orden de venta',
    quote_id: 'Cotización',
    purchase_id: 'Orden de compra',
    invoice_id: 'Factura',
    supplier_id: 'Proveedor',
    requester_user_id: 'Solicitante',
    authorized_by_user_id: 'Autorizado por',
    authorized_user_name: 'Autorizado por',
    authorizer_id: 'Autorizado por',
    authorizer_name: 'Autorizado por',
    supplier_bank_account_id: 'Cuenta bancaria',
    authorized_at: 'Fecha de autorización',
    denied_at: 'Fecha de rechazo',
    approved_at: 'Fecha de aprobación',
    sent_at: 'Fecha de envío',
    returned_at: 'Fecha de devolución',
    completed_at: 'Fecha de finalización',
    expected_devolution_date: 'Devolución esperada',
    will_be_returned: 'Será devuelta',
    is_active: 'Activo',
    is_high_priority: 'Alta prioridad',
    priority: 'Prioridad',
    email: 'Correo',
    phone: 'Teléfono',
    address: 'Dirección',
    rfc: 'RFC',
    archived_at: 'Fecha de archivado',
    // Modificaciones de horas en nómina
    employee: 'Empleado',
    date: 'Fecha',
    entry: 'Entrada',
    exit: 'Salida',
    breaks: 'Descansos',
    worked: 'Tiempo registrado',
};

// Ruta de detalle por módulo auditado (solo los que cuentan con vista de detalle)
const RECORD_ROUTES = {
    Branch: 'branches.show',
    DesignAuthorization: 'design-authorizations.show',
    DesignOrder: 'design-orders.show',
    Invoice: 'invoices.show',
    Machine: 'machines.show',
    Manual: 'manuals.show',
    Product: 'catalog-products.show',
    ProductExchange: 'product-exchanges.show',
    Production: 'productions.show',
    Purchase: 'purchases.show',
    Quote: 'quotes.show',
    Sale: 'sales.show',
    SampleTracking: 'sample-trackings.show',
    Shipment: 'shipments.show',
    Supplier: 'suppliers.show',
    User: 'users.show',
};

// Campos irrelevantes o sensibles que no conviene mostrar en el historial
const IGNORED_FIELDS = [
    'updated_at',
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
];

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
        modules: {
            type: Array,
            default: () => [],
        },
        users: {
            type: Array,
            default: () => [],
        },
        canViewHoursAudits: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            activeTab: this.filters.event || 'all',
            moduleFilter: this.filters.module || null,
            userFilter: this.filters.user_id ? Number(this.filters.user_id) : null,
            recordIdFilter: this.filters.record_id || '',
            dateRangeFilter: this.filters.date_from && this.filters.date_to
                ? [this.filters.date_from, this.filters.date_to]
                : [],
            showDetailsModal: false,
            selectedAudit: null,
        };
    },
    computed: {
        hasActiveFilters() {
            return !!this.moduleFilter || !!this.userFilter || !!this.recordIdFilter || this.hasDateRange;
        },
        hasDateRange() {
            return Array.isArray(this.dateRangeFilter) && this.dateRangeFilter.length === 2;
        },
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
            return ['created', 'updated', 'deleted', 'authorized', 'hours_updated'].includes(audit.event);
        },
        formatKey(key) {
            if (!key) return '';
            if (FIELD_LABELS[key]) return FIELD_LABELS[key];
            // Reemplaza guiones bajos por espacios y capitaliza la primera letra
            return key.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase());
        },

        // --- Filtros ---
        buildParams(extra = {}) {
            const params = { ...extra };
            if (this.activeTab && this.activeTab !== 'all') params.event = this.activeTab;
            if (this.moduleFilter) params.module = this.moduleFilter;
            if (this.userFilter) params.user_id = this.userFilter;
            if (this.recordIdFilter) params.record_id = this.recordIdFilter;
            if (this.hasDateRange) {
                params.date_from = this.dateRangeFilter[0];
                params.date_to = this.dateRangeFilter[1];
            }
            return params;
        },
        applyFilters() {
            this.$inertia.get(route('audits.index', this.buildParams()), {}, {
                preserveState: true,
                replace: true,
            });
        },
        clearFilters() {
            this.moduleFilter = null;
            this.userFilter = null;
            this.recordIdFilter = '';
            this.dateRangeFilter = [];
            this.applyFilters();
        },
        handleTabClick(tab) {
            this.activeTab = tab.props.name;
            this.applyFilters();
        },
        handlePageChange(page) {
            this.$inertia.get(route('audits.index', this.buildParams({ page })), {
                preserveState: true,
                replace: true,
            });
        },

        // --- Detalle de valores ---
        getValueEntries(values) {
            return Object.entries(values || {})
                .filter(([key]) => !IGNORED_FIELDS.includes(key))
                .map(([key, value]) => ({ key, label: this.formatKey(key), value: this.formatValue(value) }));
        },
        getChangedKeys(audit) {
            const oldValues = audit.old_values || {};
            const newValues = audit.new_values || {};
            const keys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])];
            return keys
                .filter(key => !IGNORED_FIELDS.includes(key))
                .filter(key => String(oldValues[key] ?? '') !== String(newValues[key] ?? ''));
        },
        getChangesSummary(audit) {
            const oldValues = audit.old_values || {};
            const newValues = audit.new_values || {};
            return this.getChangedKeys(audit).slice(0, 4).map(key => ({
                key,
                label: this.formatKey(key),
                old: audit.event === 'created' ? null : this.formatValue(oldValues[key]),
                new: audit.event === 'deleted' ? null : this.formatValue(newValues[key]),
            }));
        },
        hasMoreChanges(audit) {
            return this.getChangedKeys(audit).length > 4;
        },
        getExtraChangesCount(audit) {
            return Math.max(this.getChangedKeys(audit).length - 4, 0);
        },
        formatValue(value) {
            if (value === null || value === undefined || value === '') return '—';
            if (typeof value === 'boolean') return value ? 'Sí' : 'No';
            if (typeof value === 'object') return JSON.stringify(value);
            const text = String(value);
            // Las fechas se muestran en formato legible
            if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(text)) return this.formatDateTime(text);
            if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return this.formatDateOnly(text);
            return text;
        },
        copyRecordId(audit) {
            const text = String(audit.auditable_id);
            navigator.clipboard?.writeText(text)
                .then(() => ElMessage.success(`ID ${text} copiado al portapapeles`))
                .catch(() => ElMessage.error('No se pudo copiar el ID'));
        },
        formatRelativeTime(dateString) {
            const date = new Date(dateString);
            const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
            if (seconds < 0) return 'En el futuro';
            if (seconds < 60) return 'Hace unos segundos';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `Hace ${minutes} min`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `Hace ${hours} h`;
            const days = Math.floor(hours / 24);
            if (days < 30) return `Hace ${days} d`;
            const months = Math.floor(days / 30);
            if (months < 12) return `Hace ${months} mes${months > 1 ? 'es' : ''}`;
            const years = Math.floor(months / 12);
            return `Hace ${years} año${years > 1 ? 's' : ''}`;
        },
        formatAuditMessage(audit) {
            const userName = `<strong>${audit.user?.name || 'Usuario desconocido'}</strong>`;

            // Las modificaciones de horas se describen con el empleado y el día afectado
            if (audit.event === 'hours_updated') {
                const values = audit.new_values || audit.old_values || {};
                const employee = values.employee || `Empleado #${audit.auditable_id}`;
                return `${userName} modificó las horas de <strong>${employee}</strong> del día <strong>${this.formatDateOnly(values.date)}</strong>`;
            }

            const action = this.getIconInfo(audit.event).actionText;
            const moduleName = this.formatTableName(audit.auditable_type);
            const recordId = `<strong>${audit.auditable_id}</strong>`;
            return `${userName} ${action} el registro con ID: ${recordId} del módulo <strong>${moduleName}</strong>`;
        },
        // Ruta al detalle del registro auditado, si el módulo cuenta con una vista de detalle
        getRecordRoute(audit) {
            const modelName = (audit.auditable_type || '').split('\\').pop();
            const routeName = RECORD_ROUTES[modelName];
            if (!routeName) return null;
            try {
                return route(routeName, audit.auditable_id);
            } catch (error) {
                return null;
            }
        },
        goToRecord(audit) {
            const url = this.getRecordRoute(audit);
            if (url) this.$inertia.visit(url);
        },
        formatDateOnly(dateString) {
            if (!dateString) return '—';
            const date = new Date(`${dateString}T00:00:00`);
            return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }).replace('.', '');
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
                EmployeeDetail: 'Empleado (Nómina)',
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
                case 'authorized':
                    return { iconClass: 'fa-check-double text-emerald-500', bgClass: 'bg-emerald-100 dark:bg-emerald-900', actionText: 'autorizó' };
                case 'hours_updated':
                    return { iconClass: 'fa-clock-rotate-left text-amber-500', bgClass: 'bg-amber-100 dark:bg-amber-900', actionText: 'modificó las horas de' };
                case 'deleted':
                    return { iconClass: 'fa-trash-can text-red-500', bgClass: 'bg-red-100 dark:bg-red-900', actionText: 'eliminó' };
                default:
                    return { iconClass: 'fa-question', bgClass: 'bg-gray-100 dark:bg-gray-700', actionText: 'realizó una acción en' };
            }
        },
    }
}
</script>

