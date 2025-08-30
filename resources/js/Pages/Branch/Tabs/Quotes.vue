<template>
<p class="text-xs mb-1 ml-3">Últimas 20</p>
    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-slate-900 dark:text-gray-300">
            <tr>
                <th scope="col" class="px-2 py-3 w-36">Folio</th>
                <th scope="col" class="px-2 py-3">Creado por</th>
                <th scope="col" class="px-2 py-3">Total</th>
                <th scope="col" class="px-2 py-3">Autorizado</th>
                <th scope="col" class="px-2 py-3">OV</th>
                <th scope="col" class="px-2 py-3">Estatus</th>
                <th scope="col" class="px-2 py-3">Creado el</th>
            </tr>
        </thead>
        <tbody>
            <!-- Iterar sobre las cotizaciones cuando las tengas -->
            <tr v-for="quote in quotes" :key="quote.id" @click="openQuote(quote.id)"
                class="bg-white dark:bg-slate-800/80 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                <td class="px-5 py-4 font-medium text-gray-900 dark:text-white">
                    <!-- Tooltip de descuento -->
                    <el-tooltip v-if="quote.has_early_payment_discount" placement="top">
                        <template #content>
                            <div v-if="quote.early_paid_at">
                                <p>Descuento por pago anticipado aplicado.</p>
                                <span>Pagado el: <strong>{{ formatDate(quote.early_paid_at)
                                }}</strong></span>
                            </div>
                            <p v-else>
                                Contiene descuento por pago anticipado. <br>
                                Aún no pagado
                            </p>
                        </template>
                        <i class="fa-solid fa-fire text-sm mr-2"
                            :class="quote.early_paid_at ? 'text-green-500' : 'text-red-500'"></i>
                    </el-tooltip>
                    COT-{{ String(quote.id).padStart(3, '0') }}
                </td>
                <td class="px-5 py-4">{{ quote.user?.name ?? 'Sin usuario asignado' }}</td>
                <td class="px-5 py-4">${{ quote.total_data.total_after_discount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ quote.currency }}</td>
                <td class="px-5 py-4 text-center">
                    <i v-if="quote.authorized_at" class="fa-solid fa-check-double text-green-500" title="Autorizado"></i>
                    <p v-else>No autorizado</p>
                </td>
                <td class="px-5 py-4">{{ quote.sale_id ?? 'N/A' }}</td>
                <td class="px-5 py-4">
                    <el-tooltip placement="top">
                        <template #content>
                            <p v-html="getStatusTooltip(quote)"></p>
                        </template>
                        <span v-html="getStatusIcon(quote.status)" class="text-sm mr-2"></span>
                    </el-tooltip>
                </td>
                <td class="px-5 py-4 text-gray-500 dark:text-gray-400">{{ formatDate(quote.created_at) }}</td>
            </tr>
                <!-- Mensaje si no hay cotizaciones -->
            <tr v-if="!quotes.length">
                <td colspan="6" class="text-center py-10 text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center">
                        <i class="fa-regular fa-file-lines text-4xl mb-3"></i>
                        <span>No hay cotizaciones para mostrar.</span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script>
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
data() {
    return {
        statusMap: {
            'Aceptada': { icon: '<i class="fa-solid fa-circle-check text-green-500"></i>', text: 'Aceptada por el cliente' },
            'Rechazada': { icon: '<i class="fa-solid fa-circle-xmark text-red-500"></i>', text: 'Rechazada por el cliente' },
            'Esperando respuesta': { icon: '<i class="fa-solid fa-hourglass-half text-amber-500"></i>', text: 'Esperando respuesta del cliente' },
        }
    }
},
components:{

},
props:{
    quotes: Array
},
methods:{
    openQuote(id) {
        const url = route('quotes.show', id);
        window.open(url, '_blank'); // abre en una nueva pestaña
    },
    getStatusIcon(status) {
        return this.statusMap[status]?.icon || '<i class="fa-solid fa-circle-question text-gray-400"></i>';
    },
    getStatusTooltip(row) {
        let baseText = this.statusMap[row.status]?.text || 'Estatus desconocido';
        if (row.customer_responded_at) {
            baseText += `<br>Fecha: ${this.formatDate(row.customer_responded_at)}`;
        }
        if (row.status === 'Rechazada' && row.rejection_reason) {
            baseText += `<br>Motivo: <b>${row.rejection_reason}</b>`;
        }
        return baseText;
    },
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return format(date, "d 'de' MMMM, yyyy", { locale: es });
    },
}
}
</script>
