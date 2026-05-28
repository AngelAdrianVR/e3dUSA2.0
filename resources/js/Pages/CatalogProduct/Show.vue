<template>
    <AppLayout title="Detalles de producto">
        <div class="p-4 sm:p-6 lg:p-8 dark:text-gray-200">
            <!-- Encabezado con buscador y acciones -->
            <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-slate-700">
                <div class="w-full lg:w-1/3">
                    <LoadingIsoLogo v-if="loadingProductList" />
                    <el-select v-else @change="$inertia.get(route('catalog-products.show', selectedCatalogProduct))"
                        v-model="selectedCatalogProduct" filterable placeholder="Buscar otro producto base..."
                        class="!w-full"
                        no-data-text="No hay productos registrados" no-match-text="No se encontraron coincidencias">
                        <el-option class="!w-96" v-for="item in catalog_products" :key="item.id"
                            :label="item.name" :value="item.id" />
                    </el-select>
                </div>
                <div class="flex items-center space-x-2">
                    <el-tooltip v-if="$page.props.auth.user.permissions.includes('Crear movimientos de stock')" content="Registrar Entrada" placement="top">
                        <button @click="openStockModal('Entrada')" class="size-9 flex items-center justify-center rounded-lg bg-green-100 hover:bg-green-200 dark:bg-green-800 dark:hover:bg-green-700 text-green-600 dark:text-green-300 transition-colors">
                            <i class="fa-solid fa-arrow-up"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip v-if="$page.props.auth.user.permissions.includes('Crear movimientos de stock')" content="Registrar Salida" placement="top">
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
            <main class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-6" v-if="!product.parent_id">
                <!-- Columna Izquierda: Galería de Imágenes y Variantes -->
                <section>
                    <div class="bg-white dark:bg-slate-800/50 p-4 rounded-xl shadow-lg relative">
                        <div class="w-full h-80 bg-gray-100 dark:bg-slate-900 rounded-lg flex items-center justify-center overflow-hidden">
                            <img v-if="mainDisplayedImage" :src="mainDisplayedImage" :alt="activeProduct.name" @error="handleImageError" class="w-full h-full object-contain transition-opacity duration-300">
                            <div class="flex flex-col items-center justify-end" v-else>
                                <i class="fa-regular fa-image text-gray-400 text-6xl"></i>
                                <p class="text-center italic text-gray-700 dark:text-gray-400 mt-2">Producto sin imagen</p>
                            </div>
                        </div>
                        
                        <!-- Miniaturas del producto original -->
                        <div v-if="product.media?.length > 1" class="flex items-center justify-center space-x-2 mt-3">
                            <div v-for="(image, index) in product.media" :key="index" 
                                @click="resetToMainImage(index)"
                                class="size-16 rounded-md overflow-hidden cursor-pointer border-2 transition-colors"
                                :class="!selectedVariant && index === currentImage ? 'border-primary' : 'border-transparent hover:border-gray-300 dark:hover:border-slate-600'">
                                <img :src="image.original_url" @error="handleImageError" :alt="`Thumbnail ${index + 1}`" class="w-full h-full object-cover">
                            </div>
                        </div>
                    </div>

                    <!-- NUEVA SECCIÓN: Variantes -->
                    <div v-if="product.variants?.length" class="mt-6 bg-white dark:bg-slate-800/50 p-4 rounded-xl shadow-lg animate-fade-in max-h-96 overflow-auto">
                        <h3 class="font-bold text-md mb-4 border-b dark:border-slate-700 pb-2">
                            <i class="fa-solid fa-layer-group text-gray-400 mr-2"></i> Variantes ({{ product.variants.length }})
                        </h3>
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
                            
                            <!-- PRODUCTO BASE CÓMO BOTÓN DE REGRESO -->
                            <div @click="selectedVariant = null"
                                class="cursor-pointer flex flex-col items-center p-2 border rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                                :class="!selectedVariant ? 'border-primary bg-indigo-50 dark:bg-indigo-900/30 ring-1 ring-primary' : 'border-gray-200 dark:border-gray-700'">
                                <div class="relative size-12 md:size-14 bg-white dark:bg-slate-900 rounded-md overflow-hidden border border-gray-100 dark:border-slate-700 mb-2 flex items-center justify-center shadow-sm">
                                    <img v-if="product.media?.[0]?.original_url" :src="product.media[0].original_url" @error="handleImageError" class="w-full h-full object-cover">
                                    <i v-else class="fa-regular fa-image text-gray-300 text-lg"></i>
                                    <div class="absolute bottom-0 inset-x-0 bg-primary/90 text-white text-[9px] text-center font-bold pb-0.5">BASE</div>
                                </div>
                                <span class="text-[10px] text-center text-gray-700 dark:text-gray-300 font-medium leading-tight line-clamp-2 w-full" :title="product.name">
                                    Producto Original
                                </span>
                            </div>

                            <!-- Variantes -->
                            <div v-for="variant in product.variants" :key="variant.id"
                                @click="selectedVariant = variant"
                                class="cursor-pointer flex flex-col items-center p-2 border rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                                :class="selectedVariant?.id === variant.id ? 'border-primary bg-indigo-50 dark:bg-indigo-900/30 ring-1 ring-primary' : 'border-gray-200 dark:border-gray-700'">
                                <div class="size-12 md:size-14 bg-white dark:bg-slate-900 rounded-md overflow-hidden border border-gray-100 dark:border-slate-700 mb-2 flex items-center justify-center shadow-sm">
                                    <img v-if="variant.media?.[0]?.original_url" :src="variant.media[0].original_url" @error="handleImageError" class="w-full h-full object-cover">
                                    <i v-else class="fa-regular fa-image text-gray-300 text-lg"></i>
                                </div>
                                <span class="text-[10px] text-center text-gray-700 dark:text-gray-300 font-medium leading-tight line-clamp-2 w-full" :title="variant.name">
                                    {{ variant.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Columna Derecha: Detalles del Producto -->
                <section>
                    <!-- Nombre y Código -->
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white flex flex-wrap items-center gap-3">
                            {{ activeProduct.name }}
                            <el-tag v-if="activeProduct.archived_at" type="warning">Obsoleto</el-tag>
                            
                            <!-- Botón superior para regresar a ver el producto Padre -->
                            <button v-if="selectedVariant" @click="selectedVariant = null" class="text-xs bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 px-3 py-1.5 rounded-full font-medium transition-colors flex items-center">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Ver producto original
                            </button>
                        </h1>
                        <p class="text-amber-500 font-medium mt-1">{{ activeProduct.product_type }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-mono mt-1">
                            Código: <span class="font-bold">{{ activeProduct.code }}</span>
                        </p>
                    </div>

                    <article class="max-h-[62vh] overflow-auto space-y-5 mt-5 pr-2">
                        <!-- Tarjeta de Detalles Generales -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Información General</h2>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Marca</div>
                                <div>{{ activeProduct.brand?.name ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Familia</div>
                                <div>{{ activeProduct.product_family?.name ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Material</div>
                                <div>{{ activeProduct.material ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400 col-span-2 pt-2">Características</div>
                                <div class="col-span-2 text-gray-600 dark:text-gray-300">{{ activeProduct.caracteristics || 'Sin características adicionales.' }}</div>
                            </div>
                        </div>

                        <!-- Tarjeta de Especificaciones y Almacén -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Especificaciones y Almacén</h2>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Dimensiones</div>
                                <div>{{ formattedDimensions }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Unidad de Medida</div>
                                <div>{{ activeProduct.measure_unit ?? '--' }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Existencias del p. terminado</div>
                                <div>{{ activeStorage?.quantity ?? '0' }} {{ activeProduct.measure_unit }}</div>
                                <div class="font-semibold text-gray-500 dark:text-gray-400">Ubicación</div>
                                <div>{{ activeStorage?.location ?? '--' }}</div>
                            </div>
                        </div>

                        <!-- Tarjeta de Componentes con Stock -->
                        <div v-if="product.components?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 border-b dark:border-slate-700 pb-2 gap-2">
                                <h2 class="font-bold text-lg">
                                    Componentes
                                </h2>
                                <!-- Badge Sets armables -->
                                <div class="text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-3 py-1 rounded-full font-medium border border-indigo-100 dark:border-indigo-800 flex items-center shrink-0 w-max" title="Cantidad máxima de productos que se pueden armar basándose en el inventario actual de sus componentes.">
                                    <i class="fa-solid fa-boxes-stacked mr-2"></i> Posibles sets a armar: {{ possibleSets?.toLocaleString() ?? '0' }}
                                </div>
                            </div>

                            <ul class="space-y-2 text-sm">
                                <li
                                v-for="component in product.components"
                                :key="component.id"
                                class="flex items-center justify-between p-2 bg-gray-50 dark:bg-slate-900/50 border border-gray-100 dark:border-slate-700 rounded-lg"
                                >
                                    <div class="flex items-center flex-1">
                                        <!-- Imagen o ícono como enlace -->
                                        <Link :href="route('catalog-products.show', component.id)" title="Ver detalles del componente" class="w-12 h-12 flex-shrink-0 flex items-center justify-center bg-white dark:bg-slate-800 border dark:border-slate-700 rounded-md overflow-hidden hover:ring-2 hover:ring-primary transition-all">
                                            <img
                                            v-if="component.media?.[0]?.original_url"
                                            :src="component.media[0].original_url"
                                            @error="handleImageError"
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
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h2l2-3h6l2 3h2a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13l4-4a2 2 0 012.828 0L15 14l3-3 3 3"/>
                                            </svg>
                                        </Link>

                                        <!-- Nombre como enlace -->
                                        <Link :href="route('catalog-products.show', component.id)" class="text-gray-800 dark:text-gray-200 font-medium ml-3 mr-2 leading-tight hover:text-primary hover:underline transition-colors">
                                            {{ component.name }}
                                        </Link>
                                    </div>

                                    <!-- Requerimiento y Stock -->
                                    <div class="text-right flex flex-col justify-center min-w-[90px]">
                                        <span class="font-bold text-primary">
                                            Requerido: {{ component.pivot.quantity }}
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">
                                            Stock actual: <strong :class="getComponentRawStock(component) > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500'">{{ getComponentStock(component) }}</strong>
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-0.5">
                                            Mínimo: <strong>{{ component.min_quantity?.toLocaleString() || 0 }}</strong>
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- Tarjeta de Costos y Precios DESGLOSADOS -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Costos y Precios</h2>

                            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <template v-if="$page.props.auth.user.permissions.includes('Ver costos de produccion')">
                                    <div class="font-semibold text-gray-500 dark:text-gray-400">Costo de Componentes</div>
                                    <div class="text-gray-700 dark:text-gray-300 font-mono">{{ formatCurrency(totalComponentsCost) }}</div>

                                    <div class="font-semibold text-gray-500 dark:text-gray-400">Costo de Producción</div>
                                    <div class="text-gray-700 dark:text-gray-300 font-mono">{{ formatCurrency(totalProductionCost) }}</div>

                                    <div class="font-bold text-gray-800 dark:text-gray-200 mt-2 border-t dark:border-slate-700 pt-2">Costo Total</div>
                                    <div class="font-bold text-green-600 dark:text-green-400 mt-2 border-t dark:border-slate-700 pt-2 text-base">
                                        {{ formatCurrency(product.cost) }}
                                    </div>
                                </template>

                                <div class="font-semibold text-gray-500 dark:text-gray-400 mt-3 col-span-2 border-t dark:border-slate-700 pt-3">Precio Base del producto</div>
                                <div class="flex items-center gap-3 col-span-2">
                                    <div>
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(product.base_price) }} {{ product.currency }}</span>
                                        <p class="text-xs text-gray-500 mt-1">Actualizado: <span class="font-medium text-gray-700 dark:text-gray-300">{{ formatDate(product.base_price_updated_at) }}</span></p>
                                    </div>
                                    <button 
                                        class="flex items-center justify-center rounded-full size-8 font-medium 
                                            bg-white text-gray-700 shadow-sm border border-gray-200 
                                            hover:bg-gray-100 hover:shadow-md transition-all duration-300 
                                            dark:bg-slate-700 dark:text-gray-200 dark:border-slate-600 
                                            dark:hover:bg-slate-600"
                                        @click="openEditPriceDialog"
                                        title="Editar precio base"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.651-1.651a1.875 1.875 0 112.652 2.652L10.582 16.071a4.5 4.5 0 01-1.897 1.13l-3.314.943a.75.75 0 01-.926-.926l.943-3.314a4.5 4.5 0 011.13-1.897l10.344-10.344z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Precios por Cliente -->
                            <div v-if="activeProduct.price_history?.length" class="mt-4 pt-4 border-t dark:border-slate-700">
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
                                                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">${{ pricing.price }} {{ pricing.currency }}</td>
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

                        <!-- Tarjeta de Procesos de Producción -->
                        <div v-if="product.production_costs?.length" class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-4 border-b dark:border-slate-700 pb-2">Procesos de Producción</h2>
                            <ul class="space-y-1 text-sm divide-y divide-gray-100 dark:divide-slate-700/50">
                                <li v-for="process in product.production_costs" :key="process.id" class="flex justify-between items-center py-2">
                                    <span class="text-gray-700 dark:text-gray-300 font-medium"><i class="fa-solid fa-check text-green-500 mr-2"></i> {{ process.name }}</span>
                                    <span class="text-gray-600 dark:text-gray-400 font-mono">{{ formatCurrency(process.pivot.cost) }}</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Tarjeta de movimientos de stock -->
                        <div class="bg-white dark:bg-slate-800/50 p-5 rounded-xl shadow-lg">
                            <h2 class="font-bold text-lg mb-2 text-gray-800 dark:text-gray-200 border-b dark:border-slate-700 pb-2">
                                Historial de Movimientos
                            </h2>
                            <div v-if="activeStorage?.stock_movements?.length" class="space-y-1 max-h-56 overflow-y-auto pr-2">
                                <ul class="divide-y divide-gray-200 dark:divide-slate-700">

                                    <li v-for="movement in activeStorage.stock_movements" :key="movement.id" class="py-3 flex items-center justify-between space-x-3">
                                        
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
                                                {{ movement.type === 'Entrada' ? '+' : '-' }} {{ movement.quantity_change }} {{ activeProduct.measure_unit }}
                                            </p>
                                            <p v-if="movement.notes" class="text-xs text-gray-500 dark:text-gray-400 italic truncate max-w-xs pr-1" :title="movement.notes">
                                                {{ movement.notes }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div v-else class="text-center py-8">
                                <p class="text-gray-500 dark:text-gray-400">No hay movimientos registrados.</p>
                            </div>
                        </div>
                    </article>
                </section>
            </main>
            
            <!-- Loading de redirección visual en caso de entrar a la liga del hijo -->
            <div v-else class="h-[60vh] flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                <LoadingIsoLogo />
                <p class="mt-4 font-medium animate-pulse">Redirigiendo al producto original (padre)...</p>
            </div>
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
                    <SecondaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</SecondaryButton>
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
                    Ingresa la cantidad y notas para el movimiento de "{{ activeProduct.name }}".
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
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
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
        LoadingIsoLogo,
        SecondaryButton,
        ConfirmationModal,
    },
    props: {
        product: Object,
    },
    data() {
        return {
            selectedCatalogProduct: this.product.id,
            showConfirmModal: false,
            
            // Lógica de imágenes
            currentImage: 0,
            selectedVariant: null,

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
            catalog_products: [],
            loadingProductList: false,
        };
    },
    computed: {
        // PRODUCTO ACTIVO (Padre o Variante seleccionada)
        activeProduct() {
            return this.selectedVariant || this.product;
        },

        // ALMACEN ACTIVO
        activeStorage() {
            return this.activeProduct.storages?.[0] || null;
        },

        // IMAGEN PRINCIPAL (Sabe si mostrar la imagen base o la variante)
        mainDisplayedImage() {
            if (this.selectedVariant && this.selectedVariant.media?.length) {
                return this.selectedVariant.media[0].original_url;
            } else if (this.product.media?.length) {
                return this.product.media[this.currentImage].original_url;
            }
            return null;
        },

        // SETS MÁXIMOS ARMABLES
        possibleSets() {
            if (!this.product.components || this.product.components.length === 0) return 0;
            
            let sets = [];
            this.product.components.forEach(comp => {
                const required = parseFloat(comp.pivot.quantity) || 0;
                const stock = this.getComponentRawStock(comp);
                if (required > 0) {
                    sets.push(Math.floor(stock / required));
                }
            });
            
            return sets.length ? Math.min(...sets) : 0;
        },

        // COSTOS DESGLOSADOS
        totalComponentsCost() {
            if (!this.product.components) return 0;
            return this.product.components.reduce((sum, comp) => {
                const qty = parseFloat(comp.pivot.quantity) || 0;
                const cost = parseFloat(comp.pivot.cost) || 0;
                return sum + (qty * cost);
            }, 0);
        },
        totalProductionCost() {
            if (!this.product.production_costs) return 0;
            return this.product.production_costs.reduce((sum, proc) => {
                return sum + (parseFloat(proc.pivot.cost) || 0);
            }, 0);
        },

        groupedPrices() {
            if (!this.activeProduct.price_history) return {};

            // ordenar del más nuevo al más viejo
            const sorted = [...this.activeProduct.price_history].sort((a, b) => {
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

            if (this.activeProduct.diameter) {
                return `Ø ${safe(this.activeProduct.diameter)} mm (Diámetro) x ${safe(this.activeProduct.width)} mm (Grosor)`;
            }

            return `${safe(this.activeProduct.large)} mm (L) x ${safe(this.activeProduct.height)} mm (A) x ${safe(this.activeProduct.width)} mm (G)`;
        }
    },
    methods: {
        resetToMainImage(index) {
            this.selectedVariant = null;
            this.currentImage = index;
        },

        getComponentRawStock(component) {
            if (component.storages && component.storages.length > 0) {
                return parseFloat(component.storages[0].quantity) || 0;
            }
            return 0;
        },

        getComponentStock(component) {
            return this.getComponentRawStock(component).toLocaleString('es-MX');
        },

        handleImageError(event) {
            const img = event.target;
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
        },
        formatDateTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const options = {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            };
            return date.toLocaleDateString('es-MX', options);
        },
        formatDate(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const options = {
                year: 'numeric', month: 'short', day: 'numeric',
            };
            return date.toLocaleDateString('es-MX', options);
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return parseFloat(value).toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },

        openEditPriceDialog() {
            this.newBasePrice = this.product.base_price;
            this.editDialogVisible = true;
        },

        async updateBasePrice() {
            try {
                this.loadingUpdate = true;
                const response = await axios.put(
                route("products.simple-update", this.product.id), 
                    { base_price: this.newBasePrice }
                );

                router.reload({ only: ['product'] });
                ElMessage.success("Precio actualizado correctamente");
                this.editDialogVisible = false;
            } catch (error) {
                console.error(error);
                ElMessage.error("Ocurrió un error al actualizar el precio");
            } finally {
                this.loadingUpdate = false;
            }
        },
        async fetchProductsList() {
            try {
                this.loadingProductList = true;

                // Enviamos base_only = true para traer sólo los productos padre al selector superior
                const response = await axios.get(route("products.fetch-products-list", { type: 'Todos', base_only: true }));

                if ( response.status === 200 ) {
                    this.catalog_products = response.data;
                }

            } catch (error) {
                console.error(error);
                ElMessage.error("Error al cargar lista de productos");
            } finally {
                this.loadingProductList = false;
            }
        },
        openStockModal(type) {
            this.stockMovementForm.reset();
            this.stockMovementForm.type = type;
            this.showStockModal = true;
        },
        submitStockMovement() {
            this.stockMovementForm.post(this.route('products.stock-movement', this.activeProduct.id), {
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

        confirmCloseSpecialPrice(historyId) {
            this.priceHistoryToClose = historyId;
            this.showClosePriceConfirmModal = true;
        },

        async closeSpecialPrice() {
            if (!this.priceHistoryToClose) return;
            try {
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

        async ObsoletProduct() {
            try {
                const response = await axios.get(route('catalog-products.obsolet', this.activeProduct.id));
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
        async deleteItem() {
            try {
                const response = await axios.delete(route('catalog-products.destroy', this.activeProduct.id));
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
    mounted() {
        // 1. En caso de que el backend no redirigiera, enviamos al padre pasando el ID de la variante en la URL.
        if (this.product.parent_id) {
            this.$inertia.get(
                route('catalog-products.show', this.product.parent_id),
                { variant_id: this.product.id },
                { replace: true }
            );
            return; // Detenemos la ejecución aquí
        }

        // 2. Si es el padre, validamos si en la URL venía el parámetro variant_id
        const urlParams = new URLSearchParams(window.location.search);
        const variantId = urlParams.get('variant_id');

        if (variantId && this.product.variants?.length) {
            // Buscamos la variante y la asignamos para que se vea automáticamente seleccionada
            const variant = this.product.variants.find(v => v.id == variantId);
            if (variant) {
                this.selectedVariant = variant;
            }
        }

        this.fetchProductsList();
    },
    watch: {
        'product.id'(newId) {
            this.selectedCatalogProduct = newId;
            this.currentImage = 0;
            this.selectedVariant = null; // Reiniciamos estado visual al cambiar
        }
    }
};
</script>

<style scoped>
/* Animaciones suaves */
.animate-fade-in {
  animation: fadeIn 0.4s ease-in-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>