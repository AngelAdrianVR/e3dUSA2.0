<template>
    <!-- Contenedor principal con fondo degradado oscuro -->
    <div class="min-h-screen flex flex-col sm:justify-center items-center p-4 bg-gradient-to-br from-slate-800 via-gray-600 to-slate-300">
        <div>
            <slot name="logo" />
        </div>

        <!-- Tarjeta con efecto de cristal, borde sutil y sombra -->
        <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-gray-800/50 backdrop-blur-xl border border-gray-700/50 shadow-2xl overflow-hidden sm:rounded-2xl">
            
            <!-- Barra de carga animada -->
            <div v-if="loading" class="loading-bar-container">
                <div class="loading-bar"></div>
            </div>

            <slot />
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
.loading-bar-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px; /* Grosor de la barra */
    overflow: hidden;
}

.loading-bar {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background: linear-gradient(to right, #ee2626, #e75151, #60a5fa, #226feb); /* Gradiente de Rojo a Azul */
    background-size: 200% 100%;
    animation: loading-animation 1.5s linear infinite;
}

@keyframes loading-animation {
    0% {
        transform: translateX(-100%);
    }
    50% {
        transform: translateX(100%);
    }
    100% {
        transform: translateX(-100%);
    }
}
</style>
