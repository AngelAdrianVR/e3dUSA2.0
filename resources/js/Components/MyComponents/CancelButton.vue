<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-transparent border-2 border-[#a3a3a3] dark:border-gray-300 rounded-xl font-semibold text-xs text-[#727272] dark:text-gray-400 tracking-widest focus:bg-gray-100 active:bg-gray-100 focus:outline-none focus:ring-2 transition ease-in-out duration-200 disabled:opacity-25 disabled:cursor-not-allowed">
        <slot />
    </button>
</template>
