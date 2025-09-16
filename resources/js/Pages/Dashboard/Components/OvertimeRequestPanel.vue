<template>
  <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-md dark:bg-gray-800 dark:border-gray-700 h-full flex flex-col">
    <h5 class="mb-2 text-xl font-bold tracking-tight text-gray-900 dark:text-white">Tiempo Adicional</h5>
    
    <!-- Formulario para nueva solicitud -->
    <form @submit.prevent="submitCreateForm" class="flex-grow flex flex-col">
      <p class="mb-4 text-sm font-normal text-gray-700 dark:text-gray-400">Realiza una nueva solicitud aquí.</p>
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <label for="overtime_date" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Fecha*</label>
          <input v-model="createForm.date"  type="date" id="overtime_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" required>
        </div>
        <div>
          <label for="overtime_minutes" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Minutos*</label>
          <input v-model="createForm.requested_minutes" type="number" id="overtime_minutes" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" placeholder="Ej. 120" required>
        </div>
      </div>
      <div class="mb-4 flex-grow flex flex-col">
        <label for="overtime_reason" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Motivo*</label>
        <textarea v-model="createForm.reason" required id="overtime_reason" rows="2" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white flex-grow" placeholder="Describe el motivo..."></textarea>
      </div>
      <button :disabled="createForm.processing" type="submit" class="mt-auto text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50">
        Enviar Solicitud
      </button>
    </form>
    
    <!-- Lista de solicitudes pendientes -->
    <div v-if="pendingRequests?.length" class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
        <h6 class="mb-2 font-semibold text-gray-900 dark:text-white">Mis Solicitudes Activas</h6>
        <ul class="space-y-2">
            <li v-for="request in pendingRequests" :key="request.id" class="flex justify-between items-center text-sm">
                <span class="text-gray-700 dark:text-gray-300">{{ request.date }} - {{ request.requested_minutes }} min.</span>
                <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="getStatusClass(request.status)">
                    {{ request.status }}
                </span>
            </li>
        </ul>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    pendingRequests: Array,
});

const createForm = useForm({
    date: null,
    requested_minutes: null,
    reason: '',
});

const submitCreateForm = () => {
    // El employee_detail_id se asignará en el backend para el usuario autenticado
    createForm.post(route('overtime-requests.store'), {
        onSuccess: () => {
            createForm.reset();
            ElMessage.success('Solicitud enviada correctamente');
        },
        onError: (errors) => {
            // Mostrar errores de validación si los hay
            Object.values(errors).forEach(error => {
                ElMessage.error(error);
            });
        },
        preserveScroll: true, // Mantiene la posición del scroll para no saltar al inicio
    });
};

const getStatusClass = (status) => {
    const statusClasses = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        approved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
    return statusClasses[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};
</script>

