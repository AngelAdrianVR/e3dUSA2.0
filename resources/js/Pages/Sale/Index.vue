<template>
    <AppLayout title="Órdenes de Venta">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Órdenes de Venta
        </h2>

        <div class="py-7">
            <div class="max-w-[100rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <p class="text-sm text-red-500 p-2 m-1 rounded-md bg-red-200">Por ordenes de dirección Sólo se puede crear un OV desde su respectiva cotización</p>
                        <!-- Botón para crear nueva venta -->
                        <!-- <Link v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta')"
                            :href="route('sales.create')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nueva Orden
                            </SecondaryButton>
                        </Link> -->

                        <div class="flex items-center space-x-2">
                             <!-- Botón para eliminar seleccionados -->
                            <el-popconfirm v-if="$page.props.auth.user.permissions.includes('Eliminar ordenes de venta')"
                                confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar las ventas seleccionadas?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar selección
                                    </el-button>
                                </template>
                            </el-popconfirm>
                            
                            <!-- Switch para ver todas las ventas / mis ventas -->
                            <div
                                v-if="$page.props.auth.user.permissions.includes('Ver todas las ventas')"
                                class="flex items-center px-3 py-1.5 bg-gray-100 dark:bg-slate-800 rounded-full shadow-sm border border-gray-200 dark:border-slate-700"
                            >
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 mr-2">Mías</span>
                                <el-switch
                                    v-model="showAllSales"
                                    @change="toggleView"
                                    style="--el-switch-on-color: #10b981; --el-switch-off-color: #3b82f6;"
                                />
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 ml-2">Todas</span>
                            </div>

                            <!-- Dropdown de filtros de pendientes -->
                            <el-dropdown trigger="click" @command="handleFilterCommand">
                                <button
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full shadow-sm border text-sm font-medium transition-all duration-200"
                                    :class="activeFilterLabel ? 'bg-red-100 border-red-400 text-red-700 dark:bg-red-900/50 dark:border-red-500 dark:text-red-300' : 'bg-gray-100 border-gray-200 text-gray-600 dark:bg-slate-800 dark:border-slate-700 dark:text-gray-300 hover:bg-red-50 dark:hover:bg-red-900/30'"
                                >
                                    <!-- Indicador de filtro activo -->
                                    <span v-if="activeFilterLabel" class="relative flex h-2 w-2 shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                    </span>
                                    <i v-else class="fa-solid fa-bell text-xs"></i>
                                    <span>{{ activeFilterLabel || 'Pendientes' }}</span>
                                    <span v-if="pendingCount > 0 && !isPendingFilter"
                                        class="bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[16px] h-[16px] flex items-center justify-center px-1">
                                        {{ pendingCount }}
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="pending_authorization">
                                            <i :class="isPendingAuthFilter ? 'fa-solid fa-check text-red-500' : 'fa-regular fa-circle text-gray-300'" class="mr-1.5 w-3.5"></i>
                                            Pendiente por autorizar
                                        </el-dropdown-item>
                                        <el-dropdown-item command="pending">
                                            <i :class="isPendingFilter ? 'fa-solid fa-check text-red-500' : 'fa-regular fa-circle text-gray-300'" class="mr-1.5 w-3.5"></i>
                                            Pendiente de seguimiento
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                        
                        <!-- Input de búsqueda -->
                        <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" />
                    </div>

                    <!-- Overlay de carga -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <!-- Tabla de Ventas -->
                        <el-table 
                            max-height="550" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @selection-change="handleSelectionChange" 
                            @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column type="selection" width="30" />
                            <el-table-column prop="id" label="Folio" width="140"> <!-- Aumenté ligeramente el width para el icono extra -->
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <!-- Icono de Tipo (Venta/Stock) -->
                                        <el-tooltip :content="scope.row.type === 'venta' ? 'Orden de Venta' : 'Orden de Stock'" placement="top">
                                            <i :class="scope.row.type === 'venta' ? 'fa-solid fa-cart-shopping text-purple-500' : 'fa-solid fa-box text-rose-500'"></i>
                                        </el-tooltip>
                                        
                                        <!-- Folio Text -->
                                        <span v-if="scope.row.type === 'venta'" class="font-semibold">{{ 'OV-' + scope.row.id.toString().padStart(4, '0') }}</span>
                                        <span v-else class="font-semibold">{{ 'OS-' + scope.row.id.toString().padStart(4, '0') }}</span>

                                        <!-- NUEVO: Indicador de Cambio/Garantía -->
                                        <div v-if="scope.row.product_exchanges?.length" class="ml-1">
                                            <el-tooltip placement="right" effect="light">
                                                <template #content>
                                                    <div class="text-xs p-1 max-w-xs">
                                                        <p class="font-bold text-amber-600 mb-2 border-b border-amber-200 pb-1 flex items-center">
                                                            <i class="fa-solid fa-circle-exclamation mr-1"></i>
                                                            Cambio(s) registrado(s)
                                                        </p>
                                                        <ul class="space-y-2">
                                                            <li v-for="exchange in scope.row.product_exchanges" :key="exchange.id" class="bg-slate-50 p-1.5 rounded border border-slate-100">
                                                                <div class="flex items-center gap-1 text-red-600 mb-0.5">
                                                                    <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
                                                                    <span class="font-semibold">Devolvió:</span> 
                                                                    <span class="text-gray-600 truncate">{{ exchange.returned_product?.name }}</span>
                                                                </div>
                                                                <div class="flex items-center gap-1 text-green-600">
                                                                    <i class="fa-solid fa-arrow-right-from-bracket text-[10px]"></i>
                                                                    <span class="font-semibold">Llevó:</span> 
                                                                    <span class="text-gray-600 truncate">{{ exchange.new_product?.name }}</span>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </template>
                                                <!-- El ícono visible en la tabla -->
                                                <div class="cursor-help bg-amber-100 hover:bg-amber-200 text-amber-600 rounded-full size-5 flex items-center justify-center transition-colors">
                                                    <i class="fa-solid fa-rotate text-[10px]"></i>
                                                </div>
                                            </el-tooltip>
                                        </div>

                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Cliente" width="140">
                                <template #default="scope">
                                    {{ scope.row.branch?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <!-- COLUMNA DE PRODUCTOS -->
                            <el-table-column label="Productos" width="130">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.sale_products?.length" placement="top">
                                        <template #content>
                                            <ul class="list-disc list-inside text-xs">
                                                <li v-for="item in scope.row.sale_products" :key="item.id">
                                                    ({{ item.quantity }}) {{ item.product.name }}
                                                </li>
                                            </ul>
                                        </template>
                                        <span class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                                            {{ scope.row.sale_products.length }} producto(s)
                                        </span>
                                    </el-tooltip>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </template>
                            </el-table-column>
                            <!-- COLUMNA DE UTILIDAD -->
                            <el-table-column v-if="$page.props.auth.user.permissions.includes('Ver utilidad ventas')" label="Utilidad" width="100">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.type === 'venta'" placement="right" effect="dark">
                                        <template #content>
                                            <div class="text-xs">
                                                <p class="text-white dark:text-gray-700 font-bold mb-2">No se toma en cuenta flete</p>
                                                <p class="text-blue-300 dark:text-blue-700">Venta: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.total_sale) }} {{ scope.row.currency }}</strong></p>
                                                <p class="text-amber-400 dark:text-amber-700">Costo: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.total_cost) }} {{ scope.row.currency }}</strong></p>
                                                <p class="text-green-400 dark:text-green-700">Utilidad: <strong class="text-white dark:text-gray-500">${{ formatNumber(scope.row.utility_data.profit) }} {{ scope.row.currency }}</strong></p>
                                            </div>
                                        </template>
                                        <div class="flex flex-col justify-center items-center space-x-2" :class="getProfitabilityClass(scope.row.utility_data.percentage)">
                                            <i class="fa-solid fa-flag"></i>
                                            <div class="flex">
                                                <i v-for="n in getProfitabilityStars(scope.row.utility_data.percentage)" :key="`filled-${n}`" class="fa-solid fa-star text-xs text-yellow-500"></i>
                                                <i v-for="n in (3 - getProfitabilityStars(scope.row.utility_data.percentage))" :key="`unfilled-${n}`" class="fa-regular fa-star text-xs"></i>
                                            </div>
                                                <span class="font-semibold text-xs">{{ scope.row.utility_data.percentage.toFixed(1).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}%</span>
                                        </div>
                                    </el-tooltip>
                                    <p v-else class="text-center">N/A</p>
                                </template>
                            </el-table-column>
                            <el-table-column label="Creado por" width="120">
                                <template #default="scope">
                                    {{ scope.row.user?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Creado el" width="140">
                                <template #default="scope">
                                    {{ formatDate(scope.row.created_at) ?? 'N/A' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="status" label="Estatus" width="145">
                                <template #default="scope">
                                    <div class="flex items-center space-x-1">
                                        <el-tag :type="getStatusTagType(scope.row.status)">
                                            {{ scope.row.status }}
                                        </el-tag>
                                        <!-- ALARMA: Orden de venta autorizada -->
                                        <el-tooltip v-if="scope.row.status === 'Autorizada'"
                                            content="¡Esta orden ha sido autorizada! Revisa los detalles." placement="top">
                                            <span class="relative flex items-center justify-center cursor-help">
                                                <i class="fa-solid fa-bell text-red-500 text-xs animate-swing"></i>
                                            </span>
                                        </el-tooltip>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Autorizado" width="100" align="center">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.authorized_at" placement="top">
                                        <template #content>
                                            Autorizado por: {{ scope.row.authorized_user_name }} <br>
                                            Fecha: {{ formatDate(scope.row.authorized_at) }}
                                        </template>
                                        <i class="fa-solid fa-check-double text-green-500 text-lg"></i>
                                    </el-tooltip>
                                    <p v-else>No autorizada</p>
                                </template>
                            </el-table-column>
                            <el-table-column label="Monto Total" width="110">
                                 <template #default="scope">
                                    {{ formatCurrency(scope.row.total_amount) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="Cotización" width="100">
                                 <template #default="scope">
                                    <a v-if="scope.row.quote_root_id" @click.stop
                                        :href="route('quotes.show', scope.row.quote_active_id)" target="_blank"
                                        class="text-blue-500 hover:underline">
                                        COT-{{ String(scope.row.quote_root_id).padStart(4, '0') }}
                                    </a>
                                    <span v-else class="text-gray-400">N/A</span>
                                </template>
                            </el-table-column>
<el-table-column label="Factura">
                                 <template #default="scope">
                                    <div v-if="scope.row.stamped_invoice_folio || scope.row.pre_invoice_folio"
                                        class="flex flex-col items-start gap-1">
                                        <!-- Folios de factura timbrada (final) -->
                                        <el-tooltip v-if="scope.row.stamped_invoice_folio" placement="top">
                                            <template #content>
                                                <div class="text-xs">
                                                    <p class="font-bold mb-1">Factura(s) timbrada(s)</p>
                                                    <p v-for="folio in splitFolios(scope.row.stamped_invoice_folio)" :key="folio">{{ folio }}</p>
                                                </div>
                                            </template>
                                            <span class="inline-flex items-center gap-1 max-w-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 text-[11px] font-medium px-2 py-0.5 rounded cursor-help">
                                                <i class="fa-solid fa-file-invoice text-[9px]"></i>
                                                <span class="truncate">{{ scope.row.stamped_invoice_folio }}</span>
                                            </span>
                                        </el-tooltip>
                                        <!-- Folios de pre-factura (solo si no hay timbrada) -->
                                        <el-tooltip v-else-if="scope.row.pre_invoice_folio" placement="top">
                                            <template #content>
                                                <div class="text-xs">
                                                    <p class="font-bold mb-1">Folio(s) pre-factura</p>
                                                    <p v-for="folio in splitFolios(scope.row.pre_invoice_folio)" :key="folio">{{ folio }}</p>
                                                </div>
                                            </template>
                                            <span class="inline-flex items-center gap-1 max-w-full bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300 text-[11px] font-medium px-2 py-0.5 rounded cursor-help">
                                                <i class="fa-regular fa-file-lines text-[9px]"></i>
                                                <span class="truncate">{{ scope.row.pre_invoice_folio }}</span>
                                            </span>
                                        </el-tooltip>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs">N/A</span>
                                </template>
                            </el-table-column>

                            <!-- COLUMNA DE LOGÍSTICA (guía, paquetería y fecha promesa en un tooltip) -->
                            <el-table-column label="Logística" width="110" align="center">
                                <template #default="scope">
                                    <el-popover
                                        v-if="scope.row.shipments?.length"
                                        placement="right"
                                        :width="320"
                                        trigger="hover"
                                        popper-class="logistics-popper"
                                    >
                                        <template #reference>
                                            <div @click.stop
                                                class="inline-flex items-center gap-1.5 cursor-help text-xs font-medium px-2 py-0.5 rounded border transition-colors"
                                                :class="hasLogisticsInfo(scope.row)
                                                    ? 'bg-green-50 hover:bg-green-100 dark:bg-green-900/30 dark:hover:bg-green-900/50 text-green-700 dark:text-green-300 border-green-200 dark:border-green-800'
                                                    : 'bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'">
                                                <i class="fa-solid fa-truck-fast text-[11px]"></i>
                                                <span>{{ scope.row.shipments.length }}</span>
                                            </div>
                                        </template>

                                        <div class="space-y-3">
                                            <p class="text-xs font-bold text-gray-700 dark:text-gray-200 border-b dark:border-gray-600 pb-1 flex items-center">
                                                <i class="fa-solid fa-truck-fast mr-1.5"></i> Información de logística
                                            </p>
                                            <div v-for="(shipment, index) in scope.row.shipments" :key="shipment.id"
                                                class="bg-gray-50 dark:bg-slate-700/50 rounded-md p-2 border border-gray-200 dark:border-gray-600">
                                                <div class="flex justify-between items-center mb-1.5">
                                                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase">Envío #{{ index + 1 }}</span>
                                                    <button @click.stop="openGuideModal(shipment)"
                                                        class="text-blue-500 hover:text-blue-700 hover:bg-blue-100 dark:hover:bg-blue-900 rounded p-1 transition-colors"
                                                        title="Editar guía y paquetería">
                                                        <i class="fa-solid fa-pencil text-[10px]"></i>
                                                    </button>
                                                </div>
                                                <ul class="space-y-1 text-xs">
                                                    <li class="flex justify-between gap-2">
                                                        <span class="text-gray-500 dark:text-gray-400 font-semibold">Paquetería:</span>
                                                        <span class="text-gray-800 dark:text-gray-200 text-right">{{ shipment.shipping_company || '-' }}</span>
                                                    </li>
                                                    <li class="flex justify-between gap-2">
                                                        <span class="text-gray-500 dark:text-gray-400 font-semibold">Guía:</span>
                                                        <span class="text-gray-800 dark:text-gray-200 font-mono text-right">{{ shipment.tracking_guide || '-' }}</span>
                                                    </li>
                                                    <li class="flex justify-between gap-2">
                                                        <span class="text-gray-500 dark:text-gray-400 font-semibold">Fecha promesa:</span>
                                                        <span class="text-gray-800 dark:text-gray-200 text-right">{{ formatShortDate(shipment.promise_date || scope.row.promise_date) }}</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </el-popover>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </template>
                            </el-table-column>

                            <!-- Menú de acciones por fila (fijo a la derecha) -->
                            <el-table-column align="center" width="70" fixed="right">
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
                                                    v-if="$page.props.auth.user.permissions.includes('Editar ordenes de venta')"
                                                    :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>Editar
                                                </el-dropdown-item>
                                                <!-- OPCION CLONAR -->
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Crear ordenes de venta')"
                                                    :command="'clone-' + scope.row.id">
                                                    <i class="fa-solid fa-copy mr-2 text-xs"></i>Clonar
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="$page.props.auth.user.permissions.includes('Autorizar ordenes de venta') && !scope.row.authorized_at"
                                                    :command="`authorize-${scope.row.id}`">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    Autorizar
                                                </el-dropdown-item>
                                                <el-dropdown-item :command="'print-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                                    </svg>
                                                    Imprimir</el-dropdown-item>
                                                <!-- <el-dropdown-item v-if="$page.props.auth.user.permissions.includes('Crear facturas') && !scope.row.invoice_id" :command="'createInvoice-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                    Crear factura
                                                </el-dropdown-item> -->
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="sales.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="sales.current_page"
                            :page-size="sales.per_page" :total="sales.total"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: editar información de rastreo (logística) -->
        <DialogModal :show="showGuideModal" @close="showGuideModal = false">
            <template #title>
                Información de Rastreo
            </template>
            <template #content>
                <form @submit.prevent="submitGuide" class="space-y-7 min-h-[300px] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha promesa de embarque</label>
                        <el-date-picker
                            v-model="guideForm.promise_date"
                            type="date"
                            :teleported="false"
                            placeholder="Selecciona una fecha"
                            format="YYYY/MM/DD"
                            value-format="YYYY-MM-DD"
                            class="!w-full"
                        />
                        <p v-if="guideForm.errors.promise_date" class="text-red-500 text-[11px] mt-1">{{ guideForm.errors.promise_date }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Paquetería</label>
                        <input v-model="guideForm.shipping_company" type="text" class="w-full rounded-md border-gray-300 dark:bg-slate-800 text-sm" placeholder="Ej. DHL, FedEx, PaqueteExpress..." />
                        <p v-if="guideForm.errors.shipping_company" class="text-red-500 text-[11px] mt-1">{{ guideForm.errors.shipping_company }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Guía</label>
                        <input v-model="guideForm.tracking_guide" type="text" class="w-full rounded-md border-gray-300 dark:bg-slate-800 text-sm" placeholder="Ingresa el número de rastreo..." />
                        <p v-if="guideForm.errors.tracking_guide" class="text-red-500 text-[11px] mt-1">{{ guideForm.errors.tracking_guide }}</p>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showGuideModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="submitGuide" :disabled="guideForm.processing">
                        Guardar Información
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- Modal: la orden autorizada no se puede editar -->
        <AuthorizedOrderLockedModal
            :show="showEditBlockedModal"
            :order-type="blockedOrderType"
            @close="showEditBlockedModal = false"
        />
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import AuthorizedOrderLockedModal from '@/Components/MyComponents/AuthorizedOrderLockedModal.vue';
import DialogModal from "@/Components/DialogModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Link, router, useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        return {
            loading: false,
            search: '',
            selectedItems: [],
            showEditBlockedModal: false,
            blockedOrderType: 'venta',
            showGuideModal: false,
            guideForm: useForm({
                shipment_id: null,
                shipping_company: '',
                tracking_guide: '',
                promise_date: null,
            }),
            tableData: this.sales.data,
            showAllSales: this.filters.view !== 'mias',
            isPendingFilter: this.filters.filter === 'pending',
            isPendingAuthFilter: this.filters.filter === 'pending_authorization',
            SearchProps: ['ID', 'Cliente', 'Creador', 'Estatus'],
        };
    },
    components: {
        Link,
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
        AuthorizedOrderLockedModal,
        DialogModal,
        CancelButton,
        PrimaryButton,
    },
    props: {
        sales: Object,
        filters: Object,
    },
    computed: {
        pendingCount() {
            return this.$page.props.pending_sale_notifications ?? 0;
        },
        activeFilterLabel() {
            if (this.isPendingAuthFilter) return 'Pendiente por autorizar';
            if (this.isPendingFilter) return 'Pendiente de seguimiento';
            return null;
        },
    },
    methods: {
        async handleSearch() {
            this.loading = true;
            try {
                if (!this.search) {
                    this.tableData = this.sales.data;
                    this.$inertia.get(this.route('sales.index'), {}, {
                        preserveState: true,
                        replace: true,
                    });
                    return;
                }
                const response = await axios.post(route('sales.get-matches', { query: this.search }));
                this.tableData = response.data.items;
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
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        handleRowClick(row) {
            router.get(route('sales.show', row.id));
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            
            if (action === 'authorize') {
                this.authorize(id);
            } else if ( action === 'print' ) {
                window.open(route('sales.print', id), '_blank');
            } else if ( action === 'createInvoice' ) {
                this.$inertia.visit(route('invoices.create', { sale_id: id }))
            } else if ( action === 'clone' ) {
                this.clone(id);
            }
            else if ( action === 'edit' ) {
                const sale = this.tableData.find(item => String(item.id) === String(id));
                // Una orden autorizada ya no puede editarse: se muestra retroalimentación al usuario.
                if (sale?.authorized_at) {
                    this.blockedOrderType = sale.type ?? 'venta';
                    this.showEditBlockedModal = true;
                    return;
                }
                router.get(route('sales.edit', id));
            }
            else {
                router.get(route(`sales.${action}`, id));
            }
        },
        // --- Método para clonar ---
        clone(sale_id) {
            ElMessageBox.confirm(
                '¿Estás seguro de clonar esta orden? Se creará una copia exacta con estatus "Pendiente" y se apartará el stock disponible de los productos.',
                'Clonar Orden',
                {
                    confirmButtonText: 'Sí, clonar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            )
            .then(() => {
                this.$inertia.post(route('sales.clone', sale_id), {}, {
                    onSuccess: () => {
                        ElMessage.success('Orden clonada y creada exitosamente.');
                    },
                    onError: () => {
                        ElMessage.error('No se pudo clonar la orden.');
                    }
                });
            })
            .catch(() => {
                // Acción cancelada
            });
        },
        // --- Método para autorizar ---
        async authorize(sale_id) {
            try {
                const response = await axios.put(route('sales.authorize', sale_id));
                if (response.status === 200) {
                    const index = this.tableData.findIndex(item => item.id == sale_id);
                    if (index !== -1) {
                        this.tableData[index].authorized_at = response.data.item.authorized_at;
                        this.tableData[index].authorized_user_name = response.data.item.authorized_user_name;
                        this.tableData[index].status = response.data.item.status;
                    }
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar la venta');
                console.error(err);
            }
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            router.post(route('sales.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Ventas eliminadas correctamente');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar las ventas');
                }
            });
        },
        handlePageChange(page) {
            router.get(route('sales.index', this.buildParams({ page })), {
                preserveState: true,
                replace: true,
            });
        },
        buildParams(extra = {}) {
            const params = { ...extra };
            if (this.showAllSales) {
                params.view = 'all';
            } else {
                params.view = 'mias';
            }
            if (this.isPendingAuthFilter) {
                params.filter = 'pending_authorization';
            } else if (this.isPendingFilter) {
                params.filter = 'pending';
            }
            return params;
        },
        toggleView() {
            router.get(route('sales.index', this.buildParams()), {
                preserveState: true,
                replace: true,
                onStart: () => this.loading = true,
                onFinish: () => this.loading = false,
            });
        },
        handleFilterCommand(command) {
            if (command === 'pending_authorization') {
                this.isPendingAuthFilter = !this.isPendingAuthFilter;
                this.isPendingFilter = false;
            } else if (command === 'pending') {
                this.isPendingFilter = !this.isPendingFilter;
                this.isPendingAuthFilter = false;
            }
            router.get(route('sales.index', this.buildParams()), {
                preserveState: true,
                replace: true,
                onStart: () => this.loading = true,
                onFinish: () => this.loading = false,
            });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            const num = Number(value);
            return num.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
        // --- NUEVO: Helpers para la columna de utilidad ---
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        // Convierte la cadena de folios separada por comas en un arreglo
        splitFolios(value) {
            if (!value) return [];
            return String(value).split(',').map(folio => folio.trim()).filter(Boolean);
        },
        // --- Helpers para la columna de logística ---
        formatShortDate(dateString) {
            if (!dateString) return 'Sin fecha';
            // Parseamos como fecha local para evitar el desfase de zona horaria
            const [datePart] = String(dateString).split('T');
            const [y, m, d] = datePart.split('-').map(Number);
            if (!y || !m || !d) return dateString;
            return new Date(y, m - 1, d).toLocaleDateString('es-MX', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },
        openGuideModal(shipment) {
            this.guideForm.shipment_id = shipment.id;
            this.guideForm.shipping_company = shipment.shipping_company ?? '';
            this.guideForm.tracking_guide = shipment.tracking_guide ?? '';
            this.guideForm.promise_date = shipment.promise_date ?? null;
            this.showGuideModal = true;
        },
        // Indica si TODOS los envíos ya tienen paquetería y número de guía capturados
        hasLogisticsInfo(row) {
            const shipments = row?.shipments ?? [];
            if (!shipments.length) return false;
            return shipments.every(shipment => shipment.shipping_company && shipment.tracking_guide);
        },
        submitGuide() {
            this.guideForm.put(route('shipments.update-tracking', this.guideForm.shipment_id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.showGuideModal = false;
                    ElMessage.success('Información de rastreo actualizada correctamente');
                },
                onError: () => {
                    ElMessage.error('Error al actualizar la información de rastreo.');
                },
            });
        },
        getProfitabilityClass(margin) {
            if (margin < 20) return 'text-red-600';
            if (margin >= 20 && margin < 100) return 'text-amber-600';
            return 'text-green-600';
        },
        getProfitabilityStars(margin) {
            if (margin < 20) return 1;
            if (margin >= 20 && margin < 100) return 2;
            return 3;
        },
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'info',
                'Autorizada': 'primary',
                'En Proceso': 'warning',
                'En Producción': 'primary',
                'Stock Terminado': 'success',
                'Preparando Envío': 'success',
                'Enviada': 'success',
            };
            return statusMap[status] || '';
        }
    },
    watch: {
        'sales.data': {
            handler(newData) {
                this.tableData = newData;
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
    background-color: #1f2937 !important;
    color: #d1d5db !important;
}
.dark .el-pager li.is-active {
    color: #ffffff !important;
    background-color: #3b82f6 !important;
}

/* Animación de campana para notificaciones */
@keyframes swing {
    0% { transform: rotate(0deg); }
    10% { transform: rotate(15deg); }
    20% { transform: rotate(-15deg); }
    30% { transform: rotate(10deg); }
    40% { transform: rotate(-10deg); }
    50% { transform: rotate(5deg); }
    60% { transform: rotate(-5deg); }
    70% { transform: rotate(0deg); }
    100% { transform: rotate(0deg); }
}
.animate-swing {
    animation: swing 2s ease-in-out infinite;
    transform-origin: top center;
}
</style>