<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    dropdown: Boolean,
});

const open = ref(props.active); // El dropdown se abre si está activo

const baseClasses = `
    relative flex flex-col items-center w-full h-[72px] justify-center px-2 
    rounded-lg transition-all duration-300 ease-in-out 
    focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400
`;

const interactiveClasses = `
    hover:bg-slate-700/50
`;

const activeClasses = 'text-white';
const inactiveClasses = 'text-slate-400 hover:text-white';

const classes = computed(() => {
    return `${baseClasses} ${interactiveClasses} ${props.active ? activeClasses : inactiveClasses}`;
});
</script>

<template>
    <div>
        <!-- Si es un dropdown, usa un botón -->
        <button v-if="props.dropdown" @click="open = !open" :class="classes">
            <slot name="trigger" />
        </button>
        <!-- Si no, usa un Link de Inertia -->
        <Link v-else :href="href" :class="classes">
            <slot name="trigger" />
        </Link>

        <!-- Contenido del dropdown con animación de acordeón -->
        <div v-if="props.dropdown" v-show="open" class="overflow-hidden transition-all duration-300 ease-in-out">
            <div class="pt-2 pb-1 pl-4 space-y-1">
                <slot name="content" />
            </div>
        </div>
    </div>
</template>
