<template>
    <AppLayout title="Detalle de Autorización">
        <div class="max-w-7xl mx-auto p-4">
            <!-- Encabezado con título y estado -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
                <div>
                    <div class="flex items-center gap-3">
                        <Back />
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            Autorización de Diseño
                        </h1>
                    </div>
                    <p class="mt-1 text-md text-gray-500 dark:text-gray-400">
                        Folio FA-{{ authorization.id.toString().padStart(4, '0') }} &mdash; Versión {{ authorization.version }}
                    </p>
                </div>
                <!-- Badge de Estado -->
                <div :class="status.bgClass" class="text-sm font-bold px-4 py-2 rounded-lg flex items-center gap-2 transition-all duration-300">
                    <component :is="status.icon" class="h-5 w-5" />
                    <span>{{ status.text }}</span>
                </div>
            </header>

            <!-- Contenido Principal en Grid -->
            <main class="grid grid-cols-1 lg:grid-cols-5 gap-5">
                <!-- Columna Izquierda (Visuales) -->
                <div class="lg:col-span-3 flex flex-col gap-8">
                    <!-- Imagen de Portada -->
                    <div class="bg-white dark:bg-slate-900 shadow-xl rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Visualización Principal</h3>
                        </div>
                        <div class="p-5 bg-gray-50 dark:bg-slate-800/50">
                            <div v-if="cover_image_url" class="flex justify-center items-center aspect-video">
                                 <img :src="cover_image_url" alt="Imagen de portada" draggable="false" class="max-h-full max-w-full object-contain rounded-md">
                            </div>
                            <div v-else class="flex flex-col items-center justify-center h-80 bg-gray-100 dark:bg-slate-800 rounded-md">
                                <PhotoIcon class="h-16 w-16 text-gray-400" />
                                <p class="mt-2 text-sm font-semibold text-gray-500">No hay imagen de portada</p>
                            </div>
                        </div>
                    </div>

                    <!-- Archivos Adicionales -->
                    <div v-if="additional_files.length" class="bg-white dark:bg-slate-900 shadow-xl rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                         <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Archivos Adicionales</h3>
                        </div>
                        <div class="p-5 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                            <a v-for="file in additional_files" :key="file.id" :href="file.url" target="_blank" 
                               class="group border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-center hover:bg-gray-100 dark:hover:bg-slate-800 hover:shadow-md transition-all duration-300 flex flex-col items-center justify-center aspect-square">
                                <component :is="file.mime_type.includes('pdf') ? 'DocumentTextIcon' : 'PhotoIcon'" class="h-10 w-10 text-gray-400 group-hover:text-blue-500 transition-colors" />
                                <p class="text-xs font-semibold break-all mt-3 text-gray-600 dark:text-gray-300">{{ file.name }}</p>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha (Información y Acciones) -->
                <div class="lg:col-span-2 flex flex-col gap-4">
                    <!-- Acciones -->
                    <div class="bg-white dark:bg-slate-900 shadow-xl rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Estado y Acciones</h3>
                            <button @click="print" class="flex items-center justify-center dark:text-gray-200 rounded-full size-9 dark:hover:bg-slate-700 hover:bg-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                </svg>
                            </button>
                        </div>
                        <div v-if="authorization.is_accepted === null">
                             <p class="text-sm text-center text-gray-600 dark:text-gray-400 mb-4">El cliente aún no ha dado una respuesta.</p>
                             <p v-if="!authorization.authorizer_name" class="text-sm text-center text-amber-600 dark:text-amber-400 mb-4">Se debe autorizar primero para actualizar el estatus del formato</p>
                            <div v-if="authorization.authorizer_name" class="grid grid-cols-2 gap-3">
                                <button @click="showAcceptanceModal = true" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-md transition-transform hover:scale-105 flex items-center justify-center gap-2">
                                    <CheckCircleIcon class="h-5 w-5" /> Aceptar
                                </button>
                                <button @click="showRejectionModal = true" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-md transition-transform hover:scale-105 flex items-center justify-center gap-2">
                                    <XCircleIcon class="h-5 w-5" /> Rechazar
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-sm text-center font-medium p-4 rounded-md" :class="authorization.is_accepted ? 'bg-green-50 dark:bg-green-900/50 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/50 text-red-700 dark:text-red-300'">
                            El cliente ya ha respondido.
                        </div>
                        
                        <!-- Autorización Interna -->
                        <div class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                             <button v-if="!authorization.authorizer_name && $page.props.auth.user.permissions.includes('Autorizar formatos de autorizacion de diseño')" @click="showInternalAuthModal = true" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition-transform hover:scale-105 flex items-center justify-center gap-2">
                                <ShieldCheckIcon class="h-5 w-5" /> Autorización Interna
                            </button>
                             <div v-else :class="authorization.authorizer_name ? 'bg-blue-50 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300' : 'bg-red-50 dark:bg-red-900/50 text-red-700 dark:text-red-300'"
                                class="text-sm text-center p-3 rounded-md font-semibold flex items-center justify-center gap-2">
                                <ShieldCheckIcon class="h-5 w-5" />
                                <p v-if="authorization.authorizer_name">Autorizado por: {{ authorization.authorizer_name ?? 'No autorizado' }}</p>
                                <p v-else>No autorizado</p>
                                
                            </div>
                        </div>
                    </div>

                    <!-- Detalles -->
                     <div class="bg-white dark:bg-slate-900 shadow-xl rounded-lg p-5 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold border-b border-gray-200 dark:border-gray-700 pb-3 mb-4 text-gray-800 dark:text-gray-200">Información General</h3>
                        <ul class="text-sm space-y-4 text-gray-700 dark:text-gray-300">
                            <!-- Detalles del Producto -->
                            <li class="flex items-start gap-3"><CubeIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Producto:</strong> {{ authorization.product_name }}</span></li>
                            <li class="flex items-start gap-3"><Squares2X2Icon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Material:</strong> {{ authorization.material || 'N/A' }}</span></li>
                            <li class="flex items-start gap-3"><SwatchIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Color:</strong> {{ authorization.color || 'N/A' }}</span></li>
                            <li class="flex items-start gap-3"><CogIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Métodos Prod.:</strong> {{ authorization.production_methods?.join(', ') || 'N/A' }}</span></li>
                            <li class="flex items-start gap-3 pt-3 border-t border-gray-200 dark:border-gray-600"><DocumentTextIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Especificaciones:</strong> {{ authorization.specifications || 'Ninguna' }}</span></li>
                            <!-- Información de Contacto -->
                           <li class="flex items-start gap-3 pt-3 border-t border-gray-200 dark:border-gray-600"><BriefcaseIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Cliente:</strong> {{ authorization.branch.name }}</span></li>
                           <li class="flex items-start gap-3"><UserIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Contacto:</strong> {{ authorization.contact.name }}</span></li>
                           <li class="flex items-start gap-3"><UserIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Vendedor:</strong> {{ authorization.seller.name }}</span></li>
                           <li v-if="authorization.responded_at" class="flex items-start gap-3 pt-3 border-t border-gray-200 dark:border-gray-600"><CalendarDaysIcon class="h-5 w-5 text-gray-400 mt-0.5" /><span><strong>Respuesta Cliente:</strong> {{ formatDate(authorization.responded_at) }}</span></li>
                        </ul>
                    </div>
                </div>
            </main>

            <!-- Modales -->
            <ConfirmationModal :show="showAcceptanceModal" @close="showAcceptanceModal = false" @confirm="submitClientResponse(true)" title="Confirmar Aceptación" message="¿Estás seguro de que deseas marcar este formato como ACEPTADO?" confirmButtonText="Sí, Aceptar" />
            <RejectionModal :show="showRejectionModal" @close="showRejectionModal = false" @submit="submitRejection" :processing="clientResponseForm.processing" :error="clientResponseForm.errors.rejection_reason" />
            <InternalAuthModal :show="showInternalAuthModal" @close="showInternalAuthModal = false" @submit="authorizeInternally" :processing="internalAuthForm.processing" :error="internalAuthForm.errors.authorizer_name" />
        </div>
    </AppLayout>
</template>

<script>
// Importaciones Principales
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import { useForm } from '@inertiajs/vue3';

// Importaciones de Componentes de UI (Modales)
import ConfirmationModal from './Modals/ConfirmationModal.vue';
import RejectionModal from './Modals/RejectionModal.vue';
import InternalAuthModal from './Modals/InternalAuthModal.vue';

// Importaciones de Íconos (Heroicons)
import { 
    CheckCircleIcon, XCircleIcon, ClockIcon, PhotoIcon, DocumentTextIcon,
    CubeIcon, SwatchIcon, CogIcon, Squares2X2Icon, BriefcaseIcon, UserIcon,
    CalendarDaysIcon, ShieldCheckIcon
} from '@heroicons/vue/24/outline'; // Usamos outline para un look más limpio

export default {
    components: {
        // Layout y Navegación
        AppLayout,
        Back,
        // Modales
        ConfirmationModal,
        RejectionModal,
        InternalAuthModal,
        // Íconos (registrados para uso en template)
        CheckCircleIcon, XCircleIcon, ClockIcon, PhotoIcon, DocumentTextIcon,
        CubeIcon, SwatchIcon, CogIcon, Squares2X2Icon, BriefcaseIcon, UserIcon,
        CalendarDaysIcon, ShieldCheckIcon,
    },
    props: {
        authorization: Object,
        cover_image_url: String,
        additional_files: Array,
    },
    data() {
        return {
            // Control de visibilidad de modales
            showRejectionModal: false,
            showAcceptanceModal: false,
            showInternalAuthModal: false,

            // Formulario para la respuesta del cliente
            clientResponseForm: useForm({
                is_accepted: null,
                rejection_reason: '',
            }),
            // Formulario para la autorización interna
            internalAuthForm: useForm({
                authorizer_name: '',
            }),
        };
    },
    computed: {
        // Calcula el estado actual para mostrar el badge correspondiente
        status() {
            if (this.authorization.is_accepted === true) {
                return { text: 'Aceptado por Cliente', icon: 'CheckCircleIcon', bgClass: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' };
            }
            if (this.authorization.is_accepted === false) {
                return { text: 'Rechazado por Cliente', icon: 'XCircleIcon', bgClass: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' };
            }
            return { text: 'Pendiente de Respuesta', icon: 'ClockIcon', bgClass: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' };
        },
    },
    methods: {
        print() {
            const url = route('design-authorizations.print', this.authorization.id);
            window.open(url, '_blank')
        },
        // Formatea la fecha a un formato legible
        formatDate(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Date(dateString).toLocaleDateString('es-MX', options);
        },

        // Gestiona el envío de la respuesta del cliente (aceptación)
        submitClientResponse(isAccepted) {
            this.clientResponseForm.is_accepted = isAccepted;
            const routeUrl = route('design-authorizations.client-response', this.authorization.id);
            
            this.clientResponseForm.put(routeUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    this.showAcceptanceModal = false;
                    // Aquí podrías añadir una notificación de éxito
                },
            });
        },

        // Gestiona el envío del rechazo
        submitRejection(reason) {
            this.clientResponseForm.is_accepted = false;
            this.clientResponseForm.rejection_reason = reason;
            const routeUrl = route('design-authorizations.client-response', this.authorization.id);

            this.clientResponseForm.put(routeUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    this.showRejectionModal = false;
                    this.clientResponseForm.reset('rejection_reason');
                    // Notificación de éxito
                },
            });
        },

        // Gestiona el envío de la autorización interna
        authorizeInternally() {
            this.internalAuthForm.post(route('design-authorizations.authorize-internal', this.authorization.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.showInternalAuthModal = false;
                    // Notificación de éxito
                }
            });
        },
    },
};
</script>
