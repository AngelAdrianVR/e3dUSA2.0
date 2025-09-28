<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// El componente recibe los datos de la alerta como una prop
const props = defineProps({
    alertData: Object,
});

// --- CONFIGURACIÓN DE ESTILOS POR TIPO DE ALERTA ---
const alertStylesConfig = {
    pending_quotations: {
        container: 'bg-yellow-100 border-yellow-500 text-yellow-700',
        iconColor: 'text-yellow-500',
        linkColor: 'text-yellow-800',
        iconPath: 'M10 15.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm0-11a1 1 0 0 1 1 1v6a1 1 0 1 1-2 0V5.5a1 1 0 0 1 1-1zM10 0a10 10 0 1 0 0 20 10 10 0 0 0 0-20z',
    },
    overdue_task: { // Ejemplo de otro tipo de alerta
        container: 'bg-red-100 border-red-500 text-red-700',
        iconColor: 'text-red-500',
        linkColor: 'text-red-800',
        iconPath: 'M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm1-12H9v4h2V6zm0 6H9v2h2v-2z',
    },
    default: {
        container: 'bg-gray-100 border-gray-500 text-gray-700',
        iconColor: 'text-gray-500',
        linkColor: 'text-gray-800',
        iconPath: 'M10 15.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm0-11a1 1 0 0 1 1 1v6a1 1 0 1 1-2 0V5.5a1 1 0 0 1 1-1zM10 0a10 10 0 1 0 0 20 10 10 0 0 0 0-20z',
    }
};

// Propiedades computadas para obtener los estilos dinámicos
const currentStyle = computed(() => alertStylesConfig[props.alertData.type] || alertStylesConfig.default);

// Referencias para el elemento del DOM y el estado de arrastre
const alertBox = ref(null);
const isDragging = ref(false);
const position = ref({ x: 0, y: 0 });
const offset = ref({ x: 0, y: 0 });

// --- POSICIÓN INICIAL DE LA ALERTA ---
const initialTop = 80;
const initialRight = 20;

onMounted(() => {
    if (alertBox.value) {
        position.value = {
            x: window.innerWidth - alertBox.value.offsetWidth - initialRight,
            y: initialTop
        };
    }
});

// --- LÓGICA DE ARRASTRE (DRAG AND DROP) ---
const startDrag = (event) => {
    isDragging.value = true;
    const clientX = event.type === 'touchstart' ? event.touches[0].clientX : event.clientX;
    const clientY = event.type === 'touchstart' ? event.touches[0].clientY : event.clientY;
    offset.value = {
        x: clientX - alertBox.value.getBoundingClientRect().left,
        y: clientY - alertBox.value.getBoundingClientRect().top,
    };
    window.addEventListener('mousemove', onDrag);
    window.addEventListener('touchmove', onDrag, { passive: false });
    window.addEventListener('mouseup', stopDrag);
    window.addEventListener('touchend', stopDrag);
};

const onDrag = (event) => {
    if (isDragging.value) {
        event.preventDefault();
        const clientX = event.type === 'touchmove' ? event.touches[0].clientX : event.clientX;
        const clientY = event.type === 'touchmove' ? event.touches[0].clientY : event.clientY;
        position.value = {
            x: clientX - offset.value.x,
            y: clientY - offset.value.y,
        };
    }
};

const stopDrag = () => {
    isDragging.value = false;
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('touchmove', onDrag);
    window.removeEventListener('mouseup', stopDrag);
    window.removeEventListener('touchend', stopDrag);
};

const alertPositionStyle = computed(() => ({
    transform: `translate(${position.value.x}px, ${position.value.y}px)`,
    cursor: isDragging.value ? 'grabbing' : 'grab',
    touchAction: 'none',
}));

</script>

<template>
    <div
        ref="alertBox"
        :class="['fixed top-0 left-0 border-l-4 p-4 rounded-md shadow-lg z-[100] w-80', currentStyle.container]"
        :style="alertPositionStyle"
        @mousedown="startDrag"
        @touchstart="startDrag"
    >
        <div class="flex">
            <div class="py-1">
                <!-- Icono de Alerta Dinámico -->
                <svg class="fill-current size-6 mr-4" :class="currentStyle.iconColor" xmlns="http://www.w.org/2000/svg" viewBox="0 0 20 20">
                    <path :d="currentStyle.iconPath" />
                </svg>
            </div>
            <div>
                <p class="font-bold">{{ alertData.title }}</p>
                <p class="text-sm">{{ alertData.message }}</p>
                
                <!-- Contenido específico por tipo de alerta -->
                <div v-if="alertData.type === 'pending_quotations'">
                    <p v-if="alertData.quote_ids && alertData.quote_ids.length" class="text-xs mt-1 text-gray-600 max-h-32 overflow-y-auto">
                        Folios: COT-{{ alertData.quote_ids.join(', COT-') }}
                    </p>
                    <Link :href="route('quotes.index')" :class="['text-sm font-bold hover:underline mt-2 inline-block', currentStyle.linkColor]">
                        Ir a cotizaciones &rarr;
                    </Link>
                </div>

                <div v-if="alertData.type === 'overdue_task'">
                     <Link href="#" :class="['text-sm font-bold hover:underline mt-2 inline-block', currentStyle.linkColor]">
                        Ver tarea &rarr;
                    </Link>
                </div>

            </div>
        </div>
    </div>
</template>
