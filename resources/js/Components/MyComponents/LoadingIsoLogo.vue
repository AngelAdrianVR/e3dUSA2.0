<template>
  <div class="flex items-center justify-center">
    <div class="relative w-12 h-12">
      <!-- Contorno giratorio -->
      <div class="absolute inset-0 rounded-full border-4 border-t-transparent animate-spin border-gradient"></div>
      <!-- Imagen dentro -->
      <img :src="imgSrc" alt="logo" class="absolute inset-0 m-auto size-7 object-contain" />
    </div>
  </div>
</template>

<script>
export default {
name: "LoadingIsoLogo",
props: {
    imgSrc: {
    type: String,
    default: '/images/isoLogoEmblems.png'
    }
}
};
</script>

<style scoped>
/* Gradiente animado de azul a rojo */
@keyframes colorChange {
  0% { border-color: #3b82f6; } /* azul */
  50% { border-color: #ef4444; } /* rojo */
  100% { border-color: #3b82f6; }
}

.border-gradient {
  animation: spin 1s linear infinite, colorChange 2s ease-in-out infinite;
  border-top-color: transparent !important;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
