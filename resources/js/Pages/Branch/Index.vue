<template>
    <AppLayout title="Cartera de Clientes">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Cartera de Clientes
        </h2>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para crear nuevo cliente -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear clientes')"
                            :href="route('branches.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nuevo Cliente
                            </SecondaryButton>
                        </Link>

                        <div class="flex items-center space-x-2">
                             <!-- Botón para eliminar seleccionados -->
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar clientes')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar los clientes seleccionados?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                        
                        <!-- Input de búsqueda (funcionalidad a futuro) -->
                        <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" />
                    </div>

                    <!-- Overlay de carga -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <!-- Tabla de Clientes -->
                        <el-table 
                            max-height="550" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="ID" width="60" />
                            <el-table-column prop="name" label="Nombre" width="250">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <el-tooltip v-if="!scope.row.parent" content="Sucursal matriz" placement="top">
                                            <i class="fa-solid fa-crown text-yellow-500"></i>
                                        </el-tooltip>
                                        <p>{{ scope.row.name }}</p>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="status" label="Estatus" width="120">
                                <template #default="scope">
                                    <el-tag :type="scope.row.status === 'Cliente' ? 'success' : 'info'" disable-transitions>
                                        {{ scope.row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="Vendedor Asignado" width="200">
                                <template #default="scope">
                                    {{ scope.row.account_manager?.name ?? 'No asignado' }}
                                </template>
                            </el-table-column>
                             <el-table-column label="Matriz" width="200">
                                <template #default="scope">
                                    {{ scope.row.parent?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="rfc" label="RFC" />

                            <!-- Menú de acciones por fila -->
                            <el-table-column align="right">
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
                                                    v-if="$page.props.auth.user.permissions.includes('Editar clientes')"
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
                    <div v-if="branches.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="branches.current_page"
                            :page-size="branches.per_page" :total="branches.total"
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
import { Link } from "@inertiajs/vue3";

export default {
    // Usando Options API como solicitaste
    data() {
        return {
            loading: false,
            search: '',
            selectedItems: [],
            tableData: this.branches.data,
            SearchProps: ['Nombre', 'Estatus', 'Matriz', 'Vendedor asignado'], // indica por cuales propiedades del registro puedes buscar
        };
    },
    components: {
        Link,
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
    },
    props: {
        branches: Object,
    },
    methods: {
        async handleSearch() {
            this.loading = true;
            try {
                // Si la búsqueda está vacía, volvemos a los datos originales de la paginación
                if (!this.search) {
                    this.tableData = this.branches.data;
                    // Forzamos un recharge con inertia para restaurar el estado original con paginación
                    this.$inertia.get(this.route('branches.index'), {}, {
                        preserveState: true,
                        replace: true,
                        onFinish: () => { this.loading = false; },
                    });
                    return;
                }

                // Si hay texto, hacemos la petición con axios como en el original
                const response = await axios.post(route('branches.get-matches', { query: this.search }));
                if (response.status === 200) {
                    this.tableData = response.data.items;
                }
            } catch (error) {
                console.error(error);
                ElMessage.error('No se pudo realizar la búsqueda');
            } finally {
                this.loading = false;
            }
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            this.$inertia.get(route('branches.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            this.$inertia.get(route(`branches.${action}`, id));
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('branches.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Clientes eliminados correctamente');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar los clientes');
                }
            });
        },
        handlePageChange(page) {
            this.$inertia.get(route('branches.index', { page: page }), {
                preserveState: true,
                replace: true,
            });
        },
    },
    watch: {
        // Observador para actualizar `tableData` si los props cambian (ej. por paginación)
        'branches.data': {
            handler(newData) {
                if (!this.search) {
                    this.tableData = newData;
                }
            },
            deep: true,
            immediate: true,
        }
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
