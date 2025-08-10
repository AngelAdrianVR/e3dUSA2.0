<script>
export default {
    name: 'SearchInput',
    props: {
        modelValue: String, // Para v-model
    },
    emits: ['update:modelValue', 'cleanSearch'],
    data() {
        return {
            isFocused: false,
        };
    },
    computed: {
        /**
         * Determina si el componente debe estar en su estado expandido.
         * Se activa si el input está enfocado o si ya existe un valor de búsqueda.
         */
        isActive() {
            return this.isFocused || !!this.modelValue;
        }
    },
    methods: {
        /**
         * Activa el modo de edición y enfoca el input.
         */
        openSearch() {
            this.isFocused = true;
            this.$nextTick(() => {
                this.$refs.searchInput.focus();
            });
        },
        /**
         * Desactiva el modo de edición cuando el input pierde el foco.
         */
        handleBlur() {
            this.isFocused = false;
        },
        /**
         * Emite el valor del input al componente padre.
         */
        handleInput(event) {
            this.$emit('update:modelValue', event.target.value);
        },
        /**
         * Limpia el valor de búsqueda y emite el cambio.
         * Esto hará que la etiqueta desaparezca y el componente se contraiga.
         */
        clearSearch() {
            this.$emit('update:modelValue', '');
            this.$emit('cleanSearch');
            this.isFocused = false; // Aseguramos que se cierre si estaba abierto.
        }
    }
}
</script>

<template>
    <div class="relative flex items-center h-10">
        <div 
            class="relative transition-all duration-500 ease-in-out flex items-center group"
            :class="isActive ? 'w-64' : 'w-10'"
        >
            <div class="absolute top-0 left-0 flex items-center justify-center h-full w-10 text-gray-400 z-10">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <input
                v-if="isActive"
                ref="searchInput"
                type="text"
                :disabled="!isFocused"
                :value="modelValue"
                @input="handleInput"
                @blur="handleBlur"
                @keyup.enter="handleBlur"
                placeholder="Buscar..."
                class="w-full h-10 pl-10 pr-4 rounded-full border text-sm transition-opacity duration-500 ease-in-out bg-gray-100 dark:bg-slate-800 border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            
            <div 
                v-if="modelValue && !isFocused" 
                @click="openSearch"
                class="absolute inset-0 flex items-center justify-between pl-10 pr-2 bg-blue-100 dark:bg-blue-900/50 rounded-full cursor-pointer"
            >
                <span class="text-sm font-medium text-blue-700 dark:text-blue-300 truncate" title="Término buscado">
                    {{ modelValue }}
                </span>
                <button 
                    @click.stop="clearSearch" 
                    class="ml-2 h-5 w-5 rounded-full flex items-center justify-center text-blue-500 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-700/50 transition-colors"
                    title="Limpiar búsqueda"
                >
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
        </div>

        <button
            v-if="!isActive"
            @click="openSearch"
            class="z-50 absolute top-0 left-0 flex items-center justify-center h-10 w-10 rounded-full text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-slate-700 transition-opacity"
        >
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
    </div>
</template>