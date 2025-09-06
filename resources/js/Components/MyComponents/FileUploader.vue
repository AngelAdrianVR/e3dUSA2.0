<template>
  <div class="w-full font-sans">
    <!-- 
      Área principal para seleccionar o arrastrar archivos.
      La funcionalidad y estilos de esta sección se mantienen.
    -->
    <div
      @click="!isLimitReached && openFileBrowser()"
      @dragover.prevent="!isLimitReached && onDragOver()"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="!isLimitReached && handleDrop($event)"
      :class="[
      { 'opacity-60 cursor-not-allowed': isLimitReached },
        'relative w-full border-2 border-dashed rounded-xl cursor-pointer transition-all duration-300 ease-in-out',
        'text-gray-500 dark:text-gray-400',
        isDragging
          ? 'border-secondary bg-secondary/10 dark:bg-slate-800'
          : 'border-gray-300 dark:border-gray-600 hover:border-secondary/70 dark:hover:border-secondary/80 hover:bg-gray-50 dark:hover:bg-slate-800/60',
      ]"
    >
      <input
        type="file"
        ref="fileInput"
        class="hidden"
        @change="handleFileInputChange"
        :multiple="props.multiple"
        :accept="acceptedMimeType"
        :disabled="isLimitReached"
      />

      <!-- Añade este div para mostrar un mensaje de límite alcanzado -->
      <div v-if="isLimitReached" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/80 dark:bg-slate-900/80 rounded-xl">
          <p class="font-semibold text-gray-700 dark:text-gray-300">Límite de archivos alcanzado</p>
      </div>

      <div class="flex flex-col items-center justify-center p-6 text-center">
        <svg
          class="size-10 mb-4 transition-transform duration-300"
          :class="{ 'scale-110 text-secondary': isDragging }"
          stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true"
        >
          <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <p class="mb-2 text-sm">
          <span class="font-semibold text-secondary dark:text-secondary/90">Haz clic para cargar</span> o arrastra y suelta
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500">
          {{ acceptedFormatLabel }}
        </p>
        <div v-if="loading" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/80 dark:bg-slate-900/80 rounded-xl">
          <svg class="w-8 h-8 text-secondary animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="mt-4 text-sm font-medium text-gray-600 dark:text-gray-300">Cargando archivos...</span>
        </div>
      </div>
    </div>

    <!-- 
      NUEVO: Lista de archivos con previsualización.
      - Muestra una imagen si es de tipo imagen.
      - Muestra un ícono si es otro tipo de archivo.
    -->
    <div v-if="processedFiles.length" class="mt-4">
      <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200">Archivos seleccionados:</h3>
      <TransitionGroup
        tag="ul"
        name="file-list"
        class="mt-2 space-y-2"
      >
        <li
          v-for="(fileItem, index) in processedFiles"
          :key="fileItem.id"
          class="flex items-center justify-between p-2 text-sm rounded-lg bg-gray-100 dark:bg-slate-800 shadow-sm"
        >
          <div class="flex items-center min-w-0 gap-3">
            <!-- Contenedor de la previsualización o ícono -->
            <div class="flex-shrink-0 size-10 rounded-md bg-gray-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                <!-- Muestra la imagen si es de tipo imagen -->
                <img v-if="fileItem.isImage" :src="fileItem.previewUrl" class="h-full w-full object-cover" alt="Previsualización">
                <!-- Muestra un ícono si NO es una imagen -->
                <i v-else :class="getFileTypeIcon(fileItem.file.name)" class="text-xl"></i>
            </div>
            
            <span class="font-medium truncate text-gray-800 dark:text-gray-100" :title="fileItem.file.name">{{ fileItem.file.name }}</span>
          </div>
          <button
            type="button"
            @click="removeFile(index)"
            class="p-1 text-gray-500 transition-colors rounded-full shrink-0 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/50 dark:hover:text-red-400 focus:outline-none"
            title="Eliminar archivo"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </li>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { ElMessage } from 'element-plus';

// --- Definición de Props y Emits ---
const props = defineProps({
  multiple: { type: Boolean, default: true },
  format: { type: String, default: 'Todo' }, // 'Video', 'PDF', 'Imagen', 'Todo'
  existingFileUrls: { type: Array, default: () => [] },
  maxFiles: { type: Number, default: 0 }, // 0 = sin límite
});
const emit = defineEmits(['files-selected']);

// --- Estado Reactivo ---
const loading = ref(false);
const fileInput = ref(null);
const isDragging = ref(false);
// NUEVO: Almacena los archivos procesados con su URL de previsualización
const processedFiles = ref([]);

// --- Lógica de Formato de Archivos ---
const formatMap = {
  Video: { mime: 'video/*', label: 'Videos (MP4, AVI, etc.)' },
  PDF: { mime: 'application/pdf', label: 'Archivos PDF' },
  Imagen: { mime: 'image/*', label: 'Imágenes (JPG, PNG, etc.)' },
  Todo: { mime: '*/*', label: 'Cualquier tipo de archivo' },
};
const acceptedMimeType = computed(() => formatMap[props.format]?.mime || '*/*');
const acceptedFormatLabel = computed(() => formatMap[props.format]?.label || 'Cualquier tipo de archivo');

// --- Métodos ---
const openFileBrowser = () => fileInput.value?.click();

const isImageFile = (fileName) => {
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    const extension = fileName?.split('.').pop().toLowerCase();
    return imageExtensions.includes(extension);
};

const isLimitReached = computed(() => {
    return props.maxFiles > 0 && processedFiles.value.length >= props.maxFiles;
});

// NUEVO: Procesa los archivos para añadirles un ID y una URL de previsualización
const processAndAddFiles = (files) => {
    const fileArray = Array.from(files);
    let filesToAdd = fileArray;

    // --- INICIO DE LA MODIFICACIÓN ---
    if (props.maxFiles > 0) {
        const currentCount = processedFiles.value.length;
        const availableSlots = props.maxFiles - currentCount;

        if (availableSlots <= 0) {
            console.warn(`Límite de ${props.maxFiles} archivos alcanzado. No se pueden agregar más.`);
            ElMessage.warning(`Límite de ${props.maxFiles} archivos alcanzado. No se pueden agregar más.`);
            // Opcional: Muestra una notificación al usuario aquí.
            return;
        }

        if (fileArray.length > availableSlots) {
          console.warn(`Solo se pueden agregar ${availableSlots} archivos más. Se han ignorado los archivos extras.`);
          ElMessage.warning(`Solo se pueden agregar ${availableSlots} archivos más. Se han ignorado los archivos extras.`);
            // Opcional: Muestra una notificación al usuario aquí.
            filesToAdd = fileArray.slice(0, availableSlots);
        }
    }
    // --- FIN DE LA MODIFICACIÓN ---

    const newFiles = filesToAdd.map(file => ({
        id: crypto.randomUUID(),
        file: file,
        isImage: isImageFile(file.name),
        previewUrl: isImageFile(file.name) ? URL.createObjectURL(file) : null
    }));

    if (props.multiple) {
        processedFiles.value = [...processedFiles.value, ...newFiles];
    } else {
        processedFiles.value.forEach(f => {
            if (f.previewUrl) URL.revokeObjectURL(f.previewUrl);
        });
        processedFiles.value = newFiles;
    }
    emit('files-selected', processedFiles.value.map(f => f.file));
};

const handleFileInputChange = (event) => processAndAddFiles(event.target.files);

const removeFile = (index) => {
  const fileToRemove = processedFiles.value[index];
  // Revocar la URL del objeto para liberar memoria
  if (fileToRemove.previewUrl) {
    URL.revokeObjectURL(fileToRemove.previewUrl);
  }
  processedFiles.value.splice(index, 1);
  emit('files-selected', processedFiles.value.map(f => f.file));
};

// --- Lógica de Drag and Drop ---
const onDragOver = () => { isDragging.value = true; };
const onDragLeave = () => { isDragging.value = false; };
const handleDrop = (event) => {
  isDragging.value = false;
  processAndAddFiles(event.dataTransfer.files);
};

// --- Lógica de Iconos ---
const getFileTypeIcon = (fileName) => {
  const extension = fileName?.split('.').pop().toLowerCase();
  const iconMap = {
    pdf: 'fa-regular fa-file-pdf text-red-600',
    doc: 'fa-regular fa-file-word text-blue-700',
    docx: 'fa-regular fa-file-word text-blue-700',
    xls: 'fa-regular fa-file-excel text-green-600',
    xlsx: 'fa-regular fa-file-excel text-green-600',
    mp4: 'fa-regular fa-file-video text-sky-500',
    avi: 'fa-regular fa-file-video text-sky-500',
    default: 'fa-regular fa-file-lines text-gray-500',
  };
  return iconMap[extension] || iconMap.default;
};

// --- Carga de Archivos Existentes ---
const fetchAndConvertFiles = async (urls) => {
  loading.value = true;
  try {
    const filePromises = urls.map(async (url) => {
      const response = await fetch(url);
      const data = await response.blob();
      const fileName = url.substring(url.lastIndexOf('/') + 1).split('?')[0]; // Limpia query params
      return new File([data], fileName, { type: data.type });
    });
    const files = await Promise.all(filePromises);
    processAndAddFiles(files);
  } catch (error) {
    console.error('Error al cargar archivos existentes:', error);
    ElMessage.error('Error al cargar archivos existentes:', error);

  } finally {
    loading.value = false;
  }
};

// --- Hooks de Ciclo de Vida ---
onMounted(() => {
  if (props.existingFileUrls.length > 0) {
    fetchAndConvertFiles(props.existingFileUrls);
  }
});

onBeforeUnmount(() => {
    // Limpia todas las URLs de previsualización al destruir el componente
    processedFiles.value.forEach(fileItem => {
        if (fileItem.previewUrl) {
            URL.revokeObjectURL(fileItem.previewUrl);
        }
    });
});
</script>

<style>
/* Estilos para las transiciones de la lista de archivos. */
.file-list-enter-active,
.file-list-leave-active {
  transition: all 0.5s ease;
}
.file-list-enter-from,
.file-list-leave-to {
  opacity: 0;
  transform: translateX(30px);
}
</style>
