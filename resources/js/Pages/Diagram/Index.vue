<template>
    <Head title="Diagrama del proceso de venta" />

    <div class="min-h-screen bg-slate-100 text-slate-800">
        <!-- ===== BARRA SUPERIOR / CONTROLES ===== -->
        <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 shadow-sm no-print">
            <div class="max-w-[100rem] mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="size-10 rounded-xl bg-sky-600 text-white flex items-center justify-center shadow">
                        <i class="fa-solid fa-diagram-project"></i>
                    </span>
                    <div>
                        <h1 class="font-bold text-lg leading-tight">Flujo operativo de la Orden de Venta</h1>
                        <p class="text-xs text-slate-500">Cotización → OV → autorización → inventario → producción → envío → cobranza</p>
                    </div>
                </div>

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <!-- Selector de vista -->
                    <div class="flex items-center rounded-lg border border-slate-300 bg-white overflow-hidden text-[11px] font-bold">
                        <button class="px-3 py-1.5 transition-colors inline-flex items-center gap-1.5"
                            :class="viewMode === 'timeline' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            @click="viewMode = 'timeline'">
                            <i class="fa-solid fa-list-ol"></i> Proceso
                        </button>
                        <button class="px-3 py-1.5 transition-colors inline-flex items-center gap-1.5"
                            :class="viewMode === 'symbols' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            @click="viewMode = 'symbols'">
                            <i class="fa-solid fa-diagram-project"></i> Símbolos
                        </button>
                    </div>

                    <!-- Zoom -->
                    <div class="flex items-center rounded-lg border border-slate-300 bg-white overflow-hidden text-sm">
                        <button class="px-2.5 py-1.5 hover:bg-slate-100 disabled:opacity-40" :disabled="zoom <= 80" title="Alejar" @click="changeZoom(-10)">−</button>
                        <span class="px-2 text-xs font-semibold w-12 text-center">{{ zoom }}%</span>
                        <button class="px-2.5 py-1.5 hover:bg-slate-100 disabled:opacity-40" :disabled="zoom >= 120" title="Acercar" @click="changeZoom(10)">+</button>
                    </div>

                    <button v-if="viewMode === 'timeline'"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors"
                        :class="showTechnical ? 'bg-slate-800 text-white border-slate-800 hover:bg-slate-700' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'"
                        @click="showTechnical = !showTechnical">
                        <i class="fa-solid" :class="showTechnical ? 'fa-eye' : 'fa-eye-slash'"></i>
                        {{ showTechnical ? 'Ocultar detalle técnico' : 'Ver detalle técnico' }}
                    </button>

                    <button class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-sky-600 text-white text-xs font-semibold hover:bg-sky-700 transition-colors"
                        @click="printDiagram">
                        <i class="fa-solid fa-print"></i> Imprimir / PDF
                    </button>

                    <Link :href="route('dashboard')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white text-slate-600 text-xs font-semibold border border-slate-300 hover:bg-slate-50 transition-colors">
                        <i class="fa-solid fa-arrow-left"></i> Volver
                    </Link>
                </div>
            </div>

            <!-- Filtros por área -->
            <div v-if="viewMode === 'timeline'" class="max-w-[100rem] mx-auto px-4 pb-3 flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wide text-slate-400 mr-1">Filtrar:</span>
                <button v-for="area in areas" :key="area.key"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-semibold transition-all"
                    :class="activeArea === area.key ? area.badge + ' ring-2 ring-offset-1 ring-slate-300 shadow-sm' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'"
                    @click="activeArea = area.key">
                    <i :class="area.icon" class="text-[10px]"></i> {{ area.label }}
                </button>
            </div>
        </header>

        <main class="max-w-[100rem] mx-auto px-4 py-6">
            <!-- ===== CINTA DE ESTATUS DE LA OV ===== -->
            <section class="mb-5 bg-white rounded-xl border border-slate-200 shadow-sm p-4 print-break-avoid">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">
                        <i class="fa-solid fa-route mr-1 text-sky-600"></i> Estatus de la Orden de Venta
                    </h2>
                    <span class="text-[11px] text-slate-400">Se recalculan automáticamente con <code class="font-mono">Sale::updateStatus()</code></span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <template v-for="(status, index) in statusFlow" :key="status.label">
                        <span class="px-3 py-1 rounded-full text-xs font-bold border" :class="status.classes">{{ status.label }}</span>
                        <i v-if="index < statusFlow.length - 1" class="fa-solid fa-arrow-right text-[10px] text-slate-300"></i>
                    </template>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-[11px] text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-teal-500"></span> OS- (stock): al terminar producción pasa a “Stock Terminado”.</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-slate-400"></span> “Cancelada” solo aparece en registros históricos importados.</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-amber-500"></span> “En Proceso” aplica cuando no hay producción y la logística ya avanzó.</span>
                </div>
            </section>

            <!-- ===== LEYENDA ===== -->
            <section class="mb-5 flex flex-wrap items-center gap-3 text-[11px] text-slate-500 print-break-avoid">
                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-emerald-500"></span> Movimiento de <strong>Entrada</strong></span>
                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-rose-500"></span> Movimiento de <strong>Salida</strong></span>
                <span class="inline-flex items-center gap-1.5"><i class="fa-regular fa-bell text-amber-500"></i> Notificación / correo</span>
                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-code-branch text-amber-500"></i> Punto de decisión</span>
            </section>

            <!-- ===== DIAGRAMA ===== -->
            <div id="diagram-zoom" :style="{ zoom: zoom / 100 }">
                <!-- VISTA 1: PROCESO (etapas) -->
                <div v-if="viewMode === 'timeline'" id="diagram-canvas" class="diagram-canvas">
                <div class="relative">
                    <!-- Línea conectora principal -->
                    <div class="absolute left-[22px] top-4 bottom-4 w-0.5 bg-slate-300" aria-hidden="true"></div>

                    <section v-for="stage in stages" :key="stage.id"
                        class="diagram-stage relative pl-16 pb-6 print-break-avoid transition-all duration-300"
                        :class="isDimmed(stage) ? 'opacity-25 grayscale' : 'opacity-100'">
                        <!-- Nodo numerado -->
                        <div class="absolute left-0 top-2 size-11 rounded-full ring-4 ring-slate-100 flex items-center justify-center text-white font-bold shadow"
                            :class="styleOf(stage.area).dot">
                            {{ stage.number }}
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                            <!-- Encabezado de la etapa -->
                            <div class="px-4 py-3 flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border text-[11px] font-bold uppercase tracking-wide"
                                    :class="styleOf(stage.area).badge">
                                    <i :class="stage.icon" class="text-[10px]"></i> {{ stage.areaLabel }}
                                </span>
                                <h2 class="font-bold text-base md:text-lg text-slate-800">{{ stage.title }}</h2>
                                <span class="ml-auto text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700">
                                    {{ stage.status }}
                                </span>
                            </div>

                            <div class="p-4 grid grid-cols-1 xl:grid-cols-3 gap-4">
                                <!-- Pasos del proceso -->
                                <div :class="showTechnical ? 'xl:col-span-2' : 'xl:col-span-3'">
                                    <p class="text-[11px] uppercase font-bold tracking-wide text-slate-400 mb-2">
                                        <i class="fa-solid fa-users mr-1"></i> Responsable:
                                        <span class="normal-case font-semibold text-slate-600">{{ stage.actor }}</span>
                                    </p>

                                    <ol class="space-y-2">
                                        <li v-for="(step, stepIndex) in stage.steps" :key="stepIndex" class="flex items-start gap-2.5 text-sm leading-relaxed">
                                            <span class="size-5 shrink-0 rounded-full bg-slate-800 text-white text-[10px] font-bold flex items-center justify-center mt-0.5">
                                                {{ stepIndex + 1 }}
                                            </span>
                                            <span>{{ step }}</span>
                                        </li>
                                    </ol>

                                    <!-- Variantes del tipo de orden -->
                                    <div v-if="stage.variants" class="mt-4 grid sm:grid-cols-3 gap-2">
                                        <div v-for="variant in stage.variants" :key="variant.title"
                                            class="rounded-lg border px-3 py-2" :class="variant.classes">
                                            <p class="text-[11px] font-extrabold uppercase tracking-wide">{{ variant.title }}</p>
                                            <p class="text-[11px] mt-1 leading-snug">{{ variant.text }}</p>
                                        </div>
                                    </div>

                                    <!-- Puntos de decisión -->
                                    <div v-for="(decision, decisionIndex) in stage.decisions" :key="'d' + decisionIndex"
                                        class="mt-4 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2.5">
                                        <p class="text-sm font-bold text-amber-800">
                                            <i class="fa-solid fa-code-branch mr-1"></i> {{ decision.question }}
                                        </p>
                                        <div class="mt-2 grid sm:grid-cols-2 gap-2 text-[11px] leading-snug">
                                            <div class="rounded-md bg-emerald-50 border border-emerald-200 px-2.5 py-1.5 text-emerald-800">
                                                <strong>Sí:</strong> {{ decision.yes }}
                                            </div>
                                            <div class="rounded-md bg-rose-50 border border-rose-200 px-2.5 py-1.5 text-rose-800">
                                                <strong>No:</strong> {{ decision.no }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detalle técnico -->
                                <aside v-show="showTechnical"
                                    class="tech-panel rounded-lg bg-slate-50 border border-slate-200 p-3 space-y-3 self-start">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">
                                            <i class="fa-solid fa-gears mr-1"></i> En el sistema
                                        </p>
                                        <p class="text-[11px] font-mono text-slate-600 leading-relaxed break-words">{{ stage.system }}</p>
                                    </div>

                                    <div v-if="stage.movements && stage.movements.length">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">
                                            <i class="fa-solid fa-boxes-stacked mr-1"></i> Movimientos de inventario
                                        </p>
                                        <ul class="space-y-1.5">
                                            <li v-for="(movement, movementIndex) in stage.movements" :key="movementIndex" class="flex items-start gap-1.5 text-[11px] leading-snug">
                                                <span class="shrink-0 px-1.5 py-0.5 rounded font-bold text-[10px] border"
                                                    :class="movement.type === 'Entrada'
                                                        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                                        : 'bg-rose-100 text-rose-700 border-rose-200'">
                                                    {{ movement.type }}
                                                </span>
                                                <span class="text-slate-600">{{ movement.text }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div v-if="stage.notifications && stage.notifications.length">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">
                                            <i class="fa-regular fa-bell mr-1"></i> Notificaciones / correos
                                        </p>
                                        <ul class="space-y-1.5">
                                            <li v-for="(notification, notificationIndex) in stage.notifications" :key="notificationIndex"
                                                class="text-[11px] text-slate-600 leading-snug flex items-start gap-1.5">
                                                <i class="fa-regular fa-bell text-amber-500 mt-0.5"></i>
                                                <span>{{ notification }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </aside>
                            </div>
                        </div>
                    </section>
                </div>
                </div>

                <!-- VISTA 2: SÍMBOLOS (flujo completo con ramificaciones) -->
                <div v-else id="symbol-canvas" class="symbol-canvas">
                    <!-- Leyenda de símbolos -->
                    <div class="mb-6 flex flex-wrap items-center justify-center gap-4 text-[11px] text-slate-500 print-break-avoid">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-10 h-4 rounded-full bg-slate-800"></span> Inicio / Fin
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-10 h-5 rounded border-2 border-sky-400 bg-sky-50"></span> Proceso
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-6 h-6 bg-amber-100 border-2 border-amber-400" style="clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);"></span> Decisión
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-10 h-5 rounded-sm border-2 border-emerald-400 bg-emerald-50"></span> Movimiento de inventario
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-10 h-5 rounded border-2 border-dashed border-teal-400 bg-teal-50"></span> Flujo paralelo
                        </span>
                    </div>

                    <div class="mx-auto max-w-4xl">
                        <template v-for="(node, index) in symbolFlow" :key="'node-' + index">
                            <!-- Nodo de decisión (rombo) -->
                            <div v-if="node.type === 'decision'" class="relative py-2 print-break-avoid">
                                <div class="relative mx-auto" style="width: min(100%, 380px); height: 136px;">
                                    <div class="absolute inset-0 bg-amber-50 border-2 border-amber-400" style="clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);"></div>
                                    <div class="relative z-10 h-full flex flex-col items-center justify-center px-12 text-center">
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-amber-500 mb-0.5">Decisión</span>
                                        <p class="text-sm font-extrabold text-amber-900 leading-snug">{{ node.title }}</p>
                                    </div>
                                </div>

                                <!-- Ramificaciones -->
                                <div class="relative mt-2 max-w-3xl mx-auto">
                                    <div class="hidden sm:block absolute left-1/2 -top-2 h-2 w-0.5 -translate-x-1/2 bg-slate-300"></div>
                                    <div class="hidden sm:block absolute left-1/4 right-1/4 top-0 h-0.5 bg-slate-300"></div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div v-for="branch in symbolBranches(node)" :key="branch.label" class="relative pt-4">
                                            <div class="absolute left-1/2 top-0 h-4 w-0.5 -translate-x-1/2 bg-slate-300"></div>
                                            <div class="rounded-lg border-2 px-3 py-2 text-center"
                                                :class="branch.label === 'Sí' ? 'bg-emerald-50 border-emerald-300' : 'bg-rose-50 border-rose-300'">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wide px-2 py-0.5 rounded-full"
                                                    :class="branch.label === 'Sí' ? 'bg-emerald-200 text-emerald-800' : 'bg-rose-200 text-rose-800'">
                                                    <i class="fa-solid" :class="branch.label === 'Sí' ? 'fa-check' : 'fa-xmark'"></i>
                                                    {{ branch.label }}
                                                </span>
                                                <p class="text-[11px] leading-snug mt-1"
                                                    :class="branch.label === 'Sí' ? 'text-emerald-800' : 'text-rose-800'">
                                                    {{ branch.title }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Nodo de proceso / inicio / fin / movimiento -->
                            <div v-else class="relative py-1 print-break-avoid">
                                <div class="mx-auto max-w-2xl border-2 px-4 py-3 shadow-sm text-center"
                                    :class="[nodeShape(node), node.classes]">
                                    <span class="text-[10px] font-bold uppercase tracking-wide opacity-70">{{ node.label }}</span>
                                    <p class="text-sm font-bold leading-snug">{{ node.title }}</p>
                                    <p v-if="node.subtitle" class="text-[11px] opacity-80 mt-0.5 leading-snug">{{ node.subtitle }}</p>
                                </div>
                            </div>

                            <!-- Conector vertical -->
                            <div v-if="index < symbolFlow.length - 1" class="relative flex flex-col items-center py-1">
                                <div class="w-0.5 h-6 bg-slate-300"></div>
                                <i class="fa-solid fa-caret-down text-slate-400 -mt-1"></i>
                            </div>
                        </template>
                    </div>

                    <!-- Flujos paralelos / transversales -->
                    <div class="mx-auto max-w-4xl mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="flow in parallelFlows" :key="flow.title"
                            class="rounded-xl border-2 border-dashed p-4 print-break-avoid" :class="flow.classes">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="size-8 rounded-lg text-white flex items-center justify-center" :class="flow.iconBg">
                                    <i class="fa-solid" :class="flow.icon"></i>
                                </span>
                                <div>
                                    <h3 class="font-bold text-sm" :class="flow.titleColor">{{ flow.title }}</h3>
                                    <p class="text-[11px]" :class="flow.subColor">{{ flow.subtitle }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <template v-for="(step, stepIndex) in flow.steps" :key="step">
                                    <span class="px-2.5 py-1 rounded-full border text-[11px] font-bold" :class="flow.stepClasses">{{ step }}</span>
                                    <i v-if="stepIndex < flow.steps.length - 1" class="fa-solid fa-arrow-right text-[10px]" :class="flow.arrowClasses"></i>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-slate-400 mt-2">
                <i class="fa-solid fa-circle-info mr-1"></i>
                Documentación interna generada a partir del flujo implementado (ventas, inventario, producción, envíos y cobranza).
                Los nombres de estatus y clases corresponden al código actual.
            </p>
        </main>
    </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3';

export default {
    name: 'ProcessDiagram',
    components: {
        Head,
        Link,
    },
    data() {
        return {
            showTechnical: true,
            viewMode: 'timeline',
            activeArea: 'all',
            zoom: 100,
            areas: [
                { key: 'all', label: 'Todas', icon: 'fa-solid fa-layer-group', dot: 'bg-slate-700', badge: 'bg-slate-100 text-slate-700 border-slate-300' },
                { key: 'quote', label: 'Cotización', icon: 'fa-solid fa-file-signature', dot: 'bg-indigo-500', badge: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
                { key: 'sale', label: 'Orden de venta', icon: 'fa-solid fa-file-invoice', dot: 'bg-sky-500', badge: 'bg-sky-50 text-sky-700 border-sky-200' },
                { key: 'auth', label: 'Autorización', icon: 'fa-solid fa-user-shield', dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
                { key: 'inventory', label: 'Inventario', icon: 'fa-solid fa-boxes-stacked', dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                { key: 'production', label: 'Producción', icon: 'fa-solid fa-industry', dot: 'bg-orange-500', badge: 'bg-orange-50 text-orange-700 border-orange-200' },
                { key: 'shipping', label: 'Envío', icon: 'fa-solid fa-truck-fast', dot: 'bg-violet-500', badge: 'bg-violet-50 text-violet-700 border-violet-200' },
                { key: 'billing', label: 'Facturación', icon: 'fa-solid fa-file-invoice-dollar', dot: 'bg-teal-500', badge: 'bg-teal-50 text-teal-700 border-teal-200' },
                { key: 'aftercare', label: 'Postventa', icon: 'fa-solid fa-rotate-left', dot: 'bg-rose-500', badge: 'bg-rose-50 text-rose-700 border-rose-200' },
            ],
            statusFlow: [
                { label: 'Pendiente', classes: 'bg-slate-100 text-slate-700 border-slate-300' },
                { label: 'Autorizada', classes: 'bg-sky-50 text-sky-700 border-sky-300' },
                { label: 'En Proceso', classes: 'bg-amber-50 text-amber-700 border-amber-300' },
                { label: 'En Producción', classes: 'bg-orange-50 text-orange-700 border-orange-300' },
                { label: 'Preparando Envío', classes: 'bg-violet-50 text-violet-700 border-violet-300' },
                { label: 'Enviada', classes: 'bg-emerald-50 text-emerald-700 border-emerald-300' },
            ],
            // Flujo de símbolos (diagrama de bloques con ramificaciones)
            symbolFlow: [
                { type: 'terminator', label: 'Inicio', title: 'Cotización creada (opcional)', subtitle: 'El vendedor registra productos, precios y condiciones.', classes: 'bg-slate-900 text-white border-slate-900' },
                { type: 'process', label: 'Proceso', title: 'Autorización de la cotización', subtitle: 'Dirección · permiso “Autorizar cotizaciones” · notifica al autorizador.', classes: 'bg-indigo-50 text-indigo-900 border-indigo-300' },
                { type: 'decision', title: '¿El cliente acepta la cotización?', yes: 'Se habilita la OV vinculada (quote.sale_id).', no: 'Seguimiento o rechazo. Alerta si lleva +3 días sin respuesta.' },
                { type: 'process', label: 'Proceso', title: 'Alta de OV / OS / OM', subtitle: 'Estado Pendiente · se crean las parcialidades de envío.', classes: 'bg-sky-50 text-sky-900 border-sky-300' },
                { type: 'decision', title: '¿Hay stock del producto terminado?', yes: 'Salida de inventario del producto y de sus componentes.', no: 'El faltante pasa a producción (quantity_to_produce).' },
                { type: 'process', label: 'Proceso', title: 'Autorización de dirección (OV)', subtitle: 'Permiso “Autorizar ordenes de venta” · estado Autorizada · notifica al creador.', classes: 'bg-amber-50 text-amber-900 border-amber-300' },
                { type: 'decision', title: '¿La OV fue autorizada?', yes: 'Avanza a producción.', no: 'Permanece Pendiente; se edita y se reautoriza.' },
                { type: 'process', label: 'Proceso', title: 'Producción: órdenes y tareas', subtitle: 'Operadores notificados · tareas Pendiente → En Proceso.', classes: 'bg-orange-50 text-orange-900 border-orange-300' },
                { type: 'decision', title: '¿La tarea de producción terminó?', yes: 'Unidades buenas → Entrada · merma → Salida (incluye componentes).', no: 'Pausa o “Sin material”: registra avance parcial.' },
                { type: 'data', label: 'Movimiento', title: 'Entrada por unidades buenas / Salida por merma', subtitle: 'Se registra en el historial de inventario del producto.', classes: 'bg-emerald-50 text-emerald-900 border-emerald-300' },
                { type: 'decision', title: '¿Todas las producciones terminadas?', yes: 'OV pasa a Preparando Envío (OS: Stock Terminado).', no: 'OV permanece En Producción.' },
                { type: 'process', label: 'Proceso', title: 'Envío: parcialidades y evidencias', subtitle: 'Etiquetas · evidencia de salida y de recibido · marcar como Enviado.', classes: 'bg-violet-50 text-violet-900 border-violet-300' },
                { type: 'decision', title: '¿Todas las parcialidades enviadas?', yes: 'Salida por envío · OV pasa a Enviada (OM: Completado).', no: 'OV pasa a En Proceso mientras resta por enviar.' },
                { type: 'process', label: 'Proceso', title: 'Postventa y cierre', subtitle: 'Cambios/devoluciones · reversión de stock · certificado de calidad.', classes: 'bg-rose-50 text-rose-900 border-rose-300' },
                { type: 'terminator', label: 'Fin', title: 'Orden cerrada', subtitle: 'Con facturación y cobranza avanzando en paralelo.', classes: 'bg-slate-900 text-white border-slate-900' },
            ],
            parallelFlows: [
                {
                    title: 'Flujo paralelo · Facturación y cobranza',
                    subtitle: 'Inicia al crear la OV y avanza junto a producción y envío.',
                    icon: 'fa-file-invoice-dollar',
                    classes: 'border-teal-300 bg-teal-50/60',
                    iconBg: 'bg-teal-600',
                    titleColor: 'text-teal-900',
                    subColor: 'text-teal-700',
                    stepClasses: 'bg-white border-teal-300 text-teal-800',
                    arrowClasses: 'text-teal-400',
                    steps: ['Pendiente Pre-factura', 'Pre-facturada', 'Pendiente Timbrado', 'Timbrada'],
                },
                {
                    title: 'Registro · Movimientos de inventario',
                    subtitle: 'Cada cambio queda en el historial y puede revertirse al eliminar.',
                    icon: 'fa-boxes-stacked',
                    classes: 'border-emerald-300 bg-emerald-50/60',
                    iconBg: 'bg-emerald-600',
                    titleColor: 'text-emerald-900',
                    subColor: 'text-emerald-700',
                    stepClasses: 'bg-white border-emerald-300 text-emerald-800',
                    arrowClasses: 'text-emerald-400',
                    steps: ['Entrada: producción, devoluciones, recepciones', 'Salida: venta, envío, merma, ajustes'],
                },
            ],
            stages: [
                {
                    id: 'quote',
                    number: 0,
                    area: 'quote',
                    areaLabel: 'Cotización (opcional)',
                    icon: 'fa-solid fa-file-signature',
                    title: 'Cotización y aprobación del cliente',
                    actor: 'Vendedor · Dirección · Cliente',
                    status: 'Esperando respuesta → Aceptada',
                    steps: [
                        'El vendedor crea la cotización (v1) con productos, precios, flete y condiciones; queda en “Esperando respuesta”.',
                        'Se notifica a los usuarios con permiso “Autorizar ordenes de venta” para su revisión.',
                        'Dirección autoriza la cotización (permiso “Autorizar cotizaciones”) y se registra quién y cuándo.',
                        'El cliente responde: se aprueban o rechazan productos y la cotización pasa a “Aceptada” o “Rechazada”.',
                        'Al generar la OV desde la cotización se vincula quote.sale_id y la cotización queda “Aceptada”.',
                    ],
                    decisions: [
                        {
                            question: '¿El cliente acepta la cotización?',
                            yes: 'Se habilita la creación de la Orden de Venta vinculada a la cotización.',
                            no: 'Se da seguimiento o se rechaza. Si lleva más de 3 días sin respuesta se genera una alerta al vendedor.',
                        },
                    ],
                    movements: [],
                    notifications: [
                        'NewQuoteForApprovalNotification → usuarios con permiso de autorizar OV.',
                        'ApprovalQuoteNotification → creador de la cotización al ser autorizada.',
                    ],
                    system: 'quote.status = Esperando respuesta / Aceptada / Rechazada · quote.authorized_at · quote.sale_id',
                },
                {
                    id: 'sale',
                    number: 1,
                    area: 'sale',
                    areaLabel: 'Orden de venta',
                    icon: 'fa-solid fa-file-invoice',
                    title: 'Alta de la Orden de Venta (OV / OS / OM)',
                    actor: 'Vendedor',
                    status: 'Pendiente',
                    steps: [
                        'Se capturan sucursal y contacto, moneda, productos, parcialidades de envío y archivos OCE.',
                        'La orden se crea con estatus “Pendiente” (las OM heredan el estatus del seguimiento de muestra).',
                        'Si la sucursal era “Prospecto”, cambia automáticamente a “Cliente”.',
                        'Se notifica al área de Cobranza que la orden requiere pre-factura.',
                        'Se crean las parcialidades de envío en estado “Pendiente” con fecha promesa, paquetería y guía.',
                        'Al finalizar se ejecuta la revisión de stock bajo y se envían alertas si aplica.',
                    ],
                    variants: [
                        {
                            title: 'Venta (OV)',
                            text: 'Descuenta stock disponible del producto terminado y descuenta los componentes completos. Lo faltante se produce.',
                            classes: 'bg-sky-50 border-sky-200 text-sky-800',
                        },
                        {
                            title: 'Stock (OS)',
                            text: 'No descuenta producto terminado; descuenta componentes. Toda la cantidad pasa a producción.',
                            classes: 'bg-teal-50 border-teal-200 text-teal-800',
                        },
                        {
                            title: 'Muestra / regalo (OM)',
                            text: 'Descuenta piezas reservadas de muestra. No genera producción; se usa para facturar.',
                            classes: 'bg-slate-50 border-slate-200 text-slate-700',
                        },
                    ],
                    decisions: [
                        {
                            question: '¿Hay stock suficiente del producto terminado?',
                            yes: 'Se toma del inventario y solo se produce el faltante (quantity_to_produce).',
                            no: 'La cantidad faltante (o total) se envía a producción.',
                        },
                    ],
                    movements: [
                        { type: 'Salida', text: '“Descuento por Orden de venta #…” al tomar producto terminado del inventario.' },
                        { type: 'Salida', text: '“Descuento de componentes para N de PRODUCTO (Orden #…)” por cada componente del producto.' },
                        { type: 'Salida', text: '“Descuento de piezas de muestra” en órdenes OM.' },
                    ],
                    notifications: [
                        'BillingNotification (job NotifyBillingDepartmentJob) → Cobranza: “requiere Pre-factura”.',
                        'LowStockNotificationMail (job CheckLowStockAndNotifyJob) → roles y correos de almacén/compras si un producto o componente llega al mínimo.',
                    ],
                    system: 'sale.status = Pendiente · sale_products.quantity_to_produce · shipments.status = Pendiente · storages.quantity',
                },
                {
                    id: 'auth',
                    number: 2,
                    area: 'auth',
                    areaLabel: 'Autorización de dirección',
                    icon: 'fa-solid fa-user-shield',
                    title: 'Revisión y autorización',
                    actor: 'Dirección / usuario con permiso “Autorizar ordenes de venta”',
                    status: 'Pendiente → Autorizada',
                    steps: [
                        'El autorizador revisa la orden: cliente, productos, precios, fletes y condiciones.',
                        'Se registra la autorización con usuario y fecha, y queda en la auditoría de la orden.',
                        'El estatus cambia de “Pendiente” a “Autorizada”.',
                        'Se notifica al creador de la orden y al creador de la cotización vinculada si los autorizó otra persona.',
                        'Solo las órdenes autorizadas aparecen disponibles para crear producción.',
                    ],
                    decisions: [
                        {
                            question: '¿La orden queda autorizada?',
                            yes: 'Avanza a producción / logística.',
                            no: 'Permanece “Pendiente”. Con el permiso “Autorizar edición de ordenes de venta” se puede retirar la autorización, editar y volver a autorizar.',
                        },
                    ],
                    movements: [],
                    notifications: [
                        'SaleAuthorizedNotification → creador de la OV y creador de la cotización vinculada.',
                    ],
                    system: 'sale.authorized_at · sale.authorized_user_name · auditoría de autorización · sale.status',
                },
                {
                    id: 'inventory',
                    number: 3,
                    area: 'inventory',
                    areaLabel: 'Inventario y abastecimiento',
                    icon: 'fa-solid fa-boxes-stacked',
                    title: 'Movimientos de inventario y resurtido',
                    actor: 'Almacén · Compras',
                    status: 'Entradas y salidas',
                    steps: [
                        'Cada cambio de inventario se registra como movimiento (Entrada o Salida) con almacén, producto y notas.',
                        'Si un producto o componente llega a su stock mínimo se generan alertas y listas de reposición.',
                        'Se crean requisiciones (Local / Importación) y órdenes de compra para resurtir.',
                        'Al recibir mercancía, la entrada al inventario se captura manualmente desde movimientos de stock.',
                        'Eliminar un movimiento revierte automáticamente el stock del almacén (resta entradas / devuelve salidas).',
                    ],
                    decisions: [
                        {
                            question: '¿Falta inventario para cumplir la orden?',
                            yes: 'Se inicia reposición / requisición / compra (flujo de abastecimiento).',
                            no: 'Se continúa con producción o envío según corresponda.',
                        },
                    ],
                    movements: [
                        { type: 'Entrada', text: 'Recepciones, devoluciones, ajustes positivos y unidades producidas.' },
                        { type: 'Salida', text: 'Consumos, envíos, mermas y ajustes negativos.' },
                    ],
                    notifications: [
                        'LowStockNotificationMail → correo de stock bajo (roles y direcciones configuradas).',
                        'StockRepositionNotification → notificación de reposición de stock.',
                        'Notificaciones del módulo de requisiciones (creación, autorización, pago, arribo).',
                    ],
                    system: 'stock_movements (type, quantity_change, notes) · storages.quantity · requisitions / purchases',
                },
                {
                    id: 'production',
                    number: 4,
                    area: 'production',
                    areaLabel: 'Producción',
                    icon: 'fa-solid fa-industry',
                    title: 'Producción y avance de tareas',
                    actor: 'Jefe de producción · Operadores',
                    status: 'Pendiente → En Proceso → Terminada',
                    steps: [
                        'Producción crea la orden de producción para los productos sin producción, con fecha tentativa o nota por folio.',
                        'Se asignan tareas a los operadores con tiempo estimado; el operador recibe una notificación.',
                        'El operador cambia el estatus de su tarea: En Proceso, Pausada, Sin material o Terminada.',
                        'Al pausar o reportar “Sin material” puede registrar unidades parciales que entran al stock.',
                        'Al terminar registra unidades buenas (entrada) y merma (salida); también puede descontar componentes por merma.',
                        'La orden de venta actualiza su estatus automáticamente según el avance de las producciones.',
                    ],
                    decisions: [
                        {
                            question: '¿Todas las producciones están terminadas?',
                            yes: 'La OV pasa a “Preparando Envío” (venta) o “Stock Terminado” (OS).',
                            no: 'La OV permanece “En Producción” (o “Pausada” / “Sin material” según las tareas).',
                        },
                    ],
                    movements: [
                        { type: 'Entrada', text: '“Avance/Terminación de orden OV/OS-…” por unidades buenas producidas.' },
                        { type: 'Salida', text: '“Merma en orden OV/OS-… - Razón: …” por piezas defectuosas.' },
                        { type: 'Salida', text: '“Salida por merma en producción de OV/OS-…” por componentes descontados.' },
                    ],
                    notifications: [
                        'TaskAssignedNotification → operador asignado a cada tarea de producción.',
                    ],
                    system: 'production.status (Pendiente, En Proceso, Pausada, Sin material, Terminada) · production_tasks.status · sale.status',
                },
                {
                    id: 'shipping',
                    number: 5,
                    area: 'shipping',
                    areaLabel: 'Envío y entrega',
                    icon: 'fa-solid fa-truck-fast',
                    title: 'Parcialidades, evidencias y salida de mercancía',
                    actor: 'Producción / Almacén / Logística',
                    status: 'Pendiente → Enviado',
                    steps: [
                        'Se administran las parcialidades: fecha promesa, paquetería, guía, cantidades y etiquetas de envío.',
                        'Se adjuntan evidencias: salida de mercancía (paquetería se la lleva) y recibido por el cliente (documento de paquetería).',
                        'Se marca la parcialidad como “Enviado” con fecha, quién la envía y notas (permiso “Marcar parcialidades como enviadas”).',
                        'Se descuenta del inventario lo efectivamente enviado, considerando lo ya descontado en envíos anteriores.',
                        'Las evidencias son las mismas desde el detalle de la OV y desde el módulo de envíos.',
                    ],
                    decisions: [
                        {
                            question: '¿Todas las parcialidades están enviadas?',
                            yes: 'La OV pasa a “Enviada”. En OM, el seguimiento de muestra se marca “Completado”.',
                            no: 'La OV pasa a “En Proceso” mientras resta por enviar.',
                        },
                    ],
                    movements: [
                        { type: 'Salida', text: '“Salida por envío (total/parcial) de OV-…” por la cantidad enviada.' },
                    ],
                    notifications: [
                        'Notificaciones del seguimiento de muestra (OM) cuando pasa a Completado.',
                    ],
                    system: 'shipment.status (Pendiente, Enviado) · sent_at · sent_by · shipment-evidence / shipment-received-evidence · sale.status',
                },
                {
                    id: 'billing',
                    number: 6,
                    area: 'billing',
                    areaLabel: 'Facturación y cobranza',
                    icon: 'fa-solid fa-file-invoice-dollar',
                    title: 'Pre-factura, timbrado y cobranza (flujo paralelo)',
                    actor: 'Cobranza · Facturación',
                    status: 'Pendiente Pre-factura → Timbrada',
                    steps: [
                        'Al crear la OV se avisa a Cobranza que requiere pre-factura.',
                        'Se registra la pre-factura y avanza el estatus de facturación de la orden.',
                        'Cuando la orden entra a proceso o producción se solicitan timbrado y seguimiento.',
                        'Se emiten facturas, se registran pagos y se envían recordatorios de vencimiento.',
                        'Este flujo corre en paralelo a producción y envío; no bloquea el proceso operativo.',
                    ],
                    decisions: [
                        {
                            question: '¿Ya existe pre-factura al entrar a producción?',
                            yes: 'Se solicita el timbrado (billing_status = Pendiente Timbrado).',
                            no: 'Se alerta a Cobranza que la OV está en producción sin pre-factura.',
                        },
                    ],
                    movements: [],
                    notifications: [
                        'BillingNotification → Cobranza (pre-factura, timbrado pendiente, producción sin pre-factura).',
                        'InvoiceOverdueNotification → recordatorios de facturas vencidas.',
                    ],
                    system: 'sale.billing_status · pre_invoice_folio · stamped_invoice_folio · invoices / payments',
                },
                {
                    id: 'aftercare',
                    number: 7,
                    area: 'aftercare',
                    areaLabel: 'Postventa y reversiones',
                    icon: 'fa-solid fa-rotate-left',
                    title: 'Cambios, devoluciones y cierre',
                    actor: 'Vendedor · Almacén · Dirección',
                    status: 'Según el caso',
                    steps: [
                        'Los cambios/devoluciones registran el producto devuelto (entrada) y el producto nuevo (salida), con evidencia fotográfica.',
                        'Se puede imprimir el formato de cambio con la firma del cliente.',
                        'Si se elimina una OV se revierte el stock comprometido (venta) o las piezas de muestra, y se libera la cotización.',
                        'Al cierre se puede emitir el certificado de calidad de la orden.',
                    ],
                    decisions: [],
                    movements: [
                        { type: 'Entrada', text: '“Devolución por cambio Folio #…” al regresar el producto del cliente.' },
                        { type: 'Salida', text: '“Salida por cambio Folio #…” al entregar el producto nuevo.' },
                    ],
                    notifications: [],
                    system: 'product_exchanges · reversión de stock al eliminar la OV · certificado de calidad',
                },
            ],
        };
    },
    methods: {
        styleOf(area) {
            return this.areas.find(item => item.key === area) || this.areas[0];
        },
        symbolBranches(node) {
            const branches = [];
            if (node.yes) branches.push({ label: 'Sí', title: node.yes });
            if (node.no) branches.push({ label: 'No', title: node.no });
            return branches;
        },
        nodeShape(node) {
            if (node.type === 'terminator') return 'rounded-full';
            if (node.type === 'data') return 'rounded-sm';
            return 'rounded-lg';
        },
        isDimmed(stage) {
            return this.activeArea !== 'all' && stage.area !== this.activeArea;
        },
        changeZoom(delta) {
            const next = this.zoom + delta;
            this.zoom = Math.min(120, Math.max(80, next));
        },
        printDiagram() {
            window.print();
        },
    },
};
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }

    .print-break-avoid {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    /* En impresión siempre se muestra el detalle técnico completo */
    .tech-panel {
        display: block !important;
    }

    #diagram-zoom,
    #diagram-canvas {
        zoom: 1 !important;
    }

    /* En impresión se muestran todas las etapas, aunque haya un filtro activo */
    .diagram-stage {
        opacity: 1 !important;
        filter: none !important;
    }

    body {
        background: #ffffff !important;
    }
}
</style>
