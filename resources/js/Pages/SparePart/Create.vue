<template>
  <AppLayout title="Refacciones - Crear">
    <div class="flex justify-between items-center">
      <Back />
      <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
        Registrar Refacción
      </h2>
    </div>

    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
        <form @submit.prevent="store">
          <div class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
              <TextInput
                v-model="form.name"
                label="Nombre*"
                placeholder="Ej. Balero 6203"
                :error="form.errors.name"
              />
              <TextInput
                v-model="form.supplier"
                label="Proveedor"
                placeholder="Ej. SKF"
                :error="form.errors.supplier"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <TextInput 
                  v-model="form.quantity" 
                  :error="form.errors.quantity"
                  :formatAsNumber="true" 
                  label="Cantidad en stock*" />
              </div>
              <div>
                <TextInput 
                  v-model="form.cost" 
                  :error="form.errors.cost"
                  :formatAsNumber="true" 
                  label="Costo unitario*">
                  <template #icon-left>
                    $
                  </template>
                </TextInput>
              </div>
            </div>

            <TextInput
              v-model="form.location"
              label="Lugar donde lo almacenas*"
              placeholder="Ej. Estante A, Nivel 3"
              :error="form.errors.location"
            />

            <TextInput
              v-model="form.description"
              label="Descripción"
              placeholder="Detalles adicionales de la refacción"
              :isTextarea="true"
              :error="form.errors.description"
            />
            
            <div>
              <InputLabel value="Imagen de la refacción" />
              <FileUploader
                @files-selected="form.media = $event"
                acceptedFormat="image/*"
                :multiple="true"
                :maxFiles="3"
                class="mt-1"
              />
              <p class="text-xs text-gray-500 mt-1">Puedes subir <strong class="text-secondary dark:text-blue-200">máximo 3 imágenes </strong> (JPG, PNG, GIF). Máx 4MB.</p>
              <InputError :message="form.errors.media" class="mt-2" />
            </div>

            <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-end">
              <SecondaryButton :loading="form.processing">
                Registrar Refacción
              </SecondaryButton>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

// Props
const props = defineProps({
  selectedMachine: Number,
});

// Form state
const form = useForm({
  name: null,
  supplier: null,
  quantity: null,
  cost: null,
  location: null,
  description: null,
  machine_id: props.selectedMachine,
  media: null,
});

// Methods
const store = () => {
  form.post(route("spare-parts.store"), {
    onSuccess: () => {
      ElMessage({
        title: "Éxito",
        message: "Refacción registrada",
        type: "success",
      });
      form.reset();
    },
    onError: () => {
      ElMessage({
        title: "Error",
        message: "Hubo un problema al registrar la refacción. Revisa los campos.",
        type: "error",
      });
    }
  });
};
</script>