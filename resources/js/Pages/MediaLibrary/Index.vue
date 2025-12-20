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

                            <!-- Branch Filter -->
                             <el-select
                                v-model="filters.branch_id"
                                filterable
                                clearable
                                placeholder="Filtrar por sucursal"
                                class="!w-full sm:!w-52"
                                >
                                <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                            </el-select>
                            
                            <!-- Search Input -->
                            <div class="relative w-full sm:w-64">
                                <input
                                    v-model="filters.search"
                                    type="text"
                                    placeholder="Buscar por nombre..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-full focus:ring-2 focus:ring-indigo-500 dark:focus:ring-sky-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 transition"
                                />
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
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
                            <FileGrid :files="completedFiles.data" />
                            <Pagination :links="completedFiles.links" class="mt-6"/>
                        </el-tab-pane>

                        <el-tab-pane name="resources">
                             <template #label>
                                <span class="flex items-center space-x-2">
                                    <i class="fa-solid fa-paperclip text-blue-500"></i>
                                    <span>Recursos de Órdenes ({{ designOrderFiles.total }})</span>
                                </span>
                            </template>
                            <FileGrid :files="designOrderFiles.data" />
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
                            
                            <!-- Grid Personalizada con Botón de Borrar -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                <div v-for="file in publicFormats.data" :key="file.id" class="group relative bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col">
                                    
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
                                            <img 
                                                v-if="file.mime_type.startsWith('image/')" 
                                                :src="file.preview_url || file.original_url" 
                                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                            />
                                            <div v-else class="text-gray-400 dark:text-gray-500">
                                                <i class="fa-solid fa-file-pdf text-5xl" v-if="file.mime_type === 'application/pdf'"></i>
                                                <i class="fa-solid fa-file-word text-5xl" v-else-if="file.mime_type.includes('word')"></i>
                                                <i class="fa-solid fa-file-excel text-5xl" v-else-if="file.mime_type.includes('sheet')"></i>
                                                <i class="fa-solid fa-file-lines text-5xl" v-else></i>
                                            </div>
                                            
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
                            
                            <!-- Misma Grid Personalizada -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                <div v-for="file in restrictedFormats.data" :key="file.id" class="group relative bg-white dark:bg-gray-700 border border-red-100 dark:border-red-900/30 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden flex flex-col">
                                    
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
                                            <img 
                                                v-if="file.mime_type.startsWith('image/')" 
                                                :src="file.preview_url || file.original_url" 
                                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                            />
                                            <div v-else class="text-gray-400 dark:text-gray-500">
                                                <i class="fa-solid fa-file-shield text-5xl text-red-400/70"></i>
                                            </div>
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
import { defineComponent, ref, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3'; // Importar useForm
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElTabs, ElTabPane, ElSelect, ElOption, ElDialog, ElSwitch, ElMessage, ElMessageBox } from 'element-plus';
import FileGrid from '@/Components/MyComponents/FileGrid.vue';
import Pagination from '@/Components/MyComponents/Pagination.vue';
import throttle from 'lodash/throttle';

export default defineComponent({
    components: {
        AppLayout,
        ElTabs,
        ElTabPane,
        ElSelect,
        ElOption,
        ElDialog,
        ElSwitch,
        FileGrid,
        Pagination,
    },
    props: {
        completedFiles: Object,
        designOrderFiles: Object,
        publicFormats: Object,     // Nuevo prop
        restrictedFormats: Object, // Nuevo prop
        branches: Array,
        filters: Object,
    },
    setup(props) {
        const activeTab = ref(props.filters.tab || 'completed');
        const uploadModalVisible = ref(false);

        const filters = reactive({
            search: props.filters.search || '',
            branch_id: props.filters.branch_id || null,
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
                `¿Estás seguro de que deseas eliminar el formato "${file.name}"? Esta acción no se puede deshacer.`,
                'Eliminar Formato',
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
        
        return {
            activeTab,
            filters,
            onTabChange,
            uploadModalVisible,
            form,
            openUploadModal,
            handleFileChange,
            submitUpload,
            confirmDelete
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