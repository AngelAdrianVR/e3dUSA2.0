<template>
    <div class="text-center p-4 rounded-xl shadow-sm">
        <svg class="ghost-icon mx-auto" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="100" height="100">
            <ellipse cx="60" cy="110" rx="30" ry="5" fill="rgba(0, 0, 0, 0.1)" />
            <path d="M30,100 C30,70 90,70 90,100 L90,60 C90,30 60,10 60,10 C60,10 30,30 30,60 Z" fill="#E2E8F0"/>
            <circle cx="50" cy="55" r="5" fill="#4A5568" />
            <circle cx="70" cy="55" r="5" fill="#4A5568" />
        </svg>

        <p class="mt-4 text-lg font-medium text-gray-600 dark:text-gray-400">
            {{ label }}
        </p>
        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
            No hay nada por aquí...
        </p>
    </div>
</template>

<script>
export default {
    // Se mantiene en Options API como solicitaste
    name: 'EmptyState',
    props: {
        label: {
            type: String,
            default: '¡Oh, no! Está vacío'
        }
    }
}
</script>

<style scoped>
/* Scoped asegura que los estilos solo aplican a este componente */

.ghost-icon {
    /* Aplicamos la animación 'float' al SVG */
    animation: float 3s ease-in-out infinite;
}

/* Definición de la animación */
@keyframes float {
    0% {
        transform: translateY(0px);
    }
    50% {
        /* Mueve el ícono 10px hacia arriba */
        transform: translateY(-10px);
    }
    100% {
        transform: translateY(0px);
    }
}
</style>