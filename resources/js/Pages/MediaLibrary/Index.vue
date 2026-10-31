<template>
    <AppLayout title="Biblioteca de Medios">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Biblioteca de Medios
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800/50 overflow-hidden shadow-xl sm:rounded-lg p-6">

                    <!-- Header with Search, Filters and Action -->
                    <header class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Explorador de Archivos
                        </h1>
                        
                        <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                            <!-- Botón Subir (Solo si tiene permiso, ajusta el nombre del permiso si es necesario) -->
                            <button 
                                v-if="$page.props.auth.user.permissions.includes('Subir formatos')"
                                @click="openUploadModal"
                                class="w-full sm:w-auto px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-semibold text-sm transition flex items-center justify-center gap-2"
                            >
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                Subir Formato
                            </button>

                            <!-- Dropdown de Filtros -->
                            <div ref="filtersDropdownRef" class="relative w-full sm:w-auto">
                                <button
                                    type="button"
                                    @click="filtersOpen = !filtersOpen"
                                    class="w-full sm:w-auto px-4 py-2 rounded-md text-sm font-semibold border transition flex items-center justify-center gap-2"
                                    :class="activeFiltersCount > 0
                                        ? 'border-indigo-500 text-indigo-600 dark:border-sky-500 dark:text-sky-400 bg-indigo-50 dark:bg-sky-500/10'
                                        : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                    :aria-expanded="filtersOpen"
                                >
                                    <i class="fa-solid fa-sliders"></i>
                                    Filtros
                                    <span
                                        v-if="activeFiltersCount > 0"
                                        class="inline-flex items-center justify-center h-5 min-w-5 px-1 rounded-full bg-indigo-600 dark:bg-sky-500 text-white text-[11px] font-bold"
                                    >
                                        {{ activeFiltersCount }}
                                    </span>
                                    <i
                                        class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200"
                                        :class="{ 'rotate-180': filtersOpen }"
                                    ></i>
                                </button>

                                <transition
                                    enter-active-class="transition ease-out duration-150"
                                    enter-from-class="opacity-0 translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-100"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0 translate-y-1"
                                >
                                    <div
                                        v-if="filtersOpen"
                                        class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] z-30 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-4 space-y-4"
                                    >
                                        <!-- Búsqueda -->
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                                                Buscar por nombre
                                            </label>
                                            <div class="relative">
                                                <input
                                                    v-model="filters.search"
                                                    type="text"
                                                    placeholder="Nombre del archivo..."
                                                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-sky-500"
                                                />
                                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sucursal -->
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                                                Sucursal
                                            </label>
                                            <SearchableSelect
                                                v-model="filters.branch_id"
                                                :options="branchOptions"
                                                placeholder="Todas las sucursales"
                                                search-placeholder="Buscar sucursal..."
                                                empty-option-label="Todas las sucursales"
                                                clearable
                                            />
                                        </div>

                                        <!-- Estatus de la orden -->
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                                                Estatus de la orden
                                            </label>
                                            <SearchableSelect
                                                v-model="filters.status"
                                                :options="statusFilterOptions"
                                                :filterable="false"
                                                placeholder="Cualquier estatus"
                                                empty-option-label="Cualquier estatus"
                                                clearable
                                            />
                                        </div>

                                        <!-- Diseñador asignado -->
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                                                Diseñador asignado
                                            </label>
                                            <SearchableSelect
                                                v-model="filters.designer_id"
                                                :options="designerOptions"
                                                placeholder="Cualquier diseñador"
                                                search-placeholder="Buscar diseñador..."
                                                empty-option-label="Cualquier diseñador"
                                                clearable
                                            />
                                        </div>

                                        <!-- Peso del archivo -->
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                                                Peso del archivo
                                            </label>
                                            <SearchableSelect
                                                v-model="filters.sort"
                                                :options="sizeSortOptions"
                                                :filterable="false"
                                                placeholder="Sin orden específico"
                                                empty-option-label="Sin orden específico"
                                                clearable
                                            />
                                        </div>

                                        <!-- Acciones -->
                                        <div class="flex items-center justify-between gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
                                            <button
                                                type="button"
                                                @click="resetFilters"
                                                :disabled="activeFiltersCount === 0"
                                                class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition"
                                            >
                                                Limpiar filtros
                                            </button>
                                            <button
                                                type="button"
                                                @click="filtersOpen = false"
                                                class="px-3 py-1.5 text-sm font-semibold rounded-md bg-indigo-600 hover:bg-indigo-700 text-white transition"
                                            >
                                                Listo
                                            </button>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>
                    </header>

                    <!-- Tabs -->
                    <el-tabs v-model="activeTab" @tab-change="onTabChange" class="media-library-tabs">
                        <el-tab-pane name="completed">
                            <template #label>
                                <span class="flex items-center space-x-2">
                                    <i class="fa-solid fa-check-double text-green-500"></i>
                                    <span>Diseños Terminados ({{ completedFiles.total }})</span>
                                </span>
                            </template>
                            <MediaSelectionBar
                                :selectable="canDeleteFiles"
                                :total="completedFiles.data.length"
                                :selected-count="selectedIds.length"
                                @toggle-all="toggleSelectAll"
                                @clear="clearSelection"
                                @delete="confirmMassiveDelete"
                            />
                            <FileGrid
                                :files="completedFiles.data"
                                :can-delete="canDeleteFiles"
                                :selectable="canDeleteFiles"
                                :selected-ids="selectedIds"
                                @delete="confirmDelete"
                                @select="toggleSelection"
                            />
                            <Pagination :links="completedFiles.links" class="mt-6"/>
                        </el-tab-pane>

                        <el-tab-pane name="resources">
                             <template #label>
                                <span class="flex items-center space-x-2">
                                    <i class="fa-solid fa-paperclip text-blue-500"></i>
                                    <span>Recursos de Órdenes ({{ designOrderFiles.total }})</span>
                                </span>
                            </template>
                            <MediaSelectionBar
                                :selectable="canDeleteFiles"
                                :total="designOrderFiles.data.length"
                                :selected-count="selectedIds.length"
                                @toggle-all="toggleSelectAll"
                                @clear="clearSelection"
                                @delete="confirmMassiveDelete"
                            />
                            <FileGrid
                                :files="designOrderFiles.data"
                                :can-delete="canDeleteFiles"
                                :selectable="canDeleteFiles"
                                :selected-ids="selectedIds"
                                @delete="confirmDelete"
                                @select="toggleSelection"
                            />
                             <Pagination :links="designOrderFiles.links" class="mt-6"/>
                        </el-tab-pane>

                        <!-- Pestaña: Formatos Frecuentes -->
                        <el-tab-pane name="public_formats">
                            <template #label>
                                <span class="flex items-center space-x-2">
                                    <i class="fa-solid fa-folder-open text-amber-500"></i>
                                    <span>Formatos Frecuentes ({{ publicFormats.total }})</span>
                                </span>
                            </template>
                            
                            <MediaSelectionBar
                                :selectable="canDeleteFiles"
                                :total="publicFormats.data.length"
                                :selected-count="selectedIds.length"
                                @toggle-all="toggleSelectAll"
                                @clear="clearSelection"
                                @delete="confirmMassiveDelete"
                            />

                            <!-- Grid Personalizada con Botón de Borrar -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                <div v-for="file in publicFormats.data" :key="file.id" class="group relative bg-white dark:bg-gray-700 border rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col"
                                    :class="isSelected(file.id)
                                        ? 'border-indigo-500 dark:border-sky-500 ring-2 ring-indigo-500/40 dark:ring-sky-500/40'
                                        : 'border-gray-200 dark:border-gray-600'"
                                >
                                    <FileSelectToggle
                                        v-if="canDeleteFiles"
                                        :selected="isSelected(file.id)"
                                        @toggle="toggleSelection(file.id)"
                                    />

                                    <!-- Botón Eliminar Flotante -->
                                    <button 
                                        v-if="$page.props.auth.user.permissions.includes('Eliminar formatos')"
                                        @click.stop="confirmDelete(file)"
                                        class="absolute top-2 right-2 z-10 p-2 bg-white/90 dark:bg-gray-800/90 text-gray-400 hover:text-red-600 rounded-full shadow-sm backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-200 hover:scale-110"
                                        title="Eliminar archivo"
                                    >
                                        <i class="fa-regular fa-trash-can text-lg"></i>
                                    </button>

                                    <!-- Área de Click para Abrir -->
                                    <a :href="file.original_url" target="_blank" class="flex-1 flex flex-col cursor-pointer">
                                        <!-- Preview -->
                                        <div class="h-40 bg-gray-100 dark:bg-gray-800 flex items-center justify-center overflow-hidden relative">
                                            <FileThumbnail :file="file" class="transition-transform duration-500 group-hover:scale-105" />
                                            
                                            <!-- Overlay al Hover -->
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-300"></div>
                                        </div>

                                        <!-- Info -->
                                        <div class="p-4 flex-1 flex flex-col justify-between">
                                            <div>
                                                <h3 class="font-semibold text-gray-800 dark:text-white text-sm truncate" :title="file.name">
                                                    {{ file.name }}
                                                </h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    {{ file.created_at }} &bull; {{ (file.size / 1024).toFixed(1) }} KB
                                                </p>
                                            </div>
                                            <div class="mt-3 text-xs font-medium text-indigo-600 dark:text-indigo-400 group-hover:underline">
                                                Ver archivo <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-[10px]"></i>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Empty State -->
                            <div v-if="publicFormats.data.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-50"></i>
                                <p>No hay formatos públicos disponibles.</p>
                            </div>

                            <Pagination :links="publicFormats.links" class="mt-6"/>
                        </el-tab-pane>

                        <!-- Pestaña: Formatos Restringidos (Solo visible con permiso) -->
                        <el-tab-pane 
                            v-if="$page.props.auth.user.permissions.includes('Ver formatos restringidos')" 
                            name="restricted_formats"
                        >
                            <template #label>
                                <span class="flex items-center space-x-2">
                                    <i class="fa-solid fa-user-lock text-red-500"></i>
                                    <span>Formatos Restringidos ({{ restrictedFormats.total }})</span>
                                </span>
                            </template>
                            
                            <MediaSelectionBar
                                :selectable="canDeleteFiles"
                                :total="restrictedFormats.data.length"
                                :selected-count="selectedIds.length"
                                @toggle-all="toggleSelectAll"
                                @clear="clearSelection"
                                @delete="confirmMassiveDelete"
                            />

                            <!-- Misma Grid Personalizada -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                <div v-for="file in restrictedFormats.data" :key="file.id" class="group relative bg-white dark:bg-gray-700 border rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col"
                                    :class="isSelected(file.id)
                                        ? 'border-red-500 dark:border-red-500 ring-2 ring-red-500/40'
                                        : 'border-red-100 dark:border-red-900/30'"
                                >
                                    <FileSelectToggle
                                        v-if="canDeleteFiles"
                                        :selected="isSelected(file.id)"
                                        @toggle="toggleSelection(file.id)"
                                    />
                                    
                                    <!-- Badge Restringido -->
                                    <!-- <div class="absolute top-2 left-2 z-10 bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-red-200">
                                        CONFIDENCIAL
                                    </div> -->

                                    <!-- Botón Eliminar -->
                                    <button 
                                        v-if="$page.props.auth.user.permissions.includes('Eliminar formatos')"
                                        @click.stop="confirmDelete(file)"
                                        class="absolute top-2 right-2 z-10 p-2 bg-white/90 dark:bg-gray-800/90 text-gray-400 hover:text-red-600 rounded-full shadow-sm backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-200 hover:scale-110"
                                        title="Eliminar archivo"
                                    >
                                        <i class="fa-regular fa-trash-can text-lg"></i>
                                    </button>

                                    <a :href="file.original_url" target="_blank" class="flex-1 flex flex-col cursor-pointer">
                                        <div class="h-40 bg-gray-100 dark:bg-gray-800 flex items-center justify-center overflow-hidden relative">
                                            <FileThumbnail
                                                :file="file"
                                                icon="fa-solid fa-file-shield text-5xl text-red-400/70"
                                                :preview-pdf="false"
                                                class="transition-transform duration-500 group-hover:scale-105"
                                            />
                                             <div class="absolute inset-0 bg-red-500/0 group-hover:bg-red-500/5 transition-colors duration-300"></div>
                                        </div>

                                        <div class="p-4 flex-1 flex flex-col justify-between">
                                            <div>
                                                <h3 class="font-semibold text-gray-800 dark:text-white text-sm truncate" :title="file.name">
                                                    {{ file.name }}
                                                </h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    {{ file.created_at }} &bull; {{ (file.size / 1024).toFixed(1) }} KB
                                                </p>
                                            </div>
                                            <div class="mt-3 text-xs font-medium text-red-600 dark:text-red-400 group-hover:underline">
                                                Acceso Seguro <i class="fa-solid fa-lock ml-1 text-[10px]"></i>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div v-if="restrictedFormats.data.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <i class="fa-solid fa-shield-cat text-4xl mb-3 opacity-50"></i>
                                <p>No hay formatos restringidos.</p>
                            </div>

                            <Pagination :links="restrictedFormats.links" class="mt-6"/>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </div>

        <!-- Modal de Subida de Archivos -->
        <el-dialog
            v-model="uploadModalVisible"
            title="Subir Nuevo Formato"
            width="500px"
            destroy-on-close
        >
            <form @submit.prevent="submitUpload">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del Formato</label>
                    <input 
                        v-model="form.name" 
                        type="text" 
                        class="w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:text-white"
                        placeholder="Ej. Solicitud de Vacaciones"
                        required
                    />
                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo (PDF, Imagen, etc)</label>
                    <input 
                        type="file" 
                        @change="handleFileChange"
                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-700 dark:file:text-gray-300"
                        required
                    />
                    <p v-if="form.errors.file" class="text-red-500 text-xs mt-1">{{ form.errors.file }}</p>
                </div>

                <div class="mb-6 bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">Requiere Permiso Especial</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">¿Este archivo es solo para admins?</span>
                    </div>
                    <el-switch v-model="form.is_restricted" />
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="uploadModalVisible = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition text-sm">Cancelar</button>
                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-sm disabled:opacity-50"
                    >
                        {{ form.processing ? 'Subiendo...' : 'Guardar Formato' }}
                    </button>
                </div>
            </form>
        </el-dialog>

    </AppLayout>
</template>

<script>
import { defineComponent, ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3'; // Importar useForm
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElTabs, ElTabPane, ElDialog, ElSwitch, ElMessage, ElMessageBox } from 'element-plus';
import FileGrid from '@/Components/MyComponents/FileGrid.vue';
import FileThumbnail from '@/Components/MyComponents/FileThumbnail.vue';
import FileSelectToggle from '@/Components/MyComponents/FileSelectToggle.vue';
import MediaSelectionBar from '@/Components/MyComponents/MediaSelectionBar.vue';
import SearchableSelect from '@/Components/MyComponents/SearchableSelect.vue';
import Pagination from '@/Components/MyComponents/Pagination.vue';
import throttle from 'lodash/throttle';

export default defineComponent({
    components: {
        AppLayout,
        ElTabs,
        ElTabPane,
        ElDialog,
        ElSwitch,
        FileGrid,
        FileThumbnail,
        FileSelectToggle,
        MediaSelectionBar,
        SearchableSelect,
        Pagination,
    },
    props: {
        completedFiles: Object,
        designOrderFiles: Object,
        publicFormats: Object,     // Nuevo prop
        restrictedFormats: Object, // Nuevo prop
        branches: Array,
        designers: Array,
        statusOptions: Array,
        filters: Object,
    },
    setup(props) {
        // Laravel serializa un arreglo vacío como [] (array de JS), no como objeto
        const initialFilters = Array.isArray(props.filters) ? {} : (props.filters || {});

        const activeTab = ref(initialFilters.tab || 'completed');
        const uploadModalVisible = ref(false);
        const filtersOpen = ref(false);
        const filtersDropdownRef = ref(null);

        const filters = reactive({
            search: initialFilters.search || '',
            branch_id: initialFilters.branch_id || null,
            status: initialFilters.status || null,
            designer_id: initialFilters.designer_id || null,
            sort: initialFilters.sort || null,
        });

        const page = usePage();

        const canDeleteFiles = computed(() =>
            page.props.auth.user.permissions.includes('Eliminar formatos')
        );

        const activeFiltersCount = computed(() => {
            let count = 0;
            if (filters.search) count++;
            if (filters.branch_id) count++;
            if (filters.status) count++;
            if (filters.designer_id) count++;
            if (filters.sort) count++;
            return count;
        });

        const branchOptions = computed(() =>
            (props.branches || []).map((branch) => ({ value: branch.id, label: branch.name }))
        );

        const designerOptions = computed(() =>
            (props.designers || []).map((designer) => ({ value: designer.id, label: designer.name }))
        );

        const statusFilterOptions = computed(() =>
            (props.statusOptions || []).map((status) => ({ value: status, label: status }))
        );

        const sizeSortOptions = [
            { value: 'size_desc', label: 'Mayor a menor peso' },
            { value: 'size_asc', label: 'Menor a mayor peso' },
        ];

        // --- Selección múltiple ---
        const selectedIds = ref([]);

        const currentTabFiles = computed(() => {
            switch (activeTab.value) {
                case 'resources':
                    return props.designOrderFiles?.data || [];
                case 'public_formats':
                    return props.publicFormats?.data || [];
                case 'restricted_formats':
                    return props.restrictedFormats?.data || [];
                default:
                    return props.completedFiles?.data || [];
            }
        });

        const isSelected = (id) => selectedIds.value.includes(id);

        const toggleSelection = (id) => {
            selectedIds.value = isSelected(id)
                ? selectedIds.value.filter((selectedId) => selectedId !== id)
                : [...selectedIds.value, id];
        };

        const toggleSelectAll = () => {
            const pageIds = currentTabFiles.value.map((file) => file.id);

            selectedIds.value = pageIds.every((id) => selectedIds.value.includes(id))
                ? []
                : pageIds;
        };

        const clearSelection = () => {
            selectedIds.value = [];
        };

        // La selección solo aplica a los archivos visibles de la pestaña actual
        watch(
            () => [activeTab.value, props.completedFiles, props.designOrderFiles, props.publicFormats, props.restrictedFormats],
            clearSelection,
        );

        const resetFilters = () => {
            filters.search = '';
            filters.branch_id = null;
            filters.status = null;
            filters.designer_id = null;
            filters.sort = null;
        };

        const closeFiltersOnOutsideClick = (event) => {
            if (!filtersOpen.value) return;

            // Clicks within the panel (including the selects inside it)
            if (filtersDropdownRef.value && filtersDropdownRef.value.contains(event.target)) return;

            filtersOpen.value = false;
        };

        const closeFiltersOnEscape = (event) => {
            if (event.key === 'Escape') {
                filtersOpen.value = false;
            }
        };

        onMounted(() => {
            document.addEventListener('click', closeFiltersOnOutsideClick);
            document.addEventListener('keydown', closeFiltersOnEscape);
        });

        onUnmounted(() => {
            document.removeEventListener('click', closeFiltersOnOutsideClick);
            document.removeEventListener('keydown', closeFiltersOnEscape);
        });

        const form = useForm({
            name: '',
            file: null,
            is_restricted: false,
        });

        const onTabChange = (tabName) => {
            router.get(route('media-library.index'), {
                ...filters,
                tab: tabName
            }, {
                preserveState: true,
                replace: true
            });
        };

        watch(filters, throttle(() => {
            router.get(route('media-library.index'), {
                ...filters,
                tab: activeTab.value,
            }, {
                preserveState: true,
                replace: true,
            });
        }, 300));

        // --- Manejo del Modal ---
        const openUploadModal = () => {
            form.reset();
            uploadModalVisible.value = true;
        };

        const handleFileChange = (e) => {
            form.file = e.target.files[0];
        };

        const submitUpload = () => {
            form.post(route('media-library.store'), {
                onSuccess: () => {
                    uploadModalVisible.value = false;
                    ElMessage.success('Formato agregado correctamente');
                    // Cambiamos a la pestaña donde se subió el archivo
                    activeTab.value = form.is_restricted ? 'restricted_formats' : 'public_formats';
                },
            });
        };

        // --- Manejo de Eliminación con Confirmación ---
        const confirmDelete = (file) => {
            ElMessageBox.confirm(
                `¿Estás seguro de que deseas eliminar el archivo "${file.name}"? Esta acción no se puede deshacer.`,
                'Eliminar Archivo',
                {
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                    confirmButtonClass: 'bg-red-600 border-red-600 hover:bg-red-700',
                }
            )
            .then(() => {
                router.delete(route('media-library.destroy', file.id), {
                    preserveScroll: true,
                    onSuccess: () => ElMessage.success('Archivo eliminado correctamente'),
                    onError: () => ElMessage.error('No se pudo eliminar el archivo. Verifica tus permisos.'),
                });
            })
            .catch(() => {
                // Cancelado por el usuario
            });
        };

        const confirmMassiveDelete = () => {
            const count = selectedIds.value.length;

            if (count === 0) return;

            ElMessageBox.confirm(
                `¿Estás seguro de que deseas eliminar ${count} archivo${count === 1 ? '' : 's'}? Esta acción no se puede deshacer.`,
                'Eliminar Archivos',
                {
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                    confirmButtonClass: 'bg-red-600 border-red-600 hover:bg-red-700',
                }
            )
            .then(() => {
                router.post(route('media-library.massive-delete'), { ids: selectedIds.value }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        selectedIds.value = [];
                        ElMessage.success('Archivos eliminados correctamente');
                    },
                    onError: () => ElMessage.error('No se pudieron eliminar los archivos. Verifica tus permisos.'),
                });
            })
            .catch(() => {
                // Cancelado por el usuario
            });
        };

        return {
            activeTab,
            filters,
            onTabChange,
            filtersOpen,
            filtersDropdownRef,
            activeFiltersCount,
            branchOptions,
            designerOptions,
            statusFilterOptions,
            sizeSortOptions,
            resetFilters,
            canDeleteFiles,
            selectedIds,
            isSelected,
            toggleSelection,
            toggleSelectAll,
            clearSelection,
            uploadModalVisible,
            form,
            openUploadModal,
            handleFileChange,
            submitUpload,
            confirmDelete,
            confirmMassiveDelete
        };
    }
});
</script>

<style>
.media-library-tabs .el-tabs__header {
    margin-bottom: 25px;
}
.media-library-tabs .el-tabs__item {
    font-size: 1rem;
    padding: 0 20px;
    height: 50px;
}
.media-library-tabs .el-tabs__item.is-active {
    font-weight: bold;
}
.media-library-tabs .el-tabs__active-bar {
    height: 3px;
}
</style>