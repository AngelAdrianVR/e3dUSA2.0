<template>
    <AppLayout :title="`Requisición ${requisition.folio}`">
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Encabezado -->
                <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-6">
                    <div class="flex flex-col lg:flex-row justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <Back route="requisitions.index" class="shrink-0" />
                            <div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 font-mono">
                                    {{ requisition.folio }}
                                </h2>
                                <el-tag :type="requisition.type === 'Importación' ? 'primary' : 'success'">
                                    <i :class="requisition.type === 'Importación' ? 'fa-solid fa-ship mr-1' : 'fa-solid fa-store mr-1'"></i>
                                    {{ requisition.type }}
                                </el-tag>
                                <el-tag type="info">{{ requisition.origin }}</el-tag>
                                <el-tag :type="getStatusTagType(requisition.status)">{{ requisition.status }}</el-tag>
                                <el-tag v-if="requisition.priority" :type="getPriorityTagType(requisition.priority)">
                                    Prioridad {{ requisition.priority }}
                                </el-tag>
                                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="inline-block w-3 h-3 rounded-full"
                                        :style="{ backgroundColor: arrivalColors[requisition.arrival_status] ?? '#9CA3AF' }"></span>
                                    Arribo: {{ requisition.arrival_status }}
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                Levantada por <strong>{{ requisition.user?.name }}</strong>
                                el {{ formatDateTime(requisition.created_at) }}
                            </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-start gap-2">
                            <!-- Grupo 1: acciones del flujo -->
                            <el-dropdown v-if="flowActions.length" trigger="click" @command="handleFlowCommand">
                                <PrimaryButton>
                                    <i class="fa-solid fa-bolt mr-2"></i> Acciones
                                    <i class="fa-solid fa-chevron-down ml-2 text-xs"></i>
                                </PrimaryButton>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="action in flowActions" :key="action.key" :command="action.key">
                                            <i :class="[action.icon, 'mr-2']"></i> {{ action.label }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>

                            <!-- Grupo 2: editar / eliminar (acciones independientes) -->
                            <SecondaryButton v-if="can.edit" @click="handleManageCommand('edit')">
                                <i class="fa-solid fa-pen-to-square mr-2"></i> Editar
                            </SecondaryButton>
                            <DangerButton v-if="can.delete" @click="handleManageCommand('delete')">
                                <i class="fa-solid fa-trash-can mr-2"></i> Eliminar
                            </DangerButton>
                        </div>
                    </div>
                </div>

                <!-- Rechazo: se re-trabaja sobre el mismo folio -->
                <div v-if="requisition.status === 'Rechazada'"
                    class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-800 rounded-lg p-4">
                    <p class="font-semibold text-red-700 dark:text-red-300">
                        <i class="fa-solid fa-circle-xmark mr-2"></i> Requisición rechazada
                    </p>
                    <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                        Motivo: {{ requisition.rejection_reason ?? 'Sin motivo registrado.' }}
                    </p>
                    <p class="text-xs text-red-500 dark:text-red-400 mt-1">
                        Rechazada por {{ requisition.rejecter?.name ?? 'N/A' }} el {{ formatDateTime(requisition.rejected_at) }}.
                        Se re-trabaja conservando el folio <strong>{{ requisition.folio }}</strong>.
                    </p>
                    <p class="text-sm font-semibold text-red-700 dark:text-red-300 mt-2">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                        Edita la requisición para corregirla y volver a habilitar las acciones del flujo.
                    </p>
                    <SecondaryButton v-if="can.edit" class="mt-3" @click="handleManageCommand('edit')">
                        <i class="fa-solid fa-pen-to-square mr-2"></i> Editar requisición
                    </SecondaryButton>
                </div>

                <!-- Línea de proceso -->
                <Stepper v-else :currentStatus="requisition.status" :steps="progressSteps" />

                <!-- CONTENIDO PRINCIPAL: izquierda información general / derecha pestañas -->
                <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-2">
                    <div class="lg:col-span-1 space-y-4">

                <!-- Información general -->
                <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-6">
                    <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4">
                        <i class="fa-solid fa-circle-info mr-2"></i> Información general
                    </h3>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div v-if="requisition.branch">
                            <p class="text-gray-500 dark:text-gray-400">Cliente</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ requisition.branch.name }}</p>
                        </div>
                        <div v-if="requisition.branch">
                            <p class="text-gray-500 dark:text-gray-400">Razón social</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ requisition.business_name ?? '—' }}</p>
                        </div>
                        <div v-if="requisition.items?.length">
                            <p class="text-gray-500 dark:text-gray-400">Partidas</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ requisition.items.length }} producto(s)
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Cantidad solicitada</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ formatQuantity(requisition.quantity) }} piezas
                            </p>
                        </div>
                        <div v-if="requisition.sale_specification">
                            <p class="text-gray-500 dark:text-gray-400">Especificación de venta</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ requisition.sale_specification }}</p>
                        </div>
                        <div v-if="requisition.reason">
                            <p class="text-gray-500 dark:text-gray-400">Motivo</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ requisition.reason }}</p>
                        </div>
                        <div v-if="requisition.sale_frequency">
                            <p class="text-gray-500 dark:text-gray-400">Frecuencia de venta / consumo</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ requisition.sale_frequency }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Fecha de alta</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ formatDateTime(requisition.created_at) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Entrega estimada</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ requisition.expected_delivery_date ? formatDate(requisition.expected_delivery_date) : 'Sin definir' }}
                            </p>
                        </div>
                        <div v-if="requisition.authorized_at">
                            <p class="text-gray-500 dark:text-gray-400">Validada por</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ requisition.authorizer?.name }} · {{ formatDateTime(requisition.authorized_at) }}
                            </p>
                        </div>
                        <div v-if="requisition.notes" class="col-span-2">
                            <p class="text-gray-500 dark:text-gray-400">Notas</p>
                            <p class="text-gray-800 dark:text-gray-100 whitespace-pre-line">{{ requisition.notes }}</p>
                        </div>
                    </div>
                </div>
                    </div><!-- /columna izquierda -->

                    <!-- COLUMNA DERECHA: pestañas de secciones -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-6 min-h-[340px]">
                            <div class="flex flex-nowrap gap-x-4 border-b border-gray-200 dark:border-slate-700 mb-4 overflow-x-auto">
                                <button v-for="tab in tabs" :key="tab.key" type="button" @click="activeTab = tab.key"
                                    class="pb-2 text-xs font-semibold transition-colors flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === tab.key
                                        ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                                    <i :class="tab.icon"></i> {{ tab.label }}
                                    <span v-if="tab.count" class="text-[10px] bg-gray-100 dark:bg-slate-700 rounded-full px-1.5 py-0.5">
                                        {{ tab.count }}
                                    </span>
                                </button>
                            </div>

                            <!-- Pestaña: Cotización y pagos -->
                            <div v-show="activeTab === 'payments'" class="space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">Costo cotizado</p>
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">
                                            {{ formatCurrency(requisition.quoted_cost, requisition.quoted_currency) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">Monto de factura</p>
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">
                                            {{ formatCurrency(requisition.invoice_amount, requisition.invoice_currency) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">Fecha de pago</p>
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">
                                            {{ requisition.paid_at ? formatDateTime(requisition.paid_at) : 'Pendiente' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- La cotización solo se captura una vez -->
                                <div v-if="hasQuotation"
                                    class="flex items-start gap-2 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-3">
                                    <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 mt-0.5"></i>
                                    <p class="text-xs text-emerald-700 dark:text-emerald-300">
                                        Esta requisición ya tiene una cotización adjunta. Consulta el archivo en la
                                        pestaña <strong>Documentos</strong>; si necesitas reemplazarla, elimínala primero desde ahí.
                                    </p>
                                </div>

                                <div v-if="paymentActions.length"
                                    class="flex flex-wrap gap-2 border-t border-dashed border-gray-200 dark:border-slate-700 pt-4">
                                    <SecondaryButton v-for="action in paymentActions" :key="action.key" @click="openAction(action.key)">
                                        <i :class="[action.icon, 'mr-2']"></i> {{ action.label }}
                                    </SecondaryButton>
                                </div>
                            </div>

                            <!-- Pestaña: Productos solicitados -->
                            <div v-show="activeTab === 'items'" class="space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                        <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200">
                            <i class="fa-solid fa-boxes-stacked mr-2"></i> Productos solicitados
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                ({{ requisition.items?.length ?? 0 }} partida(s) · {{ formatQuantity(totalItemsQuantity) }} piezas)
                            </span>
                        </h3>
                        <el-tooltip v-if="can.manage_arrivals" content="Selecciona en la tabla los productos que viajan en la misma guía" placement="top">
                            <SecondaryButton @click="openShipmentModal">
                                <i class="fa-solid fa-truck-fast mr-2"></i>
                                Asignar a guía
                                <span v-if="selectedItems.length" class="ml-2 font-bold">({{ selectedItems.length }})</span>
                            </SecondaryButton>
                        </el-tooltip>
                </div>

                <!-- Bloque: productos sin guía (pendientes de asignar) -->
                <div class="rounded-xl border border-amber-300 dark:border-amber-700/60 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-2 bg-amber-50 dark:bg-amber-900/20">
                            <p class="text-sm font-bold text-amber-700 dark:text-amber-300">
                                <i class="fa-solid fa-box mr-2"></i> Sin guía asignada
                            </p>
                            <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                                {{ unassignedItems.length }} producto(s)
                            </span>
                        </div>
                        <el-table v-if="unassignedItems.length" :data="unassignedItems" style="width: 100%" stripe
                            @selection-change="handleUnassignedSelection"
                            class="dark:!bg-slate-900 dark:!text-gray-300">
                            <el-table-column type="selection" width="40" :selectable="() => can.manage_arrivals" />
                            <el-table-column label="Producto" min-width="220">
                                <template #default="scope">
                                    <div class="flex items-center gap-3">
                                        <img v-if="scope.row.product?.media?.length" :src="scope.row.product.media[0].original_url"
                                            class="size-10 rounded object-cover border border-gray-200 dark:border-slate-700"
                                            :alt="scope.row.product?.name" />
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ scope.row.product?.name ?? scope.row.description ?? 'Sin producto de catálogo' }}
                                            </p>
                                            <p v-if="scope.row.product?.code" class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ scope.row.product.code }}
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Cantidad" width="110">
                                <template #default="scope">{{ formatQuantity(scope.row.quantity) }}</template>
                            </el-table-column>
                            <el-table-column label="Costo unitario" width="140">
                                <template #default="scope">
                                    <span v-if="scope.row.unit_cost !== null && scope.row.unit_cost !== undefined">
                                        {{ formatCurrency(scope.row.unit_cost, requisition.quoted_currency) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Arribo" width="150">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-block w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: scope.row.arrival_color ?? '#9CA3AF' }"></span>
                                        <span class="text-xs">{{ scope.row.arrival_status }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                        <p v-else class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            Todos los productos ya están ligados a una guía de paquetería.
                        </p>
                </div>

                <!-- Bloque: productos con guía ligada -->
                <div class="rounded-xl border border-emerald-300 dark:border-emerald-700/60 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-2 bg-emerald-50 dark:bg-emerald-900/20">
                            <p class="text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                <i class="fa-solid fa-truck-fast mr-2"></i> Con guía ligada
                            </p>
                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                {{ assignedItems.length }} producto(s)
                            </span>
                        </div>
                        <el-table v-if="assignedItems.length" :data="assignedItems" style="width: 100%" stripe
                            class="dark:!bg-slate-900 dark:!text-gray-300">
                            <el-table-column label="Producto" min-width="220">
                                <template #default="scope">
                                    <div class="flex items-center gap-3">
                                        <img v-if="scope.row.product?.media?.length" :src="scope.row.product.media[0].original_url"
                                            class="size-10 rounded object-cover border border-gray-200 dark:border-slate-700"
                                            :alt="scope.row.product?.name" />
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ scope.row.product?.name ?? scope.row.description ?? 'Sin producto de catálogo' }}
                                            </p>
                                            <p v-if="scope.row.product?.code" class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ scope.row.product.code }}
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Cantidad" width="110">
                                <template #default="scope">{{ formatQuantity(scope.row.quantity) }}</template>
                            </el-table-column>
                            <el-table-column label="Costo unitario" width="140">
                                <template #default="scope">
                                    <span v-if="scope.row.unit_cost !== null && scope.row.unit_cost !== undefined">
                                        {{ formatCurrency(scope.row.unit_cost, requisition.quoted_currency) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Guía de paquetería" min-width="200">
                                <template #default="scope">
                                    <div class="space-y-1">
                                        <p v-for="shipment in scope.row.shipments" :key="shipment.id" class="text-xs">
                                            <span class="font-mono font-semibold">{{ shipment.tracking_number }}</span>
                                            <span class="text-gray-500 dark:text-gray-400"> · {{ shipment.carrier ?? 'N/A' }}</span>
                                        </p>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Arribo" width="150">
                                <template #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-block w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: scope.row.arrival_color ?? '#9CA3AF' }"></span>
                                        <span class="text-xs">{{ scope.row.arrival_status }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                        <p v-else class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            Ningún producto tiene guía de paquetería asignada.
                        </p>
                </div>
                                </div><!-- /pestaña productos solicitados -->

                            <!-- Pestaña: Documentos -->
                            <div v-show="activeTab === 'documents'" class="space-y-4">
                                <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200">
                                    <i class="fa-solid fa-folder-open mr-2"></i> Documentos
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="group in documentGroups" :key="group.label" class="border border-gray-300 p-4 rounded-xl dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ group.label }}</p>
                                <!-- Con archivos existentes el cargador se compacta en un botón "+" -->
                                <el-tooltip v-if="canManageDocuments && group.files.length" content="Adjuntar archivo" placement="top">
                                    <button type="button" @click="toggleUpload(group.collection)"
                                        class="flex items-center justify-center size-7 rounded-full border border-gray-300 dark:border-slate-600 text-gray-500 dark:text-gray-300 hover:border-primary hover:text-primary transition-colors">
                                        <i class="fa-solid fa-plus text-xs"></i>
                                    </button>
                                </el-tooltip>
                            </div>
                            <div v-if="group.files.length" class="flex flex-wrap gap-2">
                                <FileView v-for="file in group.files" :key="file.id" :file="file"
                                    :deletable="canManageDocuments" @delete-file="handleDocumentDeleted" />
                            </div>
                            <p v-else class="text-xs text-gray-400">Sin archivos.</p>
                            <div v-if="canManageDocuments && (!group.files.length || expandedUploads[group.collection])" class="mt-2">
                                <FileUploader format="Todo" :maxFiles="5" :maxFileSize="10"
                                    @files-selected="selected => uploadDocuments(group.collection, selected)" />
                            </div>
                        </div>
                    </div>
                            </div><!-- /pestaña documentos -->

                            <!-- Pestaña: Guías de paquetería y arribos -->
                            <div v-show="activeTab === 'shipments'" class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200">
                                        <i class="fa-solid fa-truck-fast mr-2"></i> Guías de paquetería
                                    </h3>
                                    <SecondaryButton v-if="can.manage_arrivals" @click="openAction('shipment')">
                                        <i class="fa-solid fa-plus mr-2"></i> Ligar guía
                                    </SecondaryButton>
                                </div>

                    <div v-if="requisition.shipments?.length" class="space-y-3">
                        <div v-for="shipment in requisition.shipments" :key="shipment.id"
                            class="border border-gray-200 dark:border-slate-700 rounded-lg p-4 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-center">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Número de guía</p>
                                    <p class="font-mono font-semibold dark:text-gray-200">{{ shipment.tracking_number }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Paquetería</p>
                                    <p class="dark:text-gray-200">{{ shipment.carrier ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Estatus de arribo</p>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: arrivalColors[shipment.status] ?? '#9CA3AF' }"></span>
                                        <span class="text-sm dark:text-gray-200">{{ shipment.status }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 justify-end">
                                    <div class="text-right mr-2">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Arribo estimado</p>
                                        <p class="text-sm dark:text-gray-200">
                                            {{ shipment.estimated_arrival_date ? formatDate(shipment.estimated_arrival_date) : 'Sin definir' }}
                                        </p>
                                    </div>
                                    <el-dropdown v-if="can.manage_arrivals" @command="status => updateShipmentStatus(shipment.id, status)">
                                        <SecondaryButton>
                                            Cambiar estatus <i class="fa-solid fa-chevron-down ml-2"></i>
                                        </SecondaryButton>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item v-for="status in shipmentStatuses" :key="status" :command="status">
                                                    {{ status }}
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </div>
                            </div>

                            <div class="border-t border-dashed border-gray-200 dark:border-slate-700 pt-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Productos en esta guía</p>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-700 dark:text-gray-300">
                                    <li v-for="item in itemsInShipment(shipment.id)" :key="item.id">
                                        <i class="fa-solid fa-box mr-1 text-gray-400"></i>
                                        {{ item.product?.name ?? 'Sin producto de catálogo' }}
                                        <span class="text-gray-500 dark:text-gray-400">({{ formatQuantity(item.quantity) }})</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Una misma guía puede agrupar varios productos y requisiciones; todo se muestra en una sola
                            entrada del calendario de arribos.
                        </p>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        Ningún producto de esta requisición está ligado a una guía de paquetería.
                    </p>
                            </div><!-- /pestaña guías -->

                            <!-- Pestaña: Comentarios -->
                            <div v-show="activeTab === 'comments'" class="space-y-4">
                                <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200">
                                    <i class="fa-solid fa-comments mr-2"></i> Comentarios
                                </h3>

                                <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                                    <div v-for="comment in requisition.comments" :key="comment.id"
                                        class="bg-gray-50 dark:bg-slate-800/60 rounded-lg p-3">
                                        <div class="flex justify-between items-center">
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ comment.user?.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(comment.created_at) }}</p>
                                        </div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 whitespace-pre-line break-words"
                                            v-html="highlightMentions(comment.content)"></p>
                                        <p v-if="comment.mentioned_user_ids?.length" class="text-[10px] text-gray-400 mt-1">
                                            <i class="fa-solid fa-at mr-1"></i>
                                            Mencionó a {{ mentionedNames(comment) }}
                                        </p>
                                    </div>
                                    <p v-if="!requisition.comments?.length" class="text-sm text-gray-500 dark:text-gray-400">
                                        Sin comentarios todavía.
                                    </p>
                                </div>

                                <div class="space-y-2 border-t border-dashed border-gray-200 dark:border-slate-700 pt-4">
                                    <MentionInput v-model="commentForm.content"
                                        v-model:mentioned-ids="commentForm.mentioned_user_ids"
                                        :members="mentionableUsers"
                                        placeholder="Escribe un comentario... Usa @ para mencionar a alguien" />
                                    <div class="flex flex-wrap justify-between items-center gap-2">
                                        <p class="text-xs text-gray-400">
                                            <i class="fa-solid fa-at mr-1"></i>
                                            Los usuarios mencionados recibirán una notificación.
                                        </p>
                                        <PrimaryButton :disabled="commentForm.processing || !commentForm.content"
                                            @click="submitComment">
                                            <i class="fa-solid fa-paper-plane mr-2"></i> Comentar
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /card pestañas -->
                    </div><!-- /columna derecha -->
                </main>
            </div>
        </div>

        <!-- ============ Modales de acciones ============ -->

        <!-- Cotización (local) -->
        <el-dialog v-model="modals.quotation" title="Adjuntar cotización" width="760" :center="true">
            <div class="space-y-4">
                <div>
                    <InputLabel value="Cotización del proveedor *" />
                    <FileUploader format="Todo" :multiple="false" :maxFileSize="10"
                        @files-selected="actionForm.quotation.file = $event[0] ?? null" />
                </div>

                <div v-if="actionForm.quotation.items.length" class="space-y-3">
                    <InputLabel value="Costo unitario por producto" />
                    <div v-for="item in actionForm.quotation.items" :key="item.id"
                        class="flex flex-col sm:flex-row gap-3 border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                        <el-image v-if="item.image_url" :src="item.image_url" fit="cover"
                            :preview-src-list="[item.image_url]" :initial-index="0" preview-teleported hide-on-click-modal
                            class="w-full sm:w-20 h-20 rounded-lg border border-gray-200 dark:border-slate-700 cursor-pointer"
                            :alt="item.name" />
                        <div v-else class="w-full sm:w-20 h-20 rounded-lg border border-dashed border-gray-300 dark:border-slate-700 flex items-center justify-center text-gray-300">
                            <i class="fa-regular fa-image text-2xl"></i>
                        </div>

                        <div class="flex-1 space-y-2">
                            <div v-if="item.has_catalog_product">
                                <p class="font-semibold text-gray-800 dark:text-gray-100 text-sm">{{ item.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Código: {{ item.code ?? 'N/A' }}</p>
                            </div>
                            <div v-else>
                                <InputLabel value="Descripción del producto nuevo" />
                                <TextInput v-model="item.description" class="w-full mt-1"
                                    placeholder="Ej. Llavero metálico personalizado de 3 cm" />
                            </div>
                            <div>
                                <InputLabel value="Costo unitario" />
                                <TextInput v-model="item.unit_cost" type="number" :min="0" :step="0.01"
                                    class="w-full mt-1" placeholder="0.00" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Costo total cotizado" />
                        <TextInput v-model="actionForm.quotation.cost" type="number" :min="0" :step="0.01" class="w-full mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Moneda (todos los productos)" />
                        <el-select v-model="actionForm.quotation.currency" class="w-full mt-1" placeholder="MXN / USD">
                            <el-option label="MXN" value="MXN" />
                            <el-option label="USD" value="USD" />
                        </el-select>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.quotation = false">Cancelar</el-button>
                    <el-button type="primary" :disabled="!actionForm.quotation.file" @click="submitQuotation">
                        Guardar y enviar a autorización
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Validación Pass/Fail -->
        <el-dialog v-model="modals.authorize" title="Validación gerencial" width="540" :center="true">
            <div class="space-y-4">
                <div class="flex gap-3">
                    <button type="button" @click="actionForm.authorize.result = 'pass'"
                        :class="actionForm.authorize.result === 'pass' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-gray-200 dark:border-slate-700'"
                        class="flex-1 rounded-xl border p-3 font-semibold">
                        <i class="fa-solid fa-circle-check text-emerald-500 mr-2"></i> PASS
                    </button>
                    <button type="button" @click="actionForm.authorize.result = 'fail'"
                        :class="actionForm.authorize.result === 'fail' ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-700'"
                        class="flex-1 rounded-xl border p-3 font-semibold">
                        <i class="fa-solid fa-circle-xmark text-red-500 mr-2"></i> FAIL
                    </button>
                </div>
                <div v-if="actionForm.authorize.result === 'fail'">
                    <InputLabel value="Motivo del rechazo *" />
                    <textarea v-model="actionForm.authorize.rejection_reason" rows="3"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200"
                        placeholder="Justifica el rechazo; el solicitante re-trabajará la requisición sobre el mismo folio..."></textarea>
                </div>
                <p v-if="actionForm.authorize.result === 'pass'" class="text-xs text-gray-500 dark:text-gray-400">
                    Al validar se notifica al solicitante y se habilita la siguiente etapa del flujo.
                </p>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.authorize = false">Cancelar</el-button>
                    <el-button type="primary"
                        :disabled="!actionForm.authorize.result || (actionForm.authorize.result === 'fail' && !actionForm.authorize.rejection_reason)"
                        @click="submitAuthorize">
                        Confirmar validación
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Pedido a fábrica (importación) -->
        <el-dialog v-model="modals.factory" title="Pedido a fábrica" width="520" :center="true">
            <div class="space-y-4">
                <div>
                    <InputLabel value="Fecha estimada de arribo" />
                    <el-date-picker v-model="actionForm.factory.expected_delivery_date" type="date" value-format="YYYY-MM-DD"
                        placeholder="Selecciona la fecha" class="w-full mt-1" />
                </div>
                <div>
                    <InputLabel value="Notas del pedido" />
                    <textarea v-model="actionForm.factory.notes" rows="3"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200"
                        placeholder="Cantidad negociada, condiciones, etc."></textarea>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.factory = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitFactoryOrder">Registrar pedido</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Documentación de fábrica -->
        <el-dialog v-model="modals.docs" title="Documentación de seguimiento" width="720" :center="true">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel value="Packing list" />
                    <FileUploader format="Todo" :maxFiles="5" :maxFileSize="10"
                        @files-selected="actionForm.docs.packing_lists = $event" />
                </div>
                <div>
                    <InputLabel value="Invoice de fábrica" />
                    <FileUploader format="Todo" :maxFiles="5" :maxFileSize="10"
                        @files-selected="actionForm.docs.factory_invoices = $event" />
                </div>
                <div>
                    <InputLabel value="Fichas técnicas" />
                    <FileUploader format="Todo" :maxFiles="5" :maxFileSize="10"
                        @files-selected="actionForm.docs.technical_sheets = $event" />
                </div>
                <div>
                    <InputLabel value="Fotos de mercancía" />
                    <FileUploader format="Todo" :maxFiles="5" :maxFileSize="10"
                        @files-selected="actionForm.docs.product_photos = $event" />
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Fecha estimada de arribo" />
                    <el-date-picker v-model="actionForm.docs.expected_delivery_date" type="date" value-format="YYYY-MM-DD"
                        placeholder="Selecciona la fecha" class="w-full mt-1" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.docs = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitFactoryDocs">Guardar documentación</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Factura (local) -->
        <el-dialog v-model="modals.invoice" title="Adjuntar factura del proveedor" width="520" :center="true">
            <div class="space-y-4">
                <div>
                    <InputLabel value="Factura *" />
                    <FileUploader format="Todo" :multiple="false" :maxFileSize="10"
                        @files-selected="actionForm.invoice.file = $event[0] ?? null" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Monto de factura" />
                        <TextInput v-model="actionForm.invoice.amount" type="number" :min="0" :step="0.01" class="w-full mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Moneda" />
                        <el-select v-model="actionForm.invoice.currency" class="w-full mt-1" placeholder="MXN / USD">
                            <el-option label="MXN" value="MXN" />
                            <el-option label="USD" value="USD" />
                        </el-select>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.invoice = false">Cancelar</el-button>
                    <el-button type="primary" :disabled="!actionForm.invoice.file" @click="submitInvoice">
                        Notificar a pagos
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Pago -->
        <el-dialog v-model="modals.payment" title="Registrar pago" width="520" :center="true">
            <div class="space-y-4">
                <div>
                    <InputLabel value="Comprobante de pago *" />
                    <FileUploader format="Todo" :multiple="false" :maxFileSize="10"
                        @files-selected="actionForm.payment.receipt = $event[0] ?? null" />
                </div>
                <div>
                    <InputLabel value="Fecha de pago *" />
                    <el-date-picker v-model="actionForm.payment.paid_at" type="date" value-format="YYYY-MM-DD"
                        placeholder="Selecciona la fecha" class="w-full mt-1" />
                </div>
                <div>
                    <InputLabel value="Notas" />
                    <textarea v-model="actionForm.payment.notes" rows="2"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200"
                        placeholder="Referencia, banco, observaciones..."></textarea>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.payment = false">Cancelar</el-button>
                    <el-button type="primary" :disabled="!actionForm.payment.receipt || !actionForm.payment.paid_at"
                        @click="submitPayment">
                        Registrar pago
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Fecha de entrega -->
        <el-dialog v-model="modals.delivery" title="Fecha estimada de entrega" width="520" :center="true">
            <div class="space-y-4">
                <div>
                    <InputLabel value="Fecha estimada *" />
                    <el-date-picker v-model="actionForm.delivery.expected_delivery_date" type="date" value-format="YYYY-MM-DD"
                        placeholder="Considera los tiempos de transporte" class="w-full mt-1" />
                </div>
                <div>
                    <InputLabel value="Notas" />
                    <textarea v-model="actionForm.delivery.notes" rows="2"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200"
                        placeholder="Detalles de transporte y entrega..."></textarea>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.delivery = false">Cancelar</el-button>
                    <el-button type="primary" :disabled="!actionForm.delivery.expected_delivery_date" @click="submitDelivery">
                        Guardar fecha
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Guía de paquetería -->
        <el-dialog v-model="modals.shipment" title="Asignar productos a guía de paquetería" width="620" :center="true">
            <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-slate-800/60 border border-blue-200 dark:border-slate-700 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">
                        Productos seleccionados ({{ selectedItems.length }}):
                    </p>
                    <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-0.5">
                        <li v-for="item in selectedItems" :key="item.id">
                            <i class="fa-solid fa-box mr-1 text-gray-400"></i>
                            {{ item.product?.name ?? 'Sin producto de catálogo' }}
                            <span class="text-gray-500 dark:text-gray-400">({{ formatQuantity(item.quantity) }})</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <InputLabel value="Guía existente" />
                    <el-select v-model="actionForm.shipment.shipment_id" filterable clearable class="w-full mt-1"
                        placeholder="Busca una guía ya registrada para agrupar productos">
                        <el-option v-for="option in shipmentOptions" :key="option.id" :value="option.id"
                            :label="`${option.tracking_number} · ${option.carrier ?? 'N/A'} (${option.status})`" />
                    </el-select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Si los productos comparten número de guía con otras requisiciones, selecciónala aquí para no
                        duplicar registros en el calendario.
                    </p>
                </div>

                <div class="text-center text-xs text-gray-400">— o registra una guía nueva —</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Número de guía" />
                        <TextInput v-model="actionForm.shipment.tracking_number" class="w-full mt-1"
                            placeholder="Ej. 1234567890" />
                    </div>
                    <div>
                        <InputLabel value="Paquetería" />
                        <TextInput v-model="actionForm.shipment.carrier" class="w-full mt-1" placeholder="Ej. DHL, FedEx..." />
                    </div>
                    <div>
                        <InputLabel value="Estatus de arribo" />
                        <el-select v-model="actionForm.shipment.status" class="w-full mt-1">
                            <el-option v-for="status in shipmentStatuses" :key="status" :label="status" :value="status" />
                        </el-select>
                    </div>
                    <div>
                        <InputLabel value="Arribo estimado" />
                        <el-date-picker v-model="actionForm.shipment.estimated_arrival_date" type="date"
                            value-format="YYYY-MM-DD" placeholder="Selecciona la fecha" class="w-full mt-1" />
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.shipment = false">Cancelar</el-button>
                    <el-button type="primary"
                        :disabled="!selectedItems.length || (!actionForm.shipment.shipment_id && !actionForm.shipment.tracking_number)"
                        @click="submitShipment">
                        Asignar {{ selectedItems.length }} producto(s)
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
import DangerButton from "@/Components/DangerButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import Back from "@/Components/MyComponents/Back.vue";
import MentionInput from "@/Pages/Projects/Partials/MentionInput.vue";
import { router, useForm } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    components: {
        AppLayout,
        PrimaryButton,
        SecondaryButton,
        DangerButton,
        InputLabel,
        TextInput,
        FileUploader,
        FileView,
        Stepper,
        Back,
        MentionInput,
    },
    props: {
        requisition: Object,
        can: {
            type: Object,
            default: () => ({}),
        },
        mentionableUsers: {
            type: Array,
            default: () => [],
        },
        shipmentStatuses: {
            type: Array,
            default: () => ['Pendiente', 'En tránsito', 'Incidencia', 'Arribado'],
        },
        shipmentOptions: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            activeTab: 'payments',
            selectedItems: [],
            // Grupos de documentos cuyo cargador se expandió manualmente.
            expandedUploads: {},
            arrivalColors: {
                Pendiente: '#9CA3AF',
                'En tránsito': '#F59E0B',
                Incidencia: '#EF4444',
                Arribado: '#22C55E',
            },
            modals: {
                quotation: false,
                authorize: false,
                factory: false,
                docs: false,
                invoice: false,
                payment: false,
                delivery: false,
                shipment: false,
            },
            actionForm: {
                quotation: { file: null, cost: '', currency: 'MXN', items: this.initialQuotationItems() },
                authorize: { result: null, rejection_reason: '' },
                factory: { expected_delivery_date: null, notes: '' },
                docs: {
                    packing_lists: [],
                    factory_invoices: [],
                    technical_sheets: [],
                    product_photos: [],
                    expected_delivery_date: null,
                },
                invoice: { file: null, amount: '', currency: 'MXN' },
                payment: { receipt: null, paid_at: null, notes: '' },
                delivery: { expected_delivery_date: null, notes: '' },
                shipment: {
                    shipment_id: null,
                    tracking_number: '',
                    carrier: '',
                    status: 'Pendiente',
                    estimated_arrival_date: null,
                },
            },
            commentForm: useForm({ content: '', mentioned_user_ids: [] }),
        };
    },
    computed: {
        tabs() {
            return [
                { key: 'payments', label: 'Cotización y pagos', icon: 'fa-solid fa-coins' },
                { key: 'items', label: 'Productos', icon: 'fa-solid fa-boxes-stacked', count: this.requisition.items?.length ?? 0 },
                { key: 'documents', label: 'Documentos', icon: 'fa-solid fa-folder-open', count: this.documentCount },
                { key: 'shipments', label: 'Guía', icon: 'fa-solid fa-truck-fast', count: this.requisition.shipments?.length ?? 0 },
                { key: 'comments', label: 'Comentarios', icon: 'fa-solid fa-comments', count: this.requisition.comments?.length ?? 0 },
            ];
        },
        // Acciones del flujo disponibles según la etapa y los permisos.
        flowActions() {
            return [
                // La cotización se captura una sola vez: si ya hay archivo, no se ofrece.
                { key: 'quotation', label: 'Adjuntar cotización', icon: 'fa-solid fa-file-invoice-dollar', show: this.canQuoteNow },
                { key: 'authorize', label: 'Validar (Pass / Fail)', icon: 'fa-solid fa-gavel', show: this.can.authorize },
                { key: 'factory', label: 'Pedido a fábrica', icon: 'fa-solid fa-industry', show: this.can.factory_order },
                { key: 'docs', label: 'Documentación de fábrica', icon: 'fa-solid fa-box-open', show: this.can.upload_factory_docs },
                { key: 'invoice', label: 'Adjuntar factura', icon: 'fa-solid fa-file-invoice', show: this.can.upload_invoice },
                { key: 'payment', label: 'Registrar pago', icon: 'fa-solid fa-money-bill-wave', show: this.can.register_payment },
                { key: 'shipment', label: 'Asignar guía de paquetería', icon: 'fa-solid fa-truck-fast', show: this.can.manage_arrivals },
                { key: 'delivery', label: 'Fecha de entrega', icon: 'fa-solid fa-calendar-days', show: this.can.register_delivery },
                { key: 'complete', label: 'Completar requisición', icon: 'fa-solid fa-flag-checkered', show: this.can.complete },
            ].filter(action => action.show);
        },
        // Acciones económicas (se repiten como botones dentro de la pestaña).
        paymentActions() {
            return this.flowActions.filter(action => ['quotation', 'invoice', 'payment'].includes(action.key));
        },
        documentCount() {
            return (this.requisition.media ?? []).length;
        },
        // La cotización existe mientras el archivo siga adjunto.
        hasQuotation() {
            return (this.requisition.media ?? []).some(file => file.collection_name === 'quotations');
        },
        /*
         * El servidor habilita la captura por etapa y permisos; si la cotización
         * previa se eliminó desde Documentos, se vuelve a ofrecer el reemplazo.
         */
        canQuoteNow() {
            if (this.hasQuotation) return false;

            return Boolean(this.can.upload_quotation || this.can.quoted);
        },
        totalItemsQuantity() {
            return (this.requisition.items ?? []).reduce((total, item) => total + (Number(item.quantity) || 0), 0);
        },
        // Partidas sin guía y con guía, para separarlas visualmente.
        unassignedItems() {
            return (this.requisition.items ?? []).filter(item => !(item.shipments ?? []).length);
        },
        assignedItems() {
            return (this.requisition.items ?? []).filter(item => (item.shipments ?? []).length > 0);
        },
        progressSteps() {
            return this.requisition.type === 'Importación'
                ? ['Pendiente de negociación', 'Aprobada', 'Pedido a fábrica', 'En tránsito', 'Arribo confirmado', 'Completada']
                : ['Pendiente de cotización', 'Pendiente de autorización', 'Autorizada', 'Pendiente de pago', 'Pagada', 'Completada'];
        },
        documentGroups() {
            const media = this.requisition.media ?? [];
            const byCollection = (collection) => media.filter(file => file.collection_name === collection);

            const groups = [
                { label: 'Formato de autorización', collection: 'authorization_formats', files: byCollection('authorization_formats') },
                { label: 'Plano / render', collection: 'blueprints', files: byCollection('blueprints') },
            ];

            if (this.requisition.type === 'Importación') {
                groups.push({ label: 'Carta de uso de marca', collection: 'brand_use_letters', files: byCollection('brand_use_letters') });
                groups.push({ label: 'Packing list', collection: 'packing_lists', files: byCollection('packing_lists') });
                groups.push({ label: 'Invoice de fábrica', collection: 'factory_invoices', files: byCollection('factory_invoices') });
                groups.push({ label: 'Fichas técnicas', collection: 'technical_sheets', files: byCollection('technical_sheets') });
                groups.push({ label: 'Fotos de mercancía', collection: 'product_photos', files: byCollection('product_photos') });
            } else {
                groups.push({ label: 'Cotización', collection: 'quotations', files: byCollection('quotations') });
                groups.push({ label: 'Factura', collection: 'invoices', files: byCollection('invoices') });
                groups.push({ label: 'Comprobante de pago', collection: 'payment_receipts', files: byCollection('payment_receipts') });
            }

            return groups;
        },
        canManageDocuments() {
            return Boolean(this.can.edit) && this.requisition.status !== 'Completada';
        },
    },
    methods: {
        formatQuantity(value) {
            const number = Number(value) || 0;
            return number.toLocaleString('es-MX', { maximumFractionDigits: 2 });
        },
        // La selección para guías vive en el bloque de productos sin asignar.
        handleUnassignedSelection(selection) {
            this.selectedItems = selection;
        },
        /**
         * Productos de la requisición para capturar el costo unitario de la cotización.
         * Se muestra nombre y foto cuando el producto existe en el catálogo; para
         * productos nuevos solo se pide la descripción.
         */
        initialQuotationItems() {
            return (this.requisition?.items ?? []).map(item => ({
                id: item.id,
                name: item.product?.name ?? 'Producto nuevo',
                code: item.product?.code ?? null,
                image_url: item.product?.media?.[0]?.original_url ?? null,
                has_catalog_product: Boolean(item.product_id),
                unit_cost: item.unit_cost ?? '',
                description: item.description ?? '',
            }));
        },
        itemsInShipment(shipmentId) {
            return (this.requisition.items ?? []).filter(item =>
                (item.shipments ?? []).some(shipment => shipment.id === shipmentId));
        },
        openShipmentModal() {
            if (!this.selectedItems.length) {
                ElMessage.warning('Selecciona en la tabla los productos que viajan en esta guía.');
                return;
            }

            this.openAction('shipment');
        },
        formatDate(dateString) {
            if (!dateString) return '—';
            return format(new Date(dateString), "d 'de' MMM, yyy", { locale: es });
        },
        formatDateTime(dateString) {
            if (!dateString) return '—';
            return format(new Date(dateString), "d 'de' MMM, yyy HH:mm", { locale: es });
        },
        formatCurrency(value, currency = 'MXN') {
            if (value === null || value === undefined || value === '') return 'Sin registrar';
            return Number(value).toLocaleString('es-MX', {
                style: 'currency',
                currency: currency || 'MXN',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        getStatusTagType(status) {
            const map = {
                'Pendiente de cotización': 'info',
                'Pendiente de negociación': 'info',
                'Pendiente de autorización': 'warning',
                'Rechazada': 'danger',
                'Pendiente de pago': 'danger',
                'Autorizada': 'success',
                'Aprobada': 'success',
                'Pagada': 'success',
                'Arribo confirmado': 'success',
                'Completada': 'success',
                'Pedido a fábrica': 'primary',
                'En tránsito': 'warning',
            };
            return map[status] ?? 'info';
        },
        getPriorityTagType(priority) {
            const map = { Baja: 'info', Media: 'primary', Alta: 'warning', Urgente: 'danger' };
            return map[priority] ?? 'info';
        },
        openAction(action) {
            this.modals[action] = true;
        },
        // Grupo de acciones del flujo (menú "Acciones").
        async handleFlowCommand(command) {
            if (command === 'complete') {
                await this.confirmComplete();
                return;
            }

            if (command === 'shipment' && !this.selectedItems.length) {
                this.activeTab = 'items';
                ElMessage.warning('Selecciona en la tabla los productos que viajan en la guía.');
                return;
            }

            this.openAction(command);
        },
        // Grupo de gestión: editar / eliminar.
        async handleManageCommand(command) {
            if (command === 'edit') {
                router.get(route('requisitions.edit', this.requisition.id));
                return;
            }

            if (command === 'delete') {
                try {
                    await ElMessageBox.confirm(
                        '¿Eliminar esta requisición? Esta acción no se puede deshacer.',
                        'Confirmar eliminación',
                        { confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', type: 'warning' },
                    );

                    this.deleteRequisition();
                } catch (error) {
                    // El usuario canceló la confirmación.
                }
            }
        },
        async confirmComplete() {
            try {
                await ElMessageBox.confirm(
                    'Se marcará la requisición como completada (mercancía recibida).',
                    'Completar requisición',
                    { confirmButtonText: 'Sí, completar', cancelButtonText: 'Cancelar', type: 'info' },
                );

                this.completeRequisition();
            } catch (error) {
                // El usuario canceló la confirmación.
            }
        },
        // Resalta las menciones @Nombre dentro del texto del comentario.
        highlightMentions(content) {
            let html = this.escapeHtml(content);

            (this.mentionableUsers ?? []).forEach(member => {
                if (!member?.name) return;
                const regex = new RegExp(`@${this.escapeRegExp(member.name)}(?=\\b|$)`, 'g');
                html = html.replace(regex,
                    `<span class="text-blue-600 font-semibold bg-blue-50 dark:bg-blue-900/40 px-1 rounded">@${this.escapeHtml(member.name)}</span>`);
            });

            return html;
        },
        mentionedNames(comment) {
            const ids = (comment.mentioned_user_ids ?? []).map(id => Number(id));
            const names = (this.mentionableUsers ?? [])
                .filter(member => ids.includes(Number(member.id)))
                .map(member => member.name);

            return names.join(', ') || 'usuario(s)';
        },
        escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        },
        escapeRegExp(value) {
            return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },
        handleSuccess(message) {
            ElMessage.success(message);
        },
        submitQuotation() {
            router.post(route('requisitions.upload-quotation', this.requisition.id), {
                quotation: this.actionForm.quotation.file,
                quoted_cost: this.actionForm.quotation.cost || null,
                quoted_currency: this.actionForm.quotation.currency || null,
                items: this.actionForm.quotation.items.map(item => ({
                    id: item.id,
                    unit_cost: item.unit_cost === '' ? null : item.unit_cost,
                    description: item.description || null,
                })),
            }, {
                forceFormData: true,
                onSuccess: () => {
                    this.modals.quotation = false;
                    this.handleSuccess('Cotización adjuntada.');
                },
                onError: () => ElMessage.error('No se pudo subir la cotización.'),
            });
        },
        submitAuthorize() {
            router.put(route('requisitions.authorize', this.requisition.id), {
                result: this.actionForm.authorize.result,
                rejection_reason: this.actionForm.authorize.rejection_reason || null,
            }, {
                onSuccess: () => {
                    this.modals.authorize = false;
                    this.handleSuccess('Validación registrada.');
                },
                onError: (errors) => ElMessage.error(Object.values(errors)[0] ?? 'No se pudo registrar la validación.'),
            });
        },
        submitFactoryOrder() {
            router.post(route('requisitions.factory-order', this.requisition.id), {
                expected_delivery_date: this.actionForm.factory.expected_delivery_date,
                notes: this.actionForm.factory.notes || null,
            }, {
                onSuccess: () => {
                    this.modals.factory = false;
                    this.handleSuccess('Pedido a fábrica registrado.');
                },
            });
        },
        submitFactoryDocs() {
            const form = this.actionForm.docs;
            const hasFiles = form.packing_lists.length || form.factory_invoices.length
                || form.technical_sheets.length || form.product_photos.length;

            if (!hasFiles) {
                ElMessage.warning('Adjunta al menos un documento de seguimiento.');
                return;
            }

            router.post(route('requisitions.factory-docs', this.requisition.id), {
                packing_lists: form.packing_lists,
                factory_invoices: form.factory_invoices,
                technical_sheets: form.technical_sheets,
                product_photos: form.product_photos,
                expected_delivery_date: form.expected_delivery_date,
            }, {
                forceFormData: true,
                onSuccess: () => {
                    this.modals.docs = false;
                    this.handleSuccess('Documentación adjuntada.');
                },
                onError: () => ElMessage.error('No se pudo guardar la documentación.'),
            });
        },
        submitInvoice() {
            router.post(route('requisitions.upload-invoice', this.requisition.id), {
                invoice: this.actionForm.invoice.file,
                invoice_amount: this.actionForm.invoice.amount || null,
                invoice_currency: this.actionForm.invoice.currency || null,
            }, {
                forceFormData: true,
                onSuccess: () => {
                    this.modals.invoice = false;
                    this.handleSuccess('Factura adjuntada; se notificó a pagos.');
                },
                onError: () => ElMessage.error('No se pudo subir la factura.'),
            });
        },
        submitPayment() {
            router.post(route('requisitions.payment', this.requisition.id), {
                payment_receipt: this.actionForm.payment.receipt,
                paid_at: this.actionForm.payment.paid_at,
                notes: this.actionForm.payment.notes || null,
            }, {
                forceFormData: true,
                onSuccess: () => {
                    this.modals.payment = false;
                    this.handleSuccess('Pago registrado.');
                },
                onError: () => ElMessage.error('No se pudo registrar el pago.'),
            });
        },
        submitDelivery() {
            router.post(route('requisitions.delivery-date', this.requisition.id), {
                expected_delivery_date: this.actionForm.delivery.expected_delivery_date,
                notes: this.actionForm.delivery.notes || null,
            }, {
                onSuccess: () => {
                    this.modals.delivery = false;
                    this.handleSuccess('Fecha de entrega registrada.');
                },
            });
        },
        submitShipment() {
            const form = this.actionForm.shipment;

            router.post(route('requisitions.assign-shipment', this.requisition.id), {
                item_ids: this.selectedItems.map(item => item.id),
                shipment_id: form.shipment_id,
                tracking_number: form.shipment_id ? null : form.tracking_number,
                carrier: form.carrier,
                status: form.status,
                estimated_arrival_date: form.estimated_arrival_date,
            }, {
                onSuccess: () => {
                    this.modals.shipment = false;
                    this.selectedItems = [];
                    this.handleSuccess('Producto(s) ligado(s) a la guía.');
                },
                onError: (errors) => ElMessage.error(Object.values(errors)[0] ?? 'No se pudo ligar la guía.'),
            });
        },
        updateShipmentStatus(shipmentId, status) {
            router.put(route('requisition-shipments.update-status', shipmentId), { status }, {
                onSuccess: () => this.handleSuccess(`Guía actualizada a "${status}".`),
                onError: () => ElMessage.error('No se pudo actualizar la guía.'),
            });
        },
        completeRequisition() {
            router.post(route('requisitions.complete', this.requisition.id), {}, {
                onSuccess: () => this.handleSuccess('Requisición completada.'),
                onError: (errors) => ElMessage.error(Object.values(errors)[0] ?? 'No se pudo completar la requisición.'),
            });
        },
        deleteRequisition() {
            router.delete(route('requisitions.destroy', this.requisition.id), {
                onSuccess: () => this.handleSuccess('Requisición eliminada.'),
            });
        },
        // FileView ya elimina el archivo en el servidor; solo se refresca la vista.
        handleDocumentDeleted(mediaId) {
            this.requisition.media = (this.requisition.media ?? []).filter(file => file.id !== mediaId);
        },
        uploadDocuments(collection, files) {
            if (!files?.length) return;

            router.post(route('requisitions.upload-media', this.requisition.id), {
                collection,
                files,
            }, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    // Se colapsa el cargador para que la pestaña no crezca de más.
                    this.expandedUploads[collection] = false;
                    this.handleSuccess('Archivo(s) adjuntado(s).');
                },
                onError: () => ElMessage.error('No se pudieron adjuntar los archivos.'),
            });
        },
        toggleUpload(collection) {
            this.expandedUploads[collection] = !this.expandedUploads[collection];
        },
        submitComment() {
            this.commentForm.post(route('requisitions.comments', this.requisition.id), {
                preserveScroll: true,
                onSuccess: () => this.commentForm.reset('content', 'mentioned_user_ids'),
            });
        },
    },
};
</script>
