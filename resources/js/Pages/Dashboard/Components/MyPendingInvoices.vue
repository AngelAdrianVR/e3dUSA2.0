<template>
    <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
        <h3 class="mb-3 text-lg font-semibold text-gray-800 dark:text-white">
            Mis Facturas Pendientes de Pago
        </h3>

        <div v-if="invoices.length">
            <ul class="space-y-1">
                <li v-for="invoice in invoices" :key="invoice.id">
                    <Link :href="route('invoices.show', invoice.id)" class="flex items-center justify-between p-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        <div class="flex items-center space-x-2">
                             <svg v-if="isOverdue(invoice)" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.21 3.03-1.742 3.03H4.42c-1.532 0-2.492-1.696-1.742-3.03l5.58-9.92zM10 13a1 1 0 110-2 1 1 0 010 2zm-1-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex flex-col">
                                <span class="font-bold text-gray-700 dark:text-gray-300">{{ invoice.folio }}</span>
                                <span v-if="isOverdue(invoice)" class="text-xs text-red-500 dark:text-red-400">Venció el: {{ invoice.due_date }}</span>
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">Vence: {{ invoice.due_date }}</span>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <span :class="getStatusClass(invoice.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                                {{ invoice.status }}
                            </span>
                            <p class="mt-1 font-semibold text-red-500">
                               ${{ formatCurrency(invoice.pending_amount) }}
                            </p>
                             <p class="text-xs text-gray-500 dark:text-gray-400">
                               Total: ${{ formatCurrency(invoice.total_amount) }} {{ invoice.currency }}
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
        <div v-else>
            <p class="text-sm text-center text-gray-500 dark:text-gray-400">
                ¡Felicidades! No tienes facturas pendientes.
            </p>
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3';

export default {
    components: {
        Link,
    },
    props: {
        invoices: {
            type: Array,
            required: true,
        },
    },
    methods: {
        isOverdue(invoice) {
            const dueDate = new Date(invoice.due_date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return dueDate < today;
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '0.00';
            return parseFloat(value).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        getStatusClass(status) {
            const statusClasses = {
                'Pendiente': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                'Parcialmente pagada': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            };
            return statusClasses[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        }
    }
};
</script>

