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

                    <!-- Header with Search and Filters -->
                    <header class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Explorador de Archivos
                        </h1>
                        <div class="flex items-center gap-4 w-full sm:w-auto">
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
                                    <i class="fa-solid fa-check-double"></i>
                                    <span>Diseños Terminados ({{ completedFiles.total }})</span>
                                </span>
                            </template>
                            <FileGrid :files="completedFiles.data" />
                            <Pagination :links="completedFiles.links" class="mt-6"/>
                        </el-tab-pane>

                        <el-tab-pane name="resources">
                             <template #label>
                                <span class="flex items-center space-x-2">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span>Recursos de Órdenes ({{ designOrderFiles.total }})</span>
                                </span>
                            </template>
                            <FileGrid :files="designOrderFiles.data" />
                             <Pagination :links="designOrderFiles.links" class="mt-6"/>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { defineComponent, ref, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElTabs, ElTabPane, ElSelect, ElOption } from 'element-plus';
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
        FileGrid,
        Pagination,
    },
    props: {
        completedFiles: Object,
        designOrderFiles: Object,
        branches: Array,
        filters: Object,
    },
    setup(props) {
        const activeTab = ref(props.filters.tab || 'completed');

        const filters = reactive({
            search: props.filters.search || '',
            branch_id: props.filters.branch_id || null,
        });

        const onTabChange = (tabName) => {
            // Cuando el usuario cambia de pestaña, actualizamos la URL
            router.get(route('media-library.index.get'), {
                ...filters,
                tab: tabName
            }, {
                preserveState: true,
                replace: true
            });
        };

        watch(filters, throttle(() => {
            // Cuando el usuario filtra, enviamos la pestaña activa junto con los filtros
            router.get(route('media-library.index.get'), {
                ...filters,
                tab: activeTab.value,
            }, {
                preserveState: true,
                replace: true,
            });
        }, 300));
        
        return {
            activeTab,
            filters,
            onTabChange,
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

