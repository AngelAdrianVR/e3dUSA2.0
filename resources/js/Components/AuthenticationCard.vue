<template>
    <div class="main-container">
        <!-- Elementos de fondo animados -->
        <div class="bg-shape1"></div>
        <div class="bg-shape2"></div>

        <!-- Contenedor de contenido para centrar -->
        <div class="content-container">
            <!-- Logo con animación aplicada a través de un contenedor -->
            <div class="logo-wrapper">
                <slot name="logo" />
            </div>

            <!-- Tarjeta del formulario con animación aplicada -->
            <div class="form-card-wrapper">
                <div class="w-full sm:max-w-md px-8 py-10 bg-black/30 backdrop-blur-xl border border-gray-700/60 shadow-2xl shadow-blue-500/10 overflow-hidden sm:rounded-2xl relative">
                    <!-- Barra de carga (mantenida del original) -->
                    <div v-if="loading" class="loading-bar-container">
                        <div class="loading-bar"></div>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        loading: {
            type: Boolean,
            default: false,
        },
    }
}
</script>

<style scoped>
/* Configuración del contenedor principal */
.main-container {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1rem;
    background-color: #0d1117; /* Fondo oscuro */
    overflow: hidden;
    position: relative;
}

/* Centrado de contenido */
.content-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    z-index: 10;
}

/* Animaciones de Keyframes */
@keyframes slideInFromLeft {
    0% {
        transform: translateX(-150%);
        opacity: 0;
    }
    100% {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeInSmooth {
    0% {
        opacity: 0;
        transform: translateY(30px) scale(0.98);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Clases de contenedores de animación */
.logo-wrapper {
    margin-bottom: 2.5rem;
    animation: slideInFromLeft 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

.form-card-wrapper {
    width: 100%;
    max-width: 28rem; /* sm:max-w-md */
    opacity: 0; /* Comienza oculto */
    animation: fadeInSmooth 0.7s cubic-bezier(0.390, 0.575, 0.565, 1.000) 0.5s forwards; /* Retraso de 0.5s */
}

/* Formas de fondo y animaciones */
@keyframes rotateShapes {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.bg-shape1, .bg-shape2 {
    position: absolute;
    border-radius: 9999px;
    filter: blur(100px);
    opacity: 0.3;
}

.bg-shape1 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(29, 78, 216, 1) 0%, rgba(30, 64, 175, 0) 70%);
    top: -100px;
    left: -100px;
    animation: rotateShapes 30s linear infinite alternate;
}

.bg-shape2 {
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(147, 51, 234, 1) 0%, rgba(126, 34, 206, 0) 70%);
    bottom: -150px;
    right: -150px;
    animation: rotateShapes 40s linear infinite alternate-reverse;
}


/* Estilos de la barra de carga (mantenidos del original, con pequeños ajustes) */
.loading-bar-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    overflow: hidden;
}

.loading-bar {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background: linear-gradient(90deg, #3b82f6, #9333ea, #ec4899, #3b82f6);
    background-size: 200% 100%;
    animation: loading-animation 1.5s linear infinite;
}

@keyframes loading-animation {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
