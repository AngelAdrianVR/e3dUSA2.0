<template>
    <AppLayout title="Catálogo de productos">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Productos
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
                    <div class="lg:flex justify-between items-center mb-6 space-y-2 lg:space-y-0">
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos')"
                            :href="route('catalog-products.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nuevo producto
                            </SecondaryButton>
                        </Link>

                        <!-- Filtros y Búsqueda -->
                        <div class="flex items-center space-x-2">
                             <el-select v-model="productType" placeholder="Seleccionar tipo" class="!w-52">
                                <el-option
                                    v-for="item in productTypes"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                />
                            </el-select>
                        </div>

                        <!-- Acciones y Reportes -->
                        <div v-if="$page.props.auth.user.permissions.includes('Descargar reporte de precios')" class="flex items-center space-x-2">
                             <el-dropdown split-button type="primary" @click="openReport" plain>
                                Reporte de precios
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item disabled @click="exportToExcel">Exportar lista en Excel</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>

                            <!-- ====== BOTÓN NUEVO PARA EDICIÓN MASIVA ====== -->
                             <el-button v-if="$page.props.auth.user.permissions.includes('Editar catalogo de productos')"
                                type="warning" plain @click="showMassiveEditModal = true" :disabled="!selectedItems.length">
                                Editar selección
                            </el-button>
                            <!-- ============================================= -->

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
                    </div>
                    <!-- Costo Total -->
                    <div class="flex justify-between mb-1">
                        <el-tag v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')" type="success" size="large">
                            <p class="text-base">Costo total de inventario: {{ totalInventoryCost }}</p>
                        </el-tag>
                        <SearchInput v-model="search" placeholder="Buscar producto..." />
                    </div>

                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        <el-table 
                            max-height="550" 
                            :data="products.data"
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
                            <el-table-column prop="code" label="Código" width="160" />
                            <el-table-column prop="name" label="Nombre" width="220" />
                            <el-table-column prop="brand.name" label="Marca" width="120" />
                            <el-table-column v-if="$page.props.auth.user.permissions.includes('Ver costos de productos')" prop="cost" label="Costo" width="120">
                                <template #default="scope">
                                    <span>${{ scope.row.cost?.toFixed(2) ?? '0.00' }}</span>
                                </template>
                            </el-table-column>
                             <el-table-column label="Stock" width="170">
                                <template #default="scope">
                                    <span :class="getProductStock(scope.row).quantity <= 10 ? 'text-red-500 font-bold' : 'text-green-600'" 
                                          class="text-lg">
                                        {{ (getProductStock(scope.row).quantity).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}
                                    </span>
                                    <span class="text-xs text-gray-500 ml-1">{{ scope.row.measure_unit }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Ubicación">
                                <template #default="scope">
                                    <span class="text-gray-600 dark:text-gray-400">{{ getProductStock(scope.row).location ?? '-' }}</span>
                                </template>
                            </el-table-column>
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
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos') && !scope.row.archived_at"
                                                    :command="'obsolet-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                                    </svg>
                                                    Producto obsoleto
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos') && scope.row.archived_at"
                                                    :command="'obsolet-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                                    </svg>
                                                    Reestablecer
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <div v-if="products.total > 0" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="products.current_page"
                            :page-size="products.per_page" :total="products.total"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== MODAL PARA EDICIÓN MASIVA ====== -->
        <MassiveEditModal 
            :show="showMassiveEditModal"
            :selected_products="selectedItems"
            :product_families="product_families"
            @close="showMassiveEditModal = false"
        />
        <!-- ======================================= -->
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import MassiveEditModal from './Partials/MassiveEditModal.vue'; // <-- AÑADIR IMPORTACIÓN
import { ElMessage } from 'element-plus';
import axios from 'axios';
import { Link, router } from "@inertiajs/vue3";
import { debounce } from 'lodash';

export default {
    data() {
        return {
            loading: false,
            loadingExport: false,
            search: this.filters.search,
            productType: this.filters.product_type ?? 'Catálogo',
            selectedItems: [],
            showMassiveEditModal: false, // <-- AÑADIR ESTADO PARA EL MODAL
            productTypes: [
                { value: 'Catálogo', label: 'Productos de Catálogo' },
                { value: 'Materia prima', label: 'Materia Prima' },
                { value: 'Insumo', label: 'Insumos' },
                { value: 'Obsoleto', label: 'Obsoletos' },
            ],
        };
    },
    components: {
        SecondaryButton,
        LoadingIsoLogo,
        SearchInput,
        AppLayout,
        Link,
        MassiveEditModal, // <-- AÑADIR COMPONENTE
    },
    props: {
        products: Object,
        filters: Object,
        product_families: Array, // <-- AÑADIR PROP
    },
    computed:{
        totalInventoryCost() {
            if (!this.products || !this.products.data) return 0;
            
            const total = this.products.data.reduce((accumulator, product) => {
                const stock = this.getProductStock(product).quantity;
                const cost = product.cost ?? 0;
                return accumulator + (stock * cost);
            }, 0);

            // Formatear como moneda
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(total);
        }
    },
    methods: {
        openReport() {
            window.open(route('catalog-products.prices-report'), '_blank');
        },
        exportToExcel() {
            this.loadingExport = true;

            axios({
                url: '/export-catalog-products',
                method: 'GET',
                responseType: 'blob',
            }).then(response => {
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', 'catalogo_precios.xlsx');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }).catch(error => {
                console.error('Error al exportar:', error);
            }).finally(() => {
                this.loadingExport = false;
            });
        },
        fetchData() {
            this.loading = true;
            router.get(route('catalog-products.index'), {
                search: this.search,
                product_type: this.productType,
            }, {
                preserveState: true,
                replace: true,
                onFinish: () => {
                    this.loading = false;
                }
            });
        },
        getProductStock(product) {
            if (product.storages && product.storages.length > 0) {
                return product.storages[0];
            }
            return { quantity: 0, location: 'N/A' };
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            this.$inertia.get(route('catalog-products.show', row));
        },
        handleCommand(command) {
            const commandParts = command.split('-');
            const commandName = commandParts[0];
            const rowId = commandParts[1];
        
            if (commandName === 'show' || commandName === 'edit' || commandName === 'obsolet') {
                 router.get(route('catalog-products.' + commandName, rowId));
            } else if (commandName === 'clone') {
                this.clone(rowId);
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
            router.get(route('catalog-products.index', {
                page: page,
                search: this.search,
                product_type: this.productType
            }), {
                preserveState: true,
                replace: true,
            });
        },
        async clone(productId) {
            try {
                const response = await axios.post(route('catalog-products.clone', { catalog_product_id: productId }));
                if (response.status === 200) {
                    ElMessage.success(response.data.message);
                    this.$inertia.reload({ only: ['products'] }); // Recargar datos
                }
            } catch (error) {
                ElMessage.error(error.response.data.message || 'No se pudo clonar el producto');
            }
        }
    },
    watch: {
        search: debounce(function () {
            this.fetchData();
        }, 300),
        productType() {
            this.fetchData();
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

