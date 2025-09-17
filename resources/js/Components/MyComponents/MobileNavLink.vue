<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    as: {
        type: String,
        default: 'link', // can be 'link' or 'button'
    },
    dropdown: Boolean, // Indicates if this item is a dropdown trigger
});

const isDropdownOpen = ref(false);

// Toggles the dropdown menu if the item is configured as a dropdown.
const toggleDropdown = () => {
    if (props.dropdown) {
        isDropdownOpen.value = !isDropdownOpen.value;
    }
};

// Dynamically computes the CSS classes based on the link's active state.
const classes = computed(() => {
    return props.active
        ? 'flex items-center w-full text-start p-3 rounded-lg bg-blue-50 dark:bg-zinc-800 text-blue-600 dark:text-white font-semibold'
        : 'flex items-center w-full text-start p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-800 font-medium';
});
</script>

<template>
    <div>
        <!-- The main clickable element. Can be a Link, a button, or a dropdown trigger. -->
        <component 
            :is="dropdown ? 'button' : Link" 
            :href="href"
            :class="classes"
            @click="toggleDropdown"
            class="transition-colors duration-200"
        >
            <span class="mr-3 w-6 h-6 flex items-center justify-center">
                <slot name="icon" />
            </span>
            <span class="flex-1">
                <slot />
            </span>
            <svg v-if="dropdown" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" 
                 class="size-5 transition-transform duration-300 text-gray-400"
                 :class="{ 'rotate-90': isDropdownOpen }">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
            </svg>
        </component>

        <!-- Container for dropdown content with a smooth transition. -->
        <div v-if="dropdown" 
             class="overflow-hidden transition-all duration-300 ease-in-out"
             :style="{ maxHeight: isDropdownOpen ? '1000px' : '0px' }">
            <div class="pt-2 pl-9 pr-2 space-y-1">
                <slot name="content" />
            </div>
        </div>
    </div>
</template>
