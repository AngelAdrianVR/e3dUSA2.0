<template>
  <!-- 
    Contenedor principal del artículo/manual.
    - Se ha rediseñado como una tarjeta moderna con sombra y bordes redondeados.
    - Se aplica una transición suave para el cambio de sombra en el estado hover.
    - El evento @click para abrir el archivo se mantiene en el contenedor principal.
  -->
  <article
    @click="openFile"
    class="flex flex-col md:flex-row items-center w-full mb-4 bg-gray-50 hover:bg-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 ease-in-out cursor-pointer overflow-hidden border border-transparent dark:border-slate-700"
  >
    <!--
      Contenedor de la miniatura.
      - Mantiene una relación de aspecto consistente.
      - Muestra un esqueleto de carga (shimmer) mientras la imagen se carga.
    -->
    <figure class="relative w-full md:w-48 h-48 md:h-full flex-shrink-0 bg-slate-200 dark:bg-slate-700">
      <!-- Esqueleto de carga con animación shimmer -->
      <div v-if="isLoading" class="absolute inset-0 w-full h-full bg-slate-200 dark:bg-slate-700 animate-pulse"></div>
      
      <!-- Imagen de la portada -->
      <img
        :src="coverImageUrl"
        :alt="`Portada de ${manual.title}`"
        @load="imageLoaded"
        class="w-full h-full object-contain transition-opacity duration-500"
        :class="isLoading ? 'opacity-0' : 'opacity-100'"
      />
      
      <!-- Overlay con el tipo de archivo (Manual o Video) -->
      <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
        <div class="flex flex-col items-center text-white">
          <svg v-if="manual.type === 'Manual'" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
          </svg>
          <svg v-else class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
          </svg>
          <span class="mt-1 text-xs font-semibold uppercase tracking-wider">{{ manual.type }}</span>
        </div>
      </div>
    </figure>

    <!-- Contenido de la tarjeta -->
    <div class="flex flex-col flex-grow p-5">
      <header class="flex justify-between items-start mb-2">
        <h1 class="text-lg font-bold text-slate-800 dark:text-white pr-4">{{ manual.title }}</h1>
        <!-- Información del autor -->
        <div v-if="page.props.jetstream.managesProfilePhotos" class="flex items-center space-x-2 text-sm flex-shrink-0">
          <span class="text-gray-500 dark:text-gray-400 text-xs hidden sm:block">{{ manual.user.name }}</span>
          <img class="h-8 w-8 rounded-full object-cover border-2 border-white dark:border-slate-600" :src="manual.user.profile_photo_url" :alt="manual.user.name" />
        </div>
      </header>

      <p class="text-sm text-gray-600 dark:text-gray-300 mb-4 line-clamp-2">{{ manual.description }}</p>

      <footer class="flex justify-between items-center mt-auto pt-4 border-t border-slate-200 dark:border-slate-700">
        <!-- Metadatos del manual -->
        <div class="flex items-center space-x-3 text-xs text-gray-500 dark:text-gray-400">
          <span>{{ formattedDate }}</span>
          <span class="text-slate-300 dark:text-slate-600">•</span>
          <span>{{ viewCount }} vistas</span>
        </div>

        <!-- Botones de acción -->
        <div class="flex items-center space-x-2">
          <button v-if="canEdit" @click.stop="edit" class="px-3 py-1 text-xs font-semibold text-blue-600 bg-blue-100 rounded-full hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-300 dark:hover:bg-blue-900 transition-colors">
            Editar
          </button>
          <button v-if="canDelete" @click.stop="showConfirmationModal = true" class="px-3 py-1 text-xs font-semibold text-red-600 bg-red-100 rounded-full hover:bg-red-200 dark:bg-red-900/50 dark:text-red-300 dark:hover:bg-red-900 transition-colors">
            Eliminar
          </button>
        </div>
      </footer>
    </div>
  </article>

  <!-- Modal de confirmación para eliminar -->
  <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false">
    <template #title>Eliminar Manual</template>
    <template #content>
      ¿Estás seguro de que quieres eliminar "{{ manual.title }}"? Esta acción es permanente.
    </template>
    <template #footer>
      <CancelButton @click="showConfirmationModal = false" class="mr-2">Cancelar</CancelButton>
      <PrimaryButton @click="deleteItem">Sí, eliminar</PrimaryButton>
    </template>
  </ConfirmationModal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import axios from 'axios';

// Importación de componentes locales
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";

// --- Props y Estado ---
const props = defineProps({
  manual: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const isLoading = ref(true);
const showConfirmationModal = ref(false);
const viewCount = ref(props.manual.views);

// --- Propiedades Computadas ---

// Determina la URL de la portada, con un fallback a una imagen genérica.
const coverImageUrl = computed(() => {
  if (props.manual.type === 'Manual') {
    return '/images/pdf_grande.png'; // Asegúrate que esta ruta sea correcta en tu proyecto
  }
  return props.manual.media?.find(item => item.collection_name === 'cover')?.original_url || '/images/default-video.png';
});

// Formatea la fecha de creación para mostrarla de forma legible.
const formattedDate = computed(() => {
  const parsedDate = new Date(props.manual.created_at);
  return format(parsedDate, "dd 'de' MMMM, yyyy", { locale: es });
});

// Comprueba si el usuario tiene permisos para editar.
const canEdit = computed(() => page.props.auth.user.permissions.includes('Editar tutoriales y manuales'));

// Comprueba si el usuario tiene permisos para eliminar.
const canDelete = computed(() => page.props.auth.user.permissions.includes('Eliminar tutoriales y manuales'));

// --- Métodos ---

const imageLoaded = () => {
  isLoading.value = false;
};

const openFile = async () => {
  // Incrementa las vistas en el backend antes de abrir el archivo.
  try {
    const response = await axios.put(route('manuals.increase-views', props.manual.id));
    if (response.status === 200) {
      viewCount.value++;
    }
  } catch (error) {
    console.error("Error al actualizar las vistas:", error);
  }

  // Abre el archivo en una nueva pestaña.
  const url = props.manual.media?.find(item => item.collection_name === 'default')?.original_url;
  if (url) {
    window.open(url, '_blank');
  } else {
    console.error("No se encontró la URL del archivo principal.");
  }
};

const edit = () => {
  router.get(route('manuals.edit', props.manual.id));
};

const deleteItem = () => {
  router.delete(route('manuals.destroy', props.manual.id), {
    preserveScroll: true,
    onFinish: () => {
      showConfirmationModal.value = false;
    },
  });
};
</script>

<style scoped>
/* line-clamp-2 es una utilidad no estándar en Tailwind por defecto.
  Si no funciona, puedes añadirla a tu tailwind.config.js con el plugin @tailwindcss/line-clamp
  o usar esta clase CSS.
*/
.line-clamp-2 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
</style>
