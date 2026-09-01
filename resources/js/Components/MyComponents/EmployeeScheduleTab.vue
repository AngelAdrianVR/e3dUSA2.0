<template>
    <div>
        <!-- Pestaña del horario semanal del empleado -->
        <button type="button" @click="isOpen = !isOpen"
            class="flex items-center gap-1.5 px-2.5 h-7 rounded-md text-xs font-semibold border transition-all"
            :class="isOpen
                ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-200 border-indigo-200 dark:border-indigo-800'
                : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'">
            <i class="fa-solid fa-clock text-[11px]"></i>
            <span>Horario semanal</span>
            <i class="fa-solid fa-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': isOpen }"></i>
        </button>

        <!-- Panel con el horario semanal únicamente de este empleado -->
        <transition name="el-fade-in">
            <div v-if="isOpen"
                class="mt-2 rounded-lg border border-gray-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-900">
                <table class="w-full text-[11px] dark:text-gray-300">
                    <thead class="bg-gray-100 dark:bg-slate-800/60 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-1.5 text-left">Día</th>
                            <th class="px-3 py-1.5 text-center">Entrada</th>
                            <th class="px-3 py-1.5 text-center">Salida</th>
                            <th class="px-3 py-1.5 text-center">Break</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in (employeeData.employee.work_days || [])" :key="day.day"
                            class="border-t dark:border-slate-700/50">
                            <td class="px-3 py-1.5 font-semibold">
                                {{ day.day }}
                                <span v-if="!day.works"
                                    class="ml-1 text-[10px] text-gray-400 dark:text-gray-500 italic">Descanso</span>
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <span v-if="day.works">{{ formatTime(day.start_time) }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <span v-if="day.works">{{ formatTime(day.end_time) }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <span v-if="day.works">{{ day.break_minutes }} min</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    // Datos de nómina de UN solo empleado (employee + week_details + summary)
    employeeData: { type: Object, required: true },
});

const isOpen = ref(false);

// Formatea "HH:MM:SS" a hora de 12 horas (ignora los segundos)
const formatTime = (timeString) => {
    if (!timeString) return '--:--';
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes));
    return date.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
};
</script>
