<template>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg h-full transition-colors duration-300 flex flex-col">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-1">Calendario</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ events.length }} recordatorios</p>
            </div>
            <div class="text-center">
                <p class="text-4xl font-extrabold text-indigo-500 dark:text-indigo-400">{{ day }}</p>
                <p class="text-md font-semibold text-gray-600 dark:text-gray-300 capitalize">{{ month }}</p>
            </div>
        </div>
        <div class="space-y-4 flex-grow">
             <div v-for="event in events" :key="event.id" class="flex items-start">
                <div class="flex-shrink-0 h-8 w-8 rounded-full flex items-center justify-center mr-4" :class="getEventColor(event.entryable_type).bg">
                    <svg class="h-5 w-5" :class="getEventColor(event.entryable_type).text" fill="none" viewBox="0 0 24 24" stroke="currentColor" v-html="getIcon(event.entryable_type)"></svg>
                </div>
                <div class="flex-grow">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 capitalize">{{ formatType(event.entryable_type) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ event.title }}</p>
                        </div>
                        <p v-if="!event.is_full_day" class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ formatTime(event.start_datetime) }}</p>
                    </div>
                </div>
            </div>
             <div v-if="!events.length" class="text-center py-8">
                <p class="text-gray-500 dark:text-gray-400">No hay eventos próximos.</p>
            </div>
        </div>
        <button class="mt-auto w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-indigo-700 transition-all duration-300">
            Agendar
        </button>
    </div>
</template>

<script>
export default {
    name: 'CalendarWidget',
    props: {
        events: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            date: new Date(),
        }
    },
    computed: {
        day() {
            return this.date.getDate();
        },
        month() {
            return this.date.toLocaleString('es-MX', { month: 'long' });
        }
    },
    methods: {
        getIcon(type) {
            const icons = {
                'App\\Models\\Event': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
                'App\\Models\\Task': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                'default': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>'
            };
            return icons[type] || icons['default'];
        },
        getEventColor(type) {
             const colors = {
                'App\\Models\\Event': { text: 'text-green-400', bg: 'bg-green-400/10' },
                'App\\Models\\Task': { text: 'text-blue-400', bg: 'bg-blue-400/10' },
                'default': { text: 'text-yellow-400', bg: 'bg-yellow-400/10' }
            };
            return colors[type] || colors['default'];
        },
        formatType(type) {
            if (type.includes('Event')) return 'Evento';
            if (type.includes('Task')) return 'Tarea';
            return 'Recordatorio';
        },
        formatTime(datetime) {
            return new Date(datetime).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: true });
        }
    }
}
</script>
