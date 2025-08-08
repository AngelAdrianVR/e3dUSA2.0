<script setup>
import { computed, onMounted, ref } from 'vue';

// Se definen las propiedades que el componente puede recibir
const props = defineProps({
    // 'v-model' para enlazar el valor del input
    modelValue: {
        type: [String, Number], // <-- Cambio clave: Acepta String o Number
        default: ''
    },
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
    // El ID ahora es opcional. Si no se provee, se genera a partir del label.
    id: {
        type: String,
        default: null,
    },
    // El tipo de input (text, password, number, etc.)
    type: {
        type: String,
        default: 'text',
    },
    // Prop para decidir si es un textarea
    isTextarea: {
        type: Boolean,
        default: false,
    },
    // Prop para habilitar el contador de caracteres
    withMaxLength: {
        type: Boolean,
        default: false,
    },
    // Prop para definir el máximo de caracteres
    maxLength: {
        type: Number,
        default: 255,
    },
});

// Se define el evento para que 'v-model' funcione correctamente
defineEmits(['update:modelValue']);

const elementRef = ref(null);

// ID computado: Usa el ID proporcionado o genera uno a partir del label.
const computedId = computed(() => {
    if (props.id) {
        return props.id;
    }
    // Genera un ID amigable para el 'for' del label
    // Ejemplo: "Nombre de Usuario" -> "nombre-de-usuario"
    if (props.label) {
        return props.label.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    }
    // Fallback para un ID único si no hay id ni label
    return `text-input-${Math.random().toString(36).substring(2, 9)}`;
});


// Mantiene la funcionalidad de autofocus
onMounted(() => {
    if (elementRef.value.hasAttribute('autofocus')) {
        elementRef.value.focus();
    }
});

// Mantiene la capacidad de llamar al método .focus() desde el componente padre
defineExpose({ focus: () => elementRef.value.focus() });

// Clases base compartidas para mantener un estilo consistente
const baseClasses = computed(() => [
    'block w-full px-3 py-1 rounded-md shadow-sm',
    'bg-gray-50 dark:bg-gray-700',
    'border transition-colors duration-300',
    'placeholder-gray-400 dark:placeholder-gray-500',
    'focus:outline-none focus:ring-2',
    'text-gray-800 dark:text-gray-100',
    'caret-blue-400',
    props.error
        ? 'border-red-500 dark:border-red-600 focus:border-red-500 focus:ring-red-500/50'
        : 'border-gray-300 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500/50',
]);
</script>

<template>
    <div class="w-full">
        <!-- El label se muestra solo si la prop 'label' tiene un valor -->
        <label 
            v-if="label" 
            :for="computedId" 
            class="block ml-2 mb-1 text-sm font-medium text-gray-500 dark:text-gray-200 transition-colors duration-300"
            :class="{ 'text-red-600 dark:text-red-500': error }"
        >
            {{ label }}
        </label>
        
        <!-- Renderiza un <textarea> si isTextarea es true -->
        <textarea
            v-if="isTextarea"
            :id="computedId"
            ref="elementRef"
            :class="baseClasses"
            :value="modelValue"
            :maxlength="withMaxLength ? maxLength : undefined"
            @input="$emit('update:modelValue', $event.target.value)"
            rows="4"
        />
        
        <!-- Renderiza un <input> en caso contrario -->
        <input
            v-else
            :id="computedId"
            :type="type"
            ref="elementRef"
            :class="baseClasses"
            :value="modelValue"
            :maxlength="withMaxLength ? maxLength : undefined"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        
        <!-- Contenedor para el mensaje de error y el contador de caracteres -->
        <div class="flex justify-between items-center min-h-[1rem]">
            <!-- Mensaje de error -->
            <p v-if="error" class="text-xs text-red-600 dark:text-red-500 ml-4">
                campo obligatorio
            </p>
            
            <!-- Contador de caracteres -->
            <span 
                v-if="withMaxLength" 
                class="text-xs ml-auto"
                :class="[ (modelValue?.length || 0) > maxLength ? 'text-red-500' : 'text-gray-500 dark:text-gray-300' ]"
            >
                {{ modelValue?.length || 0 }} / {{ maxLength }}
            </span>
        </div>
    </div>
</template>
