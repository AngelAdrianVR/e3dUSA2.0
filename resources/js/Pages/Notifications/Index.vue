<template>
    <AppLayout title="Notificaciones">
        <div class="py-7">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-5">
                <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <Back route="dashboard" />
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Notificaciones</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ unreadTotal }} pendiente{{ unreadTotal === 1 ? '' : 's' }}
                                    · {{ notifications.total }} en total
                                </p>
                            </div>
                        </div>

                        <SecondaryButton v-if="unreadTotal > 0" @click="markAllAsRead">
                            <i class="fa-solid fa-check-double mr-2"></i> Marcar todas como vistas
                        </SecondaryButton>
                    </div>

                    <!-- Filtros por categoría, fecha o ID -->
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 items-end">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Categoría</p>
                            <el-select v-model="filtersForm.category" clearable placeholder="Todas las categorías"
                                class="w-full" @change="applyFilters">
                                <el-option v-for="category in categories" :key="category.key"
                                    :label="category.label" :value="category.key" />
                            </el-select>
                        </div>

                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Fecha</p>
                            <el-date-picker v-model="filtersForm.dateRange" type="daterange" value-format="YYYY-MM-DD"
                                start-placeholder="Desde" end-placeholder="Hasta" class="!w-full" @change="applyFilters" />
                        </div>

                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">ID del registro</p>
                            <el-input v-model="filtersForm.id" clearable placeholder="Ej. 3700, COT-3700, OV-3700"
                                @keyup.enter="applyFilters" @clear="applyFilters">
                                <template #append>
                                    <el-button @click="applyFilters">Buscar</el-button>
                                </template>
                            </el-input>
                        </div>

                        <div class="flex justify-start xl:justify-end">
                            <SecondaryButton @click="clearFilters">
                                <i class="fa-solid fa-filter-circle-xmark mr-2"></i> Limpiar filtros
                            </SecondaryButton>
                        </div>
                    </div>

                    <div v-if="notifications.total === 0" class="py-12 text-center">
                        <i class="fa-solid fa-bell-slash text-4xl text-gray-300 dark:text-gray-600"></i>
                        <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Sin notificaciones</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No hay notificaciones que coincidan con los filtros.
                        </p>
                    </div>

                    <div v-else class="mt-6 space-y-8">
                        <section v-for="category in groupedCategories" :key="category.key">
                            <div class="flex items-center justify-between gap-3 pb-2 border-b border-gray-200 dark:border-slate-700">
                                <h3 class="flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-200">
                                    <i :class="[categoryIcon(category.key), 'text-gray-400']"></i>
                                    {{ category.label }}
                                </h3>
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1"
                                    :class="categoryUnread(category.key) > 0
                                        ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300'
                                        : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400'">
                                    {{ categoryUnread(category.key) }} pendiente{{ categoryUnread(category.key) === 1 ? '' : 's' }}
                                </span>
                            </div>

                            <ul class="divide-y divide-gray-100 dark:divide-slate-700">
                                <li v-for="notification in category.notifications" :key="notification.id"
                                    :class="['group relative flex items-start gap-3 p-4 transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-slate-800/60',
                                        { 'bg-blue-50/50 dark:bg-slate-800/80': isUnread(notification) }]">

                                    <div class="flex-shrink-0 pt-1">
                                        <input type="checkbox" v-model="selectedNotifications" :value="notification.id"
                                            title="Seleccionar"
                                            class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-slate-700 dark:checked:bg-blue-500">
                                    </div>

                                    <div @click="handleNotificationClick(notification)" class="flex items-start gap-4 flex-grow cursor-pointer min-w-0">
                                        <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-blue-500 bg-blue-100 dark:bg-slate-700 dark:text-blue-400">
                                            <i :class="notification.data.icon || 'fa-solid fa-bell'"></i>
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <p class="text-sm text-gray-800 dark:text-gray-200 leading-snug" v-html="notification.data.message"></p>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ formatTimeAgo(notification.created_at) }}</span>
                                        </div>
                                        <div v-if="isUnread(notification)" class="flex-shrink-0 w-2.5 h-2.5 bg-blue-500 rounded-full mt-1.5"></div>
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <el-tooltip v-if="isUnread(notification)" content="Marcar como vista" placement="top">
                                            <button @click.stop="markNotificationRead(notification)"
                                                class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-300 transition-colors hover:bg-emerald-500 hover:text-white">
                                                <i class="fa-solid fa-check text-xs"></i>
                                            </button>
                                        </el-tooltip>
                                        <el-tooltip content="Eliminar" placement="top">
                                            <button @click.stop="deleteNotification(notification.id)"
                                                class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-300 transition-colors hover:bg-red-500 hover:text-white">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </el-tooltip>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <div v-if="notifications.total > 0" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="notifications.current_page" :page-size="notifications.per_page"
                            :total="notifications.total" layout="prev, pager, next" background
                            @current-change="goToPage" />
                    </div>
                </div>

                <!-- Acciones masivas -->
                <div v-if="selectedNotifications.length > 0"
                    class="sticky bottom-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 shadow-lg rounded-lg p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ selectedNotifications.length }} notificación{{ selectedNotifications.length === 1 ? '' : 'es' }} seleccionada{{ selectedNotifications.length === 1 ? '' : 's' }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button v-if="hasUnreadSelected" @click="markSelectedAsRead"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white rounded-md bg-emerald-600 hover:bg-emerald-700 transition-colors">
                            <i class="fa-solid fa-check mr-2"></i>
                            Marcar como vistas ({{ unreadSelectedCount }})
                        </button>
                        <DangerButton @click="deleteSelected">
                            <i class="fa-solid fa-trash-can mr-2"></i> Eliminar seleccionadas
                        </DangerButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import { ElMessage } from "element-plus";
import { formatDistanceToNow } from "date-fns";
import { es } from "date-fns/locale";

export default {
    components: {
        AppLayout,
        Back,
        SecondaryButton,
        DangerButton,
    },
    props: {
        notifications: {
            type: Object,
            default: () => ({ data: [], current_page: 1, per_page: 100, total: 0 }),
        },
        categories: {
            type: Array,
            default: () => [],
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            selectedNotifications: [],
            readLocally: [],
            filtersForm: {
                category: this.filters.category ?? '',
                dateRange: this.filters.from && this.filters.to
                    ? [this.filters.from, this.filters.to]
                    : null,
                id: this.filters.id ?? '',
            },
        };
    },
    computed: {
        groupedCategories() {
            const grouped = {};

            this.notifications.data.forEach((notification) => {
                const key = notification.category || 'otros';
                grouped[key] = [...(grouped[key] ?? []), notification];
            });

            return this.categories
                .map((category) => ({ ...category, notifications: grouped[category.key] ?? [] }))
                .filter((category) => category.notifications.length > 0);
        },
        unreadTotal() {
            if (!this.categories.length) {
                return this.notifications.data.filter((notification) => this.isUnread(notification)).length;
            }

            return this.categories.reduce((total, category) => total + this.categoryUnread(category.key), 0);
        },
        hasUnreadSelected() {
            return this.unreadSelectedCount > 0;
        },
        unreadSelectedCount() {
            return this.selectedNotifications.filter((id) => {
                const notification = this.notifications.data.find((item) => item.id === id);

                return notification && this.isUnread(notification);
            }).length;
        },
    },
    methods: {
        isUnread(notification) {
            return !notification.read_at && !this.readLocally.includes(notification.id);
        },
        categoryUnread(key) {
            if (!this.categories.length) {
                return this.notifications.data.filter(
                    (notification) => (notification.category || 'otros') === key && this.isUnread(notification)
                ).length;
            }

            const category = this.categories.find((item) => item.key === key);
            const markedNow = this.notifications.data.filter(
                (notification) => (notification.category || 'otros') === key
                    && !notification.read_at
                    && this.readLocally.includes(notification.id)
            ).length;

            return Math.max((category?.unread ?? 0) - markedNow, 0);
        },
        markNotificationRead(notification) {
            if (!this.isUnread(notification)) return;

            this.readLocally.push(notification.id);

            axios.patch(route('notifications.read', notification.id)).catch(() => {
                this.readLocally = this.readLocally.filter((id) => id !== notification.id);
                ElMessage.error('No se pudo marcar la notificación como vista.');
            });
        },
        async markSelectedAsRead() {
            const ids = this.selectedNotifications.filter((id) => {
                const notification = this.notifications.data.find((item) => item.id === id);

                return notification && this.isUnread(notification);
            });

            if (!ids.length) return;

            this.readLocally.push(...ids);

            try {
                await axios.post(route('notifications.read-selected'), { ids });
                this.selectedNotifications = [];
            } catch (error) {
                this.readLocally = this.readLocally.filter((id) => !ids.includes(id));
                ElMessage.error('No se pudieron marcar las notificaciones como vistas.');
            }
        },
        markAllAsRead() {
            if (this.unreadTotal === 0) return;

            router.post(route('notifications.read-all'), { general_only: true }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    this.readLocally = [];
                    this.selectedNotifications = [];
                },
            });
        },
        handleNotificationClick(notification) {
            this.markNotificationRead(notification);

            const url = this.resolveNotificationUrl(notification.data.url);

            if (url !== '#') {
                router.visit(url);
            }
        },
        resolveNotificationUrl(url) {
            if (!url || url === '#') return '#';
            try {
                const parsed = new URL(url, window.location.origin);
                return parsed.pathname + parsed.search + parsed.hash;
            } catch (e) {
                return url;
            }
        },
        deleteNotification(notificationId) {
            router.delete(route('notifications.destroy', notificationId), {
                preserveScroll: true,
                onSuccess: () => {
                    this.selectedNotifications = this.selectedNotifications.filter((id) => id !== notificationId);
                },
            });
        },
        deleteSelected() {
            if (this.selectedNotifications.length === 0) return;

            router.post(route('notifications.destroy-selected'), {
                ids: this.selectedNotifications,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    this.selectedNotifications = [];
                },
            });
        },
        buildParams(page = null) {
            const params = {
                category: this.filtersForm.category || undefined,
                from: this.filtersForm.dateRange?.[0] || undefined,
                to: this.filtersForm.dateRange?.[1] || undefined,
                id: this.filtersForm.id?.trim() || undefined,
            };

            if (page) {
                params.page = page;
            }

            return Object.fromEntries(
                Object.entries(params).filter(([, value]) => value !== undefined && value !== '')
            );
        },
        applyFilters() {
            router.get(route('notifications.index'), this.buildParams(), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        },
        clearFilters() {
            this.filtersForm.category = '';
            this.filtersForm.dateRange = null;
            this.filtersForm.id = '';
            this.applyFilters();
        },
        goToPage(page) {
            router.get(route('notifications.index'), this.buildParams(page), { preserveScroll: true });
        },
        formatTimeAgo(dateString) {
            return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale: es });
        },
        categoryIcon(key) {
            return {
                ventas: 'fa-solid fa-cash-register',
                produccion: 'fa-solid fa-industry',
                diseno: 'fa-solid fa-palette',
                requisiciones: 'fa-solid fa-clipboard-list',
                otros: 'fa-solid fa-bell',
            }[key] ?? 'fa-solid fa-bell';
        },
    },
};
</script>
