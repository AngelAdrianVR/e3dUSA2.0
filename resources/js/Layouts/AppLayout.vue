<script setup>
import { ref, onMounted, nextTick, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SideNav from "@/Components/MyComponents/SideNav.vue";
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeToggleSwitch from "@/Components/MyComponents/ThemeToggleSwitch.vue";
import ThemeToggleSwitch2 from "@/Components/MyComponents/ThemeToggleSwitch2.vue";
import NotificationsDropdown from "@/Components/MyComponents/NotificationsDropdown.vue";
// 1. IMPORTAR EL COMPONENTE DE ALERTA
import DraggableAlert from "@/Components/MyComponents/DraggableAlert.vue";
import AttendanceTracker from "@/Components/MyComponents/AttendanceTracker.vue";

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

// <-- COMPUTED PARA LAS NOTIFICACIONES -->
const userNotifications = computed(() => page.props.auth.user.notifications || []);

// 2. COMPUTED PARA LAS ALERTAS ACTIVAS (¡ESTA ES LA FORMA CORRECTA!)
const activeAlerts = computed(() => page.props.auth.user?.active_alerts || {});
const hasEmployeeDetails = computed(() => !!page.props.auth.user?.employee_detail); // Para saber si mostrar el tracker

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

const openSearch = async () => {
    isFocused.value = true;
    await nextTick();
    searchInput.value.focus();
};

const closeSearch = () => {
    isFocused.value = false;
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
                                <div class="rounded-lg">
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
                                <div
                                    class="relative flex items-center justify-end pl-5 border-l border-gray-200 dark:border-slate-700">
                                    <div class="relative transition-all duration-500 ease-in-out"
                                        :class="isFocused ? 'w-64' : 'w-10'">
                                        <input ref="searchInput" type="text" placeholder="Buscar..." @blur="closeSearch"
                                            class="
                                                w-full h-9 pl-10 pr-4 rounded-full border 
                                                text-sm transition-all duration-300 ease-in-out
                                                bg-gray-100 dark:bg-slate-700 
                                                border-gray-200 dark:border-slate-600
                                                text-gray-700 dark:text-gray-200
                                                focus:outline-none focus:ring-1 focus:ring-blue-500
                                            " :class="{ 'opacity-100': isFocused, 'opacity-0': !isFocused }" />
                                        <div
                                            class="absolute top-0 left-0 flex items-center justify-center h-full w-10 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                    </div>

                                    <button v-if="!isFocused" @click="openSearch" class="
                                            absolute top-0 left-0 flex items-center justify-center h-9 w-9 rounded-full ml-5
                                            text-gray-500 dark:text-gray-400 
                                            bg-gray-200 dark:bg-slate-700
                                            transition-opacity duration-300
                                        ">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Hamburger -->
                            <div class="-me-2 flex items-center sm:hidden">

                                <!-- Dark mode toggle -->
                                <div
                                    class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition duration-300">
                                    <ThemeToggleSwitch v-model="darkModeSwitch" @update:modelValue="toggleDarkMode" />
                                </div>

                                <button
                                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
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

                    <!-- Responsive Navigation Menu -->
                    <div :class="{ 'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown }"
                        class="sm:hidden">
                        <div class="pt-2 pb-3 space-y-1">
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Dashboard
                            </ResponsiveNavLink>
                        </div>

                        <!-- Responsive Settings Options -->
                        <div class="pt-4 pb-1 border-t border-gray-200">
                            <div class="flex items-center px-4">
                                <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                    <img class="size-10 rounded-full object-cover"
                                        :src="$page.props.auth.user.profile_photo_url"
                                        :alt="$page.props.auth.user.name">
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
                                <ResponsiveNavLink :href="route('profile.show')"
                                    :active="route().current('profile.show')">
                                    Profile
                                </ResponsiveNavLink>

                                <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures"
                                    :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
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
                                    <ResponsiveNavLink :href="route('teams.show', $page.props.auth.user.current_team)"
                                        :active="route().current('teams.show')">
                                        Team Settings
                                    </ResponsiveNavLink>

                                    <ResponsiveNavLink v-if="$page.props.jetstream.canCreateTeams"
                                        :href="route('teams.create')" :active="route().current('teams.create')">
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
                                                        <svg v-if="team.id == $page.props.auth.user.current_team_id"
                                                            class="me-2 size-5 text-green-400"
                                                            xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24" stroke-width="1.5"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                <div
                    class="overflow-auto h-[calc(100vh-4rem)] rounded-3xl lg:rounded-l-3xl z-50 bg-[#f2f2f2] dark:bg-zinc-700 p-3 md:p-5 selection:bg-indigo-300 selection:text-white">
                    <slot />
                </div>
            </section>
        </div>
    </div>
</template>
