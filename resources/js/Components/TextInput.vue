<script setup>
import { onMounted, ref } from 'vue';

// Se definen las propiedades que el componente puede recibir
const props = defineProps({
    // 'v-model' para enlazar el valor del input
    modelValue: String,
    // El texto para el label. Si no se provee, no se muestra.
    label: {
        type: String,
        default: '',
    },
    // El mensaje de error. Si existe, activa el estado de error.
    error: {
        type: String,
        default: '',
    },
    // Un ID único es importante para la accesibilidad (conectar label con input)
    id: {
        type: String,
        required: true,
    },
    // Permite pasar cualquier otro atributo (como type, autocomplete, etc.) al input
    type: {
        type: String,
        default: 'text',
    }
});

// Se define el evento para que 'v-model' funcione correctamente
defineEmits(['update:modelValue']);

const input = ref(null);

// Mantiene la funcionalidad de autofocus
onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

// Mantiene la capacidad de llamar al método .focus() desde el componente padre
defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <!-- Contenedor principal del campo -->
    <div class="w-full">
        <!-- El label se muestra solo si la prop 'label' tiene un valor -->
        <label 
            v-if="label" 
            :for="id" 
            class="block ml-2 mb-1 text-sm font-medium text-gray-500 dark:text-gray-200 transition-colors duration-300"
            :class="{ 'text-red-600 dark:text-red-500': error }"
        >
            {{ label }}
        </label>
        
        <!-- Input con clases dinámicas para estilos y errores -->
        <input
            :id="id"
            :type="type"
            ref="input"
            class="
                block w-full px-3 py-2 rounded-md shadow-sm 
                bg-gray-50 dark:bg-gray-700 
                border transition-colors duration-300
                placeholder-gray-400 dark:placeholder-gray-500
                focus:outline-none focus:ring-2
            "
            :class="{
                'border-gray-300 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500/50': !error,
                'border-red-500 dark:border-red-600 focus:border-red-500 focus:ring-red-500/50': error,
            }"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
        >

        <!-- El mensaje de error se muestra solo si la prop 'error' tiene un valor -->
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-500">
            {{ error }}
        </p>
    </div>
</template>

