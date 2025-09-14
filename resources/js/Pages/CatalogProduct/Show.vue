<template>
    <AppLayout title="Detalles de producto">
        <div class="p-4 sm:p-6 lg:p-8 dark:text-gray-200">
            <!-- Encabezado con buscador y acciones -->
            <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-slate-700">
                <div class="w-full lg:w-1/3">
                    <el-select @change="$inertia.get(route('catalog-products.show', selectedCatalogProduct))"
                        v-model="selectedCatalogProduct" filterable placeholder="Buscar otro producto..."
                        class="!w-full"
                        no-data-text="No hay productos registrados" no-match-text="No se encontraron coincidencias">
                        <el-option v-for="item in catalog_products" :key="item.id"
                            :label="item.name" :value="item.id" />
                    </el-select>
                </div>
                <div class="flex items-center space-x-2">
                    <el-tooltip content="Registrar Entrada" placement="top">
                        <button @click="openStockModal('Entrada')" class="size-9 flex items-center justify-center rounded-lg bg-green-100 hover:bg-green-200 dark:bg-green-800 dark:hover:bg-green-700 text-green-600 dark:text-green-300 transition-colors">
                            <i class="fa-solid fa-arrow-up"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip content="Registrar Salida" placement="top">
                         <button @click="openStockModal('Salida')" class="size-9 flex items-center justify-center rounded-lg bg-red-100 hover:bg-red-200 dark:bg-red-800 dark:hover:bg-red-700 text-red-600 dark:text-red-300 transition-colors">
                            <i class="fa-solid fa-arrow-down"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip v-if="$page.props.auth.user.permissions.includes('Editar catalogo de productos')" content="Editar Producto" placement="top">
                        <Link :href="route('catalog-products.edit', selectedCatalogProduct)">
                            <button class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                                <i class="fa-solid fa-pencil text-sm"></i>
                            </button>
                        </Link>
                    </el-tooltip>
                    
                    <Dropdown align="right" width="48" v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos') || $page.props.auth.user.permissions.includes('Eliminar catalogo de productos')">
                        <template #trigger>
                            <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                                Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('catalog-products.create')" v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos')">
                                <i class="fa-solid fa-plus w-4 mr-2"></i> Nuevo producto
                            </DropdownLink>
                            <DropdownLink :as="'button'" v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos') && !product.archived_at" @click="ObsoletProduct">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                    Producto obsoleto
                                </div>
                            </DropdownLink>
                            <DropdownLink :as="'button'" v-if="$page.props.auth.user.permissions.includes('Crear catalogo de productos') 
                                && product.archived_at" @click="ObsoletProduct">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                    Reestablecer
                                </div>
                            </DropdownLink>
                            <div class="border-t border-gray-200 dark:border-slate-700" />
                            <DropdownLink @click="showConfirmModal = true" as="button" v-if="$page.props.auth.user.permissions.includes('Eliminar catalogo de productos')" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                                <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                            </DropdownLink>
                        </template>
                    </Dropdown>

                    <Link :href="route('catalog-products.index')"
                        class="mt-4 sm:mt-0 flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-slate-700 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                        <i class="fa-solid fa-xmark"></i>
                    </Link>
                </div>
            </header>

            <!-- Contenido Principal del Producto -->
            <main class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-6">
                <!-- Columna Izquierda: Galería de Imágenes -->
                <section>
                    <div class="bg-white dark:bg-slate-800/50 p-4 rounded-xl shadow-lg">
                        <div class="w-full h-80 bg-gray-100 dark:bg-slate-900 rounded-lg flex items-center justify-center overflow-hidden">
                            <img v-if="product.media?.length" :src="product.media[currentImage]?.original_url" :alt="product.name" class="w-full h-full object-contain">
                            <div class="flex flex-col items-center justify-end" v-else>
                                <i class="fa-regular fa-image text-gray-400 text-6xl"></i>
                                <p class="text-center italic text-gray-700 dark:text-gray-400 mt-2">Producto sin imagen</p>
                            </div>
                        </div>
                        <div v-if="product.media?.length > 1" class="flex items-center justify-center space-x-2 mt-3">
                            <div v-for="(image, index) in product.media" :key="index" @click="currentImage = index"
                                class="size-16 rounded-md overflow-hidden cursor-pointer border-2 transition-colors"
                                :class="index === currentImage ? 'border-primary' : 'border-transparent hover:border-gray-300 dark:hover:border-slate-600'">
                                <img :src="image.original_url" :alt="`Thumbnail ${index + 1}`" class="w-full h-full object-cover">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Columna Derecha: Detalles del Producto -->
                <section>
                    <!-- Nombre y Código -->
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ product.name }}
                            <el-tag v-if="product.archived_at" type="warning">Obsoleto</el-tag>
                        </h1>
                        <p class="text-base text-gray-500 dark:text-gray-400 font-mono mt-1">Código: {{ product.code }}</p>
                    </div>

                    <article class="max-h-[600px] overflow-auto space-y-5">
                        <!-- Tarjeta de Detalles Generales -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Información General</h2>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Marca</div>
                                <div>{{ product.brand?.name ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Familia</div>
                                <div>{{ product.product_family?.name ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Material</div>
                                <div>{{ product.material ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400 col-span-2 pt-2">Características</div>
                                <div class="col-span-2 text-gray-600 dark:text-gray-300">{{ product.caracteristics || 'Sin características adicionales.' }}</div>
                            </div>
                        </div>

                        <!-- Tarjeta de Especificaciones y Almacén -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Especificaciones y Almacén</h2>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Dimensiones</div>
                                <div>{{ formattedDimensions }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Unidad de Medida</div>
                                <div>{{ product.measure_unit ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Existencias</div>
                                <div>{{ product.storages?.[0]?.quantity ?? '0' }} {{ product.measure_unit }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Ubicación</div>
                                <div>{{ product.storages?.[0]?.location ?? '--' }}</div>
                            </div>
                        </div>

                        <!-- Tarjeta de Costos y Precios -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Costos y Precios</h2>

                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div 
                                v-if="$page.props.auth.user.permissions.includes('Ver costos de produccion')" 
                                class="font-semibold text-gray-500 dark:text-gray-400"
                            >
                                Costo de Producción y Componentes
                            </div>
                            <div 
                                v-if="$page.props.auth.user.permissions.includes('Ver costos de produccion')" 
                                class="font-bold text-green-600 dark:text-green-400"
                            >
                                {{ formatCurrency(product.cost) }}
                            </div>

                            <div class="font-semibold text-gray-500 dark:text-gray-400">Precio Base del producto</div>
                                <div class="flex items-center gap-3">
                                    <div>
                                        <span>{{ formatCurrency(product.base_price) }} {{ product.currency }}</span>
                                        <p class="font-bold text-xs">Actualizado: <span class="font-thin">{{ formatDate(product.base_price_updated_at) }}</span></p>
                                    </div>
                                    <button 
                                        class="flex items-center gap-2 rounded-full px-4 py-2 font-medium 
                                            bg-white text-gray-700 shadow-md border border-gray-200 
                                            hover:bg-gray-100 hover:shadow-lg transition-all duration-300 
                                            dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 
                                            dark:hover:bg-gray-700 dark:hover:shadow-lg"
                                        @click="openEditPriceDialog"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" 
                                            class="w-4 h-4" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke="currentColor" 
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" 
                                                d="M16.862 4.487l1.651-1.651a1.875 1.875 0 112.652 2.652L10.582 16.071a4.5 4.5 0 01-1.897 1.13l-3.314.943a.75.75 0 01-.926-.926l.943-3.314a4.5 4.5 0 011.13-1.897l10.344-10.344z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Precios por Cliente -->
                            <div v-if="product.price_history?.length" class="mt-4 pt-4 border-t dark:border-slate-700">
                                <h3 class="font-semibold text-sm mb-2">Precios Especiales por Cliente</h3>
                                <el-collapse accordion>
                                    <el-collapse-item 
                                        v-for="(pricings, client) in groupedPrices" 
                                        :key="client" 
                                        :title="client"
                                        >
                                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-slate-700 dark:text-gray-300">
                                                <tr>
                                                    <th scope="col" class="px-4 py-2">Precio Especial</th>
                                                    <th scope="col" class="px-4 py-2">Vigente Desde</th>
                                                    <th scope="col" class="px-4 py-2">Vigente Hasta</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="pricing in pricings" 
                                                    :key="pricing.id" class="bg-white dark:bg-slate-800 border-b dark:border-gray-600">
                                                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">${{ pricing.price }}</td>
                                                    <td class="px-4 py-2">{{ formatDate(pricing.valid_from) }}</td>
                                                    <td class="px-4 py-2 flex items-center justify-between">
                                                        <span>{{ pricing.valid_to ? formatDate(pricing.valid_to) : 'Indefinido' }}</span>
                                                        <!-- BOTÓN PARA FINALIZAR PRECIO ACTIVO -->
                                                        <el-tooltip v-if="!pricing.valid_to" content="Finalizar vigencia de este precio" placement="top">
                                                            <button @click="confirmCloseSpecialPrice(pricing.id)" class="size-7 flex items-center justify-center rounded-md text-red-500 bg-red-100 hover:bg-red-200 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                                                                <i class="fa-solid fa-calendar-xmark text-sm"></i>
                                                            </button>
                                                        </el-tooltip>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>
                        </div>

                        <!-- Tarjeta de Componentes -->
                        <div v-if="product.components?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">
                                Componentes (Materia Prima)
                            </h2>

                            <ul class="space-y-2 text-sm">
                                <li
                                v-for="component in product.components"
                                :key="component.id"
                                class="flex items-center justify-between p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md"
                                >
                                <!-- Imagen o ícono -->
                                <div class="w-10 h-10 flex items-center justify-center bg-gray-200 dark:bg-slate-700 rounded-lg overflow-hidden">
                                    <img
                                    v-if="component.media?.[0]?.original_url"
                                    :src="component.media[0].original_url"
                                    alt="Componente"
                                    class="w-full h-full object-cover"
                                    />
                                    <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="w-6 h-6 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 7a2 2 0 012-2h2l2-3h6l2 3h2a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 13l4-4a2 2 0 012.828 0L15 14l3-3 3 3"
                                    />
                                    </svg>
                                </div>

                                <!-- Nombre -->
                                <span class="text-gray-700 dark:text-gray-300 flex-1 ml-3">
                                    {{ component.name }}
                                </span>

                                <!-- Cantidad -->
                                <span class="font-semibold text-primary">
                                    x {{ component.pivot.quantity }}
                                </span>
                                </li>
                            </ul>
                        </div>


                        <!-- Tarjeta de Procesos de Producción -->
                        <div v-if="product.production_costs?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Procesos de Producción</h2>
                            <ul class="space-y-1 text-sm">
                                <li v-for="process in product.production_costs" :key="process.id" class="flex justify-between items-center p-2">
                                    <span class="text-gray-700 dark:text-gray-300">{{ process.name }}</span>
                                    <span class="text-gray-500 dark:text-gray-400 text-xs">{{ formatCurrency(process.cost) }}</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Tarjeta de movimientos de stock -->
                        <div v-if="product.storages[0]?.stock_movements?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-2 text-gray-800 dark:text-gray-200 border-b dark:border-slate-700 pb-2">
                                Historial de Movimientos
                            </h2>
                            <div class="space-y-1 max-h-56 overflow-y-auto pr-2">
                                <ul class="divide-y divide-gray-200 dark:divide-slate-700">

                                    <li v-for="movement in product.storages[0].stock_movements" :key="movement.id" class="py-3 flex items-center justify-between space-x-3">
                                        
                                        <div class="flex items-center min-w-0">
                                            <div class="flex-shrink-0">
                                                <span class="inline-flex items-center justify-center size-10 rounded-full"
                                                    :class="movement.type === 'Entrada' ? 'bg-green-100 dark:bg-green-900/50' : 'bg-red-100 dark:bg-red-900/50'">
                                                    <i class="fa-solid text-md" 
                                                    :class="movement.type === 'Entrada' ? 'fa-arrow-up text-green-600 dark:text-green-400' : 'fa-arrow-down text-red-600 dark:text-red-400'"></i>
                                                </span>
                                            </div>

                                            <div class="min-w-0 ml-4">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                    {{ movement.type === 'Entrada' ? 'Entrada de Stock' : 'Salida de Stock' }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {{ formatDateTime(movement.created_at) }} </p>
                                            </div>
                                        </div>

                                        <div class="text-right flex-shrink-0">
                                            <p class="text-sm font-bold"
                                            :class="movement.type === 'Entrada' ? 'text-green-600 dark:text-green-500' : 'text-red-600 dark:text-red-500'">
                                                {{ movement.type === 'Entrada' ? '+' : '-' }} {{ movement.quantity_change }} {{ product.measure_unit }}
                                            </p>
                                            <p v-if="movement.notes" class="text-xs text-gray-500 dark:text-gray-400 italic truncate max-w-xs pr-1" :title="movement.notes">
                                                {{ movement.notes }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="!product.storages[0]?.stock_movements?.length" class="text-center py-8">
                                <p class="text-gray-500 dark:text-gray-400">No hay movimientos registrados.</p>
                            </div>
                        </div>
                    </article>
                </section>
            </main>
        </div>

        <!-- Modal de Confirmación para Eliminar -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Producto del Catálogo
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente este producto? Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Stock modal -->
        <Modal :show="showStockModal" @close="showStockModal = false">
            <div class="p-6 dark:bg-slate-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ stockMovementForm.type === 'Entrada' ? 'Registrar Entrada de Stock' : 'Registrar Salida de Stock' }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Ingresa la cantidad y notas para el movimiento de "{{ product.name }}".
                </p>

                <div class="mt-6 space-y-4">
                    <div>
                        <TextInput v-model="stockMovementForm.quantity" 
                            :error="stockMovementForm.errors.quantity" 
                            :label="'Cantidad'" 
                            type="numeric-stepper" 
                            :step="0.1" 
                            class="mt-1 block w-full" 
                            placeholder="0.00" />
                    </div>
                    <div>
                        <TextInput v-model="stockMovementForm.notes" 
                            :label="'Notas (Opcional)'"
                            :error="stockMovementForm.errors.notes" 
                            :isTextarea="true"
                            :withMaxLength="true"
                            class="mt-1 block w-full" 
                            placeholder="Ej. Ajuste de inventario, entrada de proveedor..." />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <CancelButton @click="showStockModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="submitStockMovement" :class="{ 'opacity-25': stockMovementForm.processing }" :disabled="stockMovementForm.processing"
                        :style="{ backgroundColor: stockMovementForm.type === 'Entrada' ? '' : '#DC2626' }">
                        Confirmar {{ stockMovementForm.type === 'Entrada' ? 'Entrada' : 'Salida' }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Confirmación para Finalizar Precio -->
        <ConfirmationModal :show="showClosePriceConfirmModal" @close="showClosePriceConfirmModal = false">
            <template #title>
                Finalizar Precio Especial
            </template>
            <template #content>
                ¿Estás seguro de que deseas finalizar la vigencia de este precio especial? El producto volverá a su precio base para este cliente.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showClosePriceConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="closeSpecialPrice" class="!bg-red-600 hover:!bg-red-700">Sí, finalizar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Dialogo para editar precio -->
        <el-dialog 
        title="Editar Precio Base" 
        v-model="editDialogVisible" 
        width="400px"
        >
        <el-form @submit.prevent>
            <el-form-item label="Nuevo precio">
            <el-input-number 
                v-model="newBasePrice" 
                :min="0" 
                :step="0.01" 
                :precision="2"
                controls-position="right" 
                class="w-full"
            />
            </el-form-item>
        </el-form>
        <template #footer>
            <div class="flex justify-end gap-2">
            <el-button @click="editDialogVisible = false">Cancelar</el-button>
            <el-button 
                type="success" 
                @click="updateBasePrice"
                :loading="loadingUpdate"
            >
                Guardar
            </el-button>
            </div>
        </template>
        </el-dialog>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    // Se mantiene en Options API para mayor claridad como fue solicitado.
    components: {
        Link,
        Modal,
        Dropdown,
        TextInput,
        AppLayout,
        InputLabel,
        InputError,
        CancelButton,
        DropdownLink,
        PrimaryButton,
        SecondaryButton,
        ConfirmationModal,
    },
    props: {
        product: Object,
        catalog_products: Array, // Es mejor que sea un Array para el select
    },
    data() {
        return {
            selectedCatalogProduct: this.product.id,
            showConfirmModal: false,
            currentImage: 0,

            // Para finalizar precio especial
            showClosePriceConfirmModal: false,
            priceHistoryToClose: null,

            // Para editar precio base
            editDialogVisible: false,
            newBasePrice: null,
            loadingUpdate: false,

            // movimientos de stock
            showStockModal: false,
            stockMovementForm: useForm({
                quantity: null,
                notes: '',
                type: '', // 'Entrada' o 'Salida'
            }),
        };
    },
    computed: {
        groupedPrices() {
            if (!this.product.price_history) return {};

            // ordenar del más nuevo al más viejo
            const sorted = [...this.product.price_history].sort((a, b) => {
                return new Date(b.created_at) - new Date(a.created_at);
            });

            // agrupar por cliente
            return sorted.reduce((groups, pricing) => {
                const clientName = pricing.branch?.name || "Sin cliente";
                if (!groups[clientName]) {
                groups[clientName] = [];
                }
                groups[clientName].push(pricing);
                return groups;
            }, {});
        },
        formattedDimensions() {
            const safe = (val) => val ?? "-";

            if (this.product.is_circular) {
                return `Ø ${safe(this.product.diameter)} mm (Diámetro) x ${safe(this.product.width)} mm (Grosor)`;
            }

            return `${safe(this.product.large)} mm (L) x ${safe(this.product.height)} mm (A) x ${safe(this.product.width)} mm (G)`;
        }
    },
    methods: {
        // formatea la fecha
        formatDateTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            // Opciones para un formato más localizado y amigable
            const options = {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            };
            return date.toLocaleDateString('es-MX', options);
        },
        // formatea la fecha sin la hora
        formatDate(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            // Opciones para un formato más localizado y amigable
            const options = {
                year: 'numeric', month: 'short', day: 'numeric',
            };
            return date.toLocaleDateString('es-MX', options);
        },
        // Formatea un número como moneda
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return parseFloat(value).toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },

        openEditPriceDialog() {
            this.newBasePrice = this.product.base_price; // precargar precio actual
            this.editDialogVisible = true;
        },

        async updateBasePrice() {
            try {
                this.loadingUpdate = true;

                // llamada al backend (ajusta la ruta a tu endpoint)
                const response = await axios.put(
                route("products.simple-update", this.product.id), // se puede modificar el metodo del controlador para actualizar otras variables (queda flexible)
                { base_price: this.newBasePrice }
                );

                // actualizar localmente sin recargar la página
                this.product.base_price = response.data.base_price;

                ElMessage.success("Precio actualizado correctamente");
                this.editDialogVisible = false;
            } catch (error) {
                console.error(error);
                ElMessage.error("Ocurrió un error al actualizar el precio");
            } finally {
                this.loadingUpdate = false;
            }
        },
        openStockModal(type) {
            this.stockMovementForm.reset();
            this.stockMovementForm.type = type;
            this.showStockModal = true;
        },
        submitStockMovement() {
            this.stockMovementForm.post(this.route('products.stock-movement', this.product.id), {
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    this.showStockModal = false;
                    this.stockMovementForm.reset();
                    ElMessage.success('Movimiento de stock realizado');
                },
                onError: () => {
                    ElMessage.error('Hubo un error al realizar el movimiento');
                }
            });
        },

        // --- MÉTODOS PARA FINALIZAR PRECIO ---
        confirmCloseSpecialPrice(historyId) {
            this.priceHistoryToClose = historyId;
            this.showClosePriceConfirmModal = true;
        },

        async closeSpecialPrice() {
            if (!this.priceHistoryToClose) return;
            try {
                // Usamos PATCH para indicar una actualización parcial del recurso
                const response = await axios.patch(route('branch-price-history.close', this.priceHistoryToClose));
                if (response.status === 200) {
                    ElMessage.success('El precio especial ha sido finalizado.');
                    this.$inertia.reload({ preserveScroll: true });
                }
            } catch (error) {
                console.error("Error al finalizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'No se pudo finalizar el precio.');
            } finally {
                this.showClosePriceConfirmModal = false;
                this.priceHistoryToClose = null;
            }
        },

        // Método para mandar a obsoletos al producto
        async ObsoletProduct() {
            try {
                const response = await axios.get(route('catalog-products.obsolet', this.product.id));
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
        // Método para eliminar el producto
        async deleteItem() {
            try {
                const response = await axios.delete(route('catalog-products.destroy', this.product.id));
                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Producto eliminado con éxito.');
                    this.$inertia.visit(route('catalog-products.index'));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar el producto.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
            }
        },
    },
    watch: {
        // Observador para resetear la imagen actual si el producto cambia
        'product.id'(newId) {
            this.selectedCatalogProduct = newId;
            this.currentImage = 0;
        }
    }
};
</script>
