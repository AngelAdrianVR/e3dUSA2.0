<template>
  <AppLayout title="Registrar Mantenimiento">
    <!-- Encabezado con título y botón para volver -->
    <template #header>
      <div class="flex justify-between items-center">
        <Back :href="route('machines.show', machine.id)" />
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
          Registrar Mantenimiento
        </h2>
      </div>
    </template>

    <!-- Contenedor principal del formulario -->
    <div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8">
      <div ref="formContainer" class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
        <!-- Formulario -->
        <form @submit.prevent="store">
          <div class="space-y-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-right border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
              Máquina: {{ machine.name }}
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

            <!-- Carga de Archivos (Evidencia) -->
            <div>
              <InputLabel value="Imágenes de evidencia max. 3" />
              <FileUploader @files-selected="form.media = $event" acceptedFormat="image/*" :multiple="true" :maxFiles="3" class="mt-1" />
              <p class="text-xs text-gray-500 mt-1">Puedes subir imágenes como JPG, PNG, GIF. Máx 4MB.</p>
              <InputError :message="form.errors.media" class="mt-2" />
            </div>

            <!-- Pie de página del formulario y botón de envío -->
            <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-between items-center">
              <p class="text-xs text-gray-500 dark:text-gray-300">Código de registro: REG-MT-05</p>
              <SecondaryButton :loading="form.processing">
                {{ form.maintenance_type === 'Limpieza' ? 'Enviar a validación' : 'Registrar Mantenimiento' }}
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
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { ElMessage, ElMessageBox } from 'element-plus'; // Para notificaciones
import { useForm } from "@inertiajs/vue3";
import { watch, ref, nextTick } from 'vue';

export default {
  components: {
    Back,
    AppLayout,
    TextInput,
    InputLabel,
    InputError,
    FileUploader,
    SecondaryButton,
  },
  props: {
    machine: Object,
  },
  setup(props) {
    // --- State ---
    const form = useForm({
      maintenance_type: "Preventivo",
      maintenance_date: new Date().toISOString().split('T')[0], // Formato YYYY-MM-DD
      problems: null,
      actions: [], // Usar array para checkboxes, se convierte a string para los otros tipos
      cost: null,
      responsible: null,
      machine_id: props.machine.id,
      media: null,
    });

    const formContainer = ref(null);

    const cleaningActions = [
      'Limpieza externa',
      'Limpieza interna',
      'Lubricación',
      'Inspección general',
    ];

    // --- Watchers ---
    // Limpia el campo 'actions' cuando cambia el tipo de mantenimiento para evitar enviar datos incorrectos.
    watch(() => form.maintenance_type, (newType) => {
      if (newType === 'Limpieza') {
        form.actions = []; // Resetear a array para los checkboxes
      } else {
        form.actions = ''; // Resetear a string para el textarea
      }
      form.clearErrors('actions'); // Limpiar errores de validación del campo
    });

    // --- Methods ---
    function store() {
      // Clona y transforma los datos antes de enviar.
      // Si el tipo es 'Limpieza', convierte el array de acciones a un string separado por comas.
      const dataToPost = {
        ...form.data(),
        actions: Array.isArray(form.actions) ? form.actions.join(', ') : form.actions,
      };

      form.transform(() => dataToPost).post(route("maintenances.store"), {
        onSuccess: () => {
          ElMessage({
              type: 'success',
              message: 'Mantenimiento registrado',
          });
          form.reset();
        },
        onError: () => {
          ElMessage({
              type: 'warning',
              message: 'Puedes tener campos sin llenar',
          });
        }
      });
    }

    return {
      form,
      cleaningActions,
      store,
    };
  },
};
</script>
