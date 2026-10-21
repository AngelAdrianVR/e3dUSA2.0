<template>
    <div class="p-1 md:p-4 font-sans">
        <div v-if="!arrivals.enabled" class="text-center text-gray-500 dark:text-gray-400 py-10">
            No tienes acceso al calendario de arribos de mercancía.
        </div>

        <template v-else>
            <!-- Leyenda de estatus -->
            <div class="flex flex-wrap items-center gap-4 mb-4">
                <p class="font-semibold text-gray-700 dark:text-gray-200">Estatus de arribo:</p>
                <div v-for="(color, status) in legend" :key="status" class="flex items-center gap-2">
                    <span class="inline-block w-3.5 h-3.5 rounded-full" :style="{ backgroundColor: color }"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ status }}</span>
                </div>
                <div class="flex items-center gap-4 border-l border-gray-300 dark:border-gray-600 pl-4">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-flag text-gray-500 dark:text-gray-400"></i>
                        <span class="text-sm text-gray-600 dark:text-gray-300">Fecha tentativa de arribo</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-truck-fast text-gray-500 dark:text-gray-400"></i>
                        <span class="text-sm text-gray-600 dark:text-gray-300">Registro de guía / actualización</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-flag-checkered text-gray-500 dark:text-gray-400"></i>
                        <span class="text-sm text-gray-600 dark:text-gray-300">Fecha de arribo</span>
                    </div>
                </div>
                <!-- <p class="text-xs text-gray-400 dark:text-gray-500 ml-auto">
                    Destino: Guadalajara (GDL). Una guía agrupa todas las requisiciones que viajan en ella.
                </p> -->
            </div>

            <!-- Controles de mes -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button @click="changeMonth(-1)"
                        class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 w-48 text-center capitalize">
                        {{ monthAndYear }}
                    </h2>
                    <button @click="changeMonth(1)"
                        class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <el-input v-model="search" clearable placeholder="Buscar por folio de requisición o guía" class="!w-96"
                        @keyup.enter="searchArrival" @clear="clearHighlight">
                        <template #prefix>
                            <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
                        </template>
                        <template #append>
                            <el-button @click="searchArrival">Buscar</el-button>
                        </template>
                    </el-input>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ arrivals.arrivals.length }} registro(s) en el calendario
                    </p>
                    <el-button v-if="canManage" type="primary" size="small" @click="openEventDialog()">
                        <i class="fa-solid fa-plus mr-2"></i> Registrar actualización / evento
                    </el-button>
                </div>
            </div>

            <!-- Calendario -->
            <div class="grid grid-cols-7 gap-px mt-6 bg-gray-200 dark:bg-gray-700 border rounded-lg overflow-hidden shadow">
                <div v-for="day in weekDays" :key="day"
                    class="py-2 text-center text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800">
                    {{ day }}
                </div>

                <div v-for="day in calendarDays" :key="day.date"
                    @click="openEventDialog(day.date)"
                    :title="canManage ? 'Registrar actualización / evento en este día' : null"
                    :class="['relative min-h-[120px] p-2 bg-white dark:bg-gray-800 transition-colors',
                        { 'bg-gray-50 dark:bg-gray-900': !day.isCurrentMonth },
                        dayHasHighlight(day.date) ? 'ring-2 ring-inset ring-blue-500 bg-blue-50/70 dark:bg-blue-900/20' : '',
                        canManage ? 'cursor-pointer hover:bg-blue-50/60 dark:hover:bg-slate-700/40' : '']">
                    <span
                        :class="['font-semibold', day.isToday ? 'bg-blue-600 text-white rounded-full h-7 w-7 flex items-center justify-center' : 'text-gray-700 dark:text-gray-200']">
                        {{ day.dayNumber }}
                    </span>
                    <div class="mt-2 space-y-1">
                        <el-tooltip v-for="entry in entriesByDay[day.date]" :key="entry.key"
                            :content="entryTooltip(entry)" placement="top" effect="dark" :show-after="200">
                            <div @click.stop="selectedEntry = entry" :data-entry-key="entry.key"
                                class="px-2 py-1 rounded-md text-xs font-medium cursor-pointer truncate flex items-center gap-2 text-white transition-transform hover:scale-[1.02]"
                                :class="[entry.kind === 'event' || entry.state_kind === 'event' ? 'ring-1 ring-white/40' : '',
                                    entry.key === highlightKey ? 'arrivals-highlight' : '']"
                                :style="{ backgroundColor: entry.color }">
                                <i :class="entryIcon(entry)"></i>
                                <span class="truncate">
                                    {{ entryLabel(entry) }}
                                    <template v-if="entry.kind === 'shipment' && entry.requisitions?.length > 1">
                                        ({{ entry.requisitions.length }})
                                    </template>
                                </span>
                            </div>
                        </el-tooltip>
                    </div>
                </div>
            </div>

            <!-- Arribos sin fecha -->
            <div v-if="arrivals.undated?.length" class="mt-6 bg-white dark:bg-slate-900 rounded-lg shadow p-4">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-2">
                    <i class="fa-solid fa-hourglass-half mr-2"></i> Guías sin fecha de registro
                </h3>
                <ul class="space-y-1">
                    <li v-for="entry in arrivals.undated" :key="entry.key"
                        class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2 cursor-pointer"
                        @click="selectedEntry = entry">
                        <span class="inline-block w-3 h-3 rounded-full" :style="{ backgroundColor: entry.color }"></span>
                        <i :class="[entryIcon(entry), 'text-gray-400']"></i>
                        <span class="font-mono">{{ entryLabel(entry) }}</span>
                        <span class="text-xs text-gray-400">{{ entry.subtitle }}</span>
                        <span class="text-xs">· {{ entry.status }}</span>
                    </li>
                </ul>
            </div>
        </template>

        <!-- Detalle del arribo -->
        <el-drawer v-model="drawerOpen" :title="selectedEntry?.title ?? 'Detalle de arribo'" size="576px">
            <div v-if="selectedEntry" class="arrivals-detail p-4 space-y-4">
                <!-- Encabezado: estatus y tipo de entrada -->
                <div class="flex flex-wrap items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
                    <span class="inline-block w-4 h-4 rounded-full" :style="{ backgroundColor: selectedEntry.color }"></span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ selectedEntry.status }}</span>
                    <el-tag size="small" :type="kindTagType(selectedEntry)">
                        {{ kindLabel(selectedEntry) }}
                    </el-tag>
                    <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDate(selectedEntry.date) }}
                    </span>
                </div>

                <el-collapse v-model="openSections">
                    <!-- Detalles generales -->
                    <el-collapse-item name="general" title="Detalles generales">
                        <!-- Actualización / evento -->
                        <div v-if="selectedEntry.kind === 'event'" class="space-y-3 text-sm px-1">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Guía</p>
                                <p class="font-mono font-semibold dark:text-gray-200">{{ selectedEntry.title }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Fecha del evento</p>
                                <p class="dark:text-gray-200">{{ formatDate(selectedEntry.date) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Descripción</p>
                                <p class="dark:text-gray-200 whitespace-pre-line">{{ selectedEntry.description }}</p>
                            </div>
                            <div v-if="selectedEntry.created_by_name">
                                <p class="text-gray-500 dark:text-gray-400">Registrado por</p>
                                <p class="dark:text-gray-200">{{ selectedEntry.created_by_name }}</p>
                            </div>
                            <div v-if="creatorNames.length">
                                <p class="text-gray-500 dark:text-gray-400">Levantada por</p>
                                <p class="dark:text-gray-200">
                                    <i class="fa-solid fa-user text-gray-400 mr-1"></i>
                                    {{ creatorNames.join(' · ') }}
                                </p>
                            </div>
                        </div>

                        <!-- Guía de paquetería -->
                        <div v-else class="grid grid-cols-2 gap-4 text-sm px-1">
                            <div v-if="selectedEntry.kind === 'item'">
                                <p class="text-gray-500 dark:text-gray-400">Requisición</p>
                                <p class="font-mono font-semibold dark:text-gray-200">{{ selectedEntry.title }}</p>
                            </div>
                            <div v-else>
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ selectedEntry.tracking_number ? 'Número de guía' : 'Forma de entrega' }}
                                </p>
                                <p class="font-mono font-semibold dark:text-gray-200">{{ selectedEntry.title }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ selectedEntry.tracking_number ? 'Paquetería' : 'Transportista / referencia' }}
                                </p>
                                <p class="dark:text-gray-200">{{ selectedEntry.subtitle ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Fecha tentativa de arribo</p>
                                <p class="dark:text-gray-200">
                                    <i class="fa-solid fa-flag text-gray-400 mr-1"></i>
                                    {{ formatDate(selectedEntry.estimated_arrival_date) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Fecha de arribo</p>
                                <p class="dark:text-gray-200">
                                    <template v-if="selectedEntry.arrived_at">
                                        <i class="fa-solid fa-flag-checkered text-emerald-500 mr-1"></i>
                                        {{ formatDate(selectedEntry.arrived_at) }}
                                    </template>
                                    <span v-else class="text-gray-400">Pendiente</span>
                                </p>
                            </div>
                            <div v-if="creatorNames.length" class="col-span-2">
                                <p class="text-gray-500 dark:text-gray-400">Levantada por</p>
                                <p class="dark:text-gray-200">
                                    <i class="fa-solid fa-user text-gray-400 mr-1"></i>
                                    {{ creatorNames.join(' · ') }}
                                </p>
                            </div>
                            <div v-if="selectedEntry.notes" class="col-span-2">
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ selectedEntry.kind === 'shipment' ? 'Notas de la guía' : 'Notas del arribo' }}
                                </p>
                                <p class="dark:text-gray-200 whitespace-pre-line">{{ selectedEntry.notes }}</p>
                            </div>
                            <!-- Estado del seguimiento arrastrado día a día -->
                            <div v-if="selectedEntry.carried || selectedEntry.state_kind === 'event'"
                                class="col-span-2 rounded-lg border border-dashed border-gray-300 dark:border-slate-600 px-3 py-2">
                                <p class="text-gray-500 dark:text-gray-400">
                                    Estado al {{ formatDate(selectedEntry.date) }}
                                </p>
                                <p class="font-semibold dark:text-gray-200">
                                    {{ selectedEntry.state_label || 'Sin actualización registrada' }}
                                </p>
                                <p v-if="selectedEntry.carried" class="text-[11px] text-gray-400 mt-1">
                                    <i class="fa-solid fa-repeat mr-1"></i>
                                    Se repite el último registro del {{ formatDate(selectedEntry.state_date) }}
                                    hasta que se capture una nueva actualización.
                                </p>
                            </div>
                        </div>

                        <!-- Eliminar la actualización/evento que se está consultando -->
                        <div v-if="canManage && deletableEventId" class="flex justify-end pt-3">
                            <el-button type="danger" plain size="small" :loading="deleting" @click="deleteEvent(deletableEventId)">
                                <i class="fa-solid fa-trash-can mr-2"></i> Eliminar actualización
                            </el-button>
                        </div>
                    </el-collapse-item>

                    <!-- Actualizaciones / eventos (plegado por default) -->
                    <el-collapse-item v-if="selectedEntry.events?.length" name="events">
                        <template #title>
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-truck-fast"></i>
                                Actualizaciones / eventos ({{ selectedEntry.events.length }})
                            </span>
                        </template>
                        <ul class="space-y-2 px-1">
                            <li v-for="event in selectedEntry.events" :key="event.id"
                                class="text-xs text-gray-600 dark:text-gray-300 border-l-2 pl-3 py-0.5"
                                :style="{ borderColor: selectedEntry.color }">
                                <span class="font-semibold">{{ formatDate(event.date) }}</span> ·
                                {{ event.label ?? event.description }}
                                <span v-if="event.created_by_name" class="text-gray-400"> — {{ event.created_by_name }}</span>
                            </li>
                        </ul>
                    </el-collapse-item>

                    <!-- Productos en el arribo -->
                    <el-collapse-item name="products"
                        :title="`Productos en este arribo (${selectedEntry.items?.length ?? 0})`">
                        <ul class="space-y-2 px-1">
                            <li v-for="item in selectedEntry.items" :key="item.id"
                                class="border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                                <div class="flex justify-between items-center gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <el-image v-if="item.image_url" :src="item.image_url" fit="cover"
                                            :preview-src-list="productImages" :initial-index="previewIndex(item)"
                                            preview-teleported hide-on-click-modal
                                            class="size-14 rounded-lg border border-gray-200 dark:border-slate-700 cursor-pointer shrink-0"
                                            :alt="item.product" />
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ item.product }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                <span v-if="item.code">{{ item.code }} · </span>
                                                {{ item.quantity }} pieza(s)
                                                <template v-if="item.total_quantity && item.total_quantity > item.quantity">
                                                    de {{ item.total_quantity }} solicitadas
                                                </template>
                                                <template v-if="item.folio">
                                                    · <a v-if="item.url" :href="item.url" class="text-primary hover:underline font-mono">{{ item.folio }}</a>
                                                    <span v-else class="font-mono">{{ item.folio }}</span>
                                                </template>
                                            </p>
                                            <p v-if="item.requester" class="text-xs text-gray-500 dark:text-gray-400">
                                                <i class="fa-solid fa-user mr-1"></i> Solicitó: {{ item.requester }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="inline-block w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: item.color ?? '#9CA3AF' }"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.status }}</span>
                                    </div>
                                </div>
                            </li>
                            <li v-if="!selectedEntry.items?.length" class="text-xs text-gray-400">
                                Sin detalle de productos.
                            </li>
                        </ul>
                    </el-collapse-item>

                    <!-- Requisiciones involucradas -->
                    <el-collapse-item v-if="selectedEntry.requisitions?.length" name="requisitions"
                        :title="`Requisiciones en este arribo (${selectedEntry.requisitions?.length ?? 0})`">
                        <ul class="space-y-2 px-1">
                            <li v-for="requisition in selectedEntry.requisitions" :key="requisition.id"
                                class="border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <a v-if="requisition.url" :href="requisition.url" class="font-mono font-semibold text-primary hover:underline">
                                        {{ requisition.folio }}
                                    </a>
                                    <span v-else class="font-mono font-semibold">{{ requisition.folio }}</span>
                                    <el-tag size="small" :type="requisition.type === 'Importación' ? 'primary' : 'success'">
                                        {{ requisition.type }}
                                    </el-tag>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ requisition.label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Estatus: {{ requisition.status }}</p>
                                <p v-if="requisition.creator" class="text-xs text-gray-500 dark:text-gray-400">
                                    <i class="fa-solid fa-user mr-1"></i> Levantada por: {{ requisition.creator }}
                                </p>
                            </li>
                        </ul>
                    </el-collapse-item>

                    <!-- Actualización del arribo (cualquier registro: guía, evento o partida) -->
                    <el-collapse-item v-if="canManage" name="update">
                        <template #title>
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-pen-to-square text-gray-400"></i> Actualizar arribo
                            </span>
                        </template>
                        <div class="space-y-4 px-1">
                            <p v-if="selectedEntry.kind === 'event'"
                                class="text-[11px] text-gray-400 dark:text-gray-500">
                                <i class="fa-solid fa-circle-info mr-1"></i>
                                El estatus y la fecha tentativa se actualizan en la guía {{ selectedEntry.title }}.
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Estatus</p>
                                    <el-select v-model="arrivalForm.status" class="w-full" :disabled="arrivalStatusLocked">
                                        <el-option v-for="status in statuses" :key="status" :label="status" :value="status" />
                                    </el-select>
                                    <p v-if="arrivalStatusLocked" class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1">
                                        <i class="fa-solid fa-lock mr-1"></i>
                                        La guía ya fue marcada como arribada; su estatus no puede cambiarse.
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha tentativa de arribo</p>
                                    <el-date-picker v-model="arrivalForm.date" type="date" value-format="YYYY-MM-DD"
                                        placeholder="Selecciona la fecha" class="w-full" />
                                </div>
                            </div>

                            <!-- Fecha real de arribo: solo al marcar como arribado -->
                            <div v-if="showsArrivalDate">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha de arribo *</p>
                                <el-date-picker v-model="arrivalForm.arrived_at" type="date" value-format="YYYY-MM-DD"
                                    placeholder="Selecciona la fecha" class="w-full" />
                                <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                                    <i class="fa-solid fa-lock mr-1"></i>
                                    Al confirmar el arribo, el estatus de la guía queda cerrado y ya no podrá cambiarse.
                                </p>
                            </div>

                            <!-- Partidas sin guía: nota propia del arribo -->
                            <div v-if="selectedEntry.kind === 'item'">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Notas del arribo</p>
                                <textarea v-model="arrivalForm.notes" rows="2"
                                    class="w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200 text-sm"
                                    placeholder="Ej. Contiene 3 cajas, pendiente de aduana..."></textarea>
                                <p class="text-[11px] text-gray-400 mt-1">
                                    La nota se muestra junto a la fecha tentativa de arribo en el calendario.
                                </p>
                            </div>

                            <!-- Guías y eventos: la descripción se guarda como actualización con su fecha -->
                            <div v-else class="space-y-3">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha de la actualización</p>
                                    <el-date-picker v-model="arrivalForm.event_date" type="date" value-format="YYYY-MM-DD"
                                        placeholder="Selecciona la fecha" class="w-full" />
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Descripción de la actualización</p>
                                    <textarea v-model="arrivalForm.event_description" rows="2"
                                        class="w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200 text-sm"
                                        placeholder="Ej. Comienza producción"></textarea>
                                    <p class="text-[11px] text-gray-400 mt-1">
                                        <i class="fa-solid fa-clock-rotate-left mr-1"></i>
                                        Cada descripción se guarda con su fecha y queda en el historial del seguimiento.
                                    </p>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <el-button type="primary" :loading="saving" :disabled="arrivalStatusLocked"
                                    @click="saveArrival">Guardar cambios</el-button>
                            </div>
                        </div>
                    </el-collapse-item>
                </el-collapse>
            </div>
        </el-drawer>

        <!-- Registro de actualizaciones / eventos de una guía -->
        <el-dialog v-model="eventModal" title="Registrar actualización / evento" width="560" :center="true">
            <div class="space-y-4">
                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-300">Guía *</label>
                    <el-select v-model="eventForm.shipment_id" filterable clearable class="w-full mt-1"
                        placeholder="Busca por número de guía o paquetería">
                        <el-option v-for="option in shipmentOptions" :key="option.id" :value="option.id"
                            :label="`${option.display_name ?? option.tracking_number ?? option.delivery_method} · ${option.carrier ?? 'N/A'} (${option.status})`" />
                    </el-select>
                    <p v-if="!shipmentOptions.length" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                        Todavía no hay guías registradas en el calendario.
                    </p>
                </div>

                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-300">Fecha del evento *</label>
                    <el-date-picker v-model="eventForm.event_date" type="date" value-format="YYYY-MM-DD"
                        placeholder="Selecciona la fecha" class="w-full mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Estatus de arribo</label>
                        <el-select v-model="eventForm.status" clearable class="w-full mt-1"
                            :disabled="eventShipmentLocked" placeholder="Sin cambio">
                            <el-option v-for="status in statuses" :key="status" :label="status" :value="status" />
                        </el-select>
                        <p v-if="eventShipmentLocked" class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1">
                            <i class="fa-solid fa-lock mr-1"></i>
                            La guía ya fue marcada como arribada; su estatus no puede cambiarse.
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Fecha tentativa de arribo</label>
                        <el-date-picker v-model="eventForm.estimated_arrival_date" type="date" value-format="YYYY-MM-DD"
                            placeholder="Selecciona la fecha" class="w-full mt-1" />
                    </div>
                </div>

                <!-- El evento también puede confirmar el arribo con su fecha real -->
                <div v-if="eventShowsArrivalDate">
                    <label class="text-sm text-gray-600 dark:text-gray-300">Fecha de arribo *</label>
                    <el-date-picker v-model="eventForm.arrived_at" type="date" value-format="YYYY-MM-DD"
                        placeholder="Selecciona la fecha" class="w-full mt-1" />
                    <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                        <i class="fa-solid fa-lock mr-1"></i>
                        Al confirmar el arribo, el estatus de la guía queda cerrado y ya no podrá cambiarse.
                    </p>
                </div>

                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-300">Ubicación / etapa</label>
                    <el-select v-model="eventForm.location" clearable class="w-full mt-1"
                        placeholder="China, GDL, Aduanas u otra">
                        <el-option v-for="location in locationOptions" :key="location" :label="location" :value="location" />
                        <el-option label="Otro" value="Otro" />
                    </el-select>
                </div>

                <div v-if="eventForm.location === 'Otro'">
                    <label class="text-sm text-gray-600 dark:text-gray-300">Especifica la ubicación / etapa *</label>
                    <input v-model="eventForm.custom_location" type="text" maxlength="100"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200 text-sm"
                        placeholder="Ej. Bodega Guadalajara" />
                </div>

                <div>
                    <label class="text-sm text-gray-600 dark:text-gray-300">
                        Descripción <span v-if="!eventForm.location">*</span>
                    </label>
                    <textarea v-model="eventForm.description" rows="3"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200 text-sm"
                        placeholder="Ej. La mercancía llegó a GDL y quedó en resguardo de aduana."></textarea>
                    <p class="text-[11px] text-gray-400 mt-1">
                        La actualización se pinta en el día seleccionado y se repite en los días siguientes
                        hasta que registres otra.
                    </p>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end space-x-3">
                    <el-button @click="eventModal = false">Cancelar</el-button>
                    <el-button type="primary" :loading="savingEvent" :disabled="!canSubmitEvent" @click="submitEvent">
                        Guardar actualización
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import {
    addMonths,
    eachDayOfInterval,
    endOfMonth,
    endOfWeek,
    format,
    isSameMonth,
    isToday,
    parseISO,
    startOfMonth,
    startOfWeek,
} from 'date-fns';
import { es } from 'date-fns/locale';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    props: {
        arrivals: {
            type: Object,
            default: () => ({ enabled: false, arrivals: [], undated: [] }),
        },
    },
    data() {
        return {
            currentMonth: new Date(),
            selectedEntry: null,
            search: '',
            highlightKey: null,
            highlightTimer: null,
            saving: false,
            deleting: false,
            savingEvent: false,
            eventModal: false,
            eventForm: {
                shipment_id: null,
                event_date: null,
                location: '',
                custom_location: '',
                description: '',
                status: null,
                estimated_arrival_date: null,
                arrived_at: null,
            },
            arrivalForm: {
                status: 'Pendiente',
                date: null,
                arrived_at: null,
                notes: '',
                event_date: null,
                event_description: '',
            },
            // Secciones abiertas del drawer: las actualizaciones quedan plegadas.
            openSections: ['general', 'products'],
            // Última entrada abierta por enlace directo (notificaciones).
            lastDeepLinkedEntry: null,
            weekDays: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            legend: {
                Pendiente: '#9CA3AF',
                'En tránsito': '#F59E0B',
                Incidencia: '#EF4444',
                Arribado: '#22C55E',
            },
        };
    },
    computed: {
        // La edición de arribos se habilita con el permiso de gestión de arribos.
        canManage() {
            return Boolean(this.arrivals?.can_manage);
        },
        statuses() {
            return Object.keys(this.legend);
        },
        // Una guía arribada queda cerrada: su estatus ya no se puede modificar.
        arrivalStatusLocked() {
            return this.selectedEntry?.kind !== 'item' && this.selectedEntry?.status === 'Arribado';
        },
        // Actualización/evento que se muestra (registro propio o estado arrastrado).
        deletableEventId() {
            const entry = this.selectedEntry;

            if (!entry) return null;
            if (entry.kind === 'event') return entry.id;
            if (entry.state_kind === 'event') return entry.state_id ?? null;

            return null;
        },
        // La fecha real solo se captura al pasar a "Arribado".
        showsArrivalDate() {
            return this.selectedEntry?.kind !== 'item'
                && this.arrivalForm.status === 'Arribado'
                && this.selectedEntry?.status !== 'Arribado';
        },
        // Guía elegida en el formulario de actualización / evento.
        eventShipmentOption() {
            return this.shipmentOptions.find(option => option.id === this.eventForm.shipment_id) ?? null;
        },
        eventShipmentLocked() {
            return this.eventShipmentOption?.status === 'Arribado';
        },
        eventShowsArrivalDate() {
            return this.eventForm.status === 'Arribado' && this.eventShipmentOption?.status !== 'Arribado';
        },
        productImages() {
            return (this.selectedEntry?.items ?? []).map(item => item.image_url).filter(Boolean);
        },
        shipmentOptions() {
            return this.arrivals?.shipment_options ?? [];
        },
        // Ubicaciones / etapas sugeridas para el seguimiento.
        locationOptions() {
            return this.arrivals?.event_locations ?? ['China', 'GDL', 'Aduanas'];
        },
        // Ubicación efectiva: la opción elegida o el texto libre de "Otro".
        eventLocation() {
            if (this.eventForm.location === 'Otro') {
                return this.eventForm.custom_location?.trim() ?? '';
            }

            return this.eventForm.location?.trim() ?? '';
        },
        canSubmitEvent() {
            return Boolean(this.eventForm.shipment_id)
                && Boolean(this.eventForm.event_date)
                && Boolean(this.eventLocation || this.eventForm.description?.trim())
                && (!this.eventShowsArrivalDate || Boolean(this.eventForm.arrived_at));
        },
        // Quién levantó las requisiciones del arribo (sin repetir nombres).
        creatorNames() {
            const names = [
                ...(this.selectedEntry?.requisitions ?? []).map(requisition => requisition.creator),
                ...(this.selectedEntry?.items ?? []).map(item => item.requester),
            ].filter(Boolean);

            return [...new Set(names)];
        },
        drawerOpen: {
            get() {
                return Boolean(this.selectedEntry);
            },
            set(value) {
                if (!value) this.selectedEntry = null;
            },
        },
        monthAndYear() {
            return format(this.currentMonth, 'MMMM yyyy', { locale: es });
        },
        calendarDays() {
            const firstDay = startOfWeek(startOfMonth(this.currentMonth), { weekStartsOn: 1 });
            const lastDay = endOfWeek(endOfMonth(this.currentMonth), { weekStartsOn: 1 });

            return eachDayOfInterval({ start: firstDay, end: lastDay }).map(day => ({
                date: format(day, 'yyyy-MM-dd'),
                dayNumber: format(day, 'd'),
                isCurrentMonth: isSameMonth(day, this.currentMonth),
                isToday: isToday(day),
            }));
        },
        entriesByDay() {
            return (this.arrivals.arrivals ?? []).reduce((grouped, entry) => {
                if (!entry.date) return grouped;
                grouped[entry.date] = [...(grouped[entry.date] ?? []), entry];
                return grouped;
            }, {});
        },
    },
    watch: {
        // Precarga el formulario de actualización al abrir un arribo.
        selectedEntry(entry) {
            this.arrivalForm.status = entry?.status ?? 'Pendiente';
            this.arrivalForm.date = entry?.estimated_arrival_date ?? null;
            this.arrivalForm.arrived_at = entry?.arrived_at
                ? format(parseISO(entry.arrived_at), 'yyyy-MM-dd')
                : null;
            this.arrivalForm.notes = entry?.notes ?? '';
            // La descripción nueva se registra en el día que se está consultando.
            this.arrivalForm.event_date = entry?.date ?? format(new Date(), 'yyyy-MM-dd');
            this.arrivalForm.event_description = '';
            // Cada apertura reinicia las secciones plegadas.
            this.openSections = ['general', 'products'];
        },
        // Al elegir una guía en el evento se precargan estatus y fechas vigentes.
        'eventForm.shipment_id'(id) {
            const option = this.shipmentOptions.find(item => item.id === id);

            if (!option) return;

            this.eventForm.status = option.status ?? null;
            this.eventForm.estimated_arrival_date = option.estimated_arrival_date ?? null;
            this.eventForm.arrived_at = null;
        },
        // Al marcar el arribo se propone la fecha del evento (o hoy).
        'eventForm.status'(status) {
            if (status === 'Arribado' && !this.eventForm.arrived_at) {
                this.eventForm.arrived_at = this.eventForm.event_date ?? format(new Date(), 'yyyy-MM-dd');
            }
        },
        // La fecha de arribo se propone con el día seleccionado en el calendario.
        'arrivalForm.status'(status) {
            if (status === 'Arribado' && !this.arrivalForm.arrived_at) {
                this.arrivalForm.arrived_at = this.selectedEntry?.date ?? format(new Date(), 'yyyy-MM-dd');
            }
        },
        // Enlaces directos desde notificaciones (misma página reutilizada por Inertia).
        '$page.url'() {
            this.openFromQuery();
        },
    },
    mounted() {
        this.openFromQuery();
    },
    methods: {
        /**
         * Abre el registro indicado en la URL (?tab=arrivals&entry=shipment-123):
         * salta al mes de la fecha tentativa, lo resalta y muestra su detalle.
         */
        openFromQuery() {
            let entryKey = null;

            try {
                entryKey = new URL(window.location.href).searchParams.get('entry');
            } catch (error) {
                return;
            }

            if (!entryKey || entryKey === this.lastDeepLinkedEntry) return;

            const entries = [...(this.arrivals?.arrivals ?? []), ...(this.arrivals?.undated ?? [])];
            const entry = entries.find(candidate => candidate.key === entryKey);

            if (!entry) return;

            this.lastDeepLinkedEntry = entryKey;
            this.search = entry.title;
            this.highlightEntry(entry);
            this.selectedEntry = entry;
        },
        // Cierra el detalle del arribo (lo usa el calendario al cambiar de pestaña).
        closeDrawer() {
            this.selectedEntry = null;
        },
        changeMonth(offset) {
            this.currentMonth = addMonths(this.currentMonth, offset);
        },
        /**
         * Busca por número de guía o folio/id de requisición y señala el primer
         * registro (el día más antiguo) del seguimiento, no la fecha tentativa.
         */
        searchArrival() {
            const query = String(this.search ?? '').trim();

            if (!query) {
                this.clearHighlight();
                return;
            }

            const normalized = this.normalizeSearch(query);

            if (normalized.length < 2) {
                ElMessage.warning('Ingresa al menos 2 caracteres para buscar.');
                return;
            }

            const dated = this.arrivals?.arrivals ?? [];
            const undated = this.arrivals?.undated ?? [];

            // Se señala el primer registro del folio: el día más antiguo del
            // seguimiento (alta de la guía), sin importar el orden del listado.
            // La fecha tentativa no cuenta como primer registro.
            const matches = dated.filter(candidate =>
                candidate.kind !== 'estimate' && this.matchesSearch(candidate, normalized));
            const entry = matches.length
                ? matches.reduce((earliest, candidate) => (!earliest || candidate.date < earliest.date ? candidate : earliest), null)
                : undated.find(candidate => this.matchesSearch(candidate, normalized));

            if (!entry) {
                ElMessage.warning('No se encontró ninguna guía ni requisición con ese folio o número de guía.');
                return;
            }

            this.highlightEntry(entry);
        },
        normalizeSearch(value) {
            return String(value).toLowerCase().replace(/[^a-z0-9]/g, '');
        },
        foliosFor(entry) {
            return [
                ...(entry.requisitions ?? []).map(requisition => requisition.folio),
                ...(entry.items ?? []).map(item => item.folio),
            ].filter(Boolean);
        },
        // Solo se ubican las guías / partidas (registros del seguimiento), no los eventos.
        matchesSearch(entry, normalized) {
            if (!normalized || entry.kind === 'event') return false;

            if (this.normalizeSearch(entry.title).includes(normalized)) return true;

            // Permite buscar por id de requisición: "48", "REQ-0048" o "req48".
            const queryNumber = Number(normalized.replace(/[^0-9]/g, ''));

            return this.foliosFor(entry).some(folio => {
                const normalizedFolio = this.normalizeSearch(folio);

                if (normalizedFolio.includes(normalized)) return true;

                return queryNumber > 0 && Number(normalizedFolio.replace(/[^0-9]/g, '')) === queryNumber;
            });
        },
        highlightEntry(entry) {
            if (!entry.date) {
                // Sin fecha de registro no hay mes al que saltar: se abre su detalle.
                this.selectedEntry = entry;
                ElMessage.warning(`La guía ${entry.title} no tiene fecha de registro.`);
                return;
            }

            // Salta al mes correspondiente y resalta el registro.
            this.currentMonth = parseISO(entry.date);
            this.highlightKey = entry.key;

            clearTimeout(this.highlightTimer);
            this.highlightTimer = setTimeout(() => {
                this.highlightKey = null;
            }, 6000);

            this.$nextTick(() => {
                setTimeout(() => {
                    const element = document.querySelector(`[data-entry-key="${entry.key}"]`);
                    element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 60);
            });

            ElMessage.success(`Primer registro de ${entry.title}: ${this.formatDate(entry.date)}.`);
        },
        clearHighlight() {
            clearTimeout(this.highlightTimer);
            this.highlightKey = null;
        },
        dayHasHighlight(date) {
            if (!this.highlightKey) return false;

            return (this.entriesByDay[date] ?? []).some(entry => entry.key === this.highlightKey);
        },
        /**
         * Título del arribo con la nota de la guía concatenada y truncada.
         */
        entryLabel(entry) {
            if (entry.kind === 'event') {
                return `${entry.title} · ${this.truncateText(entry.description)}`;
            }

            // En los días repetidos se muestra la última actualización registrada.
            const note = (entry.state_label ?? '').trim();

            return note ? `${entry.title} · ${this.truncateText(note)}` : entry.title;
        },
        truncateText(value, maxLength = 40) {
            const text = (value ?? '').trim();

            if (!text) return '';

            return text.length > maxLength ? `${text.slice(0, maxLength).trimEnd()}…` : text;
        },
        previewIndex(item) {
            return Math.max(this.productImages.indexOf(item.image_url), 0);
        },
        // Distinción visual: bandera = fecha tentativa de arribo, bandera a cuadros
        // = fecha de arribo, coche = registro de guía, actualizaciones y eventos.
        entryIcon(entry) {
            const kind = entry.carried ? entry.state_kind : entry.kind;

            if (kind === 'arrival') return 'fa-solid fa-flag-checkered';

            return kind === 'estimate' || kind === 'item'
                ? 'fa-solid fa-flag'
                : 'fa-solid fa-truck-fast';
        },
        entryTooltip(entry) {
            if (entry.kind === 'event') {
                return `Actualización/evento · ${entry.description ?? ''}`;
            }

            if (entry.kind === 'estimate') {
                return `Fecha tentativa de arribo · ${entry.title}${entry.date ? ' · ' + this.formatDate(entry.date) : ''}`;
            }

            if (entry.kind === 'arrival') {
                return `Fecha de arribo · ${entry.title}${entry.date ? ' · ' + this.formatDate(entry.date) : ''}`;
            }

            if (entry.kind === 'item') {
                return `Fecha tentativa de arribo · ${entry.subtitle ?? 'Mercancía'}`;
            }

            const state = (entry.state_label ?? '').trim();
            const status = entry.arrived_at ? 'Arribo confirmado' : 'Registro de guía';

            return state ? `${status} · ${state}` : status;
        },
        kindLabel(entry) {
            if (entry.kind === 'event') return 'Actualización / evento';
            if (entry.kind === 'estimate') return 'Fecha tentativa de arribo';
            if (entry.kind === 'arrival') return 'Fecha de arribo';
            if (entry.kind === 'shipment') return 'Guía de paquetería';

            return entry.kind === 'item' ? 'Producto' : 'Requisición';
        },
        kindTagType(entry) {
            if (entry.kind === 'event') return 'warning';
            if (entry.kind === 'shipment') return 'primary';
            if (entry.kind === 'arrival') return 'success';

            return 'info';
        },
        // Abre el alta de evento con el día seleccionado ya cargado.
        openEventDialog(date = null) {
            if (!this.canManage) return;

            const entry = this.selectedEntry;
            const shipmentId = entry && entry.kind !== 'item'
                ? (entry.shipment_id ?? (entry.kind === 'shipment' ? entry.id : null))
                : null;

            this.eventForm.shipment_id = shipmentId;
            this.eventForm.event_date = date;
            this.eventForm.location = '';
            this.eventForm.custom_location = '';
            this.eventForm.description = '';
            this.eventForm.status = shipmentId ? (entry?.status ?? null) : null;
            this.eventForm.estimated_arrival_date = shipmentId ? (entry?.estimated_arrival_date ?? null) : null;
            this.eventForm.arrived_at = null;
            this.eventModal = true;
        },
        submitEvent() {
            if (!this.canSubmitEvent) return;

            if (!this.eventShowsArrivalDate) {
                this.postEvent();

                return;
            }

            ElMessageBox.confirm(
                'Al confirmar el arribo, el estatus de la guía quedará cerrado y ya no podrá cambiarse. ¿Continuar?',
                'Confirmar arribo',
                { confirmButtonText: 'Confirmar arribo', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.postEvent()).catch(() => {});
        },
        postEvent() {
            const form = this.eventForm;

            this.savingEvent = true;

            router.post(route('requisition-shipments.store-event', form.shipment_id), {
                event_date: form.event_date,
                location: this.eventLocation || null,
                description: form.description?.trim() || null,
                status: form.status || null,
                estimated_arrival_date: form.estimated_arrival_date || null,
                arrived_at: this.eventShowsArrivalDate ? form.arrived_at : null,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    this.eventModal = false;
                    this.selectedEntry = null;
                    ElMessage.success('Actualización registrada.');
                },
                onError: (errors) => ElMessage.error(Object.values(errors ?? {})[0] ?? 'No se pudo registrar la actualización.'),
                onFinish: () => (this.savingEvent = false),
            });
        },
        deleteEvent(eventId) {
            this.deleting = true;

            router.delete(route('requisition-shipment-events.destroy', eventId), {
                preserveScroll: true,
                onSuccess: () => {
                    this.selectedEntry = null;
                    ElMessage.success('Actualización eliminada.');
                },
                onError: () => ElMessage.error('No se pudo eliminar la actualización.'),
                onFinish: () => (this.deleting = false),
            });
        },
        saveArrival() {
            const entry = this.selectedEntry;

            if (!entry) return;

            if (this.showsArrivalDate && !this.arrivalForm.arrived_at) {
                ElMessage.warning('Captura la fecha de arribo.');

                return;
            }

            if (!this.showsArrivalDate) {
                this.persistArrival(entry);

                return;
            }

            ElMessageBox.confirm(
                'Al confirmar el arribo, el estatus de la guía quedará cerrado y ya no podrá cambiarse. ¿Continuar?',
                'Confirmar arribo',
                { confirmButtonText: 'Confirmar arribo', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.persistArrival(entry)).catch(() => {});
        },
        persistArrival(entry) {
            const status = this.arrivalForm.status;
            const date = this.arrivalForm.date || null;
            const notes = this.arrivalForm.notes || '';
            const arrivedAt = status === 'Arribado' ? this.arrivalForm.arrived_at : null;
            // Descripción nueva: se guarda como actualización con su propia fecha.
            const description = this.arrivalForm.event_description?.trim() || null;

            const options = {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    // Se refleja el cambio en el detalle abierto sin cerrar el panel.
                    entry.status = status;
                    entry.estimated_arrival_date = date;
                    entry.arrived_at = arrivedAt;
                    entry.color = this.legend[status] ?? '#9CA3AF';

                    if (entry.kind === 'item') {
                        entry.notes = notes;
                    }

                    // Con una descripción nueva se cierra el detalle para ver el
                    // registro recién agregado en el calendario.
                    if (description) {
                        this.selectedEntry = null;
                    }

                    ElMessage.success(description
                        ? 'Actualización registrada en el historial.'
                        : 'Arribo actualizado.');
                },
                onError: (errors) => ElMessage.error(Object.values(errors ?? {})[0] ?? 'No se pudo actualizar el arribo.'),
                onFinish: () => (this.saving = false),
            };

            this.saving = true;

            // Las partidas sin guía actualizan su estatus, la fecha tentativa y notas.
            if (entry.kind === 'item') {
                router.put(route('requisition-items.arrival-status', entry.id), {
                    arrival_status: status,
                    expected_delivery_date: date,
                    arrival_notes: notes,
                }, options);

                return;
            }

            // Guías y eventos actualizan la guía a la que pertenecen.
            router.put(route('requisition-shipments.update-status', entry.shipment_id ?? entry.id), {
                status,
                estimated_arrival_date: date,
                arrived_at: arrivedAt,
                event_date: this.arrivalForm.event_date,
                event_description: description,
            }, options);
        },
        formatDate(dateString) {
            if (!dateString) return 'Sin definir';
            return format(parseISO(dateString), "d 'de' MMM, yyy", { locale: es });
        },
        formatDateTime(dateString) {
            if (!dateString) return '—';
            return format(parseISO(dateString), "d 'de' MMM, yyy HH:mm", { locale: es });
        },
    },
    beforeUnmount() {
        clearTimeout(this.highlightTimer);
    },
};
</script>

<style>
/* Secciones del detalle del arribo: tarjetas con más aire entre sí.
   El ajuste se aplica solo en modo claro; en oscuro se conserva el estilo
   nativo de Element Plus. */
html:not(.dark) .arrivals-detail .el-collapse {
    border: none;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

html:not(.dark) .arrivals-detail .el-collapse-item {
    background-color: #ffffff;
    border: 1px solid var(--el-border-color-light);
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

html:not(.dark) .arrivals-detail .el-collapse-item__header {
    height: 48px;
    padding: 0 16px;
    background-color: #f5f7fa;
    border-bottom: 1px solid transparent;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
    font-size: 14px;
    color: var(--el-text-color-primary);
    transition: background-color 0.2s ease, color 0.2s ease;
}

html:not(.dark) .arrivals-detail .el-collapse-item__header:hover {
    background-color: #eef2f7;
    color: var(--el-color-primary);
}

html:not(.dark) .arrivals-detail .el-collapse-item__header.is-active {
    border-bottom-color: var(--el-border-color-lighter);
}

html:not(.dark) .arrivals-detail .el-collapse-item__arrow {
    margin-right: 4px;
    color: var(--el-text-color-secondary);
}

html:not(.dark) .arrivals-detail .el-collapse-item__wrap {
    border-bottom: none;
    background-color: #ffffff;
}

html:not(.dark) .arrivals-detail .el-collapse-item__content {
    padding: 16px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--el-text-color-regular);
}

/* Animación para señalar el registro encontrado con el buscador. */
@keyframes arrivalsPulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.75);
    }
    60% {
        transform: scale(1.08);
        box-shadow: 0 0 0 8px rgba(59, 130, 246, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
    }
}

.arrivals-highlight {
    animation: arrivalsPulse 1.2s ease-in-out 4;
    outline: 2px solid #3b82f6;
    outline-offset: 1px;
    z-index: 1;
}
</style>
