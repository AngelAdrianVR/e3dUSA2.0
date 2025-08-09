<template>
    <AppLayout title="Maquinaria">
        <!-- Encabezado de la página -->
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Maquinaria
        </h2>

        <!-- Contenido principal -->
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tarjeta contenedora -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón de creación -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear maquinas')" :href="route('machines.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva máquina
                            </SecondaryButton>
                        </Link>
                        
                        <!-- Botón de eliminación masiva -->
                        <el-popconfirm confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                            title="¿Estás seguro de eliminar los bonos seleccionados?" @confirm="deleteSelections">
                            <template #reference>
                                <el-button type="danger" plain :disabled="!selectedItems.length">
                                    Eliminar selección
                                </el-button>
                            </template>
                        </el-popconfirm>

                        <SearchInput @keyup.enter="searchItems" v-model="search" @cleanSearch="searchItems" />
                    </div>

                    <!-- Tabla de Maquinas -->
                    <div class="relative">
                        <div v-if="loading" class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        <el-table
                            max-height="550"
                            :data="machines.data" 
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange"
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300"
                        >
                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="ID" width="80" />
                            <el-table-column prop="name" label="Nombre" />
                            <el-table-column prop="serial_number" label="N° de serie" />
                            <el-table-column prop="supplier" label="Proveedor" />
                            <el-table-column prop="days_next_maintenance" label="Mantenimiento cada" />
                            <el-table-column prop="adquisition_date" label="Fecha de adquisición">
                            <template #default="scope">
                                    <span class="text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.adquisition_date) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="needs_maintenance" label="Necesita mantenimiento preventivo" />
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
                                                    </svg>
                                                    Ver</el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Editar maquinas')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                    Editar</el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="machines.total > 0" class="flex justify-center mt-6">
                        <el-pagination
                            v-model:current-page="machines.current_page"
                            :page-size="machines.per_page"
                            :total="machines.total"
                            layout="prev, pager, next"
                            background
                            @current-change="handlePageChange"
                        />
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
import { ElMessage, ElMessageBox } from 'element-plus'; // Para notificaciones
// import NotificationCenter from "@/Components/MyComponents/NotificationCenter.vue";
// import IndexSearchBar from "@/Components/MyComponents/IndexSearchBar.vue";
import axios from 'axios';
import { Link } from "@inertiajs/vue3";


export default {
    data() {
        return {
            disableMassiveActions: true,
            loading: false,
            search: this.filters.search || '',
            selectedItems: [],
        };
    },
    components: {
        // NotificationCenter,
        SecondaryButton,
        LoadingIsoLogo,
        SearchInput,
        // IndexSearchBar,
        AppLayout,
        Link,
    },
    props: {
        machines: Object,
        filters: Object, // Filtros de búsqueda
    },
    methods: {
        searchItems() {
            // 1. Inicia el estado de carga
            this.loading = true;

            this.$inertia.get(this.route('machines.index'), {
                search: this.search
            }, {
                preserveState: true,
                replace: true,
                // 2. Usa el callback onFinish para detener la carga cuando todo termine
                onFinish: () => {
                    this.loading = false;
                },
            });
        },
        // Formatea una fecha en un formato legible.
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
        // Maneja la selección de filas en la tabla
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            this.$inertia.get(route('machines.show', row));
        },
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const rowId = command.split('-')[1];

            if (commandName == 'clone') {
                this.clone(rowId);
            } else {
                this.$inertia.get(route('machines.' + commandName, rowId));
            }
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('machines.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Máquinas eliminadas');
                },
            });
        },
        // Maneja el cambio de página
        handlePageChange(page) {
            this.$inertia.get(route('machines.index', { page }));
        },
    },
};
</script>
