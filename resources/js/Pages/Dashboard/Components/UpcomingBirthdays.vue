<template>
    <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
        <h3 class="mb-4 font-semibold text-gray-800 dark:text-white">Cumpleaños clientes</h3>
        <div class="space-y-3 max-h-48 overflow-y-auto">
            <div v-for="contact in contacts" :key="contact.id" class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-8 h-8 bg-yellow-200 text-yellow-600 rounded-full">
                    <i class="fa-solid fa-cake-candles"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">{{ contact.name }} – <span class="font-normal text-gray-600 dark:text-gray-400">{{ contact.company_name }}</span></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(contact.birthdate).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) }}</p>
                    <a v-if="contact.email" :href="'mailto:' + contact.email" class="text-xs text-blue-500 underline hover:text-blue-700">{{ contact.email }}</a>
                </div>
            </div>
            <p v-if="!contacts.length" class="text-sm text-center text-gray-500 dark:text-gray-400">No hay cumpleaños próximos.</p>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        contacts: Array,
    },
};
</script>
