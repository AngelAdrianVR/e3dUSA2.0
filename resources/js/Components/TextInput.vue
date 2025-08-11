<script setup>
import { computed, onMounted, ref, useSlots } from 'vue';

// Se definen las propiedades que el componente puede recibir
const props = defineProps({
    // 'v-model' para enlazar el valor del input
    modelValue: {
        type: [String, Number],
        default: ''
    },
    // Prop para el placeholder
    placeholder: {
        type: String,
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
    // El tipo de input (text, password, number, numeric-stepper, etc.)
    type: {
        type: String,
        default: 'text',
    },
    // NUEVO: Prop para activar el formato de número y restringir a solo números.
    formatAsNumber: {
        type: Boolean,
        default: false,
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
    // Props para el stepper numérico
    min: {
        type: Number,
        default: 0,
    },
    max: {
        type: Number,
        default: 9999,
    },
    step: {
        type: Number,
        default: 1,
    }
});

// Se define el evento para que 'v-model' funcione correctamente
const emit = defineEmits(['update:modelValue']);

const elementRef = ref(null);
const slots = useSlots();

// ID computado: Usa el ID proporcionado o genera uno a partir del label.
const computedId = computed(() => {
    if (props.id) return props.id;
    if (props.label) return props.label.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    return `text-input-${Math.random().toString(36).substring(2, 9)}`;
});

// Formatea el valor para la vista si la prop 'formatAsNumber' está activa.
const formattedValue = computed(() => {
    // Si no se debe formatear o el valor está vacío, se devuelve tal cual.
    if (!props.formatAsNumber || props.modelValue === '' || props.modelValue === null) {
        return props.modelValue;
    }

    const stringValue = String(props.modelValue);

    // Si el valor termina en un punto (ej. "36."), lo devolvemos
    // inmediatamente sin formatear para que el punto no desaparezca al escribir.
    if (stringValue.endsWith('.')) {
        return stringValue;
    }

    // Si es un número válido, procedemos a formatear solo la parte entera.
    if (!isNaN(Number(stringValue))) {
        const [integerPart, decimalPart] = stringValue.split('.');
        
        // Damos formato de miles a la parte entera.
        const formattedIntegerPart = Number(integerPart).toLocaleString('es-MX');
        
        // Si había una parte decimal, la volvemos a unir.
        return decimalPart !== undefined 
            ? `${formattedIntegerPart}.${decimalPart}` 
            : formattedIntegerPart;
    }

    // Como último recurso, devolvemos el valor original.
    return props.modelValue;
});

// Maneja el input del usuario para los campos numéricos.
const handleInput = (event) => {
    let rawValue = event.target.value;

    if (props.formatAsNumber) {
        // 1. Limpiamos el valor de todo lo que no sea un número o un punto.
        //    Esto elimina las comas de miles que agrega el formateo (ej. "1,234" -> "1234").
        let numericString = rawValue.replace(/[^0-9.]/g, '');

        // 2. Nos aseguramos de que solo haya un punto decimal.
        const parts = numericString.split('.');
        if (parts.length > 2) {
            // Si el usuario intenta poner un segundo punto, lo ignoramos
            // reconstruyendo el string con el primer punto.
            numericString = parts[0] + '.' + parts.slice(1).join('');
        }
        emit('update:modelValue', numericString);

    } else if (props.type === 'numeric-stepper') {
        // La lógica para el stepper se mantiene, solo acepta enteros.
        let numericValue = rawValue.replace(/[^0-9]/g, '');
        emit('update:modelValue', numericValue);
    }
    else {
        // Para cualquier otro tipo de input, se acepta el valor tal cual.
        emit('update:modelValue', rawValue);
    }
};

// Funciones para el stepper numérico
const increment = () => {
    let currentValue = Number(props.modelValue);
    if (isNaN(currentValue)) currentValue = 0;
    const newValue = Math.min(props.max, currentValue + props.step);
    emit('update:modelValue', newValue);
};

const decrement = () => {
    let currentValue = Number(props.modelValue);
    if (isNaN(currentValue)) currentValue = 0;
    const newValue = Math.max(props.min, currentValue - props.step);
    emit('update:modelValue', newValue);
};

// NUEVO: Valida y ajusta el valor del stepper cuando el usuario sale del campo.
const handleStepperBlur = () => {
    let value = Number(props.modelValue);
    if (isNaN(value) || props.modelValue === '') {
        value = props.min; // Si está vacío o no es un número, lo establece al mínimo.
    }
    const clampedValue = Math.max(props.min, Math.min(props.max, value));
    if (clampedValue !== props.modelValue) {
        emit('update:modelValue', clampedValue);
    }
};

// Mantiene la funcionalidad de autofocus
onMounted(() => {
    if (elementRef.value && elementRef.value.hasAttribute('autofocus')) {
        elementRef.value.focus();
    }
});

// Mantiene la capacidad de llamar al método .focus() desde el componente padre
defineExpose({ focus: () => elementRef.value?.focus() });

// Clases base compartidas para mantener un estilo consistente
const baseClasses = computed(() => [
    'block w-full py-2 rounded-md shadow-sm',
    'bg-gray-50 dark:bg-gray-700',
    'border transition-colors duration-300',
    'placeholder-gray-400 dark:placeholder-gray-500',
    'focus:outline-none focus:ring-2',
    'text-gray-800 dark:text-gray-100',
    'caret-blue-400',
    slots['icon-left'] ? 'pl-9' : 'px-3',
    props.error
        ? 'border-red-500 dark:border-red-600 focus:border-red-500 focus:ring-red-500/50'
        : 'border-gray-300 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500/50',
]);

// NUEVO: Clases para el contenedor del stepper, incluyendo el estado de error.
const stepperClasses = computed(() => [
    'flex items-center justify-between bg-gray-100 dark:bg-gray-700 rounded-full p-1 shadow-inner',
    'border-2 transition-colors duration-300',
    props.error
        ? 'border-red-500 dark:border-red-600'
        : 'border-transparent'
]);
</script>

<template>
    <div class="w-full">
        <label 
            v-if="label" 
            :for="computedId" 
            class="block ml-2 mb-1 text-sm font-medium text-gray-500 dark:text-gray-200 transition-colors duration-300"
            :class="{ 'text-red-600 dark:text-red-500': error }"
        >
            {{ label }}
        </label>
        
        <div class="relative">
            <div v-if="slots['icon-left']" class="absolute inset-y-0 left-0 pl-3 pr-2 my-2 border-r border-gray-300 dark:border-gray-400 flex items-center pointer-events-none text-gray-400 dark:text-gray-400">
                <slot name="icon-left" />
            </div>

            <textarea
                v-if="isTextarea"
                :id="computedId"
                ref="elementRef"
                :class="baseClasses"
                :value="modelValue"
                :placeholder="placeholder"
                :maxlength="withMaxLength ? maxLength : undefined"
                @input="handleInput"
                rows="3"
            />
            
            <!-- MODIFICADO: Stepper numérico ahora con input editable y estado de error -->
            <div v-else-if="type === 'numeric-stepper'" :class="stepperClasses">
                <button @click="decrement" type="button" class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xl font-bold flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors hover:bg-gray-300 dark:hover:bg-gray-500 flex-shrink-0">
                    -
                </button>
                <input
                    :id="computedId"
                    type="text"
                    :value="modelValue"
                    :placeholder="placeholder"
                    @input="handleInput"
                    @blur="handleStepperBlur"
                    inputmode="numeric"
                    class="w-full h-5 text-center bg-transparent border-none focus:outline-none focus:ring-0 text-sm text-gray-800 dark:text-gray-100"
                />
                <button @click="increment" type="button" class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xl font-bold flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors hover:bg-gray-300 dark:hover:bg-gray-500 flex-shrink-0">
                    +
                </button>
            </div>

            <!-- MODIFICADO: Input estándar ahora condicionalmente numérico -->
            <input
                v-else
                :id="computedId"
                :type="formatAsNumber ? 'text' : type"
                ref="elementRef"
                :class="baseClasses"
                :value="formattedValue"
                :placeholder="placeholder"
                :maxlength="withMaxLength ? maxLength : undefined"
                @input="handleInput"
                :inputmode="formatAsNumber ? 'numeric' : undefined"
            >
        </div>
        
        <div class="flex justify-between items-center min-h-[1rem] mt-1">
            <p v-if="error" class="text-xs text-red-600 dark:text-red-500 ml-4">
                {{ error }}
            </p>
            
            <span 
                v-if="withMaxLength" 
                class="text-xs ml-auto"
                :class="[ (String(modelValue)?.length || 0) > maxLength ? 'text-red-500' : 'text-gray-500 dark:text-gray-300' ]"
            >
                {{ String(modelValue)?.length || 0 }} / {{ maxLength }}
            </span>
        </div>
    </div>
</template>
