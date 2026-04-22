<template>
  <div class="relative">
    <el-tooltip content="Notificaciones de Tareas" placement="bottom">
      <button @click.stop="toggleDropdown"
        class="relative flex justify-center items-center size-14 p-3 rounded-lg transition-colors duration-300 hover:bg-gray-100 dark:hover:bg-slate-700">
        
        <!-- Usamos un icono de tareas. Asegúrate de tener una imagen task3d.png o puedes usar un icono de fontawesome -->
        <img src="/images/task3d.png" alt="Tareas" class="w-[80%]" @error="imageError = true" v-show="!imageError">
        <i v-show="imageError" class="fa-solid fa-clipboard-list text-2xl text-blue-500"></i>

        <span v-if="unreadNotificationsCount > 0" class="absolute top-3 right-3 flex h-4 w-4 items-center justify-center">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
            <span class="relative inline-flex items-center justify-center rounded-full h-4 w-4 bg-orange-500 text-white text-[10px] font-bold">
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
          <h3 class="font-bold text-gray-800 dark:text-gray-200">Tareas y Pendientes</h3>
        </div>

        <div class="max-h-96 overflow-y-auto flex-grow">
          <ul v-if="notifications.length > 0">
            <li v-for="notification in notifications" :key="notification.id"
              :class="['group relative flex items-start gap-3 p-4 border-b dark:border-slate-700 transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-slate-700/50', 
                       { 'bg-orange-50/50 dark:bg-slate-900/50': !notification.read_at && initiallyUnread.includes(notification.id) }]">
              
              <!-- Checkbox de selección -->
              <div class="flex-shrink-0 pt-1">
                <input type="checkbox" v-model="selectedNotifications" :value="notification.id" @click.stop
                  class="rounded border-gray-300 dark:border-slate-600 text-orange-600 shadow-sm focus:ring-orange-500 dark:bg-slate-700 dark:checked:bg-orange-500">
              </div>
              
              <!-- Contenido principal de la notificación -->
              <div @click="handleNotificationClick(notification)" class="flex items-start gap-4 flex-grow cursor-pointer">
                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-orange-500 bg-orange-100 dark:bg-slate-700 dark:text-orange-400">
                  <i :class="notification.data.icon || 'fa-solid fa-list-check'"></i>
                </div>
                <div class="flex-grow">
                  <p class="text-sm text-gray-800 dark:text-gray-200 leading-tight" v-html="notification.data.message"></p>
                  <span class="text-xs text-gray-400 dark:text-gray-500">{{ formatTimeAgo(notification.created_at) }}</span>
                </div>
                <!-- Punto indicador (se oculta tras la primera vez que se abre el menú) -->
                <div v-if="!notification.read_at && initiallyUnread.includes(notification.id)" class="flex-shrink-0 w-2.5 h-2.5 bg-orange-500 rounded-full mt-1.5"></div>
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
            <i class="fa-solid fa-clipboard-check text-4xl text-gray-300 dark:text-gray-600 mb-2"></i>
            <h3 class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-300">Sin tareas pendientes</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No tienes notificaciones de tareas.</p>
          </div>
        </div>

        <!-- Footer para acciones masivas -->
        <div v-if="notifications.length > 0 && selectedNotifications.length > 0" class="p-2 border-t dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50 flex items-center justify-center">
            <button @click="deleteSelected"
                    class="w-full text-center px-4 py-2 text-sm font-medium text-white rounded-md bg-red-600 hover:bg-red-700 transition-colors duration-200">
                Eliminar seleccionadas ({{ selectedNotifications.length }})
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
      selectedNotifications: [],
      initiallyUnread: [], // Mantiene el estado visual localmente mientras se procesa la lectura
      imageError: false,
    };
  },
  computed: {
    unreadNotificationsCount() {
      return this.notifications.filter(n => !n.read_at).length;
    },
  },
  methods: {
    toggleDropdown() { 
      this.isOpen = !this.isOpen; 

      // ESTILO FACEBOOK: Si se abre el dropdown y hay notificaciones no leídas, las marcamos.
      if (this.isOpen && this.unreadNotificationsCount > 0) {
        this.markTasksAsRead();
      }
    },
    closeDropdown() { 
      this.isOpen = false; 
    },
    markTasksAsRead() {
      // Obtenemos los IDs de las notificaciones que no están leídas
      const unreadIds = this.notifications.filter(n => !n.read_at).map(n => n.id);
      
      if (unreadIds.length === 0) return;

      // Guardamos visualmente cuáles eran no leídas para no quitar el estilo "azul" inmediatamente de forma brusca
      this.initiallyUnread = [...unreadIds];

      // Llamada a la nueva ruta que marca solo los IDs seleccionados
      router.post(route('notifications.read-selected'), { ids: unreadIds }, {
        preserveScroll: true,
        preserveState: true,
      });
    },
    handleNotificationClick(notification) {
      const url = notification.data.url || '#';
      if (url !== '#') {
        router.visit(url);
      }
      this.closeDropdown();
    },
    deleteNotification(notificationId) {
      router.delete(route('notifications.destroy', notificationId), {
        preserveScroll: true,
      });
    },
    deleteSelected() {
      if (this.selectedNotifications.length === 0) return;

      router.post(route('notifications.destroy-selected'), {
        ids: this.selectedNotifications
      }, {
        preserveScroll: true,
        onSuccess: () => {
          this.selectedNotifications = []; 
        }
      });
    },
    formatTimeAgo(dateString) {
      return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale: es });
    },
  },
};
</script>