<template>
    <AppLayout :title="`Requisición ${requisition.folio}`">
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Encabezado -->
                <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-6"
                    :class="isUrgentActive ? 'ring-2 ring-red-400/70 dark:ring-red-500/60' : ''">
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
                                <!-- Urgente en proceso: distintivo animado; al completarse queda solo la etiqueta. -->
                                <span v-if="isUrgentActive"
                                    class="priority-badge inline-flex items-center gap-1.5 text-[11px] font-bold text-white bg-red-600 px-2.5 py-1 rounded-full">
                                    <i class="fa-solid fa-triangle-exclamation"></i> URGENTE
                                </span>
                                <el-tag v-else-if="requisition.priority" :type="getPriorityTagType(requisition.priority)">
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
                        <template v-if="requisition.type === 'Importación'">
                            Corrige la requisición con «Editar requisición» o vuelve a subir la cotización para
                            reactivar la validación: la gerencia puede aprobarla o rechazarla de nuevo sin editarla.
                        </template>
                        <template v-else>
                            Edita la requisición para corregirla o elimina la cotización actual y sube otra corregida
                            en caso de que sea ese el motivo de rechazo para volver a habilitar las acciones del flujo.
                        </template>
                    </p>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <SecondaryButton v-if="can.edit" @click="handleManageCommand('edit')">
                            <i class="fa-solid fa-pen-to-square mr-2"></i> Editar requisición
                        </SecondaryButton>
                        <!-- Importación: la gerencia revalida sin editar (Pass / Fail). -->
                        <PrimaryButton v-if="can.authorize" @click="openAction('authorize')">
                            <i class="fa-solid fa-gavel mr-2"></i> Validar (Pass / Fail)
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Línea de proceso -->
                <Stepper v-else :currentStatus="requisition.status" :steps="stepperSteps"
                    :completedCount="stepperCompletedCount" />

                <!-- ¿Qué sigue? Guía paso a paso del flujo (plegable) -->
                <div v-if="nextStepGuide" class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg border-l-4"
                    :class="nextStepGuide.styles.border">
                    <!-- Encabezado: el bloque izquierdo funciona como disparador del plegado -->
                    <div class="w-full p-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <button type="button" :aria-expanded="guideExpanded"
                            class="flex-1 text-left flex items-start gap-3" @click="guideExpanded = !guideExpanded">
                            <div class="size-10 shrink-0 rounded-full flex items-center justify-center"
                                :class="nextStepGuide.styles.icon">
                                <i :class="[nextStepGuide.icon, 'text-base']"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wider" :class="nextStepGuide.styles.accent">
                                    ¿Qué sigue?
                                </p>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">
                                    {{ nextStepGuide.title }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Etapa actual: <span class="font-semibold">{{ requisition.status }}</span>
                                    <template v-if="nextStepGuide.nextStatus">
                                        · Siguiente hito: <span class="font-semibold">{{ nextStepGuide.nextStatus }}</span>
                                    </template>
                                </p>
                            </div>
                        </button>
                        <div class="flex items-center gap-2 self-start shrink-0">
                            <!-- Acceso rápido al desglose de la cotización (precios, IVA y total) -->
                            <el-tooltip v-if="quotationSummaryAvailable" content="Ver precios unitarios, IVA y total de la cotización" placement="top">
                                <SecondaryButton @click="openQuotationSummary">
                                    <i class="fa-solid fa-file-invoice-dollar mr-1"></i> Ver cotización
                                </SecondaryButton>
                            </el-tooltip>
                            <!-- Atajo: levantar la orden de compra con el folio de la requisición -->
                            <el-tooltip v-if="can.create_purchase_order" content="Levantar la orden de compra con el folio de esta requisición" placement="top">
                                <SecondaryButton @click="createPurchaseOrder">
                                    <i class="fa-solid fa-file-invoice mr-1"></i> Crear orden de compra
                                </SecondaryButton>
                            </el-tooltip>
                            <el-tag :type="nextStepGuide.styles.tag" effect="dark">
                                <i class="fa-solid fa-user-gear mr-1"></i> {{ nextStepGuide.owner }}
                            </el-tag>
                            <button type="button" class="p-1" :aria-expanded="guideExpanded"
                                :title="guideExpanded ? 'Ocultar detalle' : 'Ver detalle'"
                                @click="guideExpanded = !guideExpanded">
                                <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform duration-300"
                                    :class="{ 'rotate-180': guideExpanded }"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Detalle: requisitos, pasos y acciones -->
                    <div v-show="guideExpanded" class="px-5 pb-5">
                        <div v-if="nextStepGuide.requirements.length" class="flex flex-wrap gap-x-5 gap-y-1">
                            <p v-for="requirement in nextStepGuide.requirements" :key="requirement.label"
                                class="text-xs flex items-center gap-1"
                                :class="requirement.met
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-amber-600 dark:text-amber-400'">
                                <i :class="[requirement.met ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation']"></i>
                                {{ requirement.label }}
                                <span class="text-gray-400 dark:text-gray-500">({{ requirement.met ? 'listo' : 'pendiente' }})</span>
                            </p>
                        </div>

                        <div class="mt-4">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">
                                <i class="fa-solid fa-list-ol mr-1"></i> Sigue estos pasos en orden
                            </p>
                            <ol class="space-y-2">
                                <li v-for="(step, index) in nextStepGuide.steps" :key="index" class="flex items-start gap-3">
                                    <span class="mt-0.5 size-5 shrink-0 rounded-full text-[11px] font-bold flex items-center justify-center"
                                        :class="nextStepGuide.styles.step">
                                        {{ index + 1 }}
                                    </span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ step }}</span>
                                </li>
                            </ol>
                        </div>

                        <div
                            class="mt-4 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700 flex flex-wrap items-center gap-2">
                            <PrimaryButton v-for="action in nextStepGuide.actions" :key="action.key"
                                @click="runGuideAction(action.key)">
                                <i :class="[action.icon, 'mr-2']"></i> {{ action.label }}
                            </PrimaryButton>
                            <p v-if="nextStepGuide.hint" class="text-xs text-gray-500 dark:text-gray-400">
                                <i class="fa-solid fa-circle-info mr-1"></i> {{ nextStepGuide.hint }}
                            </p>
                        </div>
                    </div>
                </div>

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
                            <p class="text-gray-500 dark:text-gray-400">
                                {{ requisition.type === 'Importación' ? 'Arribo tentativo' : 'Entrega estimada' }}
                            </p>
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
                        <div v-if="requisitionPurchases.length" class="col-span-2">
                            <p class="text-gray-500 dark:text-gray-400">Orden de compra</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                <span v-for="(purchase, index) in requisitionPurchases" :key="purchase.id" class="font-mono">
                                    <span class="cursor-pointer text-blue-500 hover:underline" @click="$inertia.visit(route('purchases.show', { id: purchase.id }))">{{ purchase.folio }}</span>
                                    <span class="text-gray-400 font-normal"> · {{ purchase.supplier?.name ?? 'Sin proveedor' }}</span>
                                    <template v-if="index < requisitionPurchases.length - 1">, </template>
                                </span>
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
                                <button v-for="tab in tabs" :key="tab.key" type="button" @click="selectTab(tab.key)"
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
                                        <template v-if="quotationHasTax">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Subtotal: {{ formatCurrency(quotationSummarySubtotal, requisition.quoted_currency) }}
                                            </p>
                                            <p class="text-xs text-amber-600 dark:text-amber-400">
                                                IVA ({{ quotationTaxRateLabel }}%):
                                                {{ formatCurrency(quotationSummaryTax, requisition.quoted_currency) }}
                                            </p>
                                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                                Total: {{ formatCurrency(quotationSummaryTotal, requisition.quoted_currency) }}
                                            </p>
                                        </template>
                                        <p v-else class="font-semibold text-gray-800 dark:text-gray-100">
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
                                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-3">
                                    <p class="text-xs text-emerald-700 dark:text-emerald-300">
                                        <i class="fa-solid fa-circle-check mr-1"></i>
                                        Esta requisición ya tiene una cotización adjunta. Consulta precios unitarios,
                                        IVA y total, o revisa el archivo en la pestaña <strong>Documentos</strong>; si
                                        necesitas reemplazarla, elimínala primero desde ahí.
                                    </p>
                                    <SecondaryButton @click="openQuotationSummary">
                                        <i class="fa-solid fa-file-invoice-dollar mr-1"></i> Ver detalle
                                    </SecondaryButton>
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
                                    <el-tooltip v-if="can.manage_arrivals"
                                        :content="isLocalRequisition
                                            ? 'Selecciona los productos que entrega el proveedor o el transporte propio'
                                            : 'Selecciona en la tabla los productos que viajan en la misma guía'"
                                        placement="top">
                                        <SecondaryButton @click="openShipmentModal">
                                            <i class="fa-solid fa-truck-fast mr-2"></i>
                                            {{ isLocalRequisition ? 'Registrar entrega' : 'Asignar a guía' }}
                                            <span v-if="selectedItems.length" class="ml-2 font-bold">({{ selectedItems.length }})</span>
                                        </SecondaryButton>
                                    </el-tooltip>
                                </div>

                <!-- Distribución de la orden de compra: vía de envío por producto -->
                <div v-if="purchaseDistributionRows.length"
                    class="rounded-xl border border-sky-300 dark:border-sky-700/60 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2 bg-sky-50 dark:bg-sky-900/20">
                        <p class="text-sm font-bold text-sky-700 dark:text-sky-300">
                            <i class="fa-solid fa-ship mr-2"></i> Distribución de la compra
                        </p>
                        <span class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                            {{ purchaseDistributionRows.length }} producto(s) comprado(s)
                        </span>
                    </div>
                    <el-table :data="purchaseDistributionRows" style="width: 100%" stripe
                        class="dark:!bg-slate-900 dark:!text-gray-300">
                        <el-table-column label="Producto" min-width="220">
                            <template #default="scope">
                                <div class="flex items-center gap-3">
                                    <img v-if="scope.row.image_url" :src="scope.row.image_url"
                                        class="size-10 rounded object-cover border border-gray-200 dark:border-slate-700"
                                        :alt="scope.row.name" />
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ scope.row.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ scope.row.folio }}
                                            <span v-if="scope.row.code"> · {{ scope.row.code }}</span>
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="Comprado" width="100" align="right">
                            <template #default="scope">{{ formatQuantity(scope.row.total) }}</template>
                        </el-table-column>
                        <el-table-column label="Avión" width="100" align="right">
                            <template #default="scope">
                                <span v-if="scope.row.plane" class="font-semibold text-sky-600 dark:text-sky-400">
                                    {{ formatQuantity(scope.row.plane) }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Barco" width="100" align="right">
                            <template #default="scope">
                                <span v-if="scope.row.ship" class="font-semibold text-indigo-600 dark:text-indigo-400">
                                    {{ formatQuantity(scope.row.ship) }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Se queda en fábrica" width="150" align="right">
                            <template #default="scope">
                                <span v-if="scope.row.factory" class="font-semibold text-amber-600 dark:text-amber-400">
                                    {{ formatQuantity(scope.row.factory) }}
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <!-- Bloque: productos sin guía (pendientes de asignar, incluye parcialidades) -->
                <div class="rounded-xl border border-amber-300 dark:border-amber-700/60 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-2 bg-amber-50 dark:bg-amber-900/20">
                            <p class="text-sm font-bold text-amber-700 dark:text-amber-300">
                                <i class="fa-solid fa-box mr-2"></i> Pendientes de asignar
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
                            <el-table-column label="Cantidad" width="170">
                                <template #default="scope">
                                    <p>{{ formatQuantity(scope.row.quantity) }} pieza(s)</p>
                                    <p class="text-[11px] text-amber-600 dark:text-amber-400">
                                        Pendiente: {{ formatQuantity(itemRemainingQuantity(scope.row)) }}
                                    </p>
                                    <p v-if="hasPurchaseDistribution(itemPurchaseDistribution(scope.row))"
                                        class="text-[11px] text-sky-600 dark:text-sky-400">
                                        <i class="fa-solid fa-ship mr-1"></i>
                                        Avión {{ formatQuantity(itemPurchaseDistribution(scope.row).plane) }} ·
                                        Barco {{ formatQuantity(itemPurchaseDistribution(scope.row).ship) }} ·
                                        Fábrica {{ formatQuantity(itemPurchaseDistribution(scope.row).factory) }}
                                    </p>
                                </template>
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
                            {{ isLocalRequisition
                                ? 'Todos los productos ya tienen una entrega registrada.'
                                : 'Todos los productos ya están ligados a una guía de paquetería.' }}
                        </p>
                </div>

                <!-- Bloque: productos con guía ligada -->
                <div class="rounded-xl border border-emerald-300 dark:border-emerald-700/60 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-2 bg-emerald-50 dark:bg-emerald-900/20">
                            <p class="text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                <i class="fa-solid fa-truck-fast mr-2"></i>
                                {{ isLocalRequisition ? 'Con entrega registrada' : 'Con guía ligada' }}
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
                                            <el-tag v-if="itemRemainingQuantity(scope.row) > 0" size="small" type="warning" class="mt-1">
                                                Envío parcial · faltan {{ formatQuantity(itemRemainingQuantity(scope.row)) }}
                                            </el-tag>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Cantidad" width="150">
                                <template #default="scope">
                                    <p>{{ formatQuantity(scope.row.quantity) }}</p>
                                    <p v-if="hasPurchaseDistribution(itemPurchaseDistribution(scope.row))"
                                        class="text-[11px] text-sky-600 dark:text-sky-400">
                                        <i class="fa-solid fa-ship mr-1"></i>
                                        Avión {{ formatQuantity(itemPurchaseDistribution(scope.row).plane) }} ·
                                        Barco {{ formatQuantity(itemPurchaseDistribution(scope.row).ship) }} ·
                                        Fábrica {{ formatQuantity(itemPurchaseDistribution(scope.row).factory) }}
                                    </p>
                                </template>
                            </el-table-column>
                            <el-table-column label="Costo unitario" width="140">
                                <template #default="scope">
                                    <span v-if="scope.row.unit_cost !== null && scope.row.unit_cost !== undefined">
                                        {{ formatCurrency(scope.row.unit_cost, requisition.quoted_currency) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Guía de paquetería" min-width="220">
                                <template #default="scope">
                                    <div class="space-y-1">
                                        <p v-for="shipment in scope.row.shipments" :key="shipment.id" class="text-xs">
                                            <span class="font-mono font-semibold">{{ shipment.tracking_number }}</span>
                                            <span class="text-gray-500 dark:text-gray-400"> · {{ shipment.carrier ?? 'N/A' }}</span>
                                            <span class="text-gray-600 dark:text-gray-300">
                                                · {{ formatQuantity(shipmentItemQuantity(scope.row, shipment)) }} pza(s)
                                            </span>
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
                            {{ isLocalRequisition
                                ? 'Ningún producto tiene una entrega registrada.'
                                : 'Ningún producto tiene guía de paquetería asignada.' }}
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
                                        <i class="fa-solid fa-truck-fast mr-2"></i>
                                        {{ isLocalRequisition ? 'Entregas de mercancía' : 'Guías de paquetería' }}
                                    </h3>
                                    <SecondaryButton v-if="can.manage_arrivals" @click="openAction('shipment')">
                                        <i class="fa-solid fa-plus mr-2"></i>
                                        {{ isLocalRequisition ? 'Registrar entrega' : 'Ligar guía' }}
                                    </SecondaryButton>
                                </div>

                    <div v-if="requisition.shipments?.length" class="space-y-3">
                        <div v-for="shipment in requisition.shipments" :key="shipment.id"
                            class="border border-gray-200 dark:border-slate-700 rounded-lg p-4 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-center">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ shipment.tracking_number ? 'Número de guía' : 'Forma de entrega' }}
                                    </p>
                                    <p class="font-mono font-semibold dark:text-gray-200">
                                        {{ shipment.tracking_number ?? shipment.delivery_method ?? 'Sin guía' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ shipment.tracking_number ? 'Paquetería' : 'Transportista / referencia' }}
                                    </p>
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
                                    <!-- La guía arribada queda cerrada: su estatus ya no se puede cambiar -->
                                    <el-tooltip v-if="can.manage_arrivals && shipment.status === 'Arribado'"
                                        content="La guía ya fue marcada como arribada; su estatus no puede cambiarse" placement="top">
                                        <el-tag type="success" effect="dark">
                                            <i class="fa-solid fa-lock mr-1"></i> Arribado
                                        </el-tag>
                                    </el-tooltip>
                                    <el-dropdown v-else-if="can.manage_arrivals" @command="status => updateShipmentStatus(shipment.id, status)">
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

                            <div v-if="shipment.notes" class="rounded-lg bg-gray-50 dark:bg-slate-800/60 px-3 py-2">
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Nota del seguimiento</p>
                                <p class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ shipment.notes }}</p>
                            </div>

                            <div class="border-t border-dashed border-gray-200 dark:border-slate-700 pt-2">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Productos en esta guía</p>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-700 dark:text-gray-300">
                                    <li v-for="item in itemsInShipment(shipment.id)" :key="item.id">
                                        <i class="fa-solid fa-box mr-1 text-gray-400"></i>
                                        {{ item.product?.name ?? 'Sin producto de catálogo' }}
                                        <span class="text-gray-500 dark:text-gray-400">
                                            ({{ formatQuantity(shipmentItemQuantity(item, shipment)) }}
                                            de {{ formatQuantity(item.quantity) }})
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Una misma guía o entrega puede agrupar varios productos y requisiciones; todo se muestra en
                            una sola entrada del calendario de arribos.
                        </p>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        {{ isLocalRequisition
                            ? 'Ningún producto de esta requisición tiene una entrega registrada.'
                            : 'Ningún producto de esta requisición está ligado a una guía de paquetería.' }}
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
                    <InputLabel value="Piezas requeridas y costo unitario por producto" />
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                                <div>
                                    <InputLabel value="Cantidad de piezas requeridas" />
                                    <div class="w-full mt-1 rounded-md border border-gray-200 dark:border-slate-700 bg-gray-100 dark:bg-slate-800 px-3 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        {{ formatQuantity(item.quantity) }} pza(s)
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="Costo unitario" />
                                    <TextInput v-model="item.unit_cost" type="number" :min="0" :step="0.01"
                                        class="w-full mt-1" placeholder="0.00" />
                                </div>
                            </div>
                            <p v-if="hasQuotationUnitCost(item)" class="text-xs text-gray-500 dark:text-gray-400">
                                Importe: {{ formatCurrency(quotationItemAmount(item), actionForm.quotation.currency) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <InputLabel :value="actionForm.quotation.tax_enabled ? 'Subtotal cotizado (sin IVA)' : 'Costo total cotizado'" />
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

                <div class="rounded-lg border border-gray-200 dark:border-slate-700 p-3 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">IVA</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Actívalo si la cotización incluye impuesto; el porcentaje es editable.
                            </p>
                        </div>
                        <el-switch v-model="actionForm.quotation.tax_enabled" />
                    </div>
                    <div v-if="actionForm.quotation.tax_enabled" class="space-y-3">
                        <div class="sm:w-48">
                            <InputLabel value="Porcentaje de IVA (%)" />
                            <TextInput v-model="actionForm.quotation.tax_rate" type="number" :min="0" :max="100"
                                :step="0.01" class="w-full mt-1" />
                        </div>
                        <dl class="space-y-1 text-sm">
                            <div class="flex items-center justify-between text-gray-700 dark:text-gray-300">
                                <dt>Subtotal</dt>
                                <dd class="font-semibold">
                                    {{ formatCurrency(quotationPreview.subtotal, actionForm.quotation.currency) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between text-amber-600 dark:text-amber-400">
                                <dt>IVA ({{ quotationPreview.rateLabel }}%)</dt>
                                <dd class="font-semibold">
                                    {{ formatCurrency(quotationPreview.tax, actionForm.quotation.currency) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-200 dark:border-slate-700 pt-1 font-bold text-gray-800 dark:text-gray-100">
                                <dt>Total</dt>
                                <dd>{{ formatCurrency(quotationPreview.total, actionForm.quotation.currency) }}</dd>
                            </div>
                        </dl>
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

        <!-- Detalle de la cotización (acceso rápido desde la guía y la pestaña de pagos) -->
        <el-dialog v-model="modals.quotationSummary" title="Detalle de la cotización" width="760" :center="true">
            <QuotationSummary :requisition="requisition" />
            <template #footer>
                <div class="flex justify-end">
                    <el-button @click="modals.quotationSummary = false">Cerrar</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Validación Pass/Fail -->
        <el-dialog v-model="modals.authorize" title="Validación gerencial" width="760" :center="true">
            <div class="space-y-4">
                <!-- La gerencia revisa aquí la información completa antes de validar -->
                <div v-if="quotationSummaryAvailable" class="rounded-lg border border-gray-200 dark:border-slate-700 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                        <i class="fa-solid fa-file-invoice-dollar mr-1"></i> Información de la cotización
                    </p>
                    <QuotationSummary :requisition="requisition" />
                </div>
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
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <i class="fa-solid fa-circle-info mr-1"></i>
                    La fecha tentativa de arribo se registra después de asignar las guías de paquetería.
                </p>
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
                <p class="md:col-span-2 text-xs text-gray-500 dark:text-gray-400">
                    <i class="fa-solid fa-circle-info mr-1"></i>
                    La fecha tentativa de arribo se registra después de asignar las guías de paquetería.
                </p>
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

        <!-- Fecha de entrega / arribo -->
        <el-dialog v-model="modals.delivery"
            :title="requisition.type === 'Importación' ? 'Fecha tentativa de arribo' : 'Fecha estimada de entrega'"
            width="520" :center="true">
            <div class="space-y-4">
                <div>
                    <InputLabel :value="requisition.type === 'Importación' ? 'Fecha tentativa de arribo *' : 'Fecha estimada *'" />
                    <el-date-picker v-model="actionForm.delivery.expected_delivery_date" type="date" value-format="YYYY-MM-DD"
                        :placeholder="requisition.type === 'Importación' ? 'Con base en las guías asignadas' : 'Considera los tiempos de transporte'"
                        class="w-full mt-1" />
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

        <!-- Entrega de mercancía (guía, proveedor o transporte propio) -->
        <el-dialog v-model="modals.shipment"
            :title="isLocalRequisition ? 'Registrar entrega de mercancía' : 'Asignar productos a guía de paquetería'"
            width="620" :center="true">
            <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-slate-800/60 border border-blue-200 dark:border-slate-700 rounded-lg p-3 space-y-3">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                        Productos seleccionados ({{ selectedItems.length }}):
                    </p>
                    <div v-for="item in selectedItems" :key="item.id"
                        class="border border-blue-200/70 dark:border-slate-700 rounded-lg p-2 bg-white/70 dark:bg-slate-900/40">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">
                                    <i class="fa-solid fa-box mr-1 text-gray-400"></i>
                                    {{ item.product?.name ?? 'Sin producto de catálogo' }}
                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Solicitado: {{ formatQuantity(item.quantity) }} ·
                                    Pendiente: {{ formatQuantity(itemRemainingQuantity(item)) }} ·
                                    Enviadas antes: {{ formatQuantity(itemShippedQuantity(item)) }}
                                </p>
                            </div>
                            <div class="w-full sm:w-32 shrink-0">
                                <InputLabel value="Cantidad en esta guía" />
                                <TextInput v-model="shipmentQuantities[item.id]" type="number" :min="0.01"
                                    :max="itemRemainingQuantity(item)" :step="0.01" class="w-full mt-1"
                                    :placeholder="formatQuantity(itemRemainingQuantity(item))" />
                            </div>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Deja la cantidad vacía para enviar todo lo pendiente. Si el proveedor entrega por
                        parcialidades, registra una guía por cada envío con la cantidad correspondiente.
                    </p>
                </div>

                <!-- Forma de entrega: guía, proveedor o transporte propio -->
                <div>
                    <InputLabel value="Forma de entrega" />
                    <el-radio-group v-model="actionForm.shipment.delivery_method" class="!w-full mt-1"
                        @change="onDeliveryMethodChange">
                        <el-radio-button v-for="method in deliveryMethods" :key="method" :label="method">
                            {{ method }}
                        </el-radio-button>
                    </el-radio-group>
                    <p v-if="actionForm.shipment.delivery_method !== 'Guía'"
                        class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Sin guía: la entrega puede registrarse igual y el estatus se actualiza desde el calendario o esta pestaña.
                    </p>
                </div>

                <template v-if="actionForm.shipment.delivery_method === 'Guía'">
                    <div>
                        <InputLabel value="Guía existente" />
                        <el-select v-model="actionForm.shipment.shipment_id" filterable clearable class="w-full mt-1"
                            placeholder="Busca una guía ya registrada para agrupar productos">
                            <el-option v-for="option in guideOptions" :key="option.id" :value="option.id"
                                :label="`${option.tracking_number} · ${option.carrier ?? 'N/A'} (${option.status})`" />
                        </el-select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Si los productos comparten número de guía con otras requisiciones, selecciónala aquí para no
                            duplicar registros en el calendario.
                        </p>
                    </div>

                    <div class="text-center text-xs text-gray-400">— o registra una guía nueva —</div>
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-if="actionForm.shipment.delivery_method === 'Guía'">
                        <InputLabel value="Número de guía" />
                        <TextInput v-model="actionForm.shipment.tracking_number" class="w-full mt-1"
                            placeholder="Ej. 1234567890" />
                    </div>
                    <div>
                        <InputLabel :value="actionForm.shipment.delivery_method === 'Guía' ? 'Paquetería' : 'Transportista / referencia'" />
                        <TextInput v-model="actionForm.shipment.carrier" class="w-full mt-1"
                            :placeholder="actionForm.shipment.delivery_method === 'Guía' ? 'Ej. DHL, FedEx...' : 'Ej. Proveedor o unidad propia'" />
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

                <div>
                    <InputLabel value="Nota del seguimiento" />
                    <textarea v-model="actionForm.shipment.notes" rows="2"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200 text-sm"
                        placeholder="Ej. En China / Entrega en bodega"></textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Se muestra en el calendario de arribos desde el día en que se registra la entrega.
                    </p>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="modals.shipment = false">Cancelar</el-button>
                    <el-button type="primary" :disabled="!canSubmitShipment" @click="submitShipment">
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
import QuotationSummary from "@/Pages/Requisition/Components/QuotationSummary.vue";
import { router, useForm } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage, ElMessageBox } from 'element-plus';

/*
 * Guías del flujo: describen el siguiente hito y los pasos concretos a seguir en
 * cada etapa, para que cualquier usuario sepa cómo continuar sin perderse.
 * `actionKeys` referencia las acciones del menú "Acciones" que se muestran como
 * botón directo en la tarjeta cuando el usuario tiene permiso para ejecutarlas.
 * `requirements` son los requisitos previos con los que ya debe contar la etapa.
 */

/*
 * Pasos del stepper y avance alcanzado según el estatus. Los pasos reflejan las
 * etapas accionables de la guía (no los estatus internos), de modo que el paso
 * marcado "en proceso" sea siempre la siguiente acción a realizar.
 */
const DEFAULT_TAB = 'items';
const LOCAL_FLOW_STEPS = ['Cotización', 'Autorización', 'Factura', 'Pago', 'Cierre'];
const IMPORT_FLOW_STEPS = ['Negociación', 'Pedido a fábrica', 'Documentación', 'Arribo', 'Cierre'];

const LOCAL_COMPLETED_STEPS = {
    'Pendiente de cotización': 0,
    'Pendiente de autorización': 1,
    'Autorizada': 2,
    'Pendiente de pago': 3,
    'Pagada': 4,
    'Completada': 5,
};

const IMPORT_COMPLETED_STEPS = {
    'Pendiente de negociación': 0,
    'Aprobada': 1,
    'Pedido a fábrica': 2,
    // Con orden de compra vinculada el pedido avanza a documentación.
    'Documentación': 2,
    'En tránsito': 3,
    'Arribo confirmado': 4,
    'Completada': 5,
};

const LOCAL_FLOW_GUIDES = {
    'Pendiente de cotización': {
        tone: 'info',
        icon: 'fa-solid fa-file-invoice-dollar',
        title: 'Cotiza con el proveedor y adjunta la cotización',
        nextStatus: 'Pendiente de autorización',
        owner: 'Compras',
        steps: [
            'Abre el menú «Acciones» de la parte superior y elige «Adjuntar cotización».',
            'Adjunta el archivo de la cotización del proveedor (máximo 10 MB).',
            'Captura el costo unitario y la descripción de cada producto, define la moneda y, si aplica, activa el IVA (16% por defecto, editable).',
            'Guarda: la requisición pasa a «Pendiente de autorización» y se notifica a la gerencia de compras.',
        ],
        actionKeys: ['quotation'],
        hint: 'Este paso lo ejecuta el área de compras. Si no ves el botón, pide que adjunten la cotización.',
    },
    'Pendiente de autorización': {
        tone: 'warning',
        icon: 'fa-solid fa-gavel',
        title: 'La gerencia debe validar la cotización (Pass / Fail)',
        nextStatus: 'Autorizada',
        owner: 'Gerencia de compras',
        steps: [
            'Revisa precios unitarios, subtotal, IVA y total con el botón «Ver cotización» o en la pestaña «Cotización y pagos».',
            'Abre el menú «Acciones» y elige «Validar (Pass / Fail)».',
            'Selecciona «Pass» para autorizar la compra: la requisición pasa a «Autorizada».',
            'Si seleccionas «Fail», escribe el motivo del rechazo: la requisición pasa a «Rechazada» y el solicitante deberá corregirla.',
        ],
        actionKeys: ['quotation', 'authorize'],
        requirements: [{ key: 'quotation', label: 'Cotización adjunta' }],
        hint: 'Solo la gerencia de compras puede registrar esta validación.',
    },
    'Autorizada': {
        tone: 'info',
        icon: 'fa-solid fa-file-invoice',
        title: 'Adjunta la factura del proveedor',
        nextStatus: 'Pendiente de pago',
        owner: 'Compras',
        steps: [
            'Solicita al proveedor la factura correspondiente a la compra autorizada.',
            'Abre el menú «Acciones» y elige «Adjuntar factura».',
            'Sube el archivo de la factura y captura el monto y la moneda.',
            'Guarda: la requisición pasa a «Pendiente de pago» y se notifica a Pagos.',
        ],
        actionKeys: ['invoice'],
        hint: 'Este paso lo ejecuta el área de compras o quien dio seguimiento a la requisición.',
    },
    'Pendiente de pago': {
        tone: 'warning',
        icon: 'fa-solid fa-money-bill-wave',
        title: 'Tesorería debe registrar el pago al proveedor',
        nextStatus: 'Pagada',
        owner: 'Pagos',
        steps: [
            'Verifica que la factura esté adjunta en la pestaña «Documentos».',
            'Abre el menú «Acciones» y elige «Registrar pago».',
            'Adjunta el comprobante de pago y define la fecha en que se realizó el pago.',
            'Guarda: la requisición pasa a «Pagada» y se comparte el comprobante con el proveedor.',
        ],
        actionKeys: ['payment'],
        requirements: [{ key: 'invoice', label: 'Factura adjunta' }],
        hint: 'Solo Pagos puede registrar el pago. Si falta el comprobante, solicítalo al área de pagos.',
    },
    'Pagada': {
        tone: 'success',
        icon: 'fa-solid fa-flag-checkered',
        title: 'Registra la fecha de entrega y cierra la requisición',
        nextStatus: 'Completada',
        owner: 'Compras',
        steps: [
            'Acuerda con el proveedor la fecha en que entregará la mercancía.',
            'Registra la entrega desde la pestaña «Productos»: con guía de paquetería, entrega directa del proveedor o transporte propio de la empresa.',
            'Abre el menú «Acciones» y elige «Fecha de entrega» para registrarla.',
            'Cuando la mercancía esté recibida y verificada, elige «Completar requisición» y confirma la operación.',
            'La requisición pasa a «Completada» y el flujo termina.',
        ],
        actionKeys: ['delivery', 'complete'],
        requirements: [{ key: 'payment', label: 'Comprobante de pago adjunto' }],
        hint: 'Completar la requisición cierra el flujo; hazlo únicamente cuando la mercancía esté recibida.',
    },
};

const IMPORT_FLOW_GUIDES = {
    'Pendiente de negociación': {
        tone: 'info',
        icon: 'fa-solid fa-file-invoice-dollar',
        title: 'Negocia con la fábrica y adjunta la cotización',
        nextStatus: 'Aprobada',
        owner: 'Gerencia de compras',
        steps: [
            'Negocia con la fábrica precios, tiempos de producción y condiciones de embarque.',
            'Abre el menú «Acciones» y elige «Adjuntar cotización» para subir el documento.',
            'Captura el costo unitario por producto, la moneda y el IVA si aplica; guarda los cambios.',
            'Después abre «Acciones» → «Validar (Pass / Fail)» y selecciona «Pass»: la requisición pasa a «Aprobada».',
        ],
        actionKeys: ['quotation', 'authorize'],
        hint: 'La cotización de importación la captura la gerencia de compras y no cambia la etapa por sí sola.',
    },
    'Aprobada': {
        tone: 'info',
        icon: 'fa-solid fa-industry',
        title: 'Registra el pedido a fábrica',
        nextStatus: 'Pedido a fábrica',
        owner: 'Gerencia de compras',
        steps: [
            'Confirma con la fábrica los productos, cantidades y fechas de producción.',
            'Abre el menú «Acciones» y elige «Pedido a fábrica».',
            'Captura la fecha estimada de entrega y las notas para la fábrica.',
            'Guarda: la requisición pasa a «Pedido a fábrica» y comienza el seguimiento documental.',
        ],
        actionKeys: ['factory', 'docs'],
        requirements: [{ key: 'quotation', label: 'Cotización adjunta' }],
        hint: 'Este paso lo registra la gerencia de compras o Usuarios con permiso de gestión de compras.',
    },
    'Pedido a fábrica': {
        tone: 'info',
        icon: 'fa-solid fa-box-open',
        title: 'Adjunta la documentación de seguimiento de la fábrica',
        nextStatus: 'En tránsito',
        owner: 'Compras/Gerencia de compras',
        steps: [
            'Solicita a la fábrica el packing list, el invoice, las fichas técnicas y las fotos de la mercancía.',
            'Abre el menú «Acciones» y elige «Documentación de fábrica».',
            'Adjunta los archivos disponibles; el packing list es indispensable para avanzar.',
            'Guarda: al cargar el packing list la requisición pasa a «En tránsito».',
        ],
        actionKeys: ['docs'],
        hint: 'Adjunta al menos un documento; sin packing list la requisición no avanza a «En tránsito».',
    },
    'Documentación': {
        tone: 'info',
        icon: 'fa-solid fa-box-open',
        title: 'Adjunta la documentación de seguimiento de la fábrica',
        nextStatus: 'En tránsito',
        owner: 'Compras/Gerencia de compras',
        steps: [
            'La orden de compra ya está vinculada: solicita a la fábrica el packing list, el invoice, las fichas técnicas y las fotos de la mercancía.',
            'Abre el menú «Acciones» y elige «Documentación de fábrica».',
            'Adjunta los archivos disponibles; el packing list es indispensable para avanzar.',
            'Guarda: al cargar el packing list la requisición pasa a «En tránsito».',
        ],
        actionKeys: ['docs'],
        hint: 'Adjunta al menos un documento; sin packing list la requisición no avanza a «En tránsito».',
    },
    'En tránsito': {
        tone: 'warning',
        icon: 'fa-solid fa-truck-fast',
        title: 'Asigna las guías y confirma el arribo de la mercancía',
        nextStatus: 'Arribo confirmado',
        owner: 'Compras',
        steps: [
            'Ve a la pestaña «Productos» y marca con las casillas las partidas que viajan juntas.',
            'Presiona «Asignar a guía» y captura número de guía, paquetería, estatus y fecha estimada de arribo. Si el envío es parcial, indica la cantidad que llega en esa guía.',
            'Repite la asignación con el resto de las partidas; puedes reutilizar una guía ya creada.',
            'Con las guías asignadas ya puedes registrar la fecha tentativa de llegada desde «Acciones» → «Fecha tentativa de arribo».',
            'En la pestaña «Guía», cambia el estatus a «Arribado» cuando la mercancía llegue a destino.',
            'Cuando todas las partidas estén como «Arribado», la requisición pasa a «Arribo confirmado».',
        ],
        actionKeys: ['shipment', 'delivery', 'docs'],
        requirements: [
            { key: 'packing_list', label: 'Packing list adjunto' },
            { key: 'shipment', label: 'Guías asignadas' },
        ],
        hint: 'La asignación de guía requiere seleccionar antes las partidas en la pestaña «Productos».',
    },
    'Arribo confirmado': {
        tone: 'success',
        icon: 'fa-solid fa-flag-checkered',
        title: 'Verifica la recepción y cierra la requisición',
        nextStatus: 'Completada',
        owner: 'Almacén',
        steps: [
            'Confirma en la pestaña «Productos» que todas las partidas estén marcadas como «Arribado».',
            'Si alguna presenta faltantes o daños, regístralo en «Comentarios» y ajusta su estatus de arribo.',
            'Abre el menú «Acciones» y elige «Completar requisición».',
            'Confirma la operación: la requisición pasa a «Completada» y el flujo termina.',
        ],
        actionKeys: ['complete', 'delivery'],
        requirements: [
            { key: 'shipment', label: 'Guías asignadas' },
            { key: 'arrival', label: 'Partidas marcadas como arribadas' },
        ],
        hint: 'Completar la requisición cierra el flujo; verifica antes la recepción física de la mercancía.',
    },
};

/**
 * Guía para requisiciones rechazadas: el folio se conserva y el flujo reinicia.
 */
function rejectedFlowGuide(isImport) {
    // Importación: la gerencia puede volver a validar sin editar (la cotización no cambia la etapa).
    const steps = isImport
        ? [
            'Lee el motivo del rechazo indicado en el aviso rojo de la parte superior.',
            'Si el problema fue el documento, elimina la «Cotización» en la pestaña «Documentos» y vuelve a subirla con «Acciones» → «Adjuntar cotización».',
            'Si hay datos por corregir, presiona «Editar requisición»: al guardar se conserva el folio y regresa a «Pendiente de negociación».',
            'Cuando esté lista, registra otra vez la validación con «Acciones» → «Validar (Pass / Fail)».',
            'Con «Pass» la requisición pasa a «Aprobada» y sigue con el pedido a fábrica; con «Fail» se rechaza de nuevo y se actualiza el motivo.',
        ]
        : [
            'Lee el motivo del rechazo indicado en el aviso rojo de la parte superior.',
            'Si el problema fue el documento, elimina la «Cotización» en la pestaña «Documentos» y vuelve a subirla con «Acciones» → «Adjuntar cotización».',
            'O presiona «Editar requisición» y corrige lo señalado: productos, cantidades, especificación o archivos.',
            'Al guardar se conserva el folio y la requisición regresa al inicio del flujo.',
            'Al adjuntar la cotización pasa a «Pendiente de autorización», donde la gerencia registra la validación.',
        ];

    return {
        tone: 'danger',
        icon: 'fa-solid fa-triangle-exclamation',
        title: 'Corrige la requisición para reactivar el flujo',
        nextStatus: isImport ? 'Aprobada' : 'Pendiente de cotización',
        owner: isImport ? 'Gerencia de compras / Solicitante' : 'Solicitante',
        steps,
        actionKeys: isImport ? ['authorize', 'quotation'] : ['quotation'],
        editAction: true,
        hint: 'Solo el solicitante o un usuario con permiso de edición puede corregir la requisición.',
    };
}

/**
 * Guía mostrada cuando el flujo ya terminó (sin acciones pendientes).
 */
function completedFlowGuide(isImport) {
    return {
        tone: 'success',
        icon: 'fa-solid fa-circle-check',
        title: 'Flujo finalizado',
        nextStatus: null,
        owner: 'Flujo cerrado',
        steps: [
            'La requisición está cerrada y la mercancía fue recibida.',
            isImport
                ? 'Consulta la pestaña «Documentos» para revisar packing list, invoice de fábrica, fichas técnicas y fotos.'
                : 'Consulta la pestaña «Documentos» para revisar cotización, factura y comprobante de pago.',
            'Usa la pestaña «Comentarios» si necesitas dejar alguna nota o seguimiento posterior.',
        ],
        actionKeys: [],
        hint: 'No hay acciones pendientes en esta requisición. Si requieres más mercancía, crea una requisición nueva.',
    };
}

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
        QuotationSummary,
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
        // Formas de entrega disponibles (guía, proveedor, transporte propio).
        deliveryMethods: {
            type: Array,
            default: () => ['Guía', 'Proveedor', 'Transporte propio'],
        },
    },
    data() {
        return {
            // Pestaña activa: se sincroniza con el parámetro ?tab= de la URL.
            activeTab: DEFAULT_TAB,
            selectedItems: [],
            // Cantidad a enviar por producto al asignar una guía (parcialidades).
            shipmentQuantities: {},
            // La sección de ayuda "¿Qué sigue?" inicia plegada (solo el encabezado).
            guideExpanded: false,
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
                quotationSummary: false,
                authorize: false,
                factory: false,
                docs: false,
                invoice: false,
                payment: false,
                delivery: false,
                shipment: false,
            },
            actionForm: {
                quotation: {
                    file: null,
                    cost: '',
                    currency: 'MXN',
                    tax_enabled: false,
                    tax_rate: 16,
                    items: this.initialQuotationItems(),
                },
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
                    delivery_method: 'Guía',
                    status: 'Pendiente',
                    estimated_arrival_date: null,
                    notes: '',
                },
            },
            commentForm: useForm({ content: '', mentioned_user_ids: [] }),
        };
    },
    created() {
        // Se respeta la pestaña indicada en la URL (enlaces de notificaciones, recargas).
        this.activeTab = this.resolveTab(this.$page?.url);
    },
    watch: {
        // Al navegar entre requisiciones se vuelve a evaluar la pestaña de la URL.
        '$page.url'(url) {
            this.activeTab = this.resolveTab(url);
        },
    },
    computed: {
        tabs() {
            return [
                { key: 'items', label: 'Productos', icon: 'fa-solid fa-boxes-stacked', count: this.requisition.items?.length ?? 0 },
                { key: 'documents', label: 'Documentos', icon: 'fa-solid fa-folder-open', count: this.documentCount },
                { key: 'payments', label: 'Cotización y pagos', icon: 'fa-solid fa-coins' },
                { key: 'shipments', label: 'Guía', icon: 'fa-solid fa-truck-fast', count: this.requisition.shipments?.length ?? 0 },
                { key: 'comments', label: 'Comentarios/Historial', icon: 'fa-solid fa-comments', count: this.requisition.comments?.length ?? 0 },
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
                {
                    key: 'delivery',
                    label: this.requisition.type === 'Importación' ? 'Fecha tentativa de arribo' : 'Fecha de entrega',
                    icon: 'fa-solid fa-calendar-days',
                    show: this.can.register_delivery,
                },
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
        // El desglose está disponible en cuanto hay archivo o costo registrado.
        quotationSummaryAvailable() {
            return this.hasQuotation || this.quotationHasStoredSubtotal;
        },
        quotationHasStoredSubtotal() {
            const cost = this.requisition.quoted_cost;

            return cost !== null && cost !== undefined && cost !== '';
        },
        quotationHasTax() {
            return Boolean(this.requisition.quoted_tax_enabled);
        },
        quotationTaxRateLabel() {
            return Number(this.requisition.quoted_tax_rate ?? 16)
                .toLocaleString('es-MX', { maximumFractionDigits: 2 });
        },
        // Subtotal: costo capturado o, en su defecto, la suma de precio unitario × cantidad.
        quotationSummarySubtotal() {
            if (this.quotationHasStoredSubtotal) {
                return Number(this.requisition.quoted_cost) || 0;
            }

            return (this.requisition.items ?? []).reduce((sum, item) => {
                if (item.unit_cost === null || item.unit_cost === undefined || item.unit_cost === '') {
                    return sum;
                }

                return sum + (Number(item.unit_cost) || 0) * (Number(item.quantity) || 0);
            }, 0);
        },
        quotationSummaryTax() {
            if (!this.quotationHasTax) return 0;

            const stored = Number(this.requisition.quoted_tax_amount);
            if (this.quotationHasStoredSubtotal && Number.isFinite(stored)) {
                return stored;
            }

            return Math.round(this.quotationSummarySubtotal * Number(this.requisition.quoted_tax_rate ?? 16)) / 100;
        },
        quotationSummaryTotal() {
            const stored = Number(this.requisition.quoted_total);
            if (this.quotationHasTax && this.quotationHasStoredSubtotal && Number.isFinite(stored)) {
                return stored;
            }

            return Math.round((this.quotationSummarySubtotal + this.quotationSummaryTax) * 100) / 100;
        },
        // Vista previa del desglose mientras se captura la cotización.
        quotationPreview() {
            const subtotal = Number(this.actionForm.quotation.cost) || 0;
            const rawRate = this.actionForm.quotation.tax_rate;
            const rate = rawRate === '' || rawRate === null ? 0 : Number(rawRate) || 0;
            const tax = Math.round(subtotal * rate) / 100;

            return {
                subtotal,
                tax,
                total: subtotal + tax,
                rateLabel: rate.toLocaleString('es-MX', { maximumFractionDigits: 2 }),
            };
        },
        // Urgente solo se distingue mientras la requisición siga abierta.
        isUrgentActive() {
            return this.requisition.priority === 'Urgente' && this.requisition.status !== 'Completada';
        },
        isLocalRequisition() {
            return this.requisition.type === 'Local';
        },
        // Solo las guías con número se pueden reutilizar.
        guideOptions() {
            return this.shipmentOptions.filter(option => option.tracking_number);
        },
        // La entrega por proveedor o transporte propio no requiere número de guía.
        canSubmitShipment() {
            if (!this.selectedItems.length) return false;

            if (this.actionForm.shipment.delivery_method !== 'Guía') return true;

            return Boolean(this.actionForm.shipment.shipment_id || this.actionForm.shipment.tracking_number);
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
        // Órdenes de compra vinculadas a la requisición.
        requisitionPurchases() {
            return this.requisition.purchases ?? [];
        },
        /*
         * Distribución de la compra por producto: cuánto viaja por avión, por
         * barco y cuánto se queda en fábrica. Se agrupa por producto para que la
         * partida de la requisición siempre muestre su reparto.
         */
        purchaseDistributionByProduct() {
            const distribution = {};

            this.requisitionPurchases.forEach(purchase => {
                (purchase.items ?? []).forEach(item => {
                    if (!item.product_id) return;

                    const current = distribution[item.product_id] ?? { plane: 0, ship: 0, factory: 0, total: 0 };

                    current.plane += Number(item.plane_stock) || 0;
                    current.ship += Number(item.ship_stock) || 0;
                    current.factory += Number(item.additional_stock) || 0;
                    current.total += Number(item.quantity) || 0;

                    distribution[item.product_id] = current;
                });
            });

            return distribution;
        },
        // Renglones de la tarjeta "Distribución de la compra".
        purchaseDistributionRows() {
            const rows = [];

            this.requisitionPurchases.forEach(purchase => {
                (purchase.items ?? []).forEach(item => {
                    rows.push({
                        key: `${purchase.id}-${item.id}`,
                        folio: purchase.folio,
                        name: item.product?.name ?? item.description ?? 'Producto',
                        code: item.product?.code ?? null,
                        image_url: item.product?.media?.[0]?.original_url ?? null,
                        total: Number(item.quantity) || 0,
                        plane: Number(item.plane_stock) || 0,
                        ship: Number(item.ship_stock) || 0,
                        factory: Number(item.additional_stock) || 0,
                    });
                });
            });

            return rows;
        },
        // Partidas sin guía y con guía, para separarlas visualmente. Con
        // parcialidades, la partida sigue pendiente hasta cubrir su cantidad.
        unassignedItems() {
            return (this.requisition.items ?? []).filter(item => this.itemRemainingQuantity(item) > 0);
        },
        assignedItems() {
            return (this.requisition.items ?? []).filter(item => (item.shipments ?? []).length > 0);
        },
        stepperSteps() {
            return this.requisition.type === 'Importación' ? IMPORT_FLOW_STEPS : LOCAL_FLOW_STEPS;
        },
        // Pasos ya logrados según el estatus actual (el siguiente queda en proceso).
        stepperCompletedCount() {
            const progress = this.requisition.type === 'Importación' ? IMPORT_COMPLETED_STEPS : LOCAL_COMPLETED_STEPS;

            return progress[this.requisition.status] ?? 0;
        },
        /*
         * Guía del flujo: indica el siguiente hito, quién lo ejecuta y los pasos
         * concretos a seguir, además de las acciones que el usuario puede abrir
         * directamente desde la tarjeta.
         */
        nextStepGuide() {
            const status = this.requisition.status;
            const isImport = this.requisition.type === 'Importación';
            const source = isImport ? IMPORT_FLOW_GUIDES : LOCAL_FLOW_GUIDES;

            const guide = status === 'Rechazada'
                ? rejectedFlowGuide(isImport)
                : status === 'Completada'
                    ? completedFlowGuide(isImport)
                    : source[status];

            if (!guide) return null;

            // Solo se ofrecen las acciones que el usuario puede ejecutar en esta etapa,
            // respetando el orden en que aparecen dentro de la guía.
            const available = new Map(this.flowActions.map(action => [action.key, action]));
            const actions = (guide.actionKeys ?? []).map(key => available.get(key)).filter(Boolean);

            if (guide.editAction && this.can.edit) {
                actions.unshift({ key: 'edit', label: 'Editar requisición', icon: 'fa-solid fa-pen-to-square' });
            }

            // Atajo para levantar la orden de compra desde la requisición.
            if (this.can.create_purchase_order) {
                actions.push({
                    key: 'purchase_order',
                    label: 'Crear orden de compra',
                    icon: 'fa-solid fa-file-invoice',
                });
            }

            return {
                ...guide,
                styles: this.guideStyles(guide.tone),
                actions,
                requirements: (guide.requirements ?? []).map(requirement => ({
                    label: requirement.label,
                    met: this.guideRequirementMet(requirement.key),
                })),
                hint: guide.hint ?? null,
            };
        },
        documentGroups() {
            const media = this.requisition.media ?? [];
            const byCollection = (collection) => media.filter(file => file.collection_name === collection);

            const groups = [
                { label: 'Formato de autorización', collection: 'authorization_formats', files: byCollection('authorization_formats') },
                { label: 'Plano / render', collection: 'blueprints', files: byCollection('blueprints') },
            ];

            if (this.requisition.type === 'Importación') {
                // La cotización de fábrica se captura en la etapa de negociación.
                groups.push({ label: 'Cotización', collection: 'quotations', files: byCollection('quotations') });
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
                quantity: Number(item.quantity) || 0,
                unit_cost: item.unit_cost ?? '',
                description: item.description ?? '',
            }));
        },
        hasQuotationUnitCost(item) {
            return item.unit_cost !== null && item.unit_cost !== undefined && item.unit_cost !== '';
        },
        // Importe de la partida cotizada: piezas requeridas por costo unitario.
        quotationItemAmount(item) {
            return (Number(item.unit_cost) || 0) * (Number(item.quantity) || 0);
        },
        itemsInShipment(shipmentId) {
            return (this.requisition.items ?? []).filter(item =>
                (item.shipments ?? []).some(shipment => shipment.id === shipmentId));
        },
        // Cantidad de la partida que viaja en una guía (parcialidad).
        shipmentItemQuantity(item, shipment) {
            const quantity = shipment?.pivot?.quantity;

            return quantity !== null && quantity !== undefined ? Number(quantity) : Number(item.quantity) || 0;
        },
        // Total ya asignado a guías de la partida.
        itemShippedQuantity(item) {
            return (item.shipments ?? []).reduce(
                (total, shipment) => total + this.shipmentItemQuantity(item, shipment),
                0,
            );
        },
        // Cantidad pendiente de asignar a una guía.
        itemRemainingQuantity(item) {
            return Math.max(0, (Number(item.quantity) || 0) - this.itemShippedQuantity(item));
        },
        // Reparto de la compra de la partida (avión / barco / fábrica).
        itemPurchaseDistribution(item) {
            if (!item?.product_id) return null;

            return this.purchaseDistributionByProduct[item.product_id] ?? null;
        },
        hasPurchaseDistribution(distribution) {
            return Boolean(distribution && (distribution.plane || distribution.ship || distribution.factory));
        },
        openShipmentModal() {
            if (!this.selectedItems.length) {
                ElMessage.warning('Selecciona en la tabla los productos que viajan en esta entrega.');
                return;
            }

            this.shipmentQuantities = {};
            this.openAction('shipment');
        },
        // Al cambiar a una entrega sin guía se limpian los datos de paquetería.
        onDeliveryMethodChange(method) {
            if (method === 'Guía') return;

            this.actionForm.shipment.shipment_id = null;
            this.actionForm.shipment.tracking_number = '';
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
                'Documentación': 'primary',
                'En tránsito': 'warning',
            };
            return map[status] ?? 'info';
        },
        getPriorityTagType(priority) {
            const map = { Baja: 'info', Media: 'primary', Urgente: 'danger' };
            return map[priority] ?? 'info';
        },
        openAction(action) {
            this.modals[action] = true;
        },
        openQuotationSummary() {
            this.modals.quotationSummary = true;
        },
        // Pestaña válida a partir del parámetro ?tab= de la URL.
        resolveTab(url) {
            try {
                // `window.location.href` sirve como base tanto para rutas absolutas como relativas.
                const requested = new URL(url ?? '', window.location.href).searchParams.get('tab');

                return this.tabs.some(tab => tab.key === requested) ? requested : DEFAULT_TAB;
            } catch (error) {
                return DEFAULT_TAB;
            }
        },
        // Cambia de pestaña y la refleja en la URL para conservarla al recargar o compartir.
        selectTab(key) {
            if (!this.tabs.some(tab => tab.key === key)) return;

            this.activeTab = key;

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', key);
                // replaceState conserva el historial de Inertia y evita recargar la página.
                window.history.replaceState(window.history.state, '', url);
            } catch (error) {
                // Sin History API la pestaña sigue funcionando en memoria.
            }
        },
        // Estilos por tono de la tarjeta "¿Qué sigue?".
        guideStyles(tone) {
            const map = {
                info: {
                    border: 'border-l-sky-500',
                    icon: 'bg-sky-100 text-sky-600 dark:bg-sky-900/40 dark:text-sky-300',
                    accent: 'text-sky-600 dark:text-sky-300',
                    step: 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300',
                    tag: 'primary',
                },
                warning: {
                    border: 'border-l-amber-500',
                    icon: 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300',
                    accent: 'text-amber-600 dark:text-amber-300',
                    step: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
                    tag: 'warning',
                },
                success: {
                    border: 'border-l-emerald-500',
                    icon: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300',
                    accent: 'text-emerald-600 dark:text-emerald-300',
                    step: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
                    tag: 'success',
                },
                danger: {
                    border: 'border-l-red-500',
                    icon: 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300',
                    accent: 'text-red-600 dark:text-red-300',
                    step: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
                    tag: 'danger',
                },
            };

            return map[tone] ?? map.info;
        },
        // Indica si la etapa ya cuenta con el requisito previo indicado.
        guideRequirementMet(key) {
            const media = this.requisition.media ?? [];
            const hasFile = collection => media.some(file => file.collection_name === collection);

            switch (key) {
                case 'quotation':
                    return this.hasQuotation;
                case 'packing_list':
                    return hasFile('packing_lists');
                case 'invoice':
                    return hasFile('invoices');
                case 'payment':
                    return hasFile('payment_receipts');
                case 'shipment':
                    return (this.requisition.shipments ?? []).length > 0;
                case 'arrival':
                    return (this.requisition.items ?? []).some(item => item.arrival_status === 'Arribado');
                default:
                    return false;
            }
        },
        // Acción disparada desde la tarjeta "¿Qué sigue?".
        runGuideAction(action) {
            if (action === 'edit') {
                this.handleManageCommand('edit');
                return;
            }

            if (action === 'purchase_order') {
                this.createPurchaseOrder();
                return;
            }

            this.handleFlowCommand(action);
        },
        // Levanta la orden de compra enviando el folio de la requisición.
        createPurchaseOrder() {
            router.visit(route('purchases.create', { requisition_id: this.requisition.id }));
        },
        // Grupo de acciones del flujo (menú "Acciones").
        async handleFlowCommand(command) {
            if (command === 'complete') {
                await this.confirmComplete();
                return;
            }

            if (command === 'shipment' && !this.selectedItems.length) {
                this.selectTab('items');
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
            const quotation = this.actionForm.quotation;
            const taxRate = quotation.tax_rate === '' || quotation.tax_rate === null ? 16 : quotation.tax_rate;

            router.post(route('requisitions.upload-quotation', this.requisition.id), {
                quotation: quotation.file,
                quoted_cost: quotation.cost || null,
                quoted_currency: quotation.currency || null,
                quoted_tax_enabled: quotation.tax_enabled ? 1 : 0,
                quoted_tax_rate: quotation.tax_enabled ? taxRate : 16,
                items: quotation.items.map(item => ({
                    id: item.id,
                    unit_cost: item.unit_cost === '' ? null : item.unit_cost,
                    description: item.description || null,
                })),
            }, {
                forceFormData: true,
                onSuccess: () => {
                    this.modals.quotation = false;
                    quotation.tax_enabled = false;
                    quotation.tax_rate = 16;
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
            const entries = this.selectedItems.map(item => {
                const raw = this.shipmentQuantities[item.id];
                const quantity = raw === '' || raw === null || raw === undefined ? null : Number(raw);

                return {
                    id: item.id,
                    quantity,
                    available: this.itemRemainingQuantity(item),
                    label: item.product?.name ?? item.description ?? `Partida #${item.id}`,
                };
            });

            const invalid = entries.find(entry =>
                entry.quantity !== null && (Number.isNaN(entry.quantity) || entry.quantity <= 0 || entry.quantity > entry.available));

            if (invalid) {
                ElMessage.warning(`La cantidad de «${invalid.label}» debe ser mayor a 0 y máximo ${invalid.available}.`);
                return;
            }

            router.post(route('requisitions.assign-shipment', this.requisition.id), {
                items: entries.map(({ id, quantity }) => ({ id, quantity })),
                shipment_id: form.delivery_method === 'Guía' ? form.shipment_id : null,
                tracking_number: form.delivery_method === 'Guía' && !form.shipment_id ? form.tracking_number : null,
                carrier: form.carrier,
                delivery_method: form.delivery_method,
                status: form.status,
                estimated_arrival_date: form.estimated_arrival_date,
                notes: form.notes || null,
            }, {
                onSuccess: () => {
                    this.modals.shipment = false;
                    this.selectedItems = [];
                    this.shipmentQuantities = {};
                    form.notes = '';
                    form.shipment_id = null;
                    form.tracking_number = '';
                    form.carrier = '';
                    form.delivery_method = 'Guía';
                    this.handleSuccess('Entrega registrada para los producto(s).');
                },
                onError: (errors) => ElMessage.error(Object.values(errors)[0] ?? 'No se pudo registrar la entrega.'),
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

<style>
/* ===== Requisiciones urgentes: distintivo con pulso ===== */
@keyframes priority-badge-pulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, .5); }
    50%      { transform: scale(1.04); box-shadow: 0 0 0 6px rgba(220, 38, 38, 0); }
}

.priority-badge {
    animation: priority-badge-pulse 2s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .priority-badge { animation: none; }
}
</style>
