<template>
<Head title="Bienvenido" />
  <div class="loader-main-container">
      <!-- Elementos de fondo animados -->
      <div class="bg-shape1"></div>
      <div class="bg-shape2"></div>

      <!-- Contenedor del logo y la animación de carga -->
      <div class="loader-content">
          <!-- Anillos de la animación del escáner -->
          <div class="scanner-ring" style="animation-delay: 0s;"></div>
          <div class="scanner-ring" style="animation-delay: 1s;"></div>
          <div class="scanner-ring" style="animation-delay: 2s;"></div>

          <!-- Logo con animación de pulso -->
          <figure class="logo-figure">
              <img class="logo-image"
                   src="/images/isoLogoEmblems.png"
                   alt="Logo de la Empresa" />
          </figure>
      </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';

onMounted(() => {
  setTimeout(() => {
    router.visit(route('login'));
  }, 2000); // Aumentado ligeramente para apreciar la animación
});
</script>

<style scoped>
/* Contenedor principal */
.loader-main-container {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: #0d1117; /* Mismo fondo oscuro que el login */
    position: relative;
    overflow: hidden;
}

/* Contenedor del contenido del loader */
.loader-content {
    position: relative;
    width: 224px; /* md:w-56 */
    height: 224px; /* md:h-56 */
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

/* Figura y logo */
.logo-figure {
    width: 192px;  /* w-48 */
    height: 144px; /* h-36 */
    padding: 8px; /* p-2 */
    background-color: rgba(229, 231, 235, 0.1); /* bg-gray-200/10 */
    border-radius: 9999px; /* rounded-full */
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse-logo 3s infinite ease-in-out;
}

.logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 9999px; /* rounded-full */
}

/* Animación del escáner (anillos expansivos) */
.scanner-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 2px solid rgba(16, 92, 212, 0.87); /* Color azul del login, semitransparente */
    animation: scan 3s infinite cubic-bezier(0.2, 0.6, 0.2, 1);
    opacity: 0;
}

/* Formas de fondo y sus animaciones */
.bg-shape1, .bg-shape2 {
    position: absolute;
    border-radius: 9999px;
    filter: blur(120px);
    opacity: 0.25;
}

.bg-shape1 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(29, 78, 216, 1) 0%, rgba(30, 64, 175, 0) 70%);
    top: -100px;
    left: -100px;
    animation: rotate-shapes 30s linear infinite alternate;
}

.bg-shape2 {
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(147, 51, 234, 1) 0%, rgba(126, 34, 206, 0) 70%);
    bottom: -150px;
    right: -150px;
    animation: rotate-shapes 40s linear infinite alternate-reverse;
}


/* Definiciones de Keyframes */
@keyframes scan {
    0% {
        transform: scale(0.6);
        opacity: 0;
    }
    50% {
        opacity: 1;
    }
    100% {
        transform: scale(1.8);
        opacity: 0;
    }
}

@keyframes pulse-logo {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 10px 25px rgba(59, 130, 246, 0.2);
    }
    50% {
        transform: scale(1.03);
        box-shadow: 0 15px 35px rgba(59, 130, 246, 0.3);
    }
}

@keyframes rotate-shapes {
    from { transform: rotate(0deg) scale(1); }
    to { transform: rotate(360deg) scale(1.2); }
}
</style>
