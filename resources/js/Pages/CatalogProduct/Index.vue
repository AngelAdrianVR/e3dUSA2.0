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
            <div class="max-w-[92rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="lg:flex justify-between items-center mb-6 space-y-4 lg:space-y-0">
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos')"
                            :href="route('catalog-products.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nuevo producto
                            </SecondaryButton>
                        </Link>

                        <!-- Filtros Rápidos -->
                        <div class="flex flex-col sm:flex-row items-center space-y-2 sm:space-y-0 sm:space-x-3">
                             <el-select v-model="productType" placeholder="Seleccionar tipo" class="!w-44">
                                <el-option
                                    v-for="item in productTypes"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                />
                            </el-select>

                            <el-select v-model="familyId" placeholder="Filtrar por familia" clearable class="!w-48">
                                <el-option
                                    v-for="fam in product_families"
                                    :key="fam.id"
                                    :label="fam.name"
                                    :value="fam.id"
                                />
                            </el-select>
                        </div>

                        <!-- Acciones y Reportes Agrupados -->
                        <div class="flex items-center space-x-3">
                            <el-dropdown split-button type="primary" @click="openReport" plain>
                                Reporte de precios
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item @click="exportToExcel">Exportar lista en Excel</el-dropdown-item>
                                        <el-dropdown-item @click="exportToExcelProductPrices">Reporte de precios para clientes nuevos</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>

                            <!-- Menú de Acciones Masivas -->
                            <el-dropdown type="primary" plain :disabled="!selectedItems.length" @command="handleMassiveAction">
                                <el-button type="primary" plain :disabled="!selectedItems.length">
                                    Acciones Masivas ({{ selectedItems.length }})
                                    <i class="fa-solid fa-chevron-down ml-2 text-xs"></i>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Editar catalogo de productos')" command="edit">
                                            <i class="fa-solid fa-pen-to-square text-blue-500"></i> Editar selección
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos')" command="obsolete">
                                            <i class="fa-solid fa-box-archive text-yellow-500"></i> Marcar obsoletos
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos')" command="delete" divided>
                                            <i class="fa-solid fa-trash text-red-500"></i> Eliminar selección
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                    
                    <!-- Búsqueda y Costo -->
                    <div class="flex justify-between mb-3 items-end">
                        <el-tag v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')" type="success" size="large">
                            <p class="text-base font-semibold">Costo total inventario: {{ totalInventoryCost }}</p>
                        </el-tag>
                        <div class="w-1/3">
                            <SearchInput v-model="search" placeholder="Buscar por material, marca, nombre, id..." />
                        </div>
                    </div>

                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <el-table 
                            ref="multipleTable"
                            row-key="id"
                            :row-class-name="rowClassName"
                            max-height="600" 
                            :data="products.data"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="40" reserve-selection />
                            
                            <!-- Columna Expandible para Variantes -->
                            <el-table-column type="expand" width="62" align="center">
                                <!-- Icono personalizado: solo se muestra si el producto tiene variantes -->
                                <template #expand="{ expanded }">
                                    <span class="variants-expand-icon" :class="{ 'is-expanded': expanded }">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </span>
                                </template>
                                <template #default="props">
                                    <div class="p-4 bg-gray-50 dark:bg-slate-800/50 rounded-lg ml-12 mr-4 my-2 border border-gray-200 dark:border-slate-700">
                                        <div class="flex justify-between items-center mb-3">
                                            <h4 class="font-semibold text-gray-700 dark:text-gray-300">
                                                <i class="fa-solid fa-layer-group mr-2"></i>
                                                Variantes ({{ props.row.variants?.length || 0 }})
                                            </h4>
                                        </div>
                                        
                                        <el-table :data="props.row.variants" :show-header="true" size="small" border v-if="props.row.variants?.length">
                                            <el-table-column label="Foto" width="70" align="center">
                                                <template #default="scope">
                                                    <img :src="scope.row.media?.[0]?.original_url" 
                                                         class="w-8 h-8 rounded object-cover cursor-pointer hover:opacity-80 border" 
                                                         @click.stop="openPreview($event)" 
                                                         @error="handleImageError" />
                                                </template>
                                            </el-table-column>
                                            <el-table-column prop="code" label="Código" width="160" />
                                            <el-table-column prop="name" label="Nombre" />
                                            
                                            <el-table-column align="right" width="160">
                                                <template #default="scope">
                                                    <!-- Botón editar -->
                                                    <el-button
                                                        size="small"
                                                        type="primary"
                                                        circle
                                                        title="Editar variante"
                                                        @click.stop="handleCommand('edit-' + scope.row.id)"
                                                    >
                                                        <i class="fas fa-edit"></i>
                                                    </el-button>

                                                    <!-- Botón obsoleto -->
                                                    <el-button
                                                        v-if="!scope.row.archived_at"
                                                        size="small"
                                                        type="warning"
                                                        circle
                                                        title="Marcar como obsoleto"
                                                        @click.stop="handleCommand('obsolet-' + scope.row.id)"
                                                    >
                                                        <i class="fa-solid fa-box-archive"></i>
                                                    </el-button>
                                                    
                                                    <!-- Botón reestablecer (En caso de que se listen los obsoletos) -->
                                                    <el-button
                                                        v-else
                                                        size="small"
                                                        type="success"
                                                        circle
                                                        title="Reestablecer variante"
                                                        @click.stop="handleCommand('obsolet-' + scope.row.id)"
                                                    >
                                                        <i class="fa-solid fa-rotate-left"></i>
                                                    </el-button>

                                                    <!-- Botón eliminar -->
                                                    <el-button
                                                        size="small"
                                                        type="danger"
                                                        circle
                                                        title="Eliminar variante"
                                                        @click.stop="handleCommand('delete-' + scope.row.id)"
                                                    >
                                                        <i class="fas fa-trash"></i>
                                                    </el-button>
                                                </template>
                                            </el-table-column>
                                        </el-table>
                                        <p v-else class="text-sm text-gray-500 italic">No hay variantes registradas para este producto.</p>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column label="Imagen" width="85">
                                <template #default="scope">
                                    <figure class="border rounded-md size-14 flex items-center justify-center bg-white overflow-hidden shadow-sm">
                                        <img @click.stop="openPreview($event)"
                                            style="width: 100%; height: 100%; object-fit: contain; cursor: pointer;"
                                            :src="scope.row.media?.[0]?.original_url"
                                            @error="handleImageError" />
                                    </figure>
                                </template>
                            </el-table-column>
                            
                            <el-table-column prop="code" label="Código" width="150" />
                            <el-table-column prop="name" label="Nombre" min-width="200" show-overflow-tooltip />
                            <!-- <el-table-column prop="brand.name" label="Marca" width="110" show-overflow-tooltip /> -->
                            <el-table-column label="Ubicación" width="120" align="center">
                                <template #default="scope">
                                    <p>{{ scope.row.storages[0].location }}</p>
                                </template>
                            </el-table-column>
                            
                            <!-- Banderas de Propiedades -->
                            <el-table-column label="Venta" width="75" align="center">
                                <template #default="scope">
                                    <i v-if="scope.row.is_sellable" class="fa-solid fa-check text-green-500 text-lg"></i>
                                    <i v-else class="fa-solid fa-xmark text-red-500"></i>
                                </template>
                            </el-table-column>
                            <el-table-column label="Compra" width="80" align="center">
                                <template #default="scope">
                                    <i v-if="scope.row.is_purchasable" class="fa-solid fa-check text-green-500 text-lg"></i>
                                    <i v-else class="fa-solid fa-xmark text-red-500"></i>
                                </template>
                            </el-table-column>
                            <el-table-column label="Componente" width="110" align="center">
                                <template #default="scope">
                                    <i v-if="scope.row.is_used_as_component" class="fa-solid fa-check text-green-500 text-lg"></i>
                                    <i v-else class="fa-solid fa-xmark text-red-500"></i>
                                </template>
                            </el-table-column>
                            <el-table-column label="Sherman" width="85" align="center">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.requires_director_approval" content="Consultar con Sherman antes de comprar" placement="top">
                                        <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg"></i>
                                    </el-tooltip>
                                    <span v-else class="text-gray-400">--</span>
                                </template>
                            </el-table-column>

                            <el-table-column v-if="$page.props.auth.user.permissions.includes('Ver costos de productos')" prop="cost" label="Costo" width="100">
                                <template #default="scope">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">${{ scope.row.cost?.toFixed(2) ?? '0.00' }}</span>
                                </template>
                            </el-table-column>
                             <el-table-column label="Stock" width="120">
                                <template #default="scope">
                                    <el-popover placement="top" trigger="hover" :width="360" :persistent="false">
                                        <template #reference>
                                            <span
                                                :class="getProductStock(scope.row).quantity <= 10 ? 'text-red-500 font-bold cursor-pointer' : 'text-green-600 font-semibold cursor-pointer'"
                                                title="Pasa el cursor para editar stock mínimo/máximo"
                                            >
                                                {{ (getProductStock(scope.row).quantity).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}
                                            </span>
                                        </template>

                                        <!-- Edición rápida de límites de stock (mínimo/máximo) -->
                                        <div class="text-left">
                                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                                Stock actual: {{ getProductStock(scope.row).quantity }} {{ scope.row.measure_unit }}
                                            </p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-1">Stock mínimo</p>
                                                    <el-input-number
                                                        v-model="stockDrafts[scope.row.id].min_quantity"
                                                        :min="0"
                                                        size="small"
                                                        controls-position="right"
                                                        class="!w-full"
                                                    />
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-1">Stock máximo</p>
                                                    <el-input-number
                                                        v-model="stockDrafts[scope.row.id].max_quantity"
                                                        :min="0"
                                                        size="small"
                                                        controls-position="right"
                                                        class="!w-full"
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex justify-end mt-2">
                                                <el-button size="small" type="primary" @click="saveStockLimits(scope.row)" :loading="savingStockLimitsId === scope.row.id">
                                                    <i class="fa-solid fa-floppy-disk mr-1"></i> Guardar
                                                </el-button>
                                            </div>

                                            <!-- Proyección de ventas del año en curso (cálculo bajo demanda) -->
                                            <div class="mt-3 border-t border-gray-200 dark:border-gray-700 pt-2">
                                                <div class="flex items-center justify-between mb-2">
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                                                        Proyección (últimos 12 meses)
                                                    </p>
                                                    <el-button
                                                        size="small"
                                                        @click="fetchAnnualProjection(scope.row)"
                                                        :loading="isCalculatingProjection === scope.row.id"
                                                        :disabled="isCalculatingProjection !== null && isCalculatingProjection !== scope.row.id"
                                                    >
                                                        <i class="fa-solid fa-calculator mr-1"></i>
                                                        {{ annualProjections[scope.row.id] ? 'Recalcular' : 'Calcular' }}
                                                    </el-button>
                                                </div>

                                                <template v-if="annualProjections[scope.row.id]">
                                                    <!-- Desglose: ventas año / promedio mensual / 3 meses -->
                                                    <div class="grid grid-cols-3 gap-1.5 mb-2">
                                                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg py-1.5 text-center">
                                                            <p class="text-[10px] text-gray-500 dark:text-gray-400">Ventas 12 m</p>
                                                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ formatProjectionNumber(annualProjections[scope.row.id].total_sold) }}</p>
                                                        </div>
                                                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg py-1.5 text-center">
                                                            <p class="text-[10px] text-gray-500 dark:text-gray-400">Prom. mensual</p>
                                                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ formatProjectionNumber(annualProjections[scope.row.id].monthly_average) }}</p>
                                                        </div>
                                                        <div class="bg-gray-50 dark:bg-slate-800 rounded-lg py-1.5 text-center">
                                                            <p class="text-[10px] text-gray-500 dark:text-gray-400">3 meses</p>
                                                            <p class="text-xs font-bold text-blue-600 dark:text-blue-400">{{ formatProjectionNumber(annualProjections[scope.row.id].projection_3_months) }}</p>
                                                        </div>
                                                    </div>

                                                    <!-- Mini gráfica mensual del año en curso -->
                                                    <div class="flex items-end gap-1 h-20">
                                                        <div
                                                            v-for="(bar, i) in annualProjections[scope.row.id].chart"
                                                            :key="i"
                                                            class="flex-1 flex flex-col items-center justify-end"
                                                            :title="`${bar.month}: ${formatProjectionNumber(bar.quantity)} u.`"
                                                        >
                                                            <span class="text-[8px] text-gray-500 dark:text-gray-400 leading-none mb-0.5">{{ formatProjectionNumber(bar.quantity) }}</span>
                                                            <div
                                                                class="w-full rounded-t bg-blue-400 dark:bg-blue-500"
                                                                :style="{ height: getBarHeight(bar.quantity, annualProjections[scope.row.id].chart) + 'px' }"
                                                            ></div>
                                                            <span class="text-[8px] text-gray-400 mt-0.5 leading-none">{{ bar.month }}</span>
                                                        </div>
                                                    </div>

                                                    <!-- Proyección anual aproximada (cantidad a comprar) -->
                                                    <!-- <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-gray-100 dark:border-gray-700">
                                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                                                            Proyección anual aprox.
                                                        </p>
                                                        <p class="text-base font-bold text-blue-700 dark:text-blue-400">
                                                            {{ formatProjectionNumber(annualProjections[scope.row.id].annual_to_order) }} u.
                                                        </p>
                                                    </div> -->
                                                </template>
                                                <p v-else class="text-xs text-gray-400 italic">Pulsa "Calcular" para ver la proyección.</p>
                                            </div>
                                        </div>
                                    </el-popover>
                                    <span class="text-xs text-gray-500 ml-1">{{ scope.row.measure_unit }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column align="right" width="70" fixed="right">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop
                                            class="el-dropdown-link flex justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-700 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :command="'show-' + scope.row.id">
                                                    <i class="fa-regular fa-eye mr-2 text-gray-500"></i> Ver detalles
                                                </el-dropdown-item>
                                                <!-- Agregar Variante -->
                                                <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos') && scope.row.product_type !== 'Insumo'"
                                                    @click="openVariantModal(scope.row)">
                                                    <i class="fa-solid fa-code-branch mr-2 text-green-500"></i> Agregar Variante
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Editar catalogo de productos')"
                                                    :command="'edit-' + scope.row.id" divided>
                                                    <i class="fa-solid fa-pen-to-square mr-2 text-blue-500"></i> Editar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos') && !scope.row.archived_at"
                                                    :command="'obsolet-' + scope.row.id">
                                                    <i class="fa-solid fa-box-archive mr-2 text-yellow-500"></i> Producto obsoleto
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos') && scope.row.archived_at"
                                                    :command="'obsolet-' + scope.row.id">
                                                    <i class="fa-solid fa-rotate-left mr-2 text-indigo-500"></i> Reestablecer
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos')"
                                                    :command="'delete-' + scope.row.id">
                                                    <i class="fa-regular fa-trash-can mr-2 text-red-500"></i> Eliminar
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
            v-if="showMassiveEditModal"
            :show="showMassiveEditModal"
            :selected_products="selectedItems"
            :product_families="product_families"
            @close="closeMassiveEditModal"
        />
        <!-- ======================================= -->

        <!-- ====== MODAL AGREGAR VARIANTE ====== -->
        <el-dialog v-model="showVariantModal" :title="`Agregar variante a: ${variantParent?.name}`" width="500">
            <div class="space-y-5 py-2">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">
                    Esta variante heredará los materiales, marcas, costos, precios y procesos del producto padre. Solo define un nombre distintivo y su fotografía.
                </p>
                <div>
                    <InputLabel value="Nombre de la variante*" />
                    <TextInput v-model="variantForm.name" placeholder="Ej. Llavero Ford Rojo" class="w-full mt-1" />
                    <InputError :message="variantForm.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Fotografía distintiva*" class="mb-2" />
                    <FileUploader @files-selected="variantForm.media = $event" acceptedFormat="image/*" :multiple="false" :maxFiles="1" />
                    <InputError :message="variantForm.errors.media" class="mt-1" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-2">
                    <el-button @click="showVariantModal = false" :disabled="variantForm.processing">Cancelar</el-button>
                    <el-button type="primary" @click="storeVariant" :loading="variantForm.processing">Guardar variante</el-button>
                </div>
            </template>
        </el-dialog>
        <!-- ======================================= -->

        <!-- Visor de imagen manual -->
        <ElImageViewer
            v-if="showImageViewer"
            :url-list="[previewImage]"
            @close="showImageViewer = false"
            :hide-on-click-modal="true"
            teleported
        />
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import FileUploader from '@/Components/MyComponents/FileUploader.vue';
import MassiveEditModal from './Partials/MassiveEditModal.vue';
import { ElMessage, ElMessageBox, ElImageViewer } from 'element-plus';
import axios from 'axios';
import { Link, router, useForm } from "@inertiajs/vue3";
import { debounce } from 'lodash';

export default {
    data() {
        return {
            loading: false,
            loadingExport: false,
            search: this.filters.search,
            productType: this.filters.product_type ?? 'Producto', // Simplificado
            familyId: this.filters.family_id ?? null,
            selectedItems: [],
            showMassiveEditModal: false,
            productTypes: [
                { value: 'Producto', label: 'Productos' },
                { value: 'Insumo', label: 'Insumos' },
                { value: 'Obsoleto', label: 'Obsoletos' },
            ],
            showImageViewer: false,
            previewImage: '',

            // --- Lógica de Variante ---
            showVariantModal: false,
            variantParent: null,
            variantForm: useForm({
                name: '',
                parent_id: null,
                product_type_key: 'P',
                is_sellable: true,
                is_purchasable: false,
                is_used_as_component: false,
                product_family_id: null,
                brand_id: null,
                material: null,
                measure_unit: 'Pieza(s)',
                currency: 'MXN',
                base_price: 0,
                cost: 0,
                current_stock: 0,
                min_quantity: 0,
                max_quantity: 0,
                media: [],
                code: 'VAR', // Marcador, backend regenerará correctamente si usamos logica compartida o podemos autogenerarlo.
            }),
            
            // Mapeo invertido de materiales para pre-llenar correctamente la llave si es necesario
            materialMapReverse: {
                'METAL': 'M', 'PLASTICO': 'PLS', 'PIEL DE LUJO': 'PL', 'ORIGINAL': 'O',
                'LUJO': 'L', 'PIEL': 'P', 'ZAMAK': 'ZK', 'SOLIDCHROME': 'SCH',
                'MICROMETAL': 'MM', 'FLEXCHROME': 'FCH', 'ALUMINIO': 'AL', 'ESTIRENO': 'ES',
                'ABS': 'ABS', 'PVC': 'PVC', 'TELA': 'T', 'CAUCHO': 'CAU', 'VINILPIEL': 'VPL', 
                'FIBRA DE CARBONO': 'FC', 'OVERLAY': 'OV', 'ACERO': 'AC', 'FIBRA DSE CARBONO': 'FDC',
                'RESINA': 'RS', 'ENCAPSULADO': 'ENC', 'CORTE DIAMANTE': 'CDT' 
            },

            // --- Edición rápida de límites de stock (mínimo/máximo) desde la tabla ---
            stockDrafts: {},
            savingStockLimitsId: null,

            // --- Proyección anual de compra (cálculo bajo demanda) ---
            annualProjections: {},
            isCalculatingProjection: null,
        };
    },
    components: {
        SecondaryButton,
        LoadingIsoLogo,
        SearchInput,
        AppLayout,
        Link,
        MassiveEditModal,
        ElImageViewer,
        TextInput,
        InputLabel,
        InputError,
        FileUploader,
    },
    props: {
        products: Object,
        filters: Object,
        product_families: Array,
    },
    computed:{
        totalInventoryCost() {
            if (!this.products || !this.products.data) return 0;
            
            const total = this.products.data.reduce((accumulator, product) => {
                const stock = this.getProductStock(product).quantity;
                const cost = product.cost ?? 0;
                return accumulator + (stock * cost);
            }, 0);

            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(total);
        }
    },
    methods: {
        openVariantModal(parent) {
            this.variantParent = parent;
            
            // Obtiene la llave corta del material para el store
            const materialKey = parent.material ? this.materialMapReverse[parent.material] : null;
            // Un código temporal que asegure uniqueness temporalmente
            const tempCode = `${parent.code}-VAR-${Math.floor(Math.random() * 10000)}`;

            this.variantForm = useForm({
                name: '',
                parent_id: parent.id,
                product_type_key: parent.product_type === 'Insumo' ? 'I' : 'P',
                is_sellable: parent.is_sellable,
                is_purchasable: parent.is_purchasable,
                is_used_as_component: parent.is_used_as_component,
                product_family_id: parent.product_family_id,
                brand_id: parent.brand_id,
                material: materialKey,
                measure_unit: parent.measure_unit,
                currency: parent.currency,
                base_price: parent.base_price,
                cost: parent.cost,
                current_stock: 0,
                location: parent.storages?.[0]?.location ?? null,
                min_quantity: 0,
                max_quantity: 0,
                code: tempCode,
                media: [],
                components: parent.actual_components || [],
                production_processes: parent.actual_production_costs || [],
            });
            this.showVariantModal = true;
        },
        storeVariant() {
            this.variantForm.post(route('catalog-products.store'), {
                onSuccess: () => {
                    ElMessage.success('Variante agregada correctamente');
                    this.showVariantModal = false;
                    this.variantForm.reset();
                    this.fetchData(); // Refresca lista para ver la variante bajo el padre
                },
                onError: (errors) => {
                    ElMessage.error('Hubo un error. Revisa que hayas subido la imagen y puesto un nombre.');
                    console.error(errors);
                }
            });
        },
        handleMassiveAction(command) {
            if (command === 'edit') {
                this.showMassiveEditModal = true;
            } else if (command === 'obsolete') {
                this.massiveObsolet();
            } else if (command === 'delete') {
                this.massiveDeleteConfirm();
            }
        },
        closeMassiveEditModal() {
            this.showMassiveEditModal = false;
            this.$refs.multipleTable.clearSelection();
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
        exportToExcelProductPrices() {
            this.loadingExport = true;
            axios({
                url: route('catalog-products.export-excel-abc'),
                method: 'GET',
                responseType: 'blob',
            }).then(response => {
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', 'catalogo_precios_ABC.xlsx');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }).catch(error => {
                console.error('Error al exportar ABC:', error);
                ElMessage.error('Error al generar el reporte ABC');
            }).finally(() => {
                this.loadingExport = false;
            });
        },
        fetchData() {
            this.loading = true;
            router.get(route('catalog-products.index'), {
                search: this.search,
                product_type: this.productType,
                family_id: this.familyId,
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
        async saveStockLimits(row) {
            const draft = this.stockDrafts[row.id] || {};
            this.savingStockLimitsId = row.id;
            try {
                const response = await axios.put(route('products.update-stock-limits', row.id), {
                    min_quantity: draft.min_quantity,
                    max_quantity: draft.max_quantity,
                });

                ElMessage.success('Límites de stock actualizados correctamente.');

                // Refrescar los valores en la fila y en el borrador para que el tooltip muestre lo guardado
                row.min_quantity = response.data.product.min_quantity;
                row.max_quantity = response.data.product.max_quantity;
                this.stockDrafts[row.id] = {
                    min_quantity: response.data.product.min_quantity,
                    max_quantity: response.data.product.max_quantity,
                };
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    ElMessage.error('El stock máximo no puede ser menor que el stock mínimo.');
                } else {
                    ElMessage.error('Ocurrió un error al actualizar los límites de stock.');
                }
                console.error(error);
            } finally {
                this.savingStockLimitsId = null;
            }
        },
        async fetchAnnualProjection(row) {
            this.isCalculatingProjection = row.id;
            try {
                const response = await axios.get(route('stock-projection.product', row.id));
                this.annualProjections = {
                    ...this.annualProjections,
                    [row.id]: response.data,
                };
            } catch (error) {
                console.error('Error al calcular la proyección anual:', error);
                ElMessage.error('Ocurrió un error al calcular la proyección anual.');
            } finally {
                this.isCalculatingProjection = null;
            }
        },
        formatProjectionNumber(value) {
            return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(value || 0);
        },
        getBarHeight(quantity, chart) {
            const max = Math.max(...(chart || []).map(b => b.quantity || 0), 1);
            return Math.max(3, Math.round((quantity / max) * 56));
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            this.$inertia.get(route('catalog-products.show', row));
        },
        // Agrega una clase a las filas sin variantes para ocultar el icono de desplegar
        rowClassName({ row }) {
            return row.variants?.length ? '' : 'no-variants-row';
        },
        handleCommand(command) {
            const commandParts = command.split('-');
            const commandName = commandParts[0];
            const rowId = commandParts[1];
        
            if (commandName === 'show' || commandName === 'edit' || commandName === 'obsolet') {
                 router.get(route('catalog-products.' + commandName, rowId));
            } else if (commandName === 'delete') {
                ElMessageBox.confirm(
                    '¿Estás seguro de que deseas eliminar este producto? Esta acción no se puede deshacer.',
                    'Advertencia',
                    {
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                        type: 'warning',
                        iconColor: '#EF4444'
                    }
                )
                .then(() => {
                    router.delete(route('catalog-products.destroy', rowId), {
                        onSuccess: () => {
                            ElMessage({ type: 'success', message: 'Producto eliminado correctamente' });
                        },
                        onError: () => {
                            ElMessage({ type: 'error', message: 'No se pudo eliminar el producto' });
                        }
                    });
                })
                .catch(() => {
                    ElMessage({ type: 'info', message: 'Eliminación cancelada' });
                });
            }
        },
        massiveDeleteConfirm() {
            ElMessageBox.confirm(
                '¿Estás seguro de eliminar los productos seleccionados? Esta acción no se puede deshacer.',
                'Eliminación Múltiple',
                {
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'error',
                }
            ).then(() => {
                this.deleteSelections();
            }).catch(() => {});
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('catalog-products.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Productos eliminados correctamente');
                    this.$refs.multipleTable.clearSelection();
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar los productos');
                }
            });
        },
        massiveObsolet() {
            ElMessageBox.confirm(
                '¿Estás seguro de marcar como obsoletos los productos seleccionados?',
                'Modificar Múltiples',
                {
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            ).then(() => {
                const ids = this.selectedItems.map(item => item.id);
                this.$inertia.post(route('catalog-products.massive-obsolet'), { ids }, {
                    onSuccess: () => {
                        ElMessage.success('Productos actualizados correctamente');
                        this.$refs.multipleTable.clearSelection();
                    },
                    onError: () => {
                        ElMessage.error('Ocurrió un error al actualizar los productos');
                    }
                });
            }).catch(() => {});
        },
        handlePageChange(page) {
            router.get(route('catalog-products.index', {
                page: page,
                search: this.search,
                product_type: this.productType,
                family_id: this.familyId,
            }), {
                preserveState: true,
                replace: true,
            });
        },
        openPreview(event) {
            const img = event.target;
            if (img && img.src) {
                this.previewImage = img.src;
                this.showImageViewer = true;
            }
        },
        handleImageError(event) {
            const img = event.target;
            if (!img || !img.src) return;

            const currentSrc = img.src;
            const prodDomain = 'https://www.intranetemblems3d.dtw.com.mx';
            
            if (img.dataset.fallbackAttempted || currentSrc.includes(prodDomain)) return;
            img.dataset.fallbackAttempted = "true";

            try {
                const urlObj = new URL(currentSrc);
                img.src = prodDomain + urlObj.pathname;
            } catch (e) {
                img.src = currentSrc.replace(/^https?:\/\/[^\/]+/, prodDomain);
            }
        }
    },
    watch: {
        search: debounce(function () {
            this.fetchData();
        }, 300),
        productType() {
            this.fetchData();
        },
        familyId() {
            this.fetchData();
        },
        // Mantiene los borradores de stock sincronizados con la página actual
        products: {
            immediate: true,
            handler(newVal) {
                const drafts = {};
                (newVal?.data || []).forEach(p => {
                    drafts[p.id] = {
                        min_quantity: p.min_quantity ?? null,
                        max_quantity: p.max_quantity ?? null,
                    };
                });
                this.stockDrafts = drafts;
            }
        }
    }
};
</script>

<style>
/* Estilos para la paginación en modo oscuro */
.dark .el-pagination button,
.dark .el-pager li {
    background-color: #1f2937 !important;
    color: #d1d5db !important;
}
.dark .el-pager li.is-active {
    color: #ffffff !important;
    background-color: #3b82f6 !important;
}

/* ===== Icono de expandir variantes ===== */
/* Área del botón más grande para facilitar el clic sin abrir el producto */
.el-table .el-table__expand-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    color: #6b7280;
    transition: background-color .2s ease, color .2s ease;
}
/* Hover: resalta el área del botón */
.el-table .el-table__expand-icon:hover {
    background-color: #dbeafe;
    color: #2563eb;
}
.el-table .el-table__expand-icon i {
    font-size: 13px;
    transition: transform .25s ease;
}
.el-table .el-table__expand-icon--expanded i {
    transform: rotate(90deg);
}

/* Ocultar el icono en productos sin variantes */
.el-table .no-variants-row .el-table__expand-icon {
    visibility: hidden;
    pointer-events: none;
}

/* Modo oscuro */
.dark .el-table .el-table__expand-icon {
    color: #9ca3af;
}
.dark .el-table .el-table__expand-icon:hover {
    background-color: rgba(59, 130, 246, 0.25);
    color: #93c5fd;
}
</style>