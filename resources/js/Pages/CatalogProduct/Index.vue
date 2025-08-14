<template>
    <AppLayout title="Catálogo de productos">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Catálogo de productos
        </h2>

        <div v-if="loadingExport" class="fixed inset-0 bg-gray-900 bg-opacity-80 z-50 flex items-center justify-center">
            <div class="text-center">
                <LoadingIsoLogo />
                <p class="mt-4 text-gray-100">Generando archivo, por favor espera...</p>
            </div>
        </div>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos')"
                            :href="route('catalog-products.create')">
                        <SecondaryButton>
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nuevo producto
                        </SecondaryButton>
                        </Link>

                        <div class="flex items-center space-x-2">
                             <el-dropdown split-button type="primary" @click="openReport" plain>
                                Reporte de precios
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item @click="exportToExcel">Exportar lista en Excel</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar los productos seleccionados?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                        </div>

                        <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" />
                    </div>

                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        <el-table 
                            max-height="670" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="30" />
                            <el-table-column label="Imagen" width="100">
                                <template #default="scope">
                                    <figure class="border rounded-md size-20 flex items-center justify-center bg-white">
                                        <el-image @click.stop="" style="width: 100%; height: 100%; border-radius: 6px"
                                            :src="scope.row.media[0]?.original_url"
                                            :preview-src-list="[scope.row.media[0]?.original_url]" fit="contain"
                                            preview-teleported :hide-on-click-modal="true" />
                                    </figure>
                                </template>
                            </el-table-column>
                            <el-table-column prop="code" label="Código" width="200" />
                            <el-table-column prop="name" label="Nombre" width="250" />
                            <el-table-column prop="material" label="Material" />
                            <el-table-column prop="brand.name" label="Marca" />
                            <el-table-column v-if="$page.props.auth.user.permissions.includes('Ver costos de productos')" prop="cost" label="Costo" width="150">
                                <template #default="scope">
                                    <span>${{ scope.row.cost?.toFixed(2) }}</span>
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
                                                    v-if="$page.props.auth.user.permissions.includes('Editar catalogo de productos')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                    Editar
                                                </el-dropdown-item>
                                                <!-- <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos')"
                                                    :command="'clone-' + scope.row.id">
                                                    <i class="fa-solid fa-clone mr-2"></i>Clonar
                                                </el-dropdown-item> -->
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <div v-if="catalog_products.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="catalog_products.current_page"
                            :page-size="catalog_products.per_page" :total="catalog_products.total"
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
import axios from 'axios';
import { Link } from "@inertiajs/vue3";

export default {
    data() {
        return {
            loading: false,
            loadingExport: false,
            search: '',
            selectedItems: [],
            // Mantenemos una propiedad separada para los datos de la tabla que pueden ser modificados por la búsqueda
            tableData: this.catalog_products.data,
            SearchProps: ['Nombre', 'Material', 'Marca'], // indica por cuales propiedades del registro puedes buscar
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
        catalog_products: Object,
    },
    methods: {
        async handleSearch() {
            this.loading = true;
            try {
                // Si la búsqueda está vacía, volvemos a los datos originales de la paginación
                if (!this.search) {
                    this.tableData = this.catalog_products.data;
                    // Forzamos un recharge con inertia para restaurar el estado original con paginación
                    this.$inertia.get(this.route('catalog-products.index'), {}, {
                        preserveState: true,
                        replace: true,
                        onFinish: () => { this.loading = false; },
                    });
                    return;
                }

                // Si hay texto, hacemos la petición con axios como en el original
                const response = await axios.post(route('catalog-products.get-matches', { query: this.search }));
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
            this.$inertia.get(route('catalog-products.show', row));
        },
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const rowId = command.split('-')[1];

            if (commandName === 'clone') {
                this.clone(rowId);
            } else {
                this.$inertia.get(route('catalog-products.' + commandName, rowId));
            }
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('catalog-products.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Productos eliminados correctamente');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar los productos');
                }
            });
        },
        handlePageChange(page) {
            // Cuando cambia la página, simplemente hacemos una visita de Inertia
            // con el número de página correspondiente, como en Index1.
            this.$inertia.get(route('catalog-products.index', { page: page, search: this.search }), {
                preserveState: true,
                replace: true,
            });
        },
        async clone(productId) {
            try {
                const response = await axios.post(route('catalog-products.clone', { catalog_product_id: productId }));
                if (response.status === 200) {
                    ElMessage.success(response.data.message);
                    this.$inertia.reload({ only: ['catalog_products'] }); // Recargar datos
                }
            } catch (error) {
                ElMessage.error(error.response.data.message || 'No se pudo clonar el producto');
            }
        },
        openReport() {
            window.open(route('catalog-products.prices-report'), '_blank');
        },
        exportToExcel() {
            this.loadingExport = true;
            axios({
                url: route('catalog-products.export-excel'),
                method: 'GET',
                responseType: 'blob',
            }).then(response => {
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', 'catalogo_productos.xlsx');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }).catch(error => {
                console.error('Error al exportar:', error);
                ElMessage.error('No se pudo generar el archivo de Excel');
            }).finally(() => {
                this.loadingExport = false;
            });
        },
    },
    watch: {
        // Observador para actualizar `tableData` si los props cambian (ej. por paginación)
        'catalog_products.data': {
            handler(newData) {
                // Solo actualiza si no hay una búsqueda activa
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
/* Estilos para la paginación en modo oscuro */
.dark .el-pagination button,
.dark .el-pager li {
    background-color: #1f2937 !important; /* bg-slate-800 */
    color: #d1d5db !important; /* text-gray-300 */
}
.dark .el-pager li.is-active {
    color: #ffffff !important;
    background-color: #3b82f6 !important; /* bg-blue-600 */
}
</style>