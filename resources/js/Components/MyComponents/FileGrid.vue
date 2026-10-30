<template>
    <div v-if="files.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
        <div @click="openFile(file.original_url)"
            v-for="file in files"
            :key="file.id"
            class="group relative bg-white cursor-pointer dark:bg-gray-700/50 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-200 dark:border-gray-700"
        >
            <button
                v-if="canDelete"
                type="button"
                @click.stop="emit('delete', file)"
                aria-label="Eliminar archivo"
                class="absolute top-2 right-2 z-10 p-2 bg-white/90 dark:bg-gray-800/90 text-gray-400 hover:text-red-600 rounded-full shadow-sm backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all duration-200 hover:scale-110"
                title="Eliminar archivo"
            >
                <i class="fa-regular fa-trash-can text-lg"></i>
            </button>
            <div class="h-40 flex items-center justify-center bg-gray-100 dark:bg-gray-800 overflow-hidden">
                <FileThumbnail :file="file" />
            </div>
            <div class="p-4">
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate group-hover:text-indigo-600 dark:group-hover:text-sky-400" :title="file.name">
                    {{ file.name }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ formatFileSize(file.size) }}
                </p>
                 <div v-if="file.order_title" class="mt-2">
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        <strong>Orden:</strong> {{ file.order_title }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <strong>Solicitante:</strong> {{ file.requester }}
                    </p>
                </div>
            </div>
            <!-- <div class="absolute top-2 right-2 flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <a :href="file.original_url" target="_blank" class="h-8 w-8 rounded-full bg-black/50 hover:bg-black/70 flex items-center justify-center text-white transition-colors" title="Descargar">
                    <i class="fa-solid fa-eye"></i>
                </a>
            </div> -->
        </div>
    </div>
    <div v-else class="text-center py-16">
        <i class="fa-solid fa-box-open text-5xl text-gray-400 dark:text-gray-500 mb-4"></i>
        <p class="text-gray-500 dark:text-gray-400">No se encontraron archivos.</p>
    </div>
</template>

<script setup>
import FileThumbnail from '@/Components/MyComponents/FileThumbnail.vue';

defineProps({
    files: Array,
    canDelete: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['delete']);

const openFile = (file) => {
    window.open(file, '_blank')
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>
