<template>
    <AppLayout title="Novedades">
        <div class="py-12 min-h-screen transition-colors duration-300">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Header estilo iOS -->
                <div class="flex justify-between items-end mb-8 px-4 sm:px-0">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Novedades</h2>
                        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Gestiona las notas de actualización</p>
                    </div>
                    <Link :href="route('admin.releases.create')" 
                       class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-full shadow-lg shadow-blue-500/30 transition-all transform hover:scale-105 active:scale-95">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Nueva Versión
                    </Link>
                </div>

                <!-- Lista de Releases -->
                <div class="space-y-6">
                    <div v-for="release in releases.data" :key="release.id" 
                         class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-md transition-all duration-300">
                        
                        <!-- Estado de Publicación (Badge Flotante) -->
                        <div class="absolute top-4 right-4">
                            <span v-if="release.is_published" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-600 border border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800">
                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span> Publicado
                            </span>
                            <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                                <span class="w-2 h-2 bg-gray-400 rounded-full mr-2"></span> Borrador
                            </span>
                        </div>

                        <div class="p-6 sm:p-8">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="h-12 w-12 flex-shrink-0 rounded-2xl bg-indigo-50 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold text-lg shadow-inner">
                                    {{ release.version.substring(0, 2) }}
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ release.title }}</h3>
                                    <p class="text-sm text-gray-400 font-medium">Versión {{ release.version }} • {{ formatDate(release.created_at) }}</p>
                                </div>
                            </div>

                            <!-- Resumen de Contenido -->
                            <div class="flex items-center gap-2 mb-6 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 p-3 rounded-xl w-fit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                <span>Contiene <strong>{{ release.items_count || 0 }}</strong> secciones</span>
                            </div>

                            <!-- Acciones -->
                            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                                
                                <!-- NUEVO: Botón Editar (Solo si no está publicado) -->
                                <Link v-if="!release.is_published" 
                                      :href="route('admin.releases.edit', release.id)" 
                                      class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors cursor-pointer">
                                    Editar
                                </Link>

                                <span v-if="!release.is_published" class="text-gray-300 dark:text-gray-600">|</span>

                                <button v-if="!release.is_published" @click="publish(release.id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors cursor-pointer">
                                    Publicar Ahora
                                </button>
                                <span v-if="!release.is_published" class="text-gray-300 dark:text-gray-600">|</span>
                                
                                <button @click="destroy(release.id)" class="text-sm font-medium text-red-500 hover:text-red-700 dark:hover:text-red-400 transition-colors cursor-pointer">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Paginación Simple -->
                <div class="mt-6 flex justify-center" v-if="releases.links && releases.links.length > 3">
                    <div class="flex gap-1">
                        <Link v-for="(link, k) in releases.links" :key="k"
                              :href="link.url || '#'"
                              v-html="link.label"
                              class="px-3 py-1 rounded-md text-sm"
                              :class="{
                                  'bg-blue-600 text-white': link.active,
                                  'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700': !link.active,
                                  'opacity-50 cursor-not-allowed': !link.url
                              }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
// Se eliminó la línea: import moment from 'moment';

export default {
    components: {
        AppLayout,
        Link
    },
    props: {
        releases: Object // Viene paginado desde Laravel
    },
    methods: {
        formatDate(date) {
            // Usamos Javascript nativo para formatear la fecha
            if (!date) return '';
            return new Date(date).toLocaleDateString('es-ES', { 
                day: 'numeric', month: 'short', year: 'numeric' 
            });
        },
        publish(id) {
            router.post(route('admin.releases.publish', id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    // Opcional: Mostrar notificación toast
                }
            });
        },
        destroy(id) {
            if (confirm('¿Seguro que deseas eliminar esta actualización?')) {
                router.delete(route('admin.releases.destroy', id), {
                    preserveScroll: true
                });
            }
        }
    }
}
</script>