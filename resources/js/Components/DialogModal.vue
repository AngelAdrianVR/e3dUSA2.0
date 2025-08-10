<script setup>
import Modal from './Modal.vue';

const emit = defineEmits(['close']);

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        @close="close"
    >
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden">
            
            <button
                v-if="closeable"
                @click="close"
                class="absolute top-3 right-4 p-1 rounded-full text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 transition"
                aria-label="Cerrar modal"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="p-6">
                <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    <slot name="title" />
                </div>

                <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    <slot name="content" />
                </div>
            </div>

            <div class="flex flex-row justify-end px-6 py-4 bg-gray-50 dark:bg-gray-900/70 border-t border-gray-200 dark:border-gray-700">
                <slot name="footer" />
            </div>
        </div>
    </Modal>
</template>