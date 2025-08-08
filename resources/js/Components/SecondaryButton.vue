<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
    // Nueva propiedad para controlar el estado de carga
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button 
        :type="type" 
        :disabled="loading"
        class="
            relative inline-flex items-center justify-center px-4 py-2 overflow-hidden
            bg-secondary border border-transparent rounded-xl 
            font-semibold text-sm text-white tracking-widest 
            transition-all duration-300 ease-in-out
            hover:bg-blue-800 hover:shadow-lg
            focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-500/50
            active:bg-blue-800 active:scale-95
            disabled:opacity-50 disabled:cursor-not-allowed
        ">
        <!-- Contenedor para el texto del botón -->
        <span 
            class="transition-transform duration-300 ease-in-out"
            :class="{ '-translate-x-3': loading }"
        >
            <slot />
        </span>

        <!-- Contenedor para el spinner, posicionado absolutamente a la derecha -->
        <div 
            class="
                absolute right-4 top-1/2 -translate-y-1/2 
                transition-opacity duration-300 ease-in-out
            "
            :class="loading ? 'opacity-100' : 'opacity-0'"
        >
            <!-- SVG del spinner -->
            <svg class="animate-spin size-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </button>
</template>
