<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticationCard from "@/Components/AuthenticationCard.vue";
import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
import Checkbox from "@/Components/Checkbox.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue"; // Usando el componente mejorado
import { ref } from 'vue';

defineProps({
  canResetPassword: Boolean,
  status: String,
});

const form = useForm({
  id: "",
  password: "",
  remember: false,
});

const submit = () => {
  form.post(route("login"), {
    onFinish: () => form.reset("password"),
  });
};

const showPassword = ref(false);
</script>

<template>
  <Head title="Log in" />

  <AuthenticationCard :loading="form.processing">
    <template #logo>
      <AuthenticationCardLogo />
    </template>

    <div v-if="status" class="mb-4 font-medium text-sm text-green-400">
      {{ status }}
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Campo de ID de Usuario -->
      <TextInput
          id="id"
          v-model="form.id"
          type="text"
          label="ID de Usuario"
          :error="form.errors.id"
          required
          autofocus
          autocomplete="username"
          placeholder="Ingresa tu ID"
          class="bg-transparent text-white placeholder:text-gray-400 border-0 border-b-2 pb-5 border-gray-600 focus:ring-0 focus:border-indigo-400"
      />

      <!-- Campo de Contraseña -->
      <div class="relative">
        <TextInput
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            label="Contraseña"
            :error="form.errors.password"
            required
            autocomplete="current-password"
            placeholder="············"
            class="bg-transparent text-white placeholder:text-gray-400 border-0 border-b-2 border-gray-600 focus:ring-0 focus:border-indigo-400"
        />
        <!-- Ícono para mostrar/ocultar contraseña -->
        <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-8 text-gray-400 hover:text-indigo-300">
            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
        </button>
      </div>

      <!-- Opciones -->
      <div class="flex items-center justify-between text-sm">
        <label class="flex items-center">
          <Checkbox v-model:checked="form.remember" name="remember" class="bg-transparent text-indigo-500 border-gray-500" />
          <span class="ml-2 text-gray-300">No cerrar sesión</span>
        </label>
        <Link
          v-if="canResetPassword"
          :href="route('password.request')"
          class="font-medium text-indigo-400 hover:text-indigo-300"
        >
          Olvidé mi contraseña
        </Link>
      </div>

      <!-- Botón de envío -->
      <div class="pt-4">
        <PrimaryButton class="w-full justify-center" :loading="form.processing">
          Iniciar sesión
        </PrimaryButton>
      </div>
    </form>
  </AuthenticationCard>
</template>
