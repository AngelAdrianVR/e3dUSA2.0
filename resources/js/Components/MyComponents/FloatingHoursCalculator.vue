<template>
    <div class="fixed bottom-4 right-4 z-50 flex flex-col items-end space-y-2">
        <!-- Panel desplegable -->
        <transition name="el-fade-in">
            <div v-if="isOpen">
                <HoursCalculator :title="title" @total-change="onTotalChange" />
            </div>
        </transition>

        <!-- Pestaña / botón flotante -->
        <!-- <el-tooltip :content="isOpen ? 'Cerrar calculadora' : 'Abrir calculadora de horas'" placement="left"> -->
            <button type="button" @click="isOpen = !isOpen"
                class="flex items-center space-x-2 h-11 px-4 rounded-full shadow-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-all">
                <i class="fa-solid fa-calculator"></i>
                <span>{{ isOpen ? 'Cerrar' : 'Calculadora de Horas' }}</span>
            </button>
        <!-- </el-tooltip> -->
    </div>
</template>

<script setup>
import { ref } from 'vue';
import HoursCalculator from './HoursCalculator.vue';

defineProps({
    title: { type: String, default: 'Calculadora de Horas' },
});

const emit = defineEmits(['total-change']);

const isOpen = ref(false);

const onTotalChange = (val) => emit('total-change', val);
</script>
