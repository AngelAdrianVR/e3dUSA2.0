<template>
    <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg overflow-hidden mb-5">
        
        <!-- HEADER DEL ACORDEÓN -->
        <div @click="isOpen = !isOpen" class="p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            
            <div class="flex items-center gap-3">
                <div class="bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400 size-10 rounded-lg flex items-center justify-center font-bold">
                    #{{ index + 1 }}
                </div>
                <div>
                    <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200">Parcialidad</h3>
                    <!-- Estado de la parcialidad -->
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                          :class="statusClass(shipment.status)">
                        {{ shipment.status }}
                    </span>
                </div>
            </div>

            <div class="flex items-center w-full sm:w-auto justify-between sm:justify-end gap-4 mt-2 sm:mt-0">
                <!-- Información mostrada cuando ESTÁ COLAPSADO y ya fue ENVIADO -->
                <div v-if="!isOpen && shipment.status === 'Enviado'" class="text-sm text-gray-500 dark:text-gray-400 flex flex-col sm:flex-row gap-2 sm:gap-4 items-start sm:items-center mr-2 bg-gray-50 dark:bg-slate-700/30 px-3 py-1.5 rounded-md">
                    <span class="flex items-center gap-1.5" title="Enviado por">
                        <i class="fa-solid fa-user-check text-green-500"></i> <span class="truncate max-w-[120px]">{{ shipment.sent_by || 'Sistema' }}</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-xs">
                        <i class="fa-solid fa-calendar-check text-blue-500"></i> {{ formatDateTime(shipment.sent_at) }}
                    </span>
                </div>
                
                <i :class="isOpen ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid text-gray-400 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-slate-600 transition-all"></i>
            </div>
        </div>

        <!-- CONTENIDO EXPANDIDO (Tu componente ShipmentCard intacto) -->
        <el-collapse-transition>
            <div v-show="isOpen" class="border-t dark:border-gray-700 bg-gray-50/50 dark:bg-slate-800/30 p-1">
                <slot></slot> 
            </div>
        </el-collapse-transition>
    </div>
</template>

<script>
export default {
    name: 'ShipmentAccordion',
    props: {
        shipment: { type: Object, required: true },
        index: { type: Number, required: true }
    },
    data() {
        return {
            isOpen: false // Siempre colapsado por defecto
        }
    },
    methods: {
        formatDateTime(dateString) {
            if (!dateString) return 'Sin fecha';
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace('.', '');
        },
        statusClass(status) {
            switch (status) {
                case 'Enviado': return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300';
                case 'Pendiente': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
                case 'Cancelado': return 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300';
                default: return 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300';
            }
        }
    }
}
</script>