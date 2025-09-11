<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import { Head, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import { ElMessage } from 'element-plus';

export default {
    components: {
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        Head,
    },
    props: {
        products: Object,
        filters: Object,
    },
    data() {
        return {
            search: this.filters.search,
            productType: this.filters.product_type ?? 'Catálogo',
            loading: false,
            productTypes: [
                { value: 'Catálogo', label: 'Productos de Catálogo' },
                { value: 'Materia prima', label: 'Materia Prima' },
                { value: 'Insumo', label: 'Insumos' },
                { value: 'Obsoleto', label: 'Obsoletos' },
            ],
        };
    },
    computed: {
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
        getProductStock(product) {
            if (product.storages && product.storages.length > 0) {
                return product.storages[0];
            }
            return { quantity: 0, location: 'N/A' };
        },
        handleRowClick(row) {
            router.get(route('catalog-products.show', row));
        },
        fetchData() {
            this.loading = true;
            router.get(route('storages.index'), {
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
        handlePageChange(page) {
            router.get(route('storages.index', {
                page: page,
                search: this.search,
                product_type: this.productType
            }), {
                preserveState: true,
                replace: true,
            });
        },
        async markAsObsolete(product) {
            try {
                const response = await axios.get(route('catalog-products.obsolet', product));
                if (response.status === 200) {

                    router.reload({ 
                        preserveScroll: true,
                        preserveState: true 
                    });
                    ElMessage.success('Estatus de producto actualizado');
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error. Refresca la página e inténtalo de nuevo');
                console.error(err);
            }
        },
        handleCommand(command) {
            const [action, productId] = command.split('-');
            if (action === 'obsolete') {
                this.markAsObsolete(productId);
            } else if (action === 'show'){
                router.get(route('catalog-products.show', productId));
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
}
</script>

<template>
    <AppLayout title="Almacén">
        <Head title="Almacén" />
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Almacén
        </h2>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- Filtros -->
                    <div class="flex items-center justify-between mb-2 p-4 border rounded-lg dark:border-slate-700">
                        <div>
                             <el-select v-model="productType" placeholder="Seleccionar tipo" class="!w-56">
                                <el-option
                                    v-for="item in productTypes"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                />
                            </el-select>
                        </div>
                        <SearchInput v-model="search" />
                    </div>

                    <!-- Costo Total -->
                    <div class="flex justify-end mb-4">
                        <el-tag type="success" size="large" effect="dark">
                           Costo total de inventario: {{ totalInventoryCost }}
                        </el-tag>
                    </div>

                    <!-- Tabla de productos -->
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
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">
                            
                            <el-table-column label="Imagen" width="100">
                                <template #default="scope">
                                    <figure @click.stop class="border rounded-md size-20 flex items-center justify-center bg-white p-1">
                                        <el-image v-if="scope.row.media[0]?.original_url" 
                                            :src="scope.row.media[0]?.original_url" 
                                            :preview-src-list="[scope.row.media[0]?.original_url]"
                                            fit="contain" 
                                            preview-teleported 
                                            :hide-on-click-modal="true"
                                            class="object-contain h-full w-full"
                                        />
                                        <span v-else class="text-gray-400 text-xs">Sin imagen</span>
                                    </figure>
                                </template>
                            </el-table-column>
                            <el-table-column prop="code" label="Código" width="150" />
                            <el-table-column prop="name" label="Nombre" width="200" />
                            <el-table-column prop="brand.name" label="Marca" width="120" />
                            <el-table-column prop="cost" label="C/U" width="120" />
                            <el-table-column label="Stock" width="180">
                                <template #default="scope">
                                    <span :class="getProductStock(scope.row).quantity <= 10 ? 'text-red-500 font-bold' : 'text-green-600'" 
                                          class="text-lg">
                                        {{ getProductStock(scope.row).quantity }}
                                    </span>
                                    <span class="text-xs text-gray-500 ml-1">{{ scope.row.measure_unit }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Ubicación" width="180">
                                <template #default="scope">
                                    <span class="text-gray-600 dark:text-gray-400">{{ getProductStock(scope.row).location ?? '-' }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column align="right" width="80">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop class="el-dropdown-link justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
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
                                                <el-dropdown-item v-if="productType === 'Catálogo' && !scope.row.archived_at && $page.props.auth.user.permissions.includes('Eliminar catalogo de productos')" :command="'obsolete-' + scope.row.id">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                                    </svg>
                                                    Mandar a obsoleto
                                                </el-dropdown-item>
                                                <el-dropdown-item v-if="productType === 'Obsoleto' && $page.props.auth.user.permissions.includes('Eliminar catalogo de productos')" :command="'obsolete-' + scope.row.id">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
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
                    <!-- Paginación -->
                     <div v-if="products.total > 0" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="products.current_page"
                            :page-size="products.per_page" :total="products.total"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

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

