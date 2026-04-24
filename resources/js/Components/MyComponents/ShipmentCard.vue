<template>
    <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-xl p-5 transition-all duration-300 border-2"
         :class="isShipped ? 'border-green-400 dark:border-green-600 shadow-green-500/20' : 'border-transparent'">

        <!-- ===== GRAN INDICADOR DE ENVIADO ===== -->
        <div v-if="isShipped" class="bg-green-50 dark:bg-green-900/30 rounded-lg p-4 mb-5 flex flex-col sm:flex-row items-start sm:items-center justify-between border border-green-200 dark:border-green-800">
            <div class="flex items-start gap-4">
                <div class="bg-green-500 text-white rounded-full w-12 h-12 flex-shrink-0 flex items-center justify-center text-2xl shadow-lg shadow-green-500/30">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-green-700 dark:text-green-400 text-lg">¡Parcialidad Enviada!</h3>
                    <p class="text-sm text-green-600 dark:text-green-500 mt-0.5">
                        <i class="fa-solid fa-user-check mr-1"></i> Enviado por: <strong>{{ shipment.sent_by || 'N/A' }}</strong>
                        <span class="mx-2">•</span>
                        <i class="fa-regular fa-clock mr-1"></i> {{ formatDateTime(shipment.sent_at) }}
                    </p>
                    
                    <!-- NUEVO: Mostrar notas prominentemente si existen -->
                    <div v-if="shipment.notes" class="mt-3 mb-1 bg-white/60 dark:bg-slate-800/60 p-3 rounded-lg border border-green-200 dark:border-green-800/50">
                        <p class="text-xs font-bold text-green-800 dark:text-green-300 uppercase tracking-wider mb-1">
                            <i class="fa-solid fa-note-sticky mr-1 opacity-70"></i> Notas del envío:
                        </p>
                        <p class="text-sm text-green-700 dark:text-green-400 italic">"{{ shipment.notes }}"</p>
                    </div>
                </div>
            </div>
            <div class="mt-3 sm:mt-0 self-start sm:self-center">
               <el-tag type="success" effect="dark" size="large" class="!font-bold !px-4 tracking-wide"><i class="fa-solid fa-box-check mr-1"></i> COMPLETADO</el-tag>
            </div>
        </div>

        <!-- ===== ENCABEZADO DE LA PARCIALIDAD ===== -->
        <div class="flex justify-between items-center border-b dark:border-gray-600 pb-3 mb-4">
            <h3 class="text-lg font-bold flex items-center gap-2 text-gray-800 dark:text-gray-100">
                Envío Parcial #{{ index + 1 }}
            </h3>
            <div class="flex items-center space-x-2">
                <!-- Botón Etiquetas -->
                <el-tooltip content="Crear etiqueta(s) de envío" placement="top">
                    <button @click="$emit('open-label', shipment)" class="px-3 py-1 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600 transition-colors duration-200 flex items-center gap-2">
                        <i class="fa-solid fa-tags"></i>
                    </button>
                </el-tooltip>

                <!-- Botón Eliminar Parcialidad (Pendiente) -->
                <el-popconfirm v-if="!isShipped" title="¿Eliminar esta parcialidad?" confirm-button-text="Sí" cancel-button-text="No" @confirm="$emit('delete-shipment', shipment.id)">
                    <template #reference>
                        <div class="inline-flex">
                            <el-tooltip content="Eliminar parcialidad" placement="top">
                                <button class="px-3 py-1 bg-red-100 text-red-600 rounded-md text-sm hover:bg-red-200 transition-colors duration-200 flex items-center gap-2">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </el-tooltip>
                        </div>
                    </template>
                </el-popconfirm>

                <!-- Botón Marcar como Enviado -->
                <!-- Se forza el false en disabled ya que a veces se quiere marcar como enviado aunque no esté se tenga la cantidad completa, se puede agregar la cantidad que en realidad se mandó. -->
                <button v-if="!isShipped && $page.props.auth.user.permissions.includes('Marcar parcialidades como enviadas')"
                    @click="$emit('open-confirmation', shipment)"
                    :disabled="!isReady && false"
                    class="px-3 py-1 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors duration-200 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-truck-fast"></i>
                    Marcar como Enviado
                </button>
                
                <el-tag v-if="!isShipped" :type="getStatusTagType(shipment.status)">{{ shipment.status }}</el-tag>
                
                <!-- Indicador de Alerta -->
                <el-tooltip v-if="isOverdueAlert" content="La fecha promesa ha pasado" placement="top">
                    <i class="fa-solid fa-triangle-exclamation text-red-500 text-xl animate-pulse"></i>
                </el-tooltip>
            </div>
        </div>
        
        <!-- ===== INFORMACIÓN DEL ENVÍO ===== -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <!-- Sección Izquierda: Datos del Envío -->
            <div class="grid grid-cols-2 gap-4 text-sm bg-gray-50 dark:bg-slate-700/30 p-4 rounded-lg border dark:border-gray-600">
                <div class="col-span-2 sm:col-span-1">
                    <p class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-1">Fecha Promesa</p>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatDate(shipment.promise_date) || 'N/A' }}</span>
                </div>
                <!-- Editar paqueteria tambien incluye botón acá para que sea más obvio -->
                <div class="col-span-2 sm:col-span-1">
                    <p class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-1">Paquetería</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ shipment.shipping_company ?? 'N/A' }}</span>
                        
                        <el-tooltip content="Editar paquetería y guía" placement="top">
                            <button @click="$emit('open-guide', shipment)" class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 p-1.5 rounded transition-colors">
                                <i class="fa-solid fa-pencil"></i>
                            </button>
                        </el-tooltip>
                    </div>
                </div>
                <div class="col-span-2">
                    <p class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-1">Guía de Rastreo</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span v-if="shipment.tracking_guide" class="font-mono bg-white dark:bg-gray-800 px-3 py-1 rounded-md text-sm select-all border dark:border-gray-600 shadow-sm text-blue-600 dark:text-blue-400 font-semibold">
                            {{ shipment.tracking_guide }}
                        </span>
                        <span v-else class="text-gray-400 italic text-sm">No asignada</span>
                        
                        <el-tooltip content="Editar información de rastreo" placement="top">
                            <button @click="$emit('open-guide', shipment)" class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 p-1.5 rounded transition-colors">
                                <i class="fa-solid fa-pencil"></i>
                            </button>
                        </el-tooltip>
                    </div>
                </div>
            </div>

            <!-- Sección Derecha: Evidencias -->
            <div class="border dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-slate-700/30 flex flex-col">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 flex items-center">
                        <i class="fa-solid fa-camera mr-2 text-gray-400"></i> Evidencia de Entrega
                    </h4>
                    <button @click="$emit('open-evidence', shipment)" class="text-[11px] bg-blue-100 text-blue-700 font-semibold px-2.5 py-1 rounded hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-300 transition-colors">
                        <i class="fa-solid fa-plus mr-1"></i> Añadir
                    </button>
                </div>
                
                <div v-if="shipment.media?.length" class="flex-1 overflow-y-auto pr-1">
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="file in shipment.media" :key="file.id" class="relative group">
                            <!-- Popconfirm eliminar -->
                            <el-popconfirm title="¿Eliminar archivo?" confirm-button-text="Sí" cancel-button-text="No" @confirm="$emit('delete-media', file.id)">
                                <template #reference>
                                    <button class="absolute -top-1 -right-1 z-10 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-md hover:bg-red-600" title="Eliminar">✕</button>
                                </template>
                            </el-popconfirm>

                            <!-- Archivo -->
                            <a :href="file.original_url" target="_blank" class="flex flex-col items-center justify-center bg-white dark:bg-slate-800 border dark:border-gray-600 rounded-lg p-2 hover:border-blue-400 transition-colors text-center shadow-sm h-full">
                                <img v-if="file.mime_type.includes('image')" :src="file.original_url" @error="handleImageError" alt="Evidencia" class="w-full h-16 object-cover rounded mb-1" />
                                <i v-else class="fa-regular fa-file-pdf text-2xl text-red-500 mb-1"></i>
                                <span class="text-[10px] text-gray-600 dark:text-gray-400 truncate w-full" :title="file.file_name">{{ file.file_name }}</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div v-else class="flex-1 flex items-center justify-center py-4 bg-white dark:bg-slate-800 rounded border border-dashed border-gray-300 dark:border-gray-600">
                    <p class="text-xs text-gray-400 italic">Sin evidencias adjuntas</p>
                </div>
            </div>
        </div>
        
        <!-- ===== PRODUCTOS EN ESTE ENVÍO ===== -->
        <h4 class="text-md font-semibold mb-3 mt-5 border-t dark:border-gray-600 pt-4 text-gray-800 dark:text-gray-200">
            <i class="fa-solid fa-box-open mr-2 text-gray-400"></i> Productos en este envío
        </h4>
        <div v-if="shipment.shipment_products?.length" class="space-y-3 max-h-[400px] overflow-auto pr-2">
            <!-- AGREGAMOS EL PROP NUEVO :is-shipment-shipped="isShipped" -->
            <ShipmentProductCard v-for="item in shipment.shipment_products" :key="item.id" :shipment-product="item" :shipmentStatus="saleStatus" :is-shipment-shipped="isShipped" />
        </div>
        <div v-else class="text-center bg-gray-50 dark:bg-slate-700/30 rounded-lg border border-dashed dark:border-gray-600 text-gray-500 text-sm py-8 mt-2">
            <p>No se han registrado productos para este envío.</p>
        </div>
    </div>
</template>

<script>
import ShipmentProductCard from "@/Components/MyComponents/ShipmentProductCard.vue";
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'ShipmentCard',
    components: {
        ShipmentProductCard,
    },
    props: {
        shipment: {
            type: Object,
            required: true
        },
        index: {
            type: Number,
            required: true
        },
        saleStatus: {
            type: String,
            required: true
        },
        isReady: {
            type: Boolean,
            required: true
        }
    },
    emits: ['open-label', 'open-confirmation', 'open-guide', 'open-evidence', 'delete-media'],
    computed: {
        isShipped() {
            return this.shipment.status === 'Enviado';
        },
        isOverdueAlert() {
            if (!this.shipment.promise_date || this.isShipped) return false;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const promise = new Date(this.shipment.promise_date.replace(/-/g, '/'));
            return promise < today;
        }
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return '';
            let date;
            if (dateString.includes(' ')) {
                const [datePart] = dateString.split(' ');
                const [year, month, day] = datePart.split('-');
                date = new Date(year, month - 1, day);
            } else {
                try {
                    date = parseISO(dateString);
                } catch {
                    return '';
                }
            }
            if (isNaN(date)) return '';
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatDateTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace('.', '');
        },
        getStatusTagType(status) {
            const statusMap = { 'Pendiente': 'warning', 'Enviado': 'success' };
            return statusMap[status] || 'info';
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
        }
    }
}
</script>