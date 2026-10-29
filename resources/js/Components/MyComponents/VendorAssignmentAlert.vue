<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    clientName: {
        type: String,
        default: '',
    },
    vendorName: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <DialogModal :show="show" maxWidth="md" @close="emit('close')">
        <template #title>
            <p class="flex items-center justify-center text-xl text-center gap-2 text-amber-600 dark:text-amber-400">
                <i class="fa-solid fa-triangle-exclamation"></i>
               ¡Alerta!
            </p>
        </template>
        <template #content>
            <p class="text-sm text-gray-700 dark:text-gray-300">
                El cliente
                <span class="font-semibold">{{ clientName }}</span>
                pertenece a
                <span class="font-semibold">{{ vendorName }}</span>.
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Puedes continuar, pero cualquier movimiento de esta cuenta debe coordinarse con el vendedor asignado. <br>
                O bien, puedes asignar otro vendedor editando al cliente.
            </p>
        </template>
        <template #footer>
            <PrimaryButton type="button" @click="emit('close')">
                Entendido
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
