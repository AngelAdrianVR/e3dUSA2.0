<template>
  <AppLayout title="Calendario">
    <div class="p-1 md:p-4 font-sans">
      
      <header class="flex items-center justify-between pb-3 border-b border-gray-300 dark:border-gray-600">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Calendario de Actividades</h1>
      </header>

      <!-- SECCIÓN DE INVITACIONES PENDIENTES -->
      <div v-if="pendingInvitations.length > 0" class="my-6 p-4 bg-blue-50 dark:bg-slate-800 border border-blue-200 dark:border-slate-700 rounded-lg">
        <h3 class="font-bold text-lg text-blue-800 dark:text-blue-300 mb-3">Invitaciones Pendientes</h3>
        <ul class="space-y-3">
          <li v-for="invitation in pendingInvitations" :key="invitation.id" class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-3 bg-white dark:bg-slate-900 rounded-md shadow-sm">
            <div>
              <p class="font-semibold text-gray-800 dark:text-gray-200">{{ invitation.calendar_entry.title }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Fecha: {{ formatDateTime(invitation.calendar_entry.start_datetime)}}</p>
            </div>
            <div class="flex items-center gap-2 mt-3 sm:mt-0">
              <SecondaryButton @click="respondToInvitation(invitation.id, 'Aceptado')">
                <i class="fa-solid fa-circle-check mr-2"></i> Aceptar
              </SecondaryButton>
              <PrimaryButton @click="respondToInvitation(invitation.id, 'Rechazado')">
                <i class="fa-solid fa-circle-xmark mr-2"></i> Rechazar
              </PrimaryButton>
            </div>
          </li>
        </ul>
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-between mt-4">
        <div class="flex items-center space-x-4">
          <button @click="changeMonth(-1)" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 w-48 text-center capitalize">{{ monthAndYear }}</h2>
          <button @click="changeMonth(1)" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 sm:mt-0">Haz clic en un día para agendar un nuevo recordatorio.</p>
      </div>

      <div class="grid grid-cols-7 gap-px mt-6 bg-gray-200 dark:bg-gray-700 border rounded-lg overflow-hidden shadow">
        <div v-for="day in weekDays" :key="day" class="py-2 text-center text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800">{{ day }}</div>
        
        <div v-for="day in calendarDays" :key="day.date" @click="createEntry(day.date)"
             :class="['relative min-h-[120px] p-2 bg-white dark:bg-gray-800 transition-colors hover:bg-blue-50 dark:hover:bg-gray-700/50 cursor-default', { 'bg-gray-50 dark:bg-gray-900': !day.isCurrentMonth }]">
          <span :class="['font-semibold', day.isToday ? 'bg-blue-600 text-white rounded-full h-7 w-7 flex items-center justify-center' : 'text-gray-700 dark:text-gray-200']">{{ day.dayNumber }}</span>
          <div class="mt-2 space-y-1">
            <div v-for="entry in entriesByDay[day.date]" :key="entry.id" @click.stop="openDetailDrawer(entry)"
                 :class="['px-2 py-1 rounded-md text-xs font-medium cursor-pointer truncate flex items-center gap-2', getEntryClasses(entry)]">
              <i :class="getEntryIcon(entry)"></i>
              <span>{{ entry.title }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Drawer para ver detalles -->
    <el-drawer 
        v-model="isDetailDrawerOpen" 
        :title="drawerTitle" 
        :direction="drawerDirection" 
        :size="drawerSize"
        class="custom-drawer"
        >
        <div v-if="selectedEntry" class="p-4 h-full flex flex-col bg-white dark:bg-slate-900 transition-colors duration-300">
            
            

            <!-- Detalles -->
            <div class="flex-grow space-y-6">
                <!-- Tarea -->
                <div v-if="isTask(selectedEntry)" class="p-4 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800">
                    <p class="font-medium text-gray-900 dark:text-gray-100 flex items-center">
                    <i class="fa-solid fa-check-circle mr-2 text-blue-500"></i> 
                    Estado: 
                    <span class="ml-2" 
                        :class="selectedEntry.entryable?.status === 'Completada' 
                        ? 'text-green-500 font-semibold' 
                        : 'text-yellow-500 font-semibold'">
                        {{ selectedEntry.entryable?.status === 'Completada' ? 'Completada' : 'Pendiente' }}
                    </span>
                    </p>
                </div>

                <!-- Descripción -->
                <div class="p-4 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800">
                    <h2 class="text-gray-900 dark:text-gray-100 text-base leading-relaxed font-semibold mb-3">Descripción:</h2>
                    <p class="text-gray-700 dark:text-gray-300 text-base leading-relaxed">
                        {{ selectedEntry.description || 'Sin descripción.' }}
                    </p>
                </div>

                <!-- Evento -->
                <div v-if="isEvent(selectedEntry)" class="space-y-2">
                    <div class="flex items-center text-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-location-dot mr-2 text-blue-500"></i>
                        <span class="font-medium">Ubicación:</span>
                        <span class="ml-2">{{ selectedEntry.entryable.location || 'No especificada' }}</span>
                    </div>
                    
                    <div class="flex items-center text-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-link mr-2 text-blue-500"></i>
                        <span class="font-medium">URL de conferencia:</span>
                        <span class="ml-2">{{ selectedEntry.entryable.conference_link || 'No aplica' }}</span>
                    </div>

                    <!-- Fecha y hora de inicio -->
                    <div class="flex items-center text-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-clock mr-2 text-blue-500"></i>
                        <span class="font-medium">Inicia:</span>
                        <span class="ml-2">{{ formatDateTime(selectedEntry.start_datetime) || 'No especificada' }}</span>
                    </div>

                    <!-- Fecha y hora de finalización -->
                    <div class="flex items-center text-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-clock mr-2 text-blue-500"></i>
                        <span class="font-medium">Termina:</span>
                        <span class="ml-2">{{ formatDateTime(selectedEntry.end_datetime) || 'No especificada' }}</span>
                    </div>

                    <div>
                        <h4 class="font-bold text-gray-900 dark:text-gray-100 flex items-center mb-2">
                            <i class="fa-solid fa-users mr-2 text-purple-500"></i> Participantes
                        </h4>
                        <ul class="space-y-2">
                            <li 
                            v-for="participant in selectedEntry.entryable.participants" 
                            :key="participant.id"
                            class="flex items-center justify-between text-sm p-2 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800"
                            >
                            <span class="text-gray-700 dark:text-gray-200">{{ participant.name }}</span>
                            <span 
                                class="px-3 py-1 rounded-full text-xs font-semibold"
                                :class="getParticipantStatusClass(participant.pivot?.status)"
                            >
                                {{ participant.pivot?.status }}
                            </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t dark:border-slate-700 pt-4 mt-6 flex justify-between items-center">
            <div class="flex gap-2">
                <!-- Acciones Tareas -->
                <SecondaryButton 
                    v-if="isTask(selectedEntry) && selectedEntry.entryable?.status === 'Pendiente'" 
                    @click="completeTask(selectedEntry.entryable.id)"
                    >
                    <i class="fa-solid fa-check mr-2"></i> Marcar como completada
                </SecondaryButton>
            </div>

            <button 
                @click="deleteEntry(selectedEntry.id)" 
                class="text-red-500 hover:text-red-700 font-semibold flex items-center gap-1"
            >
                <i class="fa-solid fa-trash"></i> Eliminar
            </button>
            </div>
        </div>
        </el-drawer>

  </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { Link, router } from "@inertiajs/vue3";
import { format, startOfMonth, endOfMonth, eachDayOfInterval, startOfWeek, endOfWeek, addMonths, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';

export default {
  components: { AppLayout, PrimaryButton, SecondaryButton, Link },
  props: {
    calendarEntries: Array,
    authUserId: Number,
    pendingInvitations: Array,
  },
  data() {
    return {
      currentDate: new Date(),
      isDetailDrawerOpen: false,
      selectedEntry: null,
      weekDays: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
      drawerDirection: 'rtl',
      drawerSize: '400px',
    };
  },
  computed: {
    monthAndYear() { return format(this.currentDate, 'MMMM \'de\' yyyy', { locale: es }); },
    calendarDays() {
      const start = startOfWeek(startOfMonth(this.currentDate), { weekStartsOn: 0 });
      const end = endOfWeek(endOfMonth(this.currentDate), { weekStartsOn: 0 });
      return eachDayOfInterval({ start, end }).map(date => ({
        date: format(date, 'yyyy-MM-dd'),
        dayNumber: format(date, 'd'),
        isCurrentMonth: format(date, 'M') === format(this.currentDate, 'M'),
        isToday: format(date, 'yyyy-MM-dd') === format(new Date(), 'yyyy-MM-dd'),
      }));
    },
    entriesByDay() {
      return this.calendarEntries.reduce((acc, entry) => {
        const date = format(new Date(entry.start_datetime), 'yyyy-MM-dd');
        if (!acc[date]) acc[date] = [];
        acc[date].push(entry);
        return acc;
      }, {});
    },
    drawerTitle() {
        if (!this.selectedEntry) return '';
        const type = this.isEvent(this.selectedEntry) ? 'Evento' : 'Tarea';
        return `${type}: ${this.selectedEntry.title}`;
    }
  },
  methods: {
    format,
    formatDateTime(dateTimeString) {
          if (!dateTimeString) return '';
          return format(parseISO(dateTimeString), 'dd MMM yy, hh:mm a', { locale: es });
      },
    changeMonth(offset) { this.currentDate = addMonths(this.currentDate, offset); },
    createEntry(date) { router.get(route('calendar.create', { date })); },
    openDetailDrawer(entry) {
      this.selectedEntry = entry;
      this.isDetailDrawerOpen = true;
    },
    isEvent(entry) { return entry.entryable_type.includes('Event'); },
    isTask(entry) { return entry.entryable_type.includes('Task'); },
    getEntryClasses(entry) {
      if (this.isEvent(entry)) return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:hover:bg-blue-700 dark:text-blue-200 hover:bg-blue-300';
      if (this.isTask(entry)) {
        return entry.entryable?.status === 'Completada'
          ? 'bg-green-100 hover:bg-green-300 text-green-800 dark:bg-green-900 dark:hover:bg-green-700 dark:text-green-200 line-through opacity-70'
          : 'bg-yellow-100 hover:bg-yellow-300 text-yellow-800 dark:bg-yellow-900 dark:hover:bg-yellow-700 dark:text-yellow-200 hover:bg-yellow-200';
      }
      return 'bg-gray-100 text-gray-800';
    },
    getEntryIcon(entry) {
      if (this.isEvent(entry)) return 'fa-solid fa-users';
      if (this.isTask(entry)) {
        return entry.entryable?.status === 'Completada' ? 'fa-solid fa-check-double' : 'fa-solid fa-list-check';
      }
      return '';
    },
    getParticipantStatusClass(status) {
        const classes = {
            Pendiente: 'bg-yellow-200 text-yellow-800',
            Aceptado: 'bg-green-200 text-green-800',
            Rechazado: 'bg-red-200 text-red-800',
        };
        return classes[status] || 'bg-gray-200 text-gray-800';
    },
    isCurrentUserPending(event) {
        const participant = event.entryable.participants.find(p => p.id === this.authUserId);
        return participant && participant.pivot?.status === 'Pendiente';
    },
    completeTask(taskId) {
        router.patch(route('calendar.tasks.complete', taskId), {}, {
            preserveState: true,
            onSuccess: () => { 
                this.isDetailDrawerOpen = false;
                ElMessage.success('Tarea marcada como completada');
            }
        });
    },
    respondToInvitation(eventId, status) {
        router.patch(route('calendar.events.invitation', eventId), { status }, {
            preserveState: true,
            onSuccess: () => { 
                this.isDetailDrawerOpen = false; 
                ElMessage.success('Se ha mandado tu respuesta');
            }
        });
    },
    deleteEntry(entryId) {
        if (confirm('¿Estás seguro de que quieres eliminar esta entrada?')) {
            router.delete(route('calendar.entries.destroy', entryId), {
                preserveState: true,
                onSuccess: () => { 
                    this.isDetailDrawerOpen = false;
                    ElMessage.success('Recordatorio eliminado');
                }
            });
        }
    },
    handleResize() {
        if (window.innerWidth < 768) {
            this.drawerDirection = 'btt'; // bottom
            this.drawerSize = '60%';
        } else {
            this.drawerDirection = 'rtl'; // right
            this.drawerSize = '500px';
        }
    },
  },
  mounted() {
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.handleResize);
  },
};
</script>

