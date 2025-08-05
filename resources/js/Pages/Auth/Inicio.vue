<template>
  <div class="flex items-center justify-center min-h-screen bg-gradient-to-br from-slate-800 via-gray-600 to-slate-300">
    <div class="relative w-48 h-48 md:w-56 md:h-56 flex items-center justify-center">
      
      <div class="spinning-border absolute inset-0 rounded-full border-8 border-transparent"></div>

      <figure class="w-40 h-32 md:w-48 md:h-36 p-2 bg-white dark:bg-gray-800 rounded-full shadow-lg flex items-center justify-center">
        <img class="w-full h-full object-contain rounded-full" 
             src="/images/isoLogoEmblems.png" 
             alt="Logo de la Empresa" />
      </figure>

    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

onMounted(() => {
  setTimeout(() => {
    router.visit(route('login'));
  }, 2000);
});
</script>

<style scoped>
.spinning-border {
  /* Aplica la animación 'loader' definida abajo */
  animation: loader 2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes loader {
  0% { 
    transform: rotate(0deg);
    border-top-color: #3b82f6; /* Azul */
    border-bottom-color: #ef4444; /* Rojo */
  }
  50% {
    border-top-color: #ef4444; /* Rojo */
    border-bottom-color: #3b82f6; /* Azul */
  }
  100% {
    transform: rotate(360deg);
    border-top-color: #3b82f6; /* Azul */
    border-bottom-color: #ef4444; /* Rojo */
  }
}
</style>