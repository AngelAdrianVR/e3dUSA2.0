<template>
  <div class="w-full font-sans">
    <!-- 
      Área principal para seleccionar o arrastrar archivos.
      - Se añaden clases para transiciones de color y borde.
      - El borde es discontinuo para sugerir un área de "soltar".
      - Cambia de color cuando un archivo es arrastrado sobre él.
    -->
    <div
      @click="openFileBrowser"
      @dragover.prevent="onDragOver"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="handleDrop"
      :class="[
        'relative w-full border-2 border-dashed rounded-xl cursor-pointer transition-all duration-300 ease-in-out',
        'text-gray-500 dark:text-gray-400',
        isDragging
          ? 'border-blue-500 bg-blue-50 dark:bg-slate-800'
          : 'border-gray-300 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-gray-50 dark:hover:bg-slate-800/60',
      ]"
    >
      <!-- Input de archivo oculto, se activa mediante programación -->
      <input
        type="file"
        ref="fileInput"
        class="hidden"
        @change="handleFileInputChange"
        :multiple="props.multiple"
        :accept="acceptedFormat"
      />

      <!-- Contenido del área de carga -->
      <div class="flex flex-col items-center justify-center p-2 text-center">
        <!-- Icono de carga -->
        <svg
          class="size-10 mb-4 transition-transform duration-300"
          :class="{ 'scale-110 text-blue-500': isDragging }"
          stroke="currentColor"
          fill="none"
          viewBox="0 0 48 48"
          aria-hidden="true"
        >
          <path
            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>

        <!-- Texto de instrucción -->
        <p class="mb-2 text-sm">
          <span class="font-semibold text-blue-600 dark:text-blue-400">Haz clic para cargar</span> o arrastra y suelta
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500">
          {{ acceptedFormatLabel }}
        </p>

        <!-- Estado de carga (Spinner) -->
        <div v-if="loading" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/80 dark:bg-slate-900/80 rounded-xl">
          <svg class="w-8 h-8 text-blue-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="mt-4 text-sm font-medium text-gray-600 dark:text-gray-300">Cargando archivos...</span>
        </div>
      </div>
    </div>

    <!-- 
      Lista de archivos seleccionados.
      - Se utiliza <TransitionGroup> para animar la entrada y salida de elementos.
    -->
    <div v-if="selectedFiles.length" class="mt-4">
      <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200">Archivos seleccionados:</h3>
      <TransitionGroup
        tag="ul"
        name="file-list"
        class="mt-2 space-y-2"
      >
        <li
          v-for="(file, index) in selectedFiles"
          :key="file.name + index"
          class="flex items-center justify-between p-2 text-sm rounded-lg bg-gray-100 dark:bg-slate-800"
        >
          <div class="flex items-center min-w-0">
            <i :class="getFileTypeIcon(file.name)" class="w-5 text-center text-lg shrink-0"></i>
            <span class="ml-3 font-medium truncate text-gray-800 dark:text-gray-100" :title="file.name">{{ file.name }}</span>
          </div>
          <button
            @click="removeFile(index)"
            class="p-1 text-gray-500 transition-colors rounded-full shrink-0 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/50 dark:hover:text-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            title="Eliminar archivo"
          >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </li>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

// --- Definición de Props y Emits ---
const props = defineProps({
  multiple: {
    type: Boolean,
    default: true,
  },
  acceptedFormat: {
    type: String,
    default: 'Todo', // Puede ser 'Video', 'PDF', 'Imagen', 'Todo'
  },
  existingFileUrls: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['files-selected']);

// --- Estado Reactivo ---
const loading = ref(false);
const selectedFiles = ref([]);
const fileInput = ref(null); // Referencia al input de archivo
const isDragging = ref(false); // Estado para el drag-and-drop

// --- Lógica de Formato de Archivos ---
// Mapeo de formatos para mayor claridad y escalabilidad
const formatMap = {
  Video: { mime: 'video/*', label: 'Videos (MP4, AVI, etc.)' },
  PDF: { mime: 'application/pdf', label: 'Archivos PDF' },
  Imagen: { mime: 'image/*', label: 'Imágenes (JPG, PNG, etc.)' },
  Todo: { mime: '*/*', label: 'Cualquier tipo de archivo' },
};

// Propiedad computada para obtener el MIME type aceptado
const acceptedFormat = computed(() => formatMap[props.acceptedFormat]?.mime || '*/*');
// Propiedad computada para mostrar la etiqueta del formato
const acceptedFormatLabel = computed(() => formatMap[props.acceptedFormat]?.label || 'Cualquier tipo de archivo');

// --- Métodos ---
const openFileBrowser = () => {
  fileInput.value?.click();
};

const handleNewFiles = (newFiles) => {
  const fileArray = Array.from(newFiles);
  if (props.multiple) {
    selectedFiles.value = [...selectedFiles.value, ...fileArray];
  } else {
    selectedFiles.value = fileArray;
  }
  emit('files-selected', selectedFiles.value, true);
};

const handleFileInputChange = (event) => {
  handleNewFiles(event.target.files);
};

const removeFile = (index) => {
  selectedFiles.value.splice(index, 1);
  emit('files-selected', selectedFiles.value, true);
};

// --- Lógica de Drag and Drop ---
const onDragOver = () => {
  isDragging.value = true;
};

const onDragLeave = () => {
  isDragging.value = false;
};

const handleDrop = (event) => {
  isDragging.value = false;
  handleNewFiles(event.dataTransfer.files);
};

// --- Lógica de Iconos ---
const getFileTypeIcon = (fileName) => {
  const extension = fileName?.split('.').pop().toLowerCase();
  const iconMap = {
    pdf: 'fa-regular fa-file-pdf text-red-600',
    jpg: 'fa-regular fa-image text-blue-500',
    jpeg: 'fa-regular fa-image text-blue-500',
    png: 'fa-regular fa-image text-blue-500',
    gif: 'fa-regular fa-image text-blue-500',
    mp4: 'fa-regular fa-file-video text-sky-500',
    avi: 'fa-regular fa-file-video text-sky-500',
    mkv: 'fa-regular fa-file-video text-sky-500',
    mov: 'fa-regular fa-file-video text-sky-500',
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
      // Extrae el nombre del archivo de la URL
      const fileName = url.substring(url.lastIndexOf('/') + 1);
      return new File([data], fileName, { type: data.type });
    });
    const files = await Promise.all(filePromises);
    selectedFiles.value = [...selectedFiles.value, ...files];
    emit('files-selected', selectedFiles.value, false);
  } catch (error) {
    console.error('Error al cargar archivos existentes:', error);
  } finally {
    loading.value = false;
  }
};

// --- Hook de Ciclo de Vida ---
onMounted(() => {
  if (props.existingFileUrls.length > 0) {
    fetchAndConvertFiles(props.existingFileUrls);
  }
});
</script>

<style>
/* Estilos para las transiciones de la lista de archivos.
  Estas clases son utilizadas por el componente <TransitionGroup> de Vue.
*/
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
