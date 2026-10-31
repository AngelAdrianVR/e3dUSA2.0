<template>
    <button
        type="button"
        :title="selected ? 'Quitar de la selección' : 'Seleccionar archivo'"
        :aria-pressed="selected ? 'true' : 'false'"
        @click.stop.prevent="emit('toggle')"
        class="absolute top-2 left-2 z-20 flex items-center justify-center h-6 w-6 rounded-md border-2 shadow-sm backdrop-blur-sm transition-all duration-150"
        :class="selected
            ? 'bg-indigo-600 border-indigo-600 dark:bg-sky-500 dark:border-sky-500'
            : 'bg-white/90 dark:bg-gray-800/90 border-gray-300 dark:border-gray-500 hover:border-indigo-500 dark:hover:border-sky-500'"
    >
        <i v-if="selected" class="fa-solid fa-check text-[11px] text-white"></i>
    </button>
</template>

<script setup>
defineProps({
    selected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle']);
</script>
