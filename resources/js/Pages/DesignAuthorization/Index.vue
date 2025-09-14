<template>
    <AppLayout title="Autorizaciones de Diseño">
        <!-- Encabezado de la página -->
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Autorizaciones de Diseño
        </h2>

        <!-- Contenido principal -->
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tarjeta contenedora -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón de creación (Ajusta la ruta y permiso según tu app) -->
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear formatos de autorizacion de diseño')" :href="route('design-authorizations.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Autorización
                            </SecondaryButton>
                        </Link>
                        
                        <!-- Botón de eliminación masiva -->
                        <el-popconfirm confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                            title="¿Estás seguro de eliminar las autorizaciones seleccionadas?" @confirm="deleteSelections">
                            <template #reference>
                                <el-button type="danger" plain :disabled="!selectedItems.length">
                                    Eliminar selección
                                </el-button>
                            </template>
                        </el-popconfirm>

                        <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" />
                    </div>

                    <!-- Tabla de Autorizaciones -->
                    <div class="relative">
                        <div v-if="loading" class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        <el-table
                            max-height="550"
                            :data="authorizations.data" 
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange"
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300"
                        >
                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="ID" width="70" sortable />
                            <el-table-column prop="product_name" label="Producto" />
                            <el-table-column prop="branch.name" label="Cliente" />
                            <el-table-column prop="contact.name" label="Contacto" />
                            <el-table-column prop="seller.name" label="Vendedor" />
                            <el-table-column prop="responded_at" label="Fecha de Respuesta" width="180">
                                <template #default="scope">
                                    <span v-if="scope.row.responded_at" class="text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.responded_at) }}</span>
                                    <span v-else class="text-gray-400 dark:text-gray-500">-</span>
                                </template>
                            </el-table-column>
                             <el-table-column label="Estado" width="150">
                                <template #default="scope">
                                    <span :class="getStatusClass(scope.row)">
                                        {{ getStatusText(scope.row) }}
                                    </span>
                                </template>
                            </el-table-column>
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
                                                    Ver
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="!scope.row.responded_at && $page.props.auth.user.permissions.includes('Editar formatos de autorizacion de diseño')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                    Editar
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="'print-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                                    </svg>
                                                    Imprimir</el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="authorizations.total > 0" class="flex justify-center mt-6">
                        <el-pagination
                            v-model:current-page="authorizations.current_page"
                            :page-size="authorizations.per_page"
                            :total="authorizations.total"
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
import { ElMessage } from 'element-plus';
import { Link } from "@inertiajs/vue3";

export default {
    data() {
        return {
            loading: false,
            search: this.filters.search || '',
            selectedItems: [],
            SearchProps: ['ID', 'Producto', 'Cliente', 'Vendedor'],
        };
    },
    components: {
        SecondaryButton,
        LoadingIsoLogo,
        SearchInput,
        AppLayout,
        Link,
    },
    props: {
        authorizations: Object,
        filters: Object,
    },
    methods: {
        async handleSearch() {
            this.loading = true;
            try {
                // Si la búsqueda está vacía, volvemos a los datos originales de la paginación
                if (!this.search) {
                    this.tableData = this.authorizations.data;
                    // Forzamos un recharge con inertia para restaurar el estado original con paginación
                    this.$inertia.get(this.route('design-authorizations.index'), {}, {
                        preserveState: true,
                        replace: true,
                        onFinish: () => { this.loading = false; },
                    });
                    return;
                }

                // Si hay texto, hacemos la petición con axios como en el original
                const response = await axios.post(route('design-authorizations.get-matches', { query: this.search }));
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
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            this.$inertia.get(route('design-authorizations.show', row));
        },
        handleCommand(command) {
            const [action, rowId] = command.split('-');
            this.$inertia.get(route(`design-authorizations.${action}`, rowId));
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('design-authorizations.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Autorizaciones eliminadas correctamente');
                },
            });
        },
        handlePageChange(page) {
            this.$inertia.get(route('design-authorizations.index', { page }));
        },
        getStatusText(row) {
            if (row.is_accepted === true) return 'Aceptado';
            if (row.is_accepted === false) return 'Rechazado';
            return 'Pendiente';
        },
        getStatusClass(row) {
            if (row.is_accepted === true) return 'px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100';
            if (row.is_accepted === false) return 'px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100';
            return 'px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100';
        }
    },
};
</script>
