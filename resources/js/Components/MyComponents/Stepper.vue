<template>
    <div class="w-full bg-white dark:bg-slate-800/50 rounded-xl shadow-lg p-3 mb-3 font-sans">
        <h2 class="font-bold text-lg dark:text-white mb-4 text-center sm:text-left">Línea de Proceso</h2>

        <div class="flex items-center w-full">
            <!-- Usamos un template para iterar y manejar la lógica de las líneas conectoras -->
            <template v-for="(step, index) in steps" :key="step">

                <!-- Contenedor del Step (Círculo + Label) -->
                <div class="flex flex-col items-center justify-center">
                    <!-- Círculo del Step -->
                    <div
                        class="relative flex items-center justify-center rounded-full transition-all duration-500 ease-in-out"
                        :class="getNodeClasses(index)"
                    >
                        <!-- Efecto Sonar para el paso en progreso -->
                        <div v-if="index === activeStepIndex" class="absolute h-full w-full rounded-full animate-sonar"></div>

                        <!-- Icono de Check para pasos completados -->
                        <svg v-if="index <= completedStepIndex" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" class="animate-checkmark"></path>
                        </svg>
                        <!-- Número del Step para pasos activos o pendientes -->
                        <span v-else class="font-bold text-sm">{{ index + 1 }}</span>
                    </div>

                    <!-- Label del Step -->
                    <p class="text-xs text-center mt-3" :class="getLabelClasses(index)">
                        {{ step }}
                    </p>
                </div>

                <!-- Línea Conectora (no se muestra después del último paso) -->
                <div v-if="index < steps.length - 1" class="flex-1 h-1 mx-2 bg-gray-200 dark:bg-slate-700 rounded-full">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-sky-400 to-secondary transition-all duration-700 ease-out"
                        :style="{ width: getLineWidth(index) }"
                    ></div>
                </div>

            </template>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
        }
    },
    props:{
        currentStatus: {
            type: String,
            required: true
        },
        steps: {
            type: Array,
            required: true
        },
    },
    computed: {
        // Devuelve el índice del último paso que se ha completado.
        completedStepIndex() {
            return this.steps.indexOf(this.currentStatus);
        },
        // Devuelve el índice del paso que está actualmente "en progreso" (el que debe animarse).
        activeStepIndex() {
            // Si el último paso ya se completó, no hay un siguiente paso activo.
            if (this.completedStepIndex === this.steps.length - 1) {
                return -1; // Un valor que nunca coincidirá con un índice.
            }
            // El paso activo es el siguiente al último completado.
            return this.completedStepIndex + 1;
        }
    },
    methods:{
        getNodeClasses(index) {
            const classes = [];
            if (index <= this.completedStepIndex) {
                // Estilo para pasos completados
                classes.push('w-8 h-8 bg-gradient-to-br from-sky-400 to-secondary shadow-md');
            } else if (index === this.activeStepIndex) {
                // Estilo para el paso actual en progreso (con animación)
                classes.push('w-10 h-10 bg-white dark:bg-slate-800 border-2 border-secondary dark:border-sky-400 text-secondary dark:text-sky-400 shadow-lg');
            } else {
                // Estilo para pasos pendientes
                classes.push('w-8 h-8 bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-gray-400');
            }
            return classes.join(' ');
        },
        getLabelClasses(index) {
            // La etiqueta se resalta si el paso está activo o ya se completó.
            if (index <= this.activeStepIndex) {
                return 'font-semibold text-gray-700 dark:text-gray-200';
            }
            return 'text-gray-400 dark:text-gray-500';
        },
        getLineWidth(index) {
            // La línea se rellena si el paso actual ya se completó.
            return this.completedStepIndex >= index ? '100%' : '0%';
        }
    }
}
</script>

<style scoped>
/* Animación para el efecto de sonar/pulso en el paso actual */
@keyframes sonar-pulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(29, 78, 216, 0.7);
    }
    70% {
        transform: scale(1.5);
        box-shadow: 0 0 0 10px rgba(29, 78, 216, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(29, 78, 216, 0);
    }
}

/* Aplicamos la animación al pseudo-elemento para no afectar el tamaño del círculo */
.dark .animate-sonar {
    border-color: #38bdf8; /* sky-400 for dark mode */
}
.animate-sonar {
    border: 2px solid #3b82f6; /* secondary-600 */
    animation: sonar-pulse 2s infinite cubic-bezier(0.66, 0, 0, 1);
}

/* Animación para dibujar el checkmark */
@keyframes draw {
    to {
        stroke-dashoffset: 0;
    }
}
.animate-checkmark {
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
    animation: draw 0.5s ease-out forwards;
    animation-delay: 0.3s; /* Pequeño retraso para un efecto más agradable */
}

/* Usamos una fuente más moderna si está disponible */
.font-sans {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
}
</style>

