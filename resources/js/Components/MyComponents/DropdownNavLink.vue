<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    active: Boolean, // Aunque no se usa en el original, es bueno tenerlo
});
</script>

<template>
    <Link 
        :href="href"
        class="
            flex items-center w-full px-3 py-1.5 rounded
            text-sm font-medium text-slate-400
            transition-colors duration-200
            hover:text-white hover:bg-slate-700/50
            focus:outline-none focus:text-white focus:bg-slate-700
        "
    >
        <!-- Pequeño punto como viñeta -->
        <span class="w-1.5 h-1.5 mr-3 bg-slate-600 rounded-full"></span>
        <slot />
    </Link>
</template>
