<template>
  <Head :title="'Formato de autorización de diseño'" />

    <!-- Botón de Imprimir Flotante -->
    <button @click="print" title="Imprimir Cotización"
        class="print:hidden fixed bottom-6 right-6 bg-sky-600 text-white rounded-full size-14 shadow-lg hover:bg-sky-700 transition-all z-50 flex items-center justify-center">
        <i class="fa-solid fa-print text-2xl"></i>
    </button>
  
    <div class="max-w-7xl mx-auto p-4">
        <!-- Encabezado con título y estado -->
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
              <ApplicationLogo />
            <div>
              <div class="flex items-center gap-3">
                  <h1 class="text-3xl font-bold text-gray-900">
                      Autorización de Diseño
                  </h1>
              </div>
              <p class="mt-1 text-md text-gray-500">
                  Folio FA-{{ authorization.id.toString().padStart(4, '0') }} &mdash; Versión {{ authorization.version }}
              </p>
            </div>
        </header>

        <!-- Contenido Principal en Grid -->
        <main class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            <!-- Columna Izquierda (Visuales) -->
            <div class="lg:col-span-3 flex flex-col gap-8">
                <!-- Imagen de Portada -->
                <div class="bg-white shadow-xl rounded-lg overflow-hidden border border-gray-200">
                    <div class="p-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Visualización Principal</h3>
                    </div>
                    <div class="p-5 bg-gray-50 flex justify-center items-center">
                        <div v-if="cover_image_url" class="flex justify-center items-center aspect-video h-72">
                              <img :src="cover_image_url" alt="Imagen de portada" class="h-full w-auto object-contain rounded-md">
                        </div>
                        <div v-else class="flex flex-col items-center justify-center h-80 bg-gray-100 rounded-md">
                            <PhotoIcon class="h-16 w-16 text-gray-400" />
                            <p class="mt-2 text-sm font-semibold text-gray-500">No hay imagen de portada</p>
                        </div>
                    </div>
                </div>

                <!-- Archivos Adicionales -->
                <div v-if="additional_files.length" class="bg-white shadow-xl rounded-lg overflow-hidden border border-gray-200">
                      <div class="p-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Archivos Adicionales</h3>
                    </div>
                    <div class="p-5 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        <a v-for="file in additional_files" :key="file.id" :href="file.url" target="_blank" 
                            class="group border border-gray-200 rounded-lg p-3 text-center hover:bg-gray-100 hover:shadow-md transition-all duration-300 flex flex-col items-center justify-center aspect-square">
                            <component :is="file.mime_type.includes('pdf') ? 'DocumentTextIcon' : 'PhotoIcon'" class="h-10 w-10 text-gray-400 group-hover:text-blue-500 transition-colors" />
                            <p class="text-xs font-semibold break-all mt-3 text-gray-600">{{ file.name }}</p>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha (Información y Acciones) -->
            <div class="lg:col-span-2 flex flex-col gap-8">
                <!-- Detalles -->
                  <div class="bg-white shadow-xl rounded-lg p-5 border border-gray-200">
                    <h3 class="text-lg font-semibold border-b border-gray-200 pb-3 mb-4 text-gray-800">Información General</h3>
                    <ul class="text-sm space-y-2 text-gray-700">
                        <!-- Detalles del Producto -->
                        <li class="flex items-start gap-3"><CubeIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Producto:</strong> {{ authorization.product_name }}</span></li>
                        <li class="flex items-start gap-3"><Squares2X2Icon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Material:</strong> {{ authorization.material || 'N/A' }}</span></li>
                        <li class="flex items-start gap-3"><SwatchIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Color:</strong> {{ authorization.color || 'N/A' }}</span></li>
                        <li class="flex items-start gap-3"><CogIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Métodos Prod.:</strong> {{ authorization.production_methods?.join(', ') || 'N/A' }}</span></li>
                        <li class="flex items-start gap-3 pt-3 border-t border-gray-200"><DocumentTextIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Especificaciones:</strong> {{ authorization.specifications || 'Ninguna' }}</span></li>
                        <!-- Información de Contacto -->
                        <li class="flex items-start gap-3 pt-3 border-t border-gray-200"><BriefcaseIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Cliente:</strong> {{ authorization.branch.name }}</span></li>
                        <li class="flex items-start gap-3"><UserIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Contacto:</strong> {{ authorization.contact.name }}</span></li>
                        <li class="flex items-start gap-3"><UserIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Vendedor:</strong> {{ authorization.seller.name }}</span></li>
                        <li v-if="authorization.responded_at" class="flex items-start gap-3 pt-3 border-t border-gray-200"><CalendarDaysIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Respuesta Cliente:</strong> {{ formatDate(authorization.responded_at) }}</span></li>
                    </ul>
                    <div class="w-96 relative">
                        <p class="text-[#9A9A9A] mt-16">Firma de autorización: __________________________</p>
                        <figure v-if="authorization.signature_media?.length" class="w-32 absolute right-20 top-4">
                            <img :src="procesarUrlImagen(authorization.signature_media[0]?.original_url)" alt="">
                        </figure>
                    </div>
                </div>
            </div>

            <footer class="lg:col-span-full p-2 border-b border-[#9A9A9A] mt-5">
                <h1 class="text-primary text-lg font-bold">Importante</h1>
                <p class="font-bold">Se solicita una revisión cuidadosa del diseño, los colores y el texto. Una vez autorizado, cualquier omisión será responsabilidad de la persona que lo firme</p>
                <p class="text-sm text-gray-500">*Los logotipos y marcas mostrados en este formato tienen un propósito exclusivamente ilustrativo, ya que los tonos de los grabados e impresiones pueden variar dependiendo del producto o lote.</p>
                <p class="text-sm text-gray-500">*Los colores de la pantalla puede variar dependiendo del dispositivo en que se visualicen. </p>
            </footer>
        </main>
    </div>
</template>

<script>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head } from '@inertiajs/vue3';


// Importaciones de Íconos (Heroicons)
import { 
    CheckCircleIcon, XCircleIcon, ClockIcon, PhotoIcon, DocumentTextIcon,
    CubeIcon, SwatchIcon, CogIcon, Squares2X2Icon, BriefcaseIcon, UserIcon,
    CalendarDaysIcon, ShieldCheckIcon
} from '@heroicons/vue/24/outline'; // Usamos outline para un look más limpio

export default {
    components: {
        // Íconos (registrados para uso en template)
        CheckCircleIcon, XCircleIcon, ClockIcon, PhotoIcon, DocumentTextIcon,
        CubeIcon, SwatchIcon, CogIcon, Squares2X2Icon, BriefcaseIcon, UserIcon,
        CalendarDaysIcon, ShieldCheckIcon,
        ApplicationLogo,
        Head
    },
    props: {
        authorization: Object,
        cover_image_url: String,
        additional_files: Array,
    },
    data() {
        return {
        };
    },
    methods: {
        // Formatea la fecha a un formato legible
        formatDate(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Date(dateString).toLocaleDateString('es-MX', options);
        },
        // Método para procesar la URL de la imagen
        procesarUrlImagen(originalUrl) {
            // Reemplaza la parte inicial de la URL
            const nuevaUrl = originalUrl?.replace('https://intranetemblems3d.dtw.com.mx', 'https://clientes-emblems3d.dtw.com.mx');
            return nuevaUrl;
        },
        print() {
          window.print();
        },
    },
};
</script>
