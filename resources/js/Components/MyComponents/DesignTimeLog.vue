<template>
    <div class="mt-2 p-2">
        <!-- === Tarjeta de Comparación (Solo visible si es un retrabajo) === -->
        <div v-if="parentOrderDurationSeconds !== null" class="mb-2 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-slate-800 dark:to-indigo-900/30 rounded-xl border border-blue-100 dark:border-indigo-800/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm transition-all hover:shadow-md">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-white dark:bg-slate-800 rounded-lg text-indigo-500 dark:text-indigo-400 shadow-sm border border-gray-100 dark:border-gray-700">
                    <i class="fa-solid fa-code-compare text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-indigo-600/80 dark:text-indigo-400 font-bold uppercase tracking-wider mb-0.5">Referencia de Diseño Original</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Tiempo total invertido en la orden padre: 
                        <span class="font-bold text-indigo-700 dark:text-indigo-300 text-base ml-1">{{ formattedParentOrderDuration }}</span>
                    </p>
                </div>
            </div>
            <div class="text-left sm:text-right bg-white/60 dark:bg-slate-900/50 px-3 py-2 rounded-lg border border-white dark:border-gray-700 w-full sm:w-auto">
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Meta sugerida:</p>
                <p class="text-sm font-bold text-amber-600 dark:text-amber-500">
                    <i class="fa-solid fa-bolt text-xs mr-1"></i> Menor al 50% <br>
                    {{ parentOrderDurationSeconds > 0 ? `(${(Math.round(parentOrderDurationSeconds * 0.5) / 60).toFixed(1)} min, o menos)` : '(N/A)' }}
                </p>
            </div>
        </div>

        <!-- === TIEMPO INVERTIDO ACTUAL (Cronómetro en vivo) === -->
        <div v-if="startedAt" class="mb-2 bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full" :class="statusConfig.bgClass">
                    <i class="fa-solid fa-stopwatch text-2xl" :class="statusConfig.iconClass"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Tiempo de trabajo invertido para esta orden</p>
                    <div class="flex items-baseline gap-2">
                        <p class="text-3xl font-bold font-mono tracking-tight text-gray-800 dark:text-white">{{ formattedInvestedTime }}</p>
                        <span v-if="!finishedAt && !isPaused" class="text-xs text-sky-500 font-medium flex items-center">
                            <span class="relative flex h-2 w-2 mr-1.5">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-2 w-2 bg-sky-500"></span>
                            </span>
                            En curso
                        </span>
                    </div>
                </div>
            </div>
            <div class="text-right w-full sm:w-auto flex sm:justify-end">
                <el-tag :type="statusConfig.tagType" effect="dark" class="!px-4 !py-1.5 !text-sm !font-semibold rounded-lg">
                    <i :class="statusConfig.statusIcon" class="mr-1.5"></i>
                    {{ statusConfig.statusText }}
                </el-tag>
            </div>
        </div>

        <!-- Si no se ha iniciado -->
        <div v-if="!startedAt" class="text-gray-500 text-center italic py-10 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed dark:border-gray-700">
            <i class="fa-solid fa-stopwatch text-3xl mb-3 text-gray-300 dark:text-gray-600 block"></i>
            El temporizador aún no ha comenzado.
        </div>
        
        <!-- Línea de tiempo -->
        <div v-else class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 space-y-8 py-4">
            
            <!-- Nodo: Inicio -->
            <div class="relative pl-6">
                <span class="absolute -left-[11px] bg-green-500 text-white rounded-full w-5 h-5 flex items-center justify-center ring-4 ring-white dark:ring-slate-800 shadow-sm">
                    <i class="fa-solid fa-play text-[9px]"></i>
                </span>
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 leading-none">Trabajo Iniciado</h3>
                <p class="text-sm text-gray-500 mt-1">{{ formatDateTime(startedAt) }}</p>
            </div>

            <!-- Nodos: Pausas -->
            <div v-for="(pause, index) in pauses" :key="pause.id" class="relative pl-6 transition-all hover:-translate-y-1">
                <!-- Marcador de Pausa -->
                <span class="absolute -left-[11px] bg-amber-500 text-white rounded-full w-5 h-5 flex items-center justify-center ring-4 ring-white dark:ring-slate-800 shadow-sm">
                    <i class="fa-solid fa-pause text-[9px]"></i>
                </span>
                
                <!-- Tarjeta de Pausa -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm inline-block min-w-[280px]">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-amber-600 dark:text-amber-400 text-sm">Pausa #{{ index + 1 }}</h3>
                        <!-- Duración (solo si ya se reanudó) -->
                        <span v-if="pause.resumed_at" class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-1 rounded-full font-medium">
                            <i class="fa-regular fa-clock mr-1"></i> {{ getDuration(pause.paused_at, pause.resumed_at) }}
                        </span>
                        <span v-else class="flex h-2.5 w-2.5 relative" title="Pausa Activa">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                        </span>
                    </div>

                    <div class="space-y-2">
                        <p class="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-2">
                            <i class="fa-solid fa-circle-pause text-gray-400 w-4"></i>
                            <span class="font-medium">Pausado:</span> {{ formatDateTime(pause.paused_at) }}
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-2">
                            <i class="fa-solid fa-circle-play text-green-500 w-4"></i>
                            <span class="font-medium">Reanudado:</span>
                            <span v-if="pause.resumed_at" class="text-green-600 dark:text-green-400 font-medium">{{ formatDateTime(pause.resumed_at) }}</span>
                            <span v-else class="text-gray-400 italic">En pausa actualmente...</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Nodo: Fin -->
            <div v-if="finishedAt" class="relative pl-6">
                <span class="absolute -left-[11px] bg-blue-500 text-white rounded-full w-5 h-5 flex items-center justify-center ring-4 ring-white dark:ring-slate-800 shadow-sm">
                    <i class="fa-solid fa-flag-checkered text-[9px]"></i>
                </span>
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 leading-none">Trabajo Finalizado</h3>
                <p class="text-sm text-gray-500 mt-1">{{ formatDateTime(finishedAt) }}</p>
            </div>
            
            <!-- Nodo Activo/En proceso -->
            <div v-else-if="startedAt && !isPaused" class="relative pl-6">
                <span class="absolute -left-[11px] bg-sky-500 text-white rounded-full w-5 h-5 flex items-center justify-center ring-4 ring-white dark:ring-slate-800 shadow-sm">
                    <i class="fa-solid fa-spinner animate-spin text-[9px]"></i>
                </span>
                <h3 class="font-semibold text-sky-600 dark:text-sky-400 italic text-sm">En progreso...</h3>
            </div>
        </div>
    </div>
</template>

<script>
import { format, formatDistanceStrict } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    props: {
        startedAt: String,
        finishedAt: String,
        pauses: {
            type: Array,
            default: () => []
        },
        isPaused: Boolean,
        parentOrderDurationSeconds: {
            type: Number,
            default: null
        }
    },
    data() {
        return {
            currentTime: new Date(),
            timerInterval: null,
        }
    },
    computed: {
        currentInvestedSeconds() {
            if (!this.startedAt) return 0;

            const start = new Date(this.startedAt).getTime();
            const end = this.finishedAt ? new Date(this.finishedAt).getTime() : this.currentTime.getTime();

            let totalMs = end - start;

            // Restar pausas
            if (this.pauses && this.pauses.length > 0) {
                this.pauses.forEach(pause => {
                    if (!pause.paused_at) return;
                    const pauseStart = new Date(pause.paused_at).getTime();
                    const pauseEnd = pause.resumed_at ? new Date(pause.resumed_at).getTime() : end;
                    
                    if (pauseEnd > pauseStart) {
                        const actualPauseStart = Math.max(pauseStart, start);
                        const actualPauseEnd = Math.min(pauseEnd, end);
                        
                        if (actualPauseEnd > actualPauseStart) {
                            totalMs -= (actualPauseEnd - actualPauseStart);
                        }
                    }
                });
            }

            return Math.max(0, Math.floor(totalMs / 1000));
        },
        formattedInvestedTime() {
            const seconds = this.currentInvestedSeconds;
            const pad = (num) => String(num).padStart(2, '0');
            
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;

            if (h > 0) {
                return `${pad(h)}:${pad(m)}:${pad(s)}`;
            } else {
                return `${pad(m)}:${pad(s)}`;
            }
        },
        formattedParentOrderDuration() {
            if (this.parentOrderDurationSeconds === null) return 'N/A';
            const seconds = this.parentOrderDurationSeconds;
            
            if (seconds === 0) return '0s';
            if (seconds < 60) return 'Menos de 1 min';
            
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            
            let timeStr = [];
            if (h > 0) timeStr.push(`${h}h`);
            if (m > 0) timeStr.push(`${m}m`);
            if (s > 0 && h === 0) timeStr.push(`${s}s`);
            
            return timeStr.join(' ');
        },
        statusConfig() {
            if (this.finishedAt) {
                return {
                    bgClass: 'bg-green-100 dark:bg-green-900/30',
                    iconClass: 'text-green-600 dark:text-green-400',
                    tagType: 'success',
                    statusIcon: 'fa-solid fa-check-double',
                    statusText: 'Terminado'
                };
            } else if (this.isPaused) {
                return {
                    bgClass: 'bg-amber-100 dark:bg-amber-900/30',
                    iconClass: 'text-amber-600 dark:text-amber-400',
                    tagType: 'warning',
                    statusIcon: 'fa-solid fa-pause',
                    statusText: 'En Pausa'
                };
            } else if (this.startedAt) {
                return {
                    bgClass: 'bg-sky-100 dark:bg-sky-900/30',
                    iconClass: 'text-sky-600 dark:text-sky-400',
                    tagType: 'primary',
                    statusIcon: 'fa-solid fa-spinner fa-spin',
                    statusText: 'En Progreso'
                };
            }
            return {};
        }
    },
    mounted() {
        if (this.startedAt && !this.finishedAt && !this.isPaused) {
            this.startTimer();
        }
    },
    watch: {
        isPaused(newVal) {
            if (newVal) {
                this.stopTimer();
                this.currentTime = new Date(); // Update one last time
            } else if (this.startedAt && !this.finishedAt) {
                this.startTimer();
            }
        },
        finishedAt(newVal) {
            if (newVal) {
                this.stopTimer();
                this.currentTime = new Date(); // Freeze at finish
            }
        }
    },
    methods: {
        formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            return format(new Date(dateString), "d MMM, yyyy HH:mm 'hrs.'", { locale: es });
        },
        getDuration(start, end) {
            if (!start || !end) return '';
            return formatDistanceStrict(new Date(start), new Date(end), { locale: es });
        },
        startTimer() {
            this.stopTimer();
            this.currentTime = new Date();
            this.timerInterval = setInterval(() => {
                this.currentTime = new Date();
            }, 1000);
        },
        stopTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        }
    },
    beforeUnmount() {
        this.stopTimer();
    }
}
</script>