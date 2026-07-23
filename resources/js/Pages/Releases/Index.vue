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
                            <div class="flex items-center gap-2 mb-3 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 p-3 rounded-xl w-fit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                <span>Contiene <strong>{{ release.items_count || 0 }}</strong> secciones</span>
                            </div>

                            <!-- Audiencia objetivo -->
                            <div class="mb-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 mb-1.5">
                                    <i class="fa-solid fa-bullseye text-purple-500"></i> Audiencia:
                                </p>
                                <span v-if="release.target_all" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-100 dark:border-purple-800">
                                    <i class="fa-solid fa-globe mr-1"></i> Todos los usuarios activos
                                </span>
                                <div v-else-if="release.target_users && release.target_users.length > 0" class="flex flex-wrap gap-1">
                                    <span 
                                        v-for="user in release.target_users.slice(0, 5)" 
                                        :key="user.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-100 dark:border-purple-800"
                                    >
                                        {{ user.name }}
                                    </span>
                                    <span v-if="release.target_users.length > 5" class="text-xs text-gray-400 dark:text-gray-500 self-center">
                                        +{{ release.target_users.length - 5 }} más
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-400 dark:text-gray-500 italic">Sin audiencia definida</span>
                            </div>

                            <!-- Usuarios que ya lo vieron -->
                            <div v-if="release.users && release.users.length > 0" class="mb-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1">
                                    <i class="fa-solid fa-eye"></i> Visto por {{ release.users.length }} usuario(s):
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span 
                                        v-for="user in release.users" 
                                        :key="user.id"
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800"
                                    >
                                        {{ user.name }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="mb-4">
                                <p class="text-xs text-gray-400 dark:text-gray-500 italic flex items-center gap-1">
                                    <i class="fa-solid fa-eye-slash"></i> Nadie ha visto esta actualización aún.
                                </p>
                            </div>

                            <!-- Acciones -->
                            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                                
                                <!-- Ver contenido (siempre disponible) -->
                                <button @click="openDrawer(release)" 
                                      class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors cursor-pointer flex items-center gap-1">
                                    <i class="fa-solid fa-eye"></i> Ver contenido
                                </button>

                                <span class="text-gray-300 dark:text-gray-600">|</span>

                                <!-- Editar (siempre disponible) -->
                                <Link :href="route('admin.releases.edit', release.id)" 
                                      class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors cursor-pointer flex items-center gap-1">
                                    <i class="fa-solid fa-pen-to-square"></i> Editar
                                </Link>

                                <span class="text-gray-300 dark:text-gray-600">|</span>

                                <button v-if="!release.is_published" @click="publish(release.id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors cursor-pointer flex items-center gap-1">
                                    <i class="fa-solid fa-paper-plane"></i> Publicar
                                </button>
                                <span v-if="!release.is_published" class="text-gray-300 dark:text-gray-600">|</span>
                                
                                <button @click="destroy(release.id)" class="text-sm font-medium text-red-500 hover:text-red-700 dark:hover:text-red-400 transition-colors cursor-pointer flex items-center gap-1">
                                    <i class="fa-solid fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mensaje si no hay releases -->
                <div v-if="releases.data && releases.data.length === 0" class="text-center py-20">
                    <div class="text-6xl mb-4">📭</div>
                    <p class="text-gray-500 dark:text-gray-400 text-lg">No hay novedades todavía.</p>
                    <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Crea la primera versión para informar a tus usuarios.</p>
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

        <!-- DRAWER: Vista previa del contenido -->
        <el-drawer
            v-model="drawerOpen"
            :title="drawerTitle"
            direction="rtl"
            :size="drawerSize"
            class="custom-drawer"
        >
            <div v-if="selectedRelease" class="p-4 h-full flex flex-col bg-white dark:bg-slate-900 transition-colors duration-300">
                
                <!-- Información general -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <span v-if="selectedRelease.is_published" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                            Publicado
                        </span>
                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                            Borrador
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Versión {{ selectedRelease.version }}</p>
                </div>

                <!-- Items / Secciones -->
                <div class="flex-grow space-y-6">
                    <div v-for="(item, index) in selectedRelease.items" :key="item.id || index"
                         class="p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800"
                    >
                        <!-- Número de paso -->
                        <div class="flex items-center gap-3 mb-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-sm font-bold">
                                {{ index + 1 }}
                            </span>
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100 text-base">
                                {{ item.module_name }}
                            </h4>
                        </div>

                        <!-- Descripción -->
                        <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed ml-11">
                            {{ item.description }}
                        </p>

                        <!-- Media: Imagen o Video -->
                        <div v-if="item.media && item.media.length > 0" class="mt-4 ml-11">
                            <div v-for="media in item.media" :key="media.id" class="rounded-xl overflow-hidden border border-gray-200 dark:border-slate-600">
                                <!-- Video -->
                                <video 
                                    v-if="media.mime_type && media.mime_type.startsWith('video/')"
                                    :src="media.original_url"
                                    controls
                                    class="w-full max-h-80 object-contain bg-black rounded-xl"
                                ></video>
                                <!-- Imagen / GIF -->
                                <img 
                                    v-else
                                    :src="media.original_url"
                                    :alt="item.module_name"
                                    class="w-full max-h-80 object-contain bg-gray-100 dark:bg-slate-700 rounded-xl"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer: Usuarios que lo vieron -->
                <div class="border-t dark:border-slate-700 pt-4 mt-6">
                    <!-- Audiencia objetivo -->
                    <div class="mb-4">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                            <i class="fa-solid fa-bullseye text-purple-500"></i> Audiencia objetivo
                        </h4>
                        <span v-if="selectedRelease.target_all" class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-100 dark:border-purple-800">
                            <i class="fa-solid fa-globe mr-1.5"></i> Todos los usuarios activos
                        </span>
                        <div v-else-if="selectedRelease.target_users && selectedRelease.target_users.length > 0" class="flex flex-wrap gap-2">
                            <span 
                                v-for="user in selectedRelease.target_users" 
                                :key="user.id"
                                class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-100 dark:border-purple-800"
                            >
                                {{ user.name }}
                            </span>
                        </div>
                        <p v-else class="text-sm text-gray-400 dark:text-gray-500 italic">Sin audiencia definida</p>
                    </div>

                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-users"></i> Usuarios que ya lo vieron
                    </h4>
                    <div v-if="selectedRelease.users && selectedRelease.users.length > 0" class="flex flex-wrap gap-2">
                        <span 
                            v-for="user in selectedRelease.users" 
                            :key="user.id"
                            class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800"
                        >
                            <i class="fa-solid fa-circle-check mr-1.5 text-green-500 text-xs"></i>
                            {{ user.name }}
                        </span>
                    </div>
                    <p v-else class="text-sm text-gray-400 dark:text-gray-500 italic">
                        Nadie ha visto esta actualización todavía.
                    </p>
                </div>
            </div>
        </el-drawer>

    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

export default {
    components: {
        AppLayout,
        Link
    },
    props: {
        releases: Object
    },
    data() {
        return {
            drawerOpen: false,
            selectedRelease: null,
            drawerSize: '500px',
        };
    },
    computed: {
        drawerTitle() {
            if (!this.selectedRelease) return '';
            return this.selectedRelease.title;
        }
    },
    methods: {
        formatDate(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('es-ES', { 
                day: 'numeric', month: 'short', year: 'numeric' 
            });
        },
        openDrawer(release) {
            this.selectedRelease = release;
            this.drawerOpen = true;
        },
        publish(id) {
            router.post(route('admin.releases.publish', id), {}, {
                preserveScroll: true,
            });
        },
        destroy(id) {
            if (confirm('¿Seguro que deseas eliminar esta actualización?')) {
                router.delete(route('admin.releases.destroy', id), {
                    preserveScroll: true
                });
            }
        },
        handleResize() {
            this.drawerSize = window.innerWidth < 768 ? '85%' : '500px';
        },
    },
    mounted() {
        this.handleResize();
        window.addEventListener('resize', this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
}
</script>