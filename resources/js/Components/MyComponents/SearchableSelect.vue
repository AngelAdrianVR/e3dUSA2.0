<template>
    <div ref="root" class="relative">
        <button
            type="button"
            @click="toggle"
            class="w-full px-3 py-2 text-sm text-left border rounded-md bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-sky-500 flex items-center justify-between gap-2"
            :class="selectedOption ? 'text-gray-900 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500'"
            :aria-expanded="open"
        >
            <span class="truncate">{{ selectedOption ? selectedOption.label : placeholder }}</span>
            <span class="flex items-center gap-1.5 shrink-0">
                <i
                    v-if="clearable && hasValue"
                    class="fa-solid fa-circle-xmark text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    title="Limpiar"
                    @click.stop="selectValue(null)"
                ></i>
                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 transition-transform duration-150" :class="{ 'rotate-180': open }"></i>
            </span>
        </button>

        <div
            v-if="open"
            class="absolute z-40 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden"
        >
            <div v-if="filterable" class="p-2 border-b border-gray-100 dark:border-gray-700">
                <input
                    ref="searchInput"
                    v-model="query"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full px-2 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:focus:ring-sky-500"
                />
            </div>

            <ul class="max-h-56 overflow-y-auto py-1">
                <li
                    v-if="clearable"
                    class="px-3 py-2 text-sm cursor-pointer text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="selectValue(null)"
                >
                    {{ emptyOptionLabel }}
                </li>
                <li
                    v-for="option in visibleOptions"
                    :key="option.value"
                    class="px-3 py-2 text-sm cursor-pointer truncate"
                    :class="option.value === modelValue
                        ? 'bg-indigo-50 dark:bg-sky-500/10 text-indigo-600 dark:text-sky-400 font-medium'
                        : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    :title="option.label"
                    @click="selectValue(option.value)"
                >
                    {{ option.label }}
                </li>
                <li v-if="visibleOptions.length === 0" class="px-3 py-3 text-sm text-center text-gray-400">
                    Sin resultados
                </li>
            </ul>

            <div
                v-if="filteredOptions.length > maxResults"
                class="px-3 py-1.5 text-[11px] text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-700"
            >
                Mostrando {{ visibleOptions.length }} de {{ filteredOptions.length }} resultados, afina la búsqueda.
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Seleccionar',
    },
    searchPlaceholder: {
        type: String,
        default: 'Buscar...',
    },
    emptyOptionLabel: {
        type: String,
        default: 'Sin filtro',
    },
    filterable: {
        type: Boolean,
        default: true,
    },
    clearable: {
        type: Boolean,
        default: false,
    },
    maxResults: {
        type: Number,
        default: 50,
    },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const searchInput = ref(null);
const open = ref(false);
const query = ref('');

const hasValue = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '');

const selectedOption = computed(() => props.options.find((option) => option.value === props.modelValue) || null);

const filteredOptions = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) {
        return props.options;
    }

    return props.options.filter((option) => option.label.toLowerCase().includes(term));
});

const visibleOptions = computed(() => filteredOptions.value.slice(0, props.maxResults));

const toggle = () => {
    open.value = !open.value;

    if (open.value) {
        query.value = '';
        nextTick(() => searchInput.value?.focus());
    }
};

const selectValue = (value) => {
    emit('update:modelValue', value);
    open.value = false;
};

const onDocumentClick = (event) => {
    if (!open.value) return;
    if (root.value && root.value.contains(event.target)) return;

    open.value = false;
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && open.value) {
        open.value = false;
        event.stopPropagation();
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>
