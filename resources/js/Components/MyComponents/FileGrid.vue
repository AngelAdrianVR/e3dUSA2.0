<template>
    <div v-if="files.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
        <div @click="openFile(file.original_url)"
            v-for="file in files"
            :key="file.id"
            class="group relative bg-white cursor-pointer dark:bg-gray-700/50 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-200 dark:border-gray-700"
        >
            <div class="h-40 flex items-center justify-center bg-gray-100 dark:bg-gray-800 overflow-hidden">
                <img v-if="isImage(file.mime_type)" :src="file.preview_url" alt="File preview" class="h-full w-full object-cover">
                <div v-else class="text-center text-gray-500 dark:text-gray-400">
                     <i :class="getFileIcon(file.mime_type)" class="text-5xl"></i>
                </div>
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
defineProps({
    files: Array,
});

const isImage = (mimeType) => mimeType.startsWith('image/');

const getFileIcon = (mimeType) => {
    if (isImage(mimeType)) return 'fa-regular fa-file-image';
    if (mimeType.includes('pdf')) return 'fa-regular fa-file-pdf';
    if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fa-regular fa-file-zipper';
    if (mimeType.includes('word')) return 'fa-regular fa-file-word';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fa-regular fa-file-excel';
    if (mimeType.includes('illustrator') || mimeType.includes('ai')) return 'fa-solid fa-pen-ruler';
    if (mimeType.includes('photoshop') || mimeType.includes('psd')) return 'fa-solid fa-camera-retro';
    return 'fa-regular fa-file';
};

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
