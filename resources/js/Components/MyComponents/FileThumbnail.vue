<template>
    <img
        v-if="isImage"
        :src="file.preview_url || file.original_url"
        :alt="file.name"
        class="w-full h-full object-cover"
    />
    <div v-else-if="isPdf && previewPdf" class="w-full h-full overflow-hidden bg-white">
        <iframe
            :src="pdfUrl"
            :title="file.name"
            loading="lazy"
            tabindex="-1"
            class="border-0 bg-white pointer-events-none"
            style="width: calc(100% + 17px); height: calc(100% + 17px);"
        ></iframe>
    </div>
    <div v-else class="text-center text-gray-500 dark:text-gray-400">
        <i :class="iconClass"></i>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    file: {
        type: Object,
        required: true,
    },
    // Clases del ícono cuando no hay vista previa disponible
    icon: {
        type: String,
        default: null,
    },
    previewPdf: {
        type: Boolean,
        default: true,
    },
});

const isImage = computed(() => (props.file.mime_type || '').startsWith('image/'));

const isPdf = computed(() => props.file.mime_type === 'application/pdf');

const pdfUrl = computed(() => {
    const base = props.file.original_url || props.file.preview_url || '';

    // El visor nativo del navegador muestra la primera página sin barras ni controles
    return base.includes('#') ? base : `${base}#page=1&view=FitH&toolbar=0&navpanes=0&scrollbar=0&statusbar=0`;
});

const iconClass = computed(() => {
    if (props.icon) return props.icon;

    const mimeType = props.file.mime_type || '';

    if (mimeType.includes('pdf')) return 'fa-regular fa-file-pdf text-5xl';
    if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fa-regular fa-file-zipper text-5xl';
    if (mimeType.includes('word')) return 'fa-regular fa-file-word text-5xl';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fa-regular fa-file-excel text-5xl';
    if (mimeType.includes('illustrator') || mimeType.includes('ai')) return 'fa-solid fa-pen-ruler text-5xl';
    if (mimeType.includes('photoshop') || mimeType.includes('psd')) return 'fa-solid fa-camera-retro text-5xl';

    return 'fa-regular fa-file text-5xl';
});
</script>
