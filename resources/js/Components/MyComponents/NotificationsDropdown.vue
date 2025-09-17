<template>
  <div class="relative">
    <el-tooltip content="Notificaciones" placement="bottom">
      <button @click.stop="toggleDropdown"
        class="relative flex justify-center items-center size-14 p-3 rounded-lg transition-colors duration-300 hover:bg-gray-100 dark:hover:bg-slate-700">
        <img src="/images/notification3d.png" alt="" class="w-[80%]">
        <span v-if="unreadNotificationsCount > 0" class="absolute top-3 right-3 flex h-4 w-4 items-center justify-center">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex items-center justify-center rounded-full h-4 w-4 bg-red-500 text-white text-[10px] font-bold">
                {{ unreadNotificationsCount }}
            </span>
        </span>
      </button>
    </el-tooltip>

    <transition enter-active-class="transition ease-out duration-200" enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
      <div v-if="isOpen" v-click-outside="closeDropdown"
        class="absolute right-0 mt-2 w-80 sm:w-96 bg-white dark:bg-slate-800 rounded-xl shadow-2xl overflow-hidden z-50 border dark:border-slate-700 flex flex-col">
        
        <div class="flex justify-between items-center p-4 border-b dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
          <h3 class="font-bold text-gray-800 dark:text-gray-200">Notificaciones</h3>
        </div>

        <div class="max-h-96 overflow-y-auto flex-grow">
          <ul v-if="notifications.length > 0">
            <li v-for="notification in notifications" :key="notification.id"
              :class="['group relative flex items-start gap-3 p-4 border-b dark:border-slate-700 transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-slate-700/50', 
                       { 'bg-blue-50/50 dark:bg-slate-900/50': !notification.read_at }]">
              
              <!-- Checkbox de selección -->
              <div class="flex-shrink-0 pt-1">
                <input type="checkbox" v-model="selectedNotifications" :value="notification.id" @click.stop
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
                <div v-if="!notification.read_at" class="flex-shrink-0 w-2.5 h-2.5 bg-blue-500 rounded-full mt-1.5"></div>
              </div>

              <!-- Botón de eliminar individual -->
              <button @click.stop="deleteNotification(notification.id)" 
                      class="absolute top-1/2 right-2 -translate-y-1/2 flex items-center justify-center w-7 h-7 rounded-full bg-gray-200 dark:bg-slate-600 text-gray-500 dark:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity duration-300 hover:bg-red-500 hover:text-white dark:hover:bg-red-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </li>
          </ul>
          
          <div v-else class="p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.586 15.586a2 2 0 112.828 2.828M11.05 11.05a2 2 0 00-2.828 2.828m.014-11.014a2 2 0 012.828 0M1.636 6.036a2 2 0 010-2.828m12.728 0a2 2 0 012.828 0M1.636 17.964a2 2 0 010 2.828m12.728 0a2 2 0 012.828 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-300">Todo al día</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No tienes notificaciones nuevas.</p>
          </div>
        </div>

        <!-- Footer para acciones masivas -->
        <div v-if="notifications.length > 0" class="p-2 border-t dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50 flex items-center"
            :class="selectedNotifications.length > 0 ? 'justify-center' : 'justify-end'">

            <!-- Botón de eliminar seleccionados (toma prioridad) -->
            <button v-if="selectedNotifications.length > 0" @click="deleteSelected"
                    class="w-full text-center px-4 py-2 text-sm font-medium text-white rounded-md bg-red-600 hover:bg-red-700 transition-colors duration-200">
                Eliminar seleccionadas ({{ selectedNotifications.length }})
            </button>

            <!-- Botón de marcar todas como leídas (se muestra si no hay nada seleccionado) -->
            <button v-else-if="unreadNotificationsCount > 0" @click="markAllAsRead"
                    class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline pr-2">
                Marcar todas como leídas
            </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';

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
  },
  data() {
    return { 
      isOpen: false,
      selectedNotifications: [], // Array para guardar los IDs de las notificaciones seleccionadas
    };
  },
  computed: {
    unreadNotificationsCount() {
      return this.notifications.filter(n => !n.read_at).length;
    },
  },
  methods: {
    toggleDropdown() { this.isOpen = !this.isOpen; },
    closeDropdown() { this.isOpen = false; },
    handleNotificationClick(notification) {
      const url = notification.data.url || '#';
      if (notification.read_at) {
        if (url !== '#') router.visit(url);
        this.closeDropdown();
        return;
      }
      router.patch(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
          if (url !== '#') router.visit(url);
          this.closeDropdown();
        }
      });
    },
    markAllAsRead() {
      router.post(route('notifications.read-all'), {}, {
        preserveScroll: true,
      });
    },
    deleteNotification(notificationId) {
      router.delete(route('notifications.destroy', notificationId), {
        preserveScroll: true,
      });
    },
    // --- NUEVO MÉTODO PARA ELIMINAR SELECCIONADAS ---
    deleteSelected() {
      if (this.selectedNotifications.length === 0) return;

      router.post(route('notifications.destroy-selected'), {
        ids: this.selectedNotifications
      }, {
        preserveScroll: true,
        onSuccess: () => {
          this.selectedNotifications = []; // Limpiar la selección después de eliminar
        }
      });
    },
    formatTimeAgo(dateString) {
      return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale: es });
    },
  },
};
</script>

