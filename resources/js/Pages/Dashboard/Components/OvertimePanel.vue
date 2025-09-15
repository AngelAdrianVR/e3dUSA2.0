<template>
    <div class="p-5 bg-white rounded-lg shadow-md dark:bg-gray-800">
        <h3 class="mb-2 text-base font-semibold text-gray-800 dark:text-white">Oportunidad de tiempo extra</h3>
        <div v-if="!responseMessage">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Necesitamos tu colaboración para realizar tiempo extra el
                <span class="font-bold text-green-600 dark:text-green-400">{{ overtimeOpportunity.date }}</span>,
                por <span class="font-bold">{{ overtimeOpportunity.hours }} horas</span>. Se otorgarán
                <span class="font-bold text-yellow-500">{{ overtimeOpportunity.points }} puntos</span> adicionales a tu desempeño.
            </p>
            <div class="flex items-center space-x-3">
                <button @click="accept" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                    Aceptar
                </button>
                <button @click="reject" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                    Rechazar
                </button>
            </div>
        </div>
        <div v-else>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ responseMessage }}</p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'OvertimePanel',
    props: {
        overtimeOpportunity: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            responseMessage: '',
            isHandled: false,
        }
    },
    methods: {
        accept() {
            console.log('Accepted overtime request:', this.overtimeOpportunity.id);
            this.responseMessage = '¡Gracias por aceptar! Tu supervisor ha sido notificado.';
            // Aquí puedes hacer una llamada a la API para actualizar el estado de la solicitud.
            // Ejemplo: this.$inertia.post(`/overtime-requests/${this.overtimeOpportunity.id}/accept`);
            setTimeout(() => this.isHandled = true, 3000);
        },
        reject() {
            console.log('Rejected overtime request:', this.overtimeOpportunity.id);
            this.responseMessage = 'Has rechazado la oportunidad. No hay problema.';
            // Podrías ocultar el componente después de rechazarlo.
            setTimeout(() => this.isHandled = true, 3000);
        }
    }
}
</script>
