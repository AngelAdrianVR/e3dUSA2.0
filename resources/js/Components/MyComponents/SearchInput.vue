<script>
export default {
    name: 'SearchInput',
    props: {
        modelValue: String, // Para v-model
    },
    emits: ['update:modelValue'],
    data() {
        return {
            isFocused: false,
        };
    },
    methods: {
        openSearch() {
            this.isFocused = true;
            // Espera a que el DOM se actualice para enfocar el input
            this.$nextTick(() => {
                this.$refs.searchInput.focus();
            });
        },
        closeSearch() {
            // Solo cierra la barra si el campo está vacío
            if (!this.$refs.searchInput.value) {
                this.isFocused = false;
            }
        },
        handleInput(event) {
            this.$emit('update:modelValue', event.target.value);
        }
    }
}
</script>

<template>
    <div class="relative flex items-center h-10">
        <!-- Contenedor animado -->
        <div 
            class="relative transition-all duration-500 ease-in-out"
            :class="isFocused ? 'w-64' : 'w-10'"
        >
            <!-- Input de búsqueda -->
            <input
                ref="searchInput"
                type="text"
                :value="modelValue"
                @input="handleInput"
                @blur="closeSearch"
                placeholder="Buscar..."
                class="w-full h-10 pl-10 pr-4 rounded-full border text-sm transition-all duration-300 ease-in-out bg-gray-100 dark:bg-slate-700 border-gray-200 dark:border-slate-600 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                :class="{ 'opacity-100': isFocused, 'opacity-0': !isFocused }"
            />
            <!-- Ícono de lupa dentro del input -->
            <div class="absolute top-0 left-0 flex items-center justify-center h-full w-10 text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
        </div>

        <!-- Botón que activa la búsqueda (se oculta cuando está activa) -->
        <button
            v-if="!isFocused"
            @click="openSearch"
            class="absolute top-0 left-0 flex items-center justify-center h-10 w-10 rounded-full text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-slate-700 transition-opacity duration-300"
        >
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
    </div>
</template>
