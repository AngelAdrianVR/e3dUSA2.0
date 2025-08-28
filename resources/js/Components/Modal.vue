<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const dialog = ref();
const showSlot = ref(props.show);

// --- Inicio: Lógica para arrastrar el modal (Versión Fluida) ---

const isDragging = ref(false);
// Almacena la posición final del modal (transform)
const position = ref({ x: 0, y: 0 });
// Almacena la posición inicial del mouse y del modal al empezar a arrastrar
const startPosition = ref({ mouseX: 0, mouseY: 0, modalX: 0, modalY: 0 });

// Inicia el arrastre
const dragStart = (event) => {
    isDragging.value = true;

    // Guarda la posición inicial del mouse
    startPosition.value.mouseX = event.clientX;
    startPosition.value.mouseY = event.clientY;

    // Guarda la posición actual del modal
    startPosition.value.modalX = position.value.x;
    startPosition.value.modalY = position.value.y;

    window.addEventListener('mousemove', dragging);
    window.addEventListener('mouseup', dragEnd);
};

// Gestiona el movimiento durante el arrastre
const dragging = (event) => {
    if (isDragging.value) {
        event.preventDefault();

        // Calcula el desplazamiento total del mouse desde el inicio del arrastre
        const dx = event.clientX - startPosition.value.mouseX;
        const dy = event.clientY - startPosition.value.mouseY;

        // La nueva posición es la posición inicial del modal más el desplazamiento del mouse
        position.value.x = startPosition.value.modalX + dx;
        position.value.y = startPosition.value.modalY + dy;
    }
};

// Finaliza el arrastre
const dragEnd = () => {
    isDragging.value = false;
    window.removeEventListener('mousemove', dragging);
    window.removeEventListener('mouseup', dragEnd);
};

// Propiedad computada para aplicar el estilo de transformación
const modalStyle = computed(() => ({
    transform: `translate(${position.value.x}px, ${position.value.y}px)`,
}));

// --- Fin: Lógica para arrastrar el modal ---


watch(() => props.show, (newValue) => {
    if (newValue) {
        // Reinicia la posición a 0 cuando el modal se muestra
        position.value = { x: 0, y: 0 };
        document.body.style.overflow = 'hidden';
        showSlot.value = true;
        dialog.value?.showModal();
    } else {
        document.body.style.overflow = null;
        setTimeout(() => {
            dialog.value?.close();
            showSlot.value = false;
        }, 200);
    }
});

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        if (props.show) {
            close();
        }
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('mousemove', dragging);
    window.removeEventListener('mouseup', dragEnd);
    document.body.style.overflow = null;
});

const maxWidthClass = computed(() => {
    return {
        'sm': 'sm:max-w-sm',
        'md': 'sm:max-w-md',
        'lg': 'sm:max-w-lg',
        'xl': 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '3xl': 'sm:max-w-3xl',
    }[props.maxWidth];
});
</script>

<template>
    <dialog class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent" ref="dialog">
        <div class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" scroll-region>
            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-show="show" class="fixed inset-0 transform transition-all" @click="close">
                    <div class="absolute inset-0 bg-gray-500 dark:bg-gray-700 opacity-75" />
                </div>
            </transition>

            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    v-show="show"
                    :style="modalStyle"
                    class="mb-6 bg-gray-100 dark:bg-slate-800 rounded-2xl overflow-hidden shadow-xl transform sm:w-full sm:mx-auto"
                    :class="[
                        maxWidthClass,
                        // CAMBIO CLAVE: La transición solo se aplica cuando NO se está arrastrando.
                        { 'transition-all': !isDragging }
                    ]"
                >
                    <!-- MANGO DE ARRASTRE -->
                    <div
                        @mousedown.prevent="dragStart"
                        class="h-8 w-full bg-gray-200 dark:bg-slate-700 flex justify-center items-center cursor-move"
                    >
                        <svg class="w-8 h-1.5 text-gray-400 dark:text-slate-500" viewBox="0 0 32 6" fill="currentColor">
                            <rect width="32" height="6" rx="3" />
                        </svg>
                    </div>

                    <!-- El contenido original del modal -->
                    <slot v-if="showSlot" />
                </div>
            </transition>
        </div>
    </dialog>
</template>
