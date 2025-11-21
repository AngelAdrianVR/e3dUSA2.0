<template>
  <AppLayout title="Eitar Refaccion">
    <div class="flex justify-between items-center">
      <Back />
      <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
        Editar Refacción
      </h2>
    </div>

    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
        <form @submit.prevent="update">
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

            <div v-if="spare_part.media?.length" label="Archivos adjuntos" class="grid grid-cols-2 lg:grid-cols-3 gap-3 col-span-full mb-3">
                <label class="col-span-full text-gray-700 dark:text-white text-sm" for="">Archivos adjuntos</label>
                <FileView v-for="file in spare_part.media" :key="file" :file="file" :deletable="true"
                    @delete-file="deleteFile($event)" />
            </div>
            
            <div v-if="spare_part.media?.length < 3">
              <InputLabel value="Imagen de la refacción" />
              <FileUploader
                @files-selected="form.media = $event"
                acceptedFormat="image/*"
                :multiple="true"
                :maxFiles="3 - spare_part.media?.length"
                class="mt-1"
              />
              <p class="text-xs text-gray-500 mt-1">Puedes subir <strong class="text-secondary dark:text-blue-200">máximo 3 imágenes </strong> (JPG, PNG, GIF). Máx 4MB.</p>
              <InputError :message="form.errors.media" class="mt-2" />
            </div>

            <p class="text-amber-600 text-sm mt-4 col-span-full" v-else>*Has alcanzado el límite de imágenes elimina alguna para poder agregar más</p>

            <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-end">
              <SecondaryButton :loading="form.processing">
                Guardar cambios
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
import FileView from "@/Components/MyComponents/FileView.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

// Props
const props = defineProps({
  spare_part: Object,
});

// Form state
const form = useForm({
  name: props.spare_part.name,
  supplier: props.spare_part.supplier,
  quantity: props.spare_part.quantity,
  cost: props.spare_part.cost,
  location: props.spare_part.location,
  description: props.spare_part.description,
  machine_id: props.spare_part.machine_id,
  media: null,
  _method: 'put'
});

const deleteFile = (fileId) => {
  props.spare_part.media = props.spare_part.media.filter(m => m.id !== fileId);
}

// Methods
const update = () => {
  form.post(route("spare-parts.update", props.spare_part), {
    onSuccess: () => {
      ElMessage({
        title: "Éxito",
        message: "Refacción Actualizada",
        type: "success",
      });
      form.reset();
    },
    onError: () => {
      ElMessage({
        title: "Error",
        message: "Hubo un problema al editar la refacción. Revisa los campos.",
        type: "error",
      });
    }
  });
};
</script>