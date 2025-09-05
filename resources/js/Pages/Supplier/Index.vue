<template>
    <AppLayout title="Proveedores">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Directorio de Proveedores
        </h2>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para crear nuevo proveedor -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear proveedores')"
                            :href="route('suppliers.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nuevo Proveedor
                            </SecondaryButton>
                        </Link>

                        <div class="flex items-center space-x-2">
                             <!-- Botón para eliminar seleccionados -->
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar proveedores')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar los proveedores seleccionados?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                        
                        <!-- Input de búsqueda -->
                        <SearchInput @search="handleSearch" v-model="search" :searchProps="SearchProps" />
                    </div>

                    <!-- Overlay de carga -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <!-- Tabla de Proveedores -->
                        <el-table 
                            max-height="550" 
                            :data="suppliers.data"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="ID" width="60" />
                            <el-table-column prop="name" label="Nombre" width="250" />
                            <el-table-column prop="nickname" label="Alias/Apodo" width="150" />
                            <el-table-column prop="rfc" label="RFC" width="150" />
                            <el-table-column prop="phone" label="Teléfono" width="150" />
                            <el-table-column prop="email" label="Correo Electrónico" />

                            <!-- Menú de acciones por fila -->
                            <el-table-column align="right" width="80">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop
                                            class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :command="'show-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>Ver
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Editar proveedores')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>Editar
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="suppliers.total > suppliers.per_page" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="suppliers.current_page"
                            :page-size="suppliers.per_page" :total="suppliers.total"
                            :links="suppliers.links"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import { ElMessage } from 'element-plus';
import { Link, router } from "@inertiajs/vue3";
import { ref, watch } from 'vue';
import { debounce } from 'lodash';

export default {
    // Usando Composition API para un enfoque más moderno
    components: {
        Link,
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
    },
    props: {
        suppliers: Object,
        filters: Object,
    },
    setup(props) {
        const loading = ref(false);
        const search = ref(props.filters.search);
        const selectedItems = ref([]);

        // Lógica de búsqueda con debounce para no saturar el servidor
        const handleSearch = debounce(() => {
            router.get(route('suppliers.index'), { search: search.value }, {
                preserveState: true,
                replace: true,
                onStart: () => loading.value = true,
                onFinish: () => loading.value = false,
            });
        }, 300);
        
        // Observador para activar la búsqueda cuando el valor de `search` cambia
        watch(search, handleSearch);

        const handleSelectionChange = (selection) => {
            selectedItems.value = selection;
        };

        const handleRowClick = (row) => {
            router.get(route('suppliers.show', row.id));
        };

        const handleCommand = (command) => {
            const [action, id] = command.split('-');
            router.get(route(`suppliers.${action}`, id));
        };

        const deleteSelections = () => {
            const ids = selectedItems.value.map(item => item.id);
            router.post(route('suppliers.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Proveedores eliminados correctamente');
                    selectedItems.value = [];
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar los proveedores');
                }
            });
        };

        const handlePageChange = (page) => {
            router.get(route('suppliers.index', { page: page, search: search.value }), {
                preserveState: true,
                replace: true,
                onStart: () => loading.value = true,
                onFinish: () => loading.value = false,
            });
        };
        
        return {
            loading,
            search,
            selectedItems,
            handleSearch,
            handleSelectionChange,
            handleRowClick,
            handleCommand,
            deleteSelections,
            handlePageChange,
            SearchProps: ['Nombre', 'Apodo', 'RFC'], // indica por cuales propiedades del registro puedes buscar
        };
    }
};
</script>

<style>
/* Estilos para la paginación en modo oscuro (heredados de tu ejemplo) */
.dark .el-pagination button,
.dark .el-pager li {
    background-color: #1f2937 !important;
    color: #d1d5db !important;
}
.dark .el-pager li.is-active {
    color: #ffffff !important;
    background-color: #3b82f6 !important;
}
</style>
