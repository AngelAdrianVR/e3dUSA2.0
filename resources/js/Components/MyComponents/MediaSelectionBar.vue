<template>
    <div
        v-if="selectable && total > 0"
        class="flex flex-wrap items-center justify-between gap-3 mb-4 px-3 py-2 rounded-lg border transition-colors"
        :class="selectedCount > 0
            ? 'border-indigo-200 dark:border-sky-500/30 bg-indigo-50/70 dark:bg-sky-500/5'
            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60'"
    >
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200 cursor-pointer select-none">
                <span
                    class="inline-flex items-center justify-center h-4 w-4 rounded border-2 transition-colors"
                    :class="allSelected
                        ? 'bg-indigo-600 border-indigo-600 dark:bg-sky-500 dark:border-sky-500'
                        : 'border-gray-400 dark:border-gray-500 bg-white dark:bg-gray-700'"
                    role="checkbox"
                    :aria-checked="allSelected ? 'true' : (selectedCount > 0 ? 'mixed' : 'false')"
                    tabindex="0"
                    @click.prevent="emit('toggle-all')"
                    @keydown.space.prevent="emit('toggle-all')"
                    @keydown.enter.prevent="emit('toggle-all')"
                >
                    <i v-if="allSelected" class="fa-solid fa-check text-[9px] text-white"></i>
                    <i v-else-if="selectedCount > 0" class="fa-solid fa-minus text-[9px] text-indigo-600 dark:text-sky-400"></i>
                </span>
                <span @click.prevent="emit('toggle-all')">
                    Seleccionar todo ({{ total }})
                </span>
            </label>

            <span v-if="selectedCount > 0" class="text-sm font-semibold text-indigo-600 dark:text-sky-400">
                {{ selectedCount }} seleccionado{{ selectedCount === 1 ? '' : 's' }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button
                v-if="selectedCount > 0"
                type="button"
                @click="emit('clear')"
                class="px-2.5 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white transition"
            >
                Cancelar
            </button>
            <button
                type="button"
                :disabled="selectedCount === 0"
                @click="emit('delete')"
                class="px-3 py-1.5 text-sm font-semibold rounded-md text-white transition flex items-center gap-2"
                :class="selectedCount === 0
                    ? 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed'
                    : 'bg-red-600 hover:bg-red-700'"
            >
                <i class="fa-regular fa-trash-can"></i>
                Eliminar seleccionados
                <span v-if="selectedCount > 0">({{ selectedCount }})</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    selectable: {
        type: Boolean,
        default: false,
    },
    total: {
        type: Number,
        default: 0,
    },
    selectedCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['toggle-all', 'clear', 'delete']);

const allSelected = computed(() => props.total > 0 && props.selectedCount === props.total);
</script>
