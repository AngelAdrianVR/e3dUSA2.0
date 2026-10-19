<template>
  <div class="relative">
    <el-tooltip content="Notificaciones Generales" placement="bottom">
      <button @click.stop="toggleDropdown"
        class="relative flex justify-center items-center size-14 p-3 rounded-lg transition-colors duration-300 hover:bg-gray-100 dark:hover:bg-slate-700">
        <img src="/images/notification3d.png" alt="" class="w-[80%]">
        <span v-if="unreadNotificationsCount > 0" class="absolute top-2 right-1 flex h-5 min-w-5 items-center justify-center"
            :title="`${unreadNotificationsCount} notificaciones pendientes`">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex items-center justify-center rounded-full h-5 min-w-5 px-1 bg-red-500 text-white text-[10px] font-bold">
                {{ unreadNotificationsCount > 99 ? '99+' : unreadNotificationsCount }}
            </span>
        </span>
      </button>
    </el-tooltip>

    <transition enter-active-class="transition ease-out duration-200" enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
      <div v-if="isOpen" v-click-outside="closeDropdown"
        class="absolute right-0 mt-2 w-80 sm:w-[30rem] bg-white dark:bg-slate-800 rounded-xl shadow-2xl overflow-hidden z-50 border dark:border-slate-700 flex flex-col">
        
        <div class="flex items-start justify-between gap-3 p-4 border-b dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
          <div class="min-w-0">
            <h3 class="font-bold text-gray-800 dark:text-gray-200">Notificaciones</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ unreadNotificationsCount }} pendiente{{ unreadNotificationsCount === 1 ? '' : 's' }}
            </p>
          </div>
          <div class="flex flex-col items-end gap-1 shrink-0">
            <button v-if="unreadNotificationsCount > 0" @click.stop="markAllAsRead"
              class="text-xs font-semibold text-primary hover:underline">
              Marcar todas como vistas
            </button>
            <button @click.stop="openAllNotifications"
              class="text-xs font-semibold text-primary hover:underline">
              Ver todas <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-[10px]"></i>
            </button>
          </div>
        </div>

        <div class="flex flex-wrap gap-1.5 px-3 py-2 border-b border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
          <button @click.stop="setCategory('')"
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors"
            :class="activeCategory === ''
              ? 'bg-primary text-white'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600'">
            Todas
            <span class="rounded-full bg-black/10 px-1.5 py-0.5 text-[10px]">{{ unreadNotificationsCount }}</span>
          </button>
          <button v-for="category in categoryList" :key="category.key" @click.stop="setCategory(category.key)"
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors"
            :class="activeCategory === category.key
              ? 'bg-primary text-white'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600'">
            {{ category.label }}
            <span class="rounded-full bg-black/10 px-1.5 py-0.5 text-[10px]">{{ categoryUnread(category.key) }}</span>
          </button>
        </div>

        <div class="max-h-[30rem] overflow-y-auto flex-grow">
          <div v-if="loading" class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Cargando notificaciones...
          </div>

          <template v-if="groupedCategories.length">
            <div v-for="category in groupedCategories" :key="category.key">
              <!-- Encabezado de categoría con su conteo de pendientes -->
              <div class="sticky top-0 z-10 flex items-center justify-between gap-3 px-4 py-2 bg-gray-100/95 dark:bg-slate-700/95 backdrop-blur border-b border-gray-200 dark:border-slate-600">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                  {{ category.label }}
                </span>
                <span class="text-[11px] font-semibold rounded-full px-2 py-0.5"
                  :class="categoryUnread(category.key) > 0
                    ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300'
                    : 'bg-gray-200 text-gray-500 dark:bg-slate-700 dark:text-gray-400'">
                  {{ categoryUnread(category.key) }} pendiente{{ categoryUnread(category.key) === 1 ? '' : 's' }}
                </span>
              </div>

              <ul v-if="category.notifications.length">
                <li v-for="notification in category.notifications" :key="notification.id"
                  :class="['group relative flex items-start gap-3 p-4 border-b dark:border-slate-700 transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-slate-700/50',
                           { 'bg-blue-50/50 dark:bg-slate-900/50': isUnread(notification) }]">

                  <!-- Selector para acciones masivas -->
                  <div class="flex-shrink-0 pt-1">
                    <input type="checkbox" v-model="selectedNotifications" :value="notification.id"
                      @click.stop
                      title="Seleccionar"
                      class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-slate-700 dark:checked:bg-blue-500">
                  </div>

                  <!-- Contenido principal de la notificación -->
                  <div @click="handleNotificationClick(notification)" class="flex items-start gap-4 flex-grow cursor-pointer">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-blue-500 bg-blue-100 dark:bg-slate-700 dark:text-blue-400">
                      <i :class="notification.data.icon || 'fa-solid fa-bell'"></i>
                    </div>
                    <div class="flex-grow">
                      <p class="text-sm text-gray-800 dark:text-gray-200 leading-tight" v-html="notification.data.message"></p>
                      <span class="text-xs text-gray-400 dark:text-gray-500">{{ formatTimeAgo(notification.created_at) }}</span>
                    </div>

                    <!-- Punto indicador de no leída -->
                    <div v-if="isUnread(notification)" class="flex-shrink-0 w-2.5 h-2.5 bg-blue-500 rounded-full mt-1.5"></div>
                  </div>

                  <!-- Acciones: marcar como vista y eliminar -->
                  <div class="flex items-center gap-1 shrink-0">
                    <el-tooltip v-if="isUnread(notification)" content="Marcar como vista" placement="top">
                      <button @click.stop="markNotificationRead(notification)"
                              class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-300 transition-colors hover:bg-emerald-500 hover:text-white">
                        <i class="fa-solid fa-check text-xs"></i>
                      </button>
                    </el-tooltip>
                    <button @click.stop="deleteNotification(notification.id)"
                            class="flex items-center justify-center w-7 h-7 rounded-full bg-gray-200 dark:bg-slate-600 text-gray-500 dark:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity duration-300 hover:bg-red-500 hover:text-white dark:hover:bg-red-500">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </li>
              </ul>

              <p v-else class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500">
                Sin notificaciones recientes en esta categoría.
              </p>
            </div>
          </template>
          
          <div v-else class="p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.586 15.586a2 2 0 112.828 2.828M11.05 11.05a2 2 0 00-2.828 2.828m.014-11.014a2 2 0 012.828 0M1.636 6.036a2 2 0 010-2.828m12.728 0a2 2 0 012.828 0M1.636 17.964a2 2 0 010 2.828m12.728 0a2 2 0 012.828 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-300">
              {{ activeCategory ? 'Sin notificaciones' : 'Todo al día' }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ activeCategory ? 'No hay notificaciones en esta categoría.' : 'No tienes notificaciones nuevas.' }}
            </p>
            <button v-if="activeCategory" @click.stop="setCategory('')"
              class="mt-3 text-xs font-semibold text-primary hover:underline">
              Ver todas las categorías
            </button>
          </div>
        </div>

        <!-- Footer para acciones masivas -->
        <div v-if="selectedNotifications.length > 0" class="p-2 border-t dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50 flex items-center gap-2">
            <button v-if="hasUnreadSelected" @click="markSelectedAsRead"
                    class="flex-1 text-center px-3 py-2 text-sm font-medium text-white rounded-md bg-emerald-600 hover:bg-emerald-700 transition-colors duration-200">
                Marcar como vistas ({{ unreadSelectedCount }})
            </button>
            <button @click="deleteSelected"
                    class="flex-1 text-center px-3 py-2 text-sm font-medium text-white rounded-md bg-red-600 hover:bg-red-700 transition-colors duration-200">
                Eliminar ({{ selectedNotifications.length }})
            </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';

const DEFAULT_CATEGORIES = [
  { key: 'ventas', label: 'Ventas', unread: 0 },
  { key: 'produccion', label: 'Producción', unread: 0 },
  { key: 'diseno', label: 'Diseño', unread: 0 },
  { key: 'requisiciones', label: 'Requisiciones', unread: 0 },
  { key: 'otros', label: 'Otros', unread: 0 },
];

const vClickOutside = {
  beforeMount(el, binding) {
    el.clickOutsideEvent = function(event) {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event);
      }
    };
    document.body.addEventListener('click', el.clickOutsideEvent);
  },
  unmounted(el) {
    document.body.removeEventListener('click', el.clickOutsideEvent);
  },
};

export default {
  directives: { clickOutside: vClickOutside },
  props: {
    notifications: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
  },
  data() {
    return { 
      isOpen: false,
      selectedNotifications: [], // IDs seleccionadas para acciones masivas
      readLocally: [], // Se actualiza de inmediato al marcar como vista
      items: null, // Últimas 30 notificaciones obtenidas del servidor
      activeCategory: '',
      serverCategories: [],
      loading: false,
    };
  },
  computed: {
    categoryList() {
      if (this.serverCategories?.length) return this.serverCategories;
      if (this.categories?.length) return this.categories;
      return DEFAULT_CATEGORIES;
    },
    displayNotifications() {
      return this.items ?? this.notifications;
    },
    groupedCategories() {
      const grouped = {};
      const source = this.activeCategory
        ? this.displayNotifications.filter((notification) => (notification.category || 'otros') === this.activeCategory)
        : this.displayNotifications;

      source.forEach((notification) => {
        const key = notification.category || 'otros';
        grouped[key] = [...(grouped[key] ?? []), notification];
      });

      const categories = this.activeCategory
        ? this.categoryList.filter((category) => category.key === this.activeCategory)
        : this.categoryList;

      return categories
        .map((category) => ({ ...category, notifications: grouped[category.key] ?? [] }))
        .filter((category) => category.notifications.length > 0
          || (!this.activeCategory && this.categoryUnread(category.key) > 0));
    },
    unreadNotificationsCount() {
      return this.categoryList.reduce((total, category) => total + this.categoryUnread(category.key), 0);
    },
    hasUnreadSelected() {
      return this.unreadSelectedCount > 0;
    },
    unreadSelectedCount() {
      return this.selectedNotifications.filter((id) => {
        const notification = this.displayNotifications.find((item) => item.id === id);

        return notification && this.isUnread(notification);
      }).length;
    },
  },
  methods: {
    isUnread(notification) {
      return !notification.read_at && !this.readLocally.includes(notification.id);
    },
    categoryUnread(key) {
      const category = this.categoryList.find((item) => item.key === key);
      const markedNow = this.displayNotifications.filter(
        (notification) => (notification.category || 'otros') === key
          && !notification.read_at
          && this.readLocally.includes(notification.id)
      ).length;

      return Math.max((category?.unread ?? 0) - markedNow, 0);
    },
    toggleDropdown() { 
      this.isOpen = !this.isOpen;

      if (this.isOpen) {
        this.loadNotifications();
      }
    },
    closeDropdown() { 
      this.isOpen = false; 
    },
    setCategory(category) {
      if (this.activeCategory === category) return;

      this.activeCategory = category;
      this.loadNotifications();
    },
    async loadNotifications() {
      this.loading = true;

      try {
        const { data } = await axios.get(route('notifications.dropdown'), {
          params: this.activeCategory ? { category: this.activeCategory } : {},
        });

        this.items = data.notifications ?? [];
        this.serverCategories = data.categories ?? [];
      } catch (error) {
        ElMessage.error('No se pudieron cargar las notificaciones.');
      } finally {
        this.loading = false;
      }
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
        const notification = this.displayNotifications.find((item) => item.id === id);

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
    async markAllAsRead() {
      if (this.unreadNotificationsCount === 0) return;

      this.loading = true;

      try {
        await axios.post(route('notifications.read-all'), { general_only: true });

        const now = new Date().toISOString();

        this.items = this.displayNotifications.map((notification) => ({
          ...notification,
          read_at: notification.read_at ?? now,
        }));
        this.serverCategories = this.categoryList.map((category) => ({ ...category, unread: 0 }));
        this.readLocally = [];
        this.selectedNotifications = [];
      } catch (error) {
        ElMessage.error('No se pudieron marcar todas las notificaciones como vistas.');
      } finally {
        this.loading = false;
      }
    },
    openAllNotifications() {
      this.closeDropdown();
      router.visit(route('notifications.index', this.activeCategory ? { category: this.activeCategory } : {}));
    },
    handleNotificationClick(notification) {
      this.markNotificationRead(notification);

      const url = this.resolveNotificationUrl(notification.data.url);

      if (url !== '#') {
        router.visit(url);
      }
      this.closeDropdown();
    },
    // Las notificaciones generadas por comandos (cron) pueden traer una URL absoluta con un
    // APP_URL distinto al host actual. Usamos solo la ruta para navegar siempre en el host actual.
    resolveNotificationUrl(url) {
      if (!url || url === '#') return '#';
      try {
        const parsed = new URL(url, window.location.origin);
        return parsed.pathname + parsed.search + parsed.hash;
      } catch (e) {
        return url;
      }
    },
    async deleteNotification(notificationId) {
      try {
        await axios.delete(route('notifications.destroy', notificationId));
        this.items = this.displayNotifications.filter((notification) => notification.id !== notificationId);
        this.selectedNotifications = this.selectedNotifications.filter((id) => id !== notificationId);
        this.loadNotifications();
      } catch (error) {
        ElMessage.error('No se pudo eliminar la notificación.');
      }
    },
    async deleteSelected() {
      if (this.selectedNotifications.length === 0) return;

      const ids = [...this.selectedNotifications];

      try {
        await axios.post(route('notifications.destroy-selected'), { ids });
        this.items = this.displayNotifications.filter((notification) => !ids.includes(notification.id));
        this.selectedNotifications = [];
        this.loadNotifications();
      } catch (error) {
        ElMessage.error('No se pudieron eliminar las notificaciones seleccionadas.');
      }
    },
    formatTimeAgo(dateString) {
      return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale: es });
    },
  },
};
</script>