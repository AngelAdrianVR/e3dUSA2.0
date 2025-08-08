<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SideNav from "@/Components/MyComponents/SideNav.vue";
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeToggleSwitch2 from "@/Components/MyComponents/ThemeToggleSwitch2.vue";

defineProps({
    title: String,
    externalLoading: Boolean,
});

const showingNavigationDropdown = ref(false);
const isDarkMode = ref(localStorage.getItem('darkMode') === 'true');// Obtener el estado del modo nocturno desde el localStorage
const darkModeSwitch = ref(localStorage.getItem('darkMode') === 'true');// Obtener el estado del modo nocturno desde el localStorage
const isFocused = ref(false); // Variable para controlar el estado del input de búsqueda
const searchInput = ref(null); // Referencia al input de búsqueda global
const unseenMessages = ref(null); // Variable para contar los mensajes no leídos


const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    darkModeSwitch.value = isDarkMode.value;
    localStorage.setItem('darkMode', isDarkMode.value); // Guardar el estado en localStorage+
    document.documentElement.classList.toggle('dark', isDarkMode.value);
};

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const openSearch = async () => {
  isFocused.value = true;
  // Espera a que Vue actualice el DOM para que el input sea visible
  await nextTick(); 
  // Ahora enfoca el input
  searchInput.value.focus();
};

const closeSearch = () => {
  isFocused.value = false;
};

const logout = () => {
    router.post(route('logout'));
};

onMounted(() => {
  document.documentElement.classList.toggle('dark', isDarkMode.value);
});

</script>

<template>
    <div>
        <Head :title="title" />

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
                                <div class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                                    <ThemeToggleSwitch2 v-model="darkModeSwitch" @update:modelValue="toggleDarkMode" />
                                </div>

                                 <!-- calendario -->
                                <div class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 relative">
                                    <el-tooltip content="Calendario">
                                        <!-- <Link :href="route('calendars.index')"> -->
                                        <button class="flex justify-center items-center size-14 p-3">
                                            <img src="/images/calendar_3d.webp" alt="" class="w-full">
                                        </button>
                                        <!-- </Link> -->
                                    </el-tooltip>
                                    <!-- <i v-if="$page.props.auth.user?.notifications?.some(notification => {
                                        return notification.data.module === 'calendar';
                                    })" class="fa-solid fa-circle fa-flip text-primary text-sm absolute -top-2 -right-0"></i> -->
                                </div>

                                <!-- chat -->
                                <div class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 relative">
                                    <el-tooltip v-if="$page.props.auth.user?.permissions?.includes('Chatear') || true" content="Chat"
                                        placement="bottom">
                                        <a :href="'#'" target="_blank" class="size-14 flex justify-center items-center p-3">
                                            <img src="/images/chat_3d.webp" alt="" class="w-full">
                                        </a>
                                    </el-tooltip>
                                    <div v-if="unseenMessages > 0"
                                        class="absolute bottom-6 right-4 bg-primary text-white w-4 h-4 flex items-center justify-center text-[10px] rounded-full">
                                        {{ unseenMessages }}
                                    </div>
                                </div>

                                <!-- Buscador global -->
                                <div class="relative flex items-center justify-end pl-5 border-l border-gray-200 dark:border-slate-700">
                                    <!-- Contenedor del input y el ícono interno -->
                                    <div 
                                        class="relative transition-all duration-500 ease-in-out"
                                        :class="isFocused ? 'w-64' : 'w-10'"
                                    >
                                        <!-- El input de búsqueda -->
                                        <input
                                            ref="searchInput"
                                            type="text"
                                            placeholder="Buscar..."
                                            @blur="closeSearch"
                                            class="
                                                w-full h-9 pl-10 pr-4 rounded-full border 
                                                text-sm transition-all duration-300 ease-in-out
                                                bg-gray-100 dark:bg-slate-700 
                                                border-gray-200 dark:border-slate-600
                                                text-gray-700 dark:text-gray-200
                                                focus:outline-none focus:ring-1 focus:ring-blue-500
                                            "
                                            :class="{ 'opacity-100': isFocused, 'opacity-0': !isFocused }"
                                        />
                                        <!-- Ícono de lupa dentro del input (siempre visible) -->
                                        <div class="absolute top-0 left-0 flex items-center justify-center h-full w-10 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Botón de la lupa que activa el buscador -->
                                    <!-- Se oculta cuando el buscador está abierto -->
                                    <button
                                        v-if="!isFocused"
                                        @click="openSearch"
                                        class="
                                            absolute top-0 left-0 flex items-center justify-center h-9 w-9 rounded-full ml-5
                                            text-gray-500 dark:text-gray-400 
                                            bg-gray-200 dark:bg-slate-700
                                            transition-opacity duration-300
                                        "
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Hamburger -->
                            <div class="-me-2 flex items-center sm:hidden">

                                <!-- Dark mode toggle -->
                                <div class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition duration-300">
                                    <ThemeToggleSwitch2 v-model="darkModeSwitch" @update:modelValue="toggleDarkMode" />
                                </div>

                                <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                    <svg
                                        class="size-6"
                                        stroke="currentColor"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16"
                                        />
                                        <path
                                            :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Responsive Navigation Menu -->
                    <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                        <div class="pt-2 pb-3 space-y-1">
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Dashboard
                            </ResponsiveNavLink>
                        </div>

                        <!-- Responsive Settings Options -->
                        <div class="pt-4 pb-1 border-t border-gray-200">
                            <div class="flex items-center px-4">
                                <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                    <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                </div>

                                <div>
                                    <div class="font-medium text-base text-gray-800">
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div class="font-medium text-sm text-gray-500">
                                        {{ $page.props.auth.user.email }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 space-y-1">
                                <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                    Profile
                                </ResponsiveNavLink>

                                <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                    API Tokens
                                </ResponsiveNavLink>

                                <!-- Authentication -->
                                <form method="POST" @submit.prevent="logout">
                                    <ResponsiveNavLink as="button">
                                        Log Out
                                    </ResponsiveNavLink>
                                </form>

                                <!-- Team Management -->
                                <template v-if="$page.props.jetstream.hasTeamFeatures">
                                    <div class="border-t border-gray-200" />

                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        Manage Team
                                    </div>

                                    <!-- Team Settings -->
                                    <ResponsiveNavLink :href="route('teams.show', $page.props.auth.user.current_team)" :active="route().current('teams.show')">
                                        Team Settings
                                    </ResponsiveNavLink>

                                    <ResponsiveNavLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" :active="route().current('teams.create')">
                                        Create New Team
                                    </ResponsiveNavLink>

                                    <!-- Team Switcher -->
                                    <template v-if="$page.props.auth.user.all_teams.length > 1">
                                        <div class="border-t border-gray-200" />

                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Switch Teams
                                        </div>

                                        <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                            <form @submit.prevent="switchToTeam(team)">
                                                <ResponsiveNavLink as="button">
                                                    <div class="flex items-center">
                                                        <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <div>{{ team.name }}</div>
                                                    </div>
                                                </ResponsiveNavLink>
                                            </form>
                                        </template>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Page Content -->
                <div class="overflow-auto h-[calc(100vh-4rem)] rounded-l-3xl z-50 bg-[#f2f2f2] dark:bg-zinc-700 p-3 md:p-5">
                    <slot />
                </div>
            </section>
        </div>
    </div>
</template>
