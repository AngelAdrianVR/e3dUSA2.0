<script setup>
import { ref, onMounted, nextTick, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SideNav from "@/Components/MyComponents/SideNav.vue";
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeToggleSwitch from "@/Components/MyComponents/ThemeToggleSwitch.vue";
import NotificationsDropdown from "@/Components/MyComponents/NotificationsDropdown.vue";
import DraggableAlert from "@/Components/MyComponents/DraggableAlert.vue";
import AttendanceTracker from "@/Components/MyComponents/AttendanceTracker.vue";
import axios from 'axios';
import { useNavigation } from '@/Composables/useNavigation.js';
import MobileNavLink from '@/Components/MyComponents/MobileNavLink.vue';

defineProps({
    title: String,
    externalLoading: Boolean,
});

const page = usePage();
const showingNavigationDropdown = ref(false);
const isDarkMode = ref(localStorage.getItem('darkMode') === 'true');
const darkModeSwitch = ref(localStorage.getItem('darkMode') === 'true');
const isFocused = ref(false);
const searchInput = ref(null);
const unseenMessages = ref(null);

// Usamos el nuevo composable para obtener los menús
const { menus } = useNavigation(route);

// Watcher para prevenir el scroll del body cuando el menú móvil está abierto
watch(showingNavigationDropdown, (isOpen) => {
    if (isOpen) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'auto';
    }
});


// --- LÓGICA DEL BUSCADOR GLOBAL ---
const searchQuery = ref('');
const searchResults = ref(null);
const isSearching = ref(false);
let searchTimeout = null;

// Observador para el campo de búsqueda con debounce
watch(searchQuery, (newQuery) => {
    clearTimeout(searchTimeout);
    if (newQuery.length > 1) {
        isSearching.value = true;
        searchTimeout = setTimeout(() => {
            performSearch(newQuery);
        }, 300); // Espera 300ms antes de buscar
    } else {
        searchResults.value = null;
        isSearching.value = false;
    }
});

// Función para realizar la llamada a la API
const performSearch = async (term) => {
    try {
        const response = await axios.get(route('global.search', { term }));
        searchResults.value = response.data;
    } catch (error) {
        console.error('Error en la búsqueda global:', error);
        searchResults.value = null;
    } finally {
        isSearching.value = false;
    }
};

const openSearch = async () => {
    isFocused.value = true;
    await nextTick();
    searchInput.value.focus();
};

const closeSearch = () => {
    // Retrasar el cierre para permitir el clic en los resultados
    setTimeout(() => {
        isFocused.value = false;
        searchQuery.value = '';
        searchResults.value = null;
    }, 200);
};

// --- FIN LÓGICA BUSCADOR ---

// <-- COMPUTED PARA LAS NOTIFICACIONES -->
const userNotifications = computed(() => page.props.auth.user.notifications || []);

// 2. COMPUTED PARA LAS ALERTAS ACTIVAS (¡ESTA ES LA FORMA CORRECTA!)
const activeAlerts = computed(() => page.props.auth.user?.active_alerts || {});
const hasEmployeeDetails = computed(() => !!page.props.auth.user?.employee_detail); // Para saber si mostrar el tracker

// --- LÓGICA MODAL DE ACTUALIZACIONES (RELEASES) ---
const popupRelease = computed(() => page.props.popupRelease || null);
const showUpdateModal = ref(false);
const currentStep = ref(0);
const isUpdating = ref(false);

const nextStep = () => {
    if (popupRelease.value && currentStep.value < popupRelease.value.items.length - 1) {
        currentStep.value++;
    } else {
        finishUpdate();
    }
};

const finishUpdate = () => {
    if (!popupRelease.value) return;
    
    isUpdating.value = true;
    router.post(route('releases.mark-read', popupRelease.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showUpdateModal.value = false;
            isUpdating.value = false;
            // Resetear paso para la próxima
            setTimeout(() => currentStep.value = 0, 300);
        },
        onError: () => {
            isUpdating.value = false;
        }
    });
};

// Cerrar sin marcar como leído (la X)
const closeUpdateModal = () => {
    showUpdateModal.value = false;
};
// --- FIN LÓGICA ACTUALIZACIONES ---


const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    darkModeSwitch.value = isDarkMode.value;
    localStorage.setItem('darkMode', isDarkMode.value);
    document.documentElement.classList.toggle('dark', isDarkMode.value);
};

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

const getUnseenMessages = async () => {
    try {
        const response = await axios.post(route("users.get-unseen-messages"));

        if (response.status === 200) {
            unseenMessages.value = response.data.count;
        }
    } catch (error) {
        console.log(error);
    }
};

onMounted(() => {
    getUnseenMessages();
    document.documentElement.classList.toggle('dark', isDarkMode.value);

    // Verificar si hay actualización pendiente al cargar
    if (popupRelease.value) {
        // Pequeño delay para que la UI cargue primero y la animación se vea bien
        setTimeout(() => {
            showUpdateModal.value = true;
        }, 1000);
    }
});

</script>

<template>
    <div>

        <Head :title="title" />

        <!-- 3. RENDERIZAR LAS ALERTAS USANDO EL COMPONENTE -->
        <!-- Se mostrará un componente por cada alerta activa que tenga el usuario -->
        <template v-for="(alert, key) in activeAlerts" :key="key">
            <DraggableAlert v-if="alert" :alert-data="alert" />
        </template>
        
        <!-- ============================================== -->
        <!-- MODAL DE ACTUALIZACIONES (RELEASES / NOVEDADES) -->
        <!-- ============================================== -->
        <teleport to="body">
            <!-- Backdrop -->
            <transition 
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showUpdateModal" class="fixed inset-0 z-[60] flex items-center justify-center px-4 py-6 sm:px-0">
                    <div class="absolute inset-0 bg-gray-900/80 dark:bg-black/70 transition-opacity" @click="closeUpdateModal"></div>

                    <!-- Modal Card -->
                    <transition 
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div v-if="popupRelease" class="relative bg-white dark:bg-zinc-900 rounded-[2rem] shadow-2xl overflow-hidden transform transition-all w-full max-w-lg flex flex-col max-h-[90vh]">
                            
                            <!-- Header con Versión y Botón Cerrar -->
                            <div class="flex items-center justify-between p-6 pb-2">
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 mb-1">
                                        Novedad
                                    </span>
                                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white leading-tight">
                                        {{ popupRelease.title }}
                                    </h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Versión {{ popupRelease.version }}</p>
                                </div>
                                <button @click="closeUpdateModal" class="p-2 rounded-full text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors focus:outline-none">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <!-- Content Wizard -->
                            <div class="flex-1 overflow-y-auto p-6 pt-2">
                                <div v-for="(item, index) in popupRelease.items" :key="item.id" v-show="currentStep === index" class="space-y-4 animate-fade-in-up">
                                    
                                    <!-- Imagen / Media -->
                                    <div class="aspect-video w-full rounded-2xl bg-gray-100 dark:bg-zinc-800 overflow-hidden shadow-inner flex items-center justify-center border border-gray-100 dark:border-zinc-700">
                                        <!-- Asumiendo que usas Spatie Media Library y el JSON trae 'media' -->
                                        <img v-if="item.media && item.media[0]" 
                                             :src="item.media[0].original_url" 
                                             class="w-full h-full object-cover" 
                                             alt="Update preview">
                                        <div v-else class="text-gray-400 dark:text-zinc-600 flex flex-col items-center">
                                            <svg class="w-12 h-12 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span class="text-xs font-medium">Sin imagen previa</span>
                                        </div>
                                    </div>

                                    <!-- Texto -->
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300 flex items-center justify-center text-xs mr-2 font-bold">{{ index + 1 }}</span>
                                            {{ item.module_name }}
                                        </h3>
                                        <p class="text-gray-600 dark:text-gray-300 text-base leading-relaxed">
                                            {{ item.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Navigation -->
                            <div class="p-6 bg-gray-50 dark:bg-zinc-800/50 border-t border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                                <!-- Dots Indicator -->
                                <div class="flex space-x-1.5">
                                    <span v-for="(_, idx) in popupRelease.items" :key="idx" 
                                          class="block rounded-full transition-all duration-300"
                                          :class="currentStep === idx ? 'w-6 h-2 bg-blue-600 dark:bg-blue-500' : 'w-2 h-2 bg-gray-300 dark:bg-zinc-600'">
                                    </span>
                                </div>

                                <!-- Action Button -->
                                <button @click="nextStep" :disabled="isUpdating" 
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-gray-900 hover:bg-gray-800 dark:bg-white dark:text-black dark:hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all transform active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed">
                                    <span v-if="currentStep < popupRelease.items.length - 1">Siguiente</span>
                                    <span v-else>
                                        {{ isUpdating ? 'Guardando...' : '¡Entendido!' }}
                                    </span>
                                    <svg v-if="currentStep < popupRelease.items.length - 1" class="ml-2 -mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    <svg v-else class="ml-2 -mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </button>
                            </div>
                        </div>
                    </transition>
                </div>
            </transition>
        </teleport>


        <Banner />

        <div class="overflow-hidden h-screen bg-white dark:bg-zinc-900 md:grid md:grid-cols-12">
            <aside>
                <SideNav :loading="externalLoading" />
            </aside>

            <section class="md:col-span-11">
                <nav class="bg-white dark:bg-zinc-900 dark:border-slate-700">
                    <!-- Primary Navigation Menu -->
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-end h-14">
                            <div class="hidden sm:flex sm:items-center sm:ms-6 transition duration-300 space-x-1">
                                <!-- Dark mode toggle -->
                                <div class="rounded-lg mr-3">
                                    <ThemeToggleSwitch v-model="darkModeSwitch" @update:modelValue="toggleDarkMode" />
                                </div>

                                <AttendanceTracker v-if="hasEmployeeDetails" class="!ml-4" />

                                <!-- calendario -->
                                <div :class="[
                                    'rounded-lg relative transition',
                                    'hover:bg-gray-100 dark:hover:bg-slate-700',
                                    route().current('calendar.*')
                                        ? 'bg-blue-100 dark:bg-slate-800'
                                        : ''
                                ]">
                                    <el-tooltip content="Calendario">
                                        <Link :href="route('calendar.index')">
                                        <button class="flex justify-center items-center size-14 p-3">
                                            <img src="/images/calendar_3d.webp" alt="" class="w-full">
                                        </button>
                                        </Link>
                                    </el-tooltip>
                                </div>

                                <!-- chat -->
                                <div class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 relative">
                                    <el-tooltip v-if="$page.props.auth.user?.permissions?.includes('Chatear')"
                                        content="Chat" placement="bottom">
                                        <a :href="route('chatify')" target="_blank"
                                            class="size-14 flex justify-center items-center p-3">
                                            <img src="/images/chat_3d.webp" alt="" class="w-full">
                                        </a>
                                    </el-tooltip>
                                    <div v-if="unseenMessages > 0"
                                        class="absolute bottom-2 right-2 bg-secondary text-white w-4 h-4 flex items-center justify-center text-[10px] rounded-full">
                                        {{ unseenMessages }}
                                    </div>
                                </div>

                                <!-- Notificaciones -->
                                <NotificationsDropdown :notifications="userNotifications" />

                                <!-- Buscador global -->
                                <div class="relative flex items-center justify-end pl-5 border-l border-gray-200 dark:border-slate-700">
                                    <div class="relative transition-all duration-500 ease-in-out" :class="isFocused ? 'w-80' : 'w-10'">
                                        <input 
                                            ref="searchInput" 
                                            type="text" 
                                            placeholder="Buscar en todo el sistema..." 
                                            @blur="closeSearch"
                                            @focus="isFocused = true"
                                            v-model="searchQuery"
                                            class="w-full h-10 pl-10 pr-4 rounded-full border text-sm transition-all duration-300 ease-in-out bg-gray-100 dark:bg-slate-700 border-gray-200 dark:border-slate-600 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                            :class="{ 'opacity-100': isFocused, 'opacity-0': !isFocused }" 
                                        />
                                        <div class="absolute top-0 left-0 flex items-center justify-center h-full w-10 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        
                                        <div v-if="isFocused && searchQuery.length > 1"
                                            class="absolute top-14 left-0 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-2xl border dark:border-zinc-700 overflow-hidden z-50">
                                            <div v-if="isSearching" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Buscando...
                                            </div>
                                            <div v-else-if="!Object.keys(searchResults || {}).length" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No se encontraron resultados para "{{ searchQuery }}"
                                            </div>
                                            <div v-else class="max-h-96 overflow-y-auto">
                                                <div v-for="(results, category) in searchResults" :key="category">
                                                    <h3 class="text-xs font-semibold text-gray-400 uppercase p-3 border-b dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900/50">{{ category }}</h3>
                                                    <ul>
                                                        <li v-for="item in results" :key="item.id">
                                                            <Link :href="item.url" class="flex items-center p-3 hover:bg-blue-50 dark:hover:bg-zinc-700 transition-colors duration-150">
                                                                <div class="flex-shrink-0 mr-3">
                                                                    <svg v-if="category === 'Usuarios'" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                                    <svg v-if="category === 'Productos'" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                                                    <svg v-if="category === 'Sucursales'" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m-1 4h1m5-4h1m-1 4h1m-1-4h1m-1 4h1"></path></svg>
                                                                    <svg v-if="category === 'Ventas'" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                                                    <svg v-if="category === 'Máquinas'" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                                </div>
                                                                <div class="flex-grow">
                                                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ item.name || `Venta #${item.id}` }}</p>
                                                                    <p v-if="item.code || item.email || item.status" class="text-xs text-gray-500 dark:text-gray-400">
                                                                        {{ item.code || item.email || item.status }}
                                                                    </p>
                                                                </div>
                                                            </Link>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button v-if="!isFocused" @click="openSearch" class="absolute top-0.5 left-0 flex items-center justify-center h-9 w-9 rounded-full ml-5 text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-slate-700 transition-opacity duration-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Hamburger -->
                            <div class="-me-2 flex items-center sm:hidden">
                                <div class="rounded-lg transition duration-300 mr-3">
                                    <ThemeToggleSwitch v-model="darkModeSwitch" @update:modelValue="toggleDarkMode" />
                                </div>

                                <!-- calendario -->
                                <div :class="[
                                    'rounded-lg relative transition',
                                    'hover:bg-gray-100 dark:hover:bg-slate-700',
                                    route().current('calendar.*')
                                        ? 'bg-blue-100 dark:bg-slate-800'
                                        : ''
                                ]">
                                    <el-tooltip content="Calendario">
                                        <Link :href="route('calendar.index')">
                                        <button class="flex justify-center items-center size-14 p-3">
                                            <img src="/images/calendar_3d.webp" alt="" class="w-full">
                                        </button>
                                        </Link>
                                    </el-tooltip>
                                </div>

                                <!-- chat -->
                                <div class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 relative">
                                    <el-tooltip v-if="$page.props.auth.user?.permissions?.includes('Chatear')"
                                        content="Chat" placement="bottom">
                                        <a :href="route('chatify')" target="_blank"
                                            class="size-14 flex justify-center items-center p-3">
                                            <img src="/images/chat_3d.webp" alt="" class="w-full">
                                        </a>
                                    </el-tooltip>
                                    <div v-if="unseenMessages > 0"
                                        class="absolute bottom-2 right-2 bg-secondary text-white w-4 h-4 flex items-center justify-center text-[10px] rounded-full">
                                        {{ unseenMessages }}
                                    </div>
                                </div>

                                <!-- Notificaciones -->
                                <NotificationsDropdown :notifications="userNotifications" />

                                <button
                                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-200 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-zinc-800 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out z-50"
                                    @click="showingNavigationDropdown = !showingNavigationDropdown">
                                    <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path
                                            :class="{ 'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16" />
                                        <path
                                            :class="{ 'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Page Content -->
                    <div
                        class="overflow-auto h-[calc(100vh-4rem)] rounded-3xl lg:rounded-l-3xl z-10 bg-[#f2f2f2] dark:bg-zinc-700 p-3 md:p-5 selection:bg-indigo-300 selection:text-white">
                        <slot />
                    </div>
                </nav>
            </section>
        </div>
        
        <!-- NEW MOBILE NAVIGATION MENU -->
        <teleport to="body">
            <Transition name="mobile-nav-fade">
                <div v-if="showingNavigationDropdown" @click="showingNavigationDropdown = false" class="fixed inset-0 bg-black/60 z-30 sm:hidden"></div>
            </Transition>

            <Transition name="mobile-nav-slide">
                <div v-if="showingNavigationDropdown" class="fixed top-0 left-0 h-full w-4/5 max-w-sm bg-white dark:bg-zinc-900 z-40 shadow-2xl flex flex-col sm:hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-start p-4 border-b border-gray-200 dark:border-zinc-800 h-14">
                         <Link :href="route('dashboard')">
                            <ApplicationMark class="block w-auto" />
                        </Link>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="flex-1 overflow-y-auto p-2 space-y-1">
                        <template v-for="menu in menus" :key="menu.label">
                            <div v-if="menu.show">
                                <MobileNavLink 
                                    :href="menu.route ? menu.route : null" 
                                    :active="menu.active" 
                                    :dropdown="menu.dropdown"
                                    @click="menu.dropdown ? null : (showingNavigationDropdown = false)">
                                    <template #icon>
                                        <span v-html="menu.icon"></span>
                                    </template>
                                    {{ menu.label }}
                                    <template v-if="menu.dropdown" #content>
                                        <Link v-for="option in menu.options.filter(opt => opt.show)" 
                                            :key="option.label" 
                                            :href="option.route ? route(option.route) : '#'"
                                            @click="showingNavigationDropdown = false"
                                            class="block w-full py-2 px-3 text-sm rounded-md transition-colors"
                                            :class="option.active 
                                                ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-zinc-800/50 font-semibold' 
                                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800'"
                                        >
                                            {{ option.label }}
                                        </Link>
                                    </template>
                                </MobileNavLink>
                            </div>
                        </template>
                    </div>

                    <!-- User Info / Footer -->
                    <div class="p-4 border-t border-gray-200 dark:border-zinc-800">
                         <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>
                            <div>
                                <div class="font-medium text-base text-gray-800 dark:text-gray-200">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Perfil
                            </ResponsiveNavLink>
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Cerrar Sesión
                                </ResponsiveNavLink>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </teleport>

    </div>
</template>

<style>
/* Transitions for Mobile Nav */
.mobile-nav-fade-enter-active,
.mobile-nav-fade-leave-active {
    transition: opacity 0.3s ease;
}
.mobile-nav-fade-enter-from,
.mobile-nav-fade-leave-to {
    opacity: 0;
}

.mobile-nav-slide-enter-active,
.mobile-nav-slide-leave-active {
    transition: transform 0.3s ease-in-out;
}
.mobile-nav-slide-enter-from,
.mobile-nav-slide-leave-to {
    transform: translateX(-100%);
}

/* Animations for Release Modal */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in-up {
    animation: fadeInUp 0.4s ease-out forwards;
}
</style>