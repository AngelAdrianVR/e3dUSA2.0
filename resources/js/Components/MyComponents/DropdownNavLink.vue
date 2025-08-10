<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({
    href: String,
    as: String,
    notifications: Boolean,
    active: Boolean,
});

const hovering = ref(false);

const iconClass = computed(() => {
  return 'fa-solid fa-circle fa-pulse text-' + (hovering.value ? 'white' : 'primary') + ' text-[9px]';
});

</script>

<template>
    <div>
        <Link
            @mouseenter="hovering = true"
            @mouseleave="hovering = false"
            :href="href"
            class="w-full px-3 py-1 text-sm leading-5 text-gray-700 dark:text-gray-200
                hover:bg-secondary hover:text-white dark:hover:bg-secondary
                focus:outline-none focus:bg-secondary focus:text-white
                transition duration-150 ease-in-out flex justify-between items-center rounded-md"
            :class="{
                'bg-secondary text-white': active
            }"
        >
            <slot />
            <i v-if="notifications" :class="iconClass"></i>
        </Link>
    </div>
</template>