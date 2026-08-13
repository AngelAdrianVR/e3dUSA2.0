<template>
    <div class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl shadow-2xl p-4 w-80 max-w-[calc(100vw-2rem)]">
        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-bold text-sm dark:text-gray-200 flex items-center space-x-2">
                <i class="fa-solid fa-calculator text-indigo-500"></i>
                <span>{{ title }}</span>
            </h4>
            <el-tooltip content="Limpiar todo" placement="top">
                <button type="button" @click="clearAll"
                    class="size-7 rounded-full text-gray-400 hover:text-red-500 hover:bg-gray-100 dark:hover:bg-slate-700 transition-all">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
            </el-tooltip>
        </div>

        <!-- Total -->
        <div class="mb-3 p-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-center">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">Total</p>
            <p class="text-2xl font-bold text-indigo-800 dark:text-indigo-200"
                :class="{ '!text-red-500 dark:!text-red-400': totalSeconds < 0 }">
                {{ totalDisplay }}
            </p>
        </div>

        <!-- Entradas -->
        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
            <div v-for="(entry, index) in entries" :key="index" class="flex items-center space-x-2">
                <button type="button" @click="toggleSign(index)"
                    class="w-10 h-9 shrink-0 rounded-lg font-bold text-white text-lg transition-all"
                    :class="entry.sign === '-' ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'"
                    :title="entry.sign === '-' ? 'Resta' : 'Suma'">
                    {{ entry.sign }}
                </button>
                <el-input v-model="entry.value" placeholder="HH:MM" size="small" class="flex-1" @keyup.enter="addEntry" />
                <button type="button" @click="removeEntry(index)"
                    class="size-7 shrink-0 rounded-full text-gray-400 hover:text-red-500 hover:bg-gray-100 dark:hover:bg-slate-700 transition-all"
                    :title="'Eliminar fila ' + (index + 1)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Acciones -->
        <div class="mt-3">
            <el-button size="small" type="primary" plain class="w-full" @click="addEntry">
                <i class="fa-solid fa-plus mr-1"></i> Agregar fila
            </el-button>
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-2 text-center">
                Escribe horas como 8:30 ó 1:45
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    title: { type: String, default: 'Calculadora de Horas' },
    // Formato: [{ sign: '+' | '-', value: 'HH:MM' }]
    initialEntries: { type: Array, default: () => [] },
});

const emit = defineEmits(['total-change']);

const entries = ref(
    props.initialEntries.length
        ? props.initialEntries.map(e => ({ sign: e.sign ?? '+', value: String(e.value ?? '') }))
        : [{ sign: '+', value: '' }]
);

// Convierte "8", "8:30" o "1:45" a segundos. Devuelve null si no es válido.
const parseToSeconds = (value) => {
    if (value === null || value === undefined) return null;
    const str = String(value).trim();
    if (!str) return null;
    const parts = str.split(':');
    if (parts.length > 2) return null;
    const hours = parseInt(parts[0], 10);
    const minutes = parts.length === 2 ? parseInt(parts[1], 10) : 0;
    if (isNaN(hours) || isNaN(minutes) || minutes < 0 || minutes > 59) return null;
    return hours * 3600 + minutes * 60;
};

const formatSeconds = (secs) => {
    const sign = secs < 0 ? '-' : '';
    const abs = Math.abs(secs);
    const h = Math.floor(abs / 3600);
    const m = Math.floor((abs % 3600) / 60);
    return `${sign}${h}h ${m}m`;
};

const totalSeconds = computed(() => {
    return entries.value.reduce((acc, entry) => {
        const secs = parseToSeconds(entry.value);
        if (secs === null) return acc;
        return entry.sign === '-' ? acc - secs : acc + secs;
    }, 0);
});

const totalDisplay = computed(() => formatSeconds(totalSeconds.value));

watch(totalSeconds, (val) => emit('total-change', val), { immediate: true });

const addEntry = () => entries.value.push({ sign: '+', value: '' });

const removeEntry = (index) => {
    if (entries.value.length === 1) {
        entries.value[0] = { sign: '+', value: '' };
        return;
    }
    entries.value.splice(index, 1);
};

const toggleSign = (index) => {
    entries.value[index].sign = entries.value[index].sign === '-' ? '+' : '-';
};

const clearAll = () => {
    entries.value = [{ sign: '+', value: '' }];
};
</script>
