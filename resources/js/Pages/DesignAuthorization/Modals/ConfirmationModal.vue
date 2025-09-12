<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4" @click.self="close">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-xl p-6 w-full max-w-md transform transition-all" :class="show ? 'scale-100 opacity-100' : 'scale-95 opacity-0'">
      <div class="flex items-start">
        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900 sm:mx-0 sm:h-10 sm:w-10">
          <CheckCircleIcon class="h-6 w-6 text-green-600 dark:text-green-300" aria-hidden="true" />
        </div>
        <div class="ml-4 text-left">
          <h3 class="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">{{ title }}</h3>
          <div class="mt-2">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ message }}</p>
          </div>
        </div>
      </div>
      <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
        <button type="button" @click="confirm" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 sm:w-auto sm:text-sm">
          {{ confirmButtonText }}
        </button>
        <button type="button" @click="close" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 sm:mt-0 sm:w-auto sm:text-sm">
          Cancelar
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { CheckCircleIcon } from '@heroicons/vue/24/outline';

export default {
  props: {
    show: Boolean,
    title: { type: String, default: 'Confirmar Acción' },
    message: String,
    confirmButtonText: { type: String, default: 'Confirmar' }
  },
  components: { CheckCircleIcon },
  emits: ['close', 'confirm'],
  methods: {
    close() { this.$emit('close'); },
    confirm() { this.$emit('confirm'); }
  }
}
</script>
