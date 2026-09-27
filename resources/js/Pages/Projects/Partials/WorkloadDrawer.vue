<template>
    <el-drawer :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)"
        size="560px" :teleported="true" @open="fetchWorkload">
        <template #header>
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-scale-balanced text-blue-500"></i>
                <span class="font-bold">Carga de tareas por usuario</span>
            </div>
        </template>

        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-relaxed">
            Tareas <b>activas</b> (Pendiente + En proceso + Pausada) de todos los proyectos que puedes ver.
            Sirve para decidir si conviene asignar más tareas a alguien o pausar algunas para atender otras de mayor urgencia.
        </p>

        <!-- Leyenda de saturación -->
        <div class="flex flex-wrap items-center gap-2 mb-4 text-[11px]">
            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">0-2 Disponible</span>
            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">3-5 Moderado</span>
            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">6-9 Alto</span>
            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">10+ Saturado</span>
        </div>

        <!-- Cargando -->
        <div v-if="loading" class="py-10 text-center text-gray-400">
            <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
            <p class="text-xs mt-2">Calculando carga…</p>
        </div>

        <!-- Sin tareas asignadas -->
        <p v-else-if="!users.length" class="py-10 text-center text-sm text-gray-400">
            No hay tareas asignadas en los proyectos visibles.
        </p>

        <!-- Lista por usuario -->
        <div v-else class="space-y-3">
            <div v-for="user in users" :key="user.user_id"
                class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 p-3">
                <div class="flex items-center gap-3">
                    <img :src="user.profile_photo_url" class="size-9 rounded-full object-cover" :alt="user.name" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ user.name }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            {{ user.active }} activa(s) · {{ user.finished }} terminada(s)
                            <template v-if="user.rating_average"> · ★ {{ user.rating_average }} ({{ user.rating_count }})</template>
                        </p>
                    </div>
                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full" :class="levelOf(user.active).chip">
                        {{ levelOf(user.active).label }}
                    </span>
                </div>

                <!-- Barra de saturación -->
                <div class="mt-2.5 h-2 w-full bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500" :class="levelOf(user.active).bar"
                        :style="{ width: Math.min(100, (user.active / 10) * 100) + '%' }"></div>
                </div>

                <!-- Desglose -->
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px]">
                    <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                        Pendientes: {{ user.pending }}
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">
                        En proceso: {{ user.in_progress }}
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                        Pausadas: {{ user.paused }}
                    </span>
                    <span v-if="user.overdue" class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                        <i class="fa-solid fa-triangle-exclamation mr-0.5"></i>{{ user.overdue }} vencida(s)
                    </span>
                    <span v-if="user.due_soon" class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                        <i class="fa-solid fa-hourglass-half mr-0.5"></i>{{ user.due_soon }} por vencer
                    </span>
                </div>
            </div>
        </div>
    </el-drawer>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

defineProps({
    modelValue: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const loading = ref(false);
const users = ref([]);

// Nivel de saturación según las tareas activas
const levelOf = (active) => {
    if (active >= 10) return { label: 'Saturado', chip: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300', bar: 'bg-red-500' };
    if (active >= 6) return { label: 'Alto', chip: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', bar: 'bg-amber-500' };
    if (active >= 3) return { label: 'Moderado', chip: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300', bar: 'bg-blue-500' };

    return { label: 'Disponible', chip: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', bar: 'bg-emerald-500' };
};

const fetchWorkload = async () => {
    loading.value = true;

    try {
        const response = await axios.get(route('projects.workload'));
        users.value = response.data.users || [];
    } catch (error) {
        console.error('Error al obtener la carga de usuarios:', error);
        users.value = [];
    } finally {
        loading.value = false;
    }
};
</script>
