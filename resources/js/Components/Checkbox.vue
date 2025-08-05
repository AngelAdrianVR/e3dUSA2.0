<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        default: false,
    },
    value: {
        type: String,
        default: null,
    },
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },
    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <!-- Usamos un label como contenedor para mejorar la accesibilidad -->
    <label class="relative inline-flex items-center cursor-pointer select-none">
        <!-- Input nativo oculto. El 'peer' es clave para que Tailwind funcione -->
        <input
            v-model="proxyChecked"
            type="checkbox"
            :value="value"
            class="sr-only peer"
        >
        <!-- Representación visual del checkbox -->
        <div
            class="
                size-5 flex items-center justify-center 
                border-2 border-gray-500 rounded 
                transition-all duration-300
                peer-checked:bg-red-600 peer-checked:border-red-600
            "
        >
            <!-- El 'check' (marca de verificación) -->
            <svg 
                class="w-3 h-3 text-white transform scale-0 transition-transform duration-200 ease-in-out"
                :class="{ 'scale-100': proxyChecked }"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>

        <!-- Elemento para la animación de pulso, se activa con 'peer-checked' -->
        <div class="pulse-effect absolute w-full h-full rounded"></div>
    </label>
</template>

<style scoped>
/*
  El 'peer' en el input nos permite aplicar estilos a elementos hermanos
  cuando el input está ':checked'. Aquí lo usamos para activar la animación.
*/
.peer:checked ~ .pulse-effect {
    animation: pulse 0.6s ease-in-out;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); /* Rojo-500 con opacidad */
    }
    70% {
        transform: scale(1.5);
        box-shadow: 0 0 10px 15px rgba(239, 68, 68, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}
</style>
