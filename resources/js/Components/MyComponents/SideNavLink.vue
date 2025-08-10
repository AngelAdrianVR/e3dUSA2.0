<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    dropdown: Boolean,
    label: String, // Se añade label para el tooltip
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '56', // Ancho del dropdown (w-56)
    },
    contentClasses: {
        type: Array,
        default: () => ['bg-white', 'dark:bg-zinc-800'],
    },
});

let open = ref(false);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const widthClass = computed(() => {
    return {
        '56': 'w-56',
        '48': 'w-48',
    }[props.width.toString()];
});

// Clases para el contenedor del link/botón
const classes = computed(() => {
    const baseClasses = 'relative flex items-center justify-center size-12 rounded-lg transition-colors duration-200';
    
    if (props.active) {
        return `${baseClasses} bg-gray-200 dark:bg-zinc-600 text-gray-600 dark:text-gray-200`;
    }
    
    return `${baseClasses} text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-zinc-700`;
});
</script>

<template>
    <div class="relative group">
        <div v-if="props.dropdown">
            <div>
                <div @click="open = !open" :class="classes" class="cursor-pointer">
                    <slot name="trigger" />
                </div>

                <div v-show="open" class="fixed inset-0 z-40" @click="open = false" />

                <transition enter-active-class="transition ease-out duration-200"
                    enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100"
                    leave-to-class="transform opacity-0 scale-95">
                    
                    <div v-show="open" 
                         class="absolute z-50 mt-2 rounded-xl shadow-lg left-[70px] top-0 ml-3"
                         :class="[widthClass]" 
                         style="display: none;" 
                         @click="open = false">
                        <div class="rounded-xl ring-1 ring-black ring-opacity-5" :class="contentClasses">
                            <slot name="content" />
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <Link v-else :href="href" :class="classes">
            <slot name="trigger" />
        </Link>
        
        <div v-if="!open"
             class="absolute left-full top-2 ml-4 px-4 py-2 bg-zinc-800 dark:bg-slate-900 text-white text-xs rounded-md whitespace-nowrap
                    invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-opacity duration-200 z-50">
            {{ props.label }}
        </div>
    </div>
</template>