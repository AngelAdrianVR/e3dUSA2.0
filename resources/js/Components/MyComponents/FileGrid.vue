<template>
    <div v-if="files.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
        <div @click="openFile(file.original_url)"
            v-for="file in files"
            :key="file.id"
            class="group relative bg-white cursor-pointer dark:bg-gray-700/50 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border"
            :class="isSelected(file.id)
                ? 'border-indigo-500 dark:border-sky-500 ring-2 ring-indigo-500/40 dark:ring-sky-500/40'
                : 'border-gray-200 dark:border-gray-700'"
        >
            <FileSelectToggle
                v-if="selectable"
                :selected="isSelected(file.id)"
                @toggle="toggleSelection(file.id)"
            />
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

                <!-- Datos de la orden de diseño -->
                <div v-if="file.order_folio" class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700/60 space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <a
                            :href="file.order_url"
                            target="_blank"
                            rel="noopener"
                            @click.stop
                            class="text-xs font-bold text-indigo-600 dark:text-sky-400 hover:underline inline-flex items-center gap-1"
                            :title="`Abrir orden ${file.order_folio}`"
                        >
                            <i class="fa-solid fa-palette text-[10px]"></i>
                            {{ file.order_folio }}
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] opacity-70"></i>
                        </a>
                        <span
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold whitespace-nowrap"
                            :class="statusClasses(file.order_status)"
                        >
                            {{ file.order_status }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate" :title="file.order_designer || 'Sin diseñador asignado'">
                        <i class="fa-solid fa-user-pen mr-1"></i>
                        {{ file.order_designer || 'Sin diseñador' }}
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
import FileSelectToggle from '@/Components/MyComponents/FileSelectToggle.vue';

const props = defineProps({
    files: Array,
    canDelete: {
        type: Boolean,
        default: false,
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['delete', 'select']);

const isSelected = (id) => props.selectedIds.includes(id);

const toggleSelection = (id) => emit('select', id);

const openFile = (file) => {
    window.open(file, '_blank')
};

const STATUS_CLASSES = {
    'Pendiente': 'bg-gray-100 text-gray-700 dark:bg-gray-600/40 dark:text-gray-300',
    'Autorizada': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
    'En proceso': 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
    'Terminada': 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
    'Cancelada': 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
};

const statusClasses = (status) => STATUS_CLASSES[status] || STATUS_CLASSES['Pendiente'];

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>
