<template>
  <AppLayout title="Editar Mantenimiento">
    <!-- Encabezado con título y botón para volver -->
      <div class="flex justify-between items-center">
        <!-- CORREGIDO: Se usa maintenance.machine.id en lugar de machine.id -->
        <Back :href="route('machines.show', maintenance.machine.id)" />
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
          Editar Mantenimiento
        </h2>
      </div>

    <!-- Contenedor principal del formulario -->
    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
      <div ref="formContainer" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
        <!-- Formulario -->
        <form @submit.prevent="update">
          <div class="space-y-3">
            <!-- CORREGIDO: Se usa maintenance.machine.name en lugar de machine.name -->
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-right border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
              Máquina: {{ maintenance.machine.name }}
            </h3>

            <!-- Tipo de Mantenimiento -->
            <div>
              <InputLabel value="Tipo de mantenimiento*" class="mb-2" />
              <el-radio-group v-model="form.maintenance_type">
                <el-radio-button label="Preventivo" />
                <el-radio-button label="Correctivo" />
                <el-radio-button label="Limpieza" />
              </el-radio-group>
              <InputError :message="form.errors.maintenance_type" class="mt-2" />
            </div>

            <!-- Fecha de Realización -->
            <div>
              <InputLabel for="maintenance_date" value="Fecha de realización *" />
              <el-date-picker
                id="maintenance_date"
                v-model="form.maintenance_date"
                type="date"
                placeholder="Selecciona una fecha"
                format="DD/MM/YYYY"
                value-format="YYYY-MM-DD"
                class="w-full mt-1"
              />
              <InputError :message="form.errors.maintenance_date" class="mt-2" />
            </div>
            
            <!-- Campos condicionales según el tipo de mantenimiento -->

            <!-- Opciones para Limpieza -->
            <div v-if="form.maintenance_type === 'Limpieza'" class="space-y-2">
              <InputLabel value="Acciones de limpieza realizadas *" />
              <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                <label v-for="action in cleaningActions" :key="action" class="flex items-center">
                  <input type="checkbox" :value="action" v-model="form.actions" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary" />
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ action }}</span>
                </label>
              </div>
              <InputError :message="form.errors.actions" class="mt-2" />
            </div>

            <!-- Problemas para Correctivo -->
            <div v-if="form.maintenance_type === 'Correctivo'">
              <TextInput
                  v-model="form.problems"
                  type="text"
                  label="Problemas detectados*"
                  :error="form.errors.problems"
                  :isTextarea="true" :withMaxLength="true" :maxLength="500"
                  placeholder="Ej. Banda transportadora atascada"
              />
            </div>

            <!-- Acciones para Preventivo y Correctivo -->
            <div v-if="form.maintenance_type !== 'Limpieza'">
              <TextInput
                  v-model="form.actions"
                  type="text"
                  label="Acciones realizadas*"
                  :error="form.errors.actions"
                  :isTextarea="true" :withMaxLength="true" :maxLength="500"
                  placeholder="Ej. Sintonización de servo para la banda"
              />
            </div>

            <!-- Costo y Responsable -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <InputLabel for="cost" value="Costo del mantenimiento" />
                <el-input
                  id="cost"
                  v-model="form.cost"
                  :formatter="(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                  :parser="(value) => value.replace(/[^\d.]/g, '')"
                  placeholder="0.00"
                  class="mt-1"
                >
                  <template #prefix>
                    <i class="fa-solid fa-dollar-sign"></i>
                  </template>
                </el-input>
                <InputError :message="form.errors.cost" class="mt-2" />
              </div>
              <div>
                <TextInput
                    v-model="form.responsible"
                    type="text"
                    label="Responsable*"
                    :error="form.errors.responsible"
                    placeholder="Ej. Ing. Juan Pérez"
                />
              </div>
            </div>

            <div v-if="maintenance.media?.length" label="Archivos adjuntos" class="grid grid-cols-2 lg:grid-cols-3 gap-3 col-span-full mb-3">
                <label class="col-span-full text-gray-700 dark:text-white text-sm" for="">Archivos adjuntos</label>
                <FileView v-for="file in maintenance.media" :key="file" :file="file" :deletable="true"
                    @delete-file="deleteFile($event)" />
            </div>

            <!-- Carga de Archivos (Evidencia) -->
            <div v-if="maintenance.media?.length < 3">
              <InputLabel value="Imágenes de evidencia max. 3" />
              <!-- Aquí puedes mostrar las imágenes ya existentes si lo deseas -->
              <FileUploader @files-selected="form.media = $event" acceptedFormat="image/*" :multiple="true" :maxFiles="3 - maintenance.media.length" class="mt-1" />
              <p class="text-xs text-gray-500 mt-1">Puedes subir imágenes como JPG, PNG, GIF. Máx 4MB.</p>
              <InputError :message="form.errors.media" class="mt-2" />
            </div>

            <p class="text-amber-600 text-sm mt-4 col-span-full" v-else>*Has alcanzado el límite de imágenes elimina alguna para poder agregar más</p>

            <!-- Pie de página del formulario y botón de envío -->
            <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-end items-center">
              <SecondaryButton :loading="form.processing" :disabled="form.processing">
                Guardar cambios
              </SecondaryButton>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import { watch } from 'vue';

export default {
  components: {
    Back,
    FileView,
    AppLayout,
    TextInput,
    InputLabel,
    InputError,
    FileUploader,
    SecondaryButton,
  },
  props: {
    maintenance: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    // --- State ---
    const form = useForm({
      // CORREGIDO: Se agrega _method: 'put' para manejar la actualización con archivos.
      _method: 'put',
      // CORREGIDO: Se accede a las props con `props.maintenance` en lugar de `this.maintenance`.
      maintenance_type: props.maintenance.maintenance_type,
      maintenance_date: props.maintenance.maintenance_date,
      problems: props.maintenance.problems,
      // CORREGIDO: Si el tipo es 'Limpieza', convierte el string de acciones en un array para los checkboxes.
      actions: props.maintenance.maintenance_type === 'Limpieza' && props.maintenance.actions
               ? props.maintenance.actions.split(', ').filter(Boolean) // filter(Boolean) elimina strings vacíos
               : props.maintenance.actions || '',
      cost: props.maintenance.cost,
      responsible: props.maintenance.responsible,
      machine_id: props.maintenance.machine.id,
      media: null, // Este campo recibirá los nuevos archivos a subir.
    });

    const cleaningActions = [
      'Limpieza externa',
      'Limpieza interna',
      'Lubricación',
      'Inspección general',
    ];

    // --- Watchers ---
    // Limpia el campo 'actions' cuando cambia el tipo de mantenimiento para evitar enviar datos incorrectos.
    watch(() => form.maintenance_type, (newType, oldType) => {
      // Si el tipo anterior era 'Limpieza', las acciones eran un array, ahora debe ser un string.
      if (oldType === 'Limpieza') {
        form.actions = '';
      }
      // Si el nuevo tipo es 'Limpieza', las acciones deben ser un array.
      if (newType === 'Limpieza') {
        form.actions = [];
      }
      form.clearErrors('actions');
    });

    // --- Methods ---
    function update() {
      // Usamos form.post porque permite enviar archivos (multipart/form-data).
      // El campo `_method: 'put'` que agregamos al `form` le dirá a Laravel
      // que trate esta petición POST como si fuera una petición PUT.
      form.transform(data => ({
        ...data,
        // Aseguramos que las acciones de 'Limpieza' se envíen como un string.
        actions: Array.isArray(data.actions) ? data.actions.join(', ') : data.actions,
      })).post(route("maintenances.update", props.maintenance.id), {
        onSuccess: () => {
          ElMessage({
              type: 'success',
              message: 'Mantenimiento actualizado correctamente.',
          });
        },
        onError: (errors) => {
          console.error("Errores de validación:", errors);
          ElMessage({
              type: 'error',
              message: 'Hubo un porblema. Por favor, revisa los campos del formulario.',
          });
        }
      });
    }

    function deleteFile(fileId) {
      props.maintenance.media = props.maintenance.media.filter(m => m.id !== fileId);
    }

    // Se retornan las variables y funciones para que estén disponibles en el template.
    return {
      form,
      cleaningActions,
      update,
      deleteFile,
    };
  },
};
</script>
