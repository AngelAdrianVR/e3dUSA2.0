<template>
  <!--
    Este es un botón unificado que maneja ambas acciones (retroceder o ir a una ruta).
    - Usa clases de Tailwind para un estilo moderno y responsivo.
    - Incluye transiciones suaves para el color de fondo.
    - Tiene estados de hover y focus bien definidos para una mejor accesibilidad y UX.
    - El icono de flecha es un SVG en línea, eliminando dependencias externas como Font Awesome.
  -->
  <button
    @click="handleClick"
    type="button"
    class="flex items-center justify-center w-10 h-10 rounded-full transition-colors duration-200 ease-in-out bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-slate-700 dark:text-gray-200 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-slate-900"
    aria-label="Volver a la página anterior"
  >
    <svg
      xmlns="http://www.w3.org/2000/svg"
      class="w-6 h-6"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      stroke-width="2.5"
    >
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
    </svg>
  </button>
</template>

<script setup>
import { defineProps } from 'vue';
// En proyectos modernos de Inertia.js, es común importar el router así.
// Si usas una configuración global, este import puede no ser necesario.
import { router } from '@inertiajs/vue3';

// --- Definición de Props ---
// Se define la prop 'route' que determinará el comportamiento del botón.
const props = defineProps({
  route: {
    type: String,
    default: null,
  },
});

// --- Métodos ---

/**
 * Maneja el evento de clic en el botón.
 * Decide si navegar hacia atrás en el historial del navegador
 * o visitar una ruta específica de Inertia.
 */
const handleClick = () => {
  if (props.route) {
    // Si se proporciona una ruta, visita esa ruta usando Inertia.
    // La función global 'route()' es estándar en setups de Laravel + Inertia.
    router.visit(route(props.route));
  } else {
    // Si no hay ruta, simplemente retrocede en el historial del navegador.
    window.history.back();
  }
};
</script>
