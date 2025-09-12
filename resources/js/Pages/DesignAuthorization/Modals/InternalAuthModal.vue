<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4" @click.self="close">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-xl p-6 w-full max-w-md transform transition-all" :class="show ? 'scale-100 opacity-100' : 'scale-95 opacity-0'">
      <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200">Autorización Interna</h2>
      <form @submit.prevent="submitForm">
        <!-- <p class="text-sm mb-2 text-gray-600 dark:text-gray-400">Introduce tu nombre para registrar quién autorizó internamente este diseño.</p>
        <input
          v-model="authorizerName"
          type="text"
          placeholder="Tu nombre"
          class="w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
          required
        />
        <p v-if="error" class="text-sm text-red-600 mt-1">{{ error }}</p> -->
        <div class="flex justify-end space-x-3 mt-5">
          <button type="button" @click="close" class="px-4 py-2 rounded-md text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600">Cancelar</button>
          <button type="submit" :disabled="processing" class="px-4 py-2 rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 flex items-center">
            <span v-if="processing" class="spinner-border animate-spin inline-block w-4 h-4 border-2 rounded-full mr-2"></span>
            Autorizar
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    show: Boolean,
    processing: Boolean,
    error: String,
  },
  emits: ['close', 'submit'],
  data() {
    return {
      // authorizerName: this.$page.props.auth.user.name
    }
  },
  methods: {
    close() { this.$emit('close'); },
    submitForm() {
      this.$emit('submit');
    }
  },
  watch: {
    show(newVal) {
      if (newVal) {
        this.authorizerName = '';
      }
    }
  }
}
</script>
