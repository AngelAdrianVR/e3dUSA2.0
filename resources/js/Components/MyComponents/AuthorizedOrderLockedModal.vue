<script>
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

export default {
    name: 'AuthorizedOrderLockedModal',
    components: {
        DialogModal,
        PrimaryButton,
    },
    props: {
        // Controla la visibilidad del modal
        show: {
            type: Boolean,
            default: false,
        },
        // Tipo de orden para personalizar el mensaje (venta / stock)
        orderType: {
            type: String,
            default: 'venta',
        },
    },
    emits: ['close'],
};
</script>

<template>
    <DialogModal :show="show" maxWidth="md" @close="$emit('close')">
        <template #title>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center size-10 rounded-full bg-amber-100 dark:bg-amber-900/40 shrink-0">
                    <i class="fa-solid fa-lock text-amber-600 dark:text-amber-400"></i>
                </span>
                <span>Orden no editable</span>
            </div>
        </template>

        <template #content>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Esta {{ orderType === 'venta' ? 'Orden de Venta' : 'Orden de Stock' }} ya ha sido
                <strong class="text-gray-800 dark:text-gray-100">autorizada</strong>, por lo que
                <strong class="text-gray-800 dark:text-gray-100">no es posible editarla</strong>.
            </p>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                Si necesitas realizar algún ajuste, por favor comunícate con
                <strong class="text-gray-800 dark:text-gray-100">Dirección</strong>.
            </p>
        </template>

        <template #footer>
            <PrimaryButton @click="$emit('close')">
                Entendido
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
