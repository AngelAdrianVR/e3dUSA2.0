<template>
  <AppLayout title="Agendar en Calendario">
    <div class="p-1 lg:p4 min-h-screen font-sans">
      <Back />
      <!-- Encabezado con título y botón para volver -->
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Agendar Recordatorio</h1>

      <!-- Contenedor principal del formulario -->
      <div class="max-w-3xl mx-auto mt-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-8">
          <form @submit.prevent="store">
            <div ref="formContainer" class="space-y-4">
              <!-- Tipo de Entrada -->
              <div>
                <InputLabel value="Tipo de entrada*" class="mb-2" />
                <el-radio-group v-model="form.entry_type" size="large">
                  <el-radio-button label="Evento">
                    <i class="fa-solid fa-users mr-2"></i> Evento
                  </el-radio-button>
                  <el-radio-button label="Tarea">
                    <i class="fa-solid fa-list-check mr-2"></i> Tarea
                  </el-radio-button>
                </el-radio-group>
                <InputError :message="form.errors.entry_type" class="mt-2" />
              </div>

              <!-- Título -->
              <div>
                <TextInput id="title" v-model="form.title" :label="'Título*'" :error="form.errors.title" type="text"
                  class="mt-1 block w-full" placeholder="Ej. Reunión de equipo" />
              </div>

              <!-- Fechas y Horas -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <InputLabel for="start_datetime" value="Inicio*" />
                  <el-date-picker id="start_datetime" v-model="form.start_datetime" type="datetime"
                    placeholder="Selecciona fecha y hora" class="w-full mt-1" format="DD/MM/YYYY hh:mm A"
                    value-format="YYYY-MM-DD HH:mm:ss" />
                  <InputError :message="form.errors.start_datetime" class="mt-2" />
                </div>
                <div>
                  <InputLabel for="end_datetime" value="Fin*" />
                  <el-date-picker id="end_datetime" v-model="form.end_datetime" type="datetime"
                    placeholder="Selecciona fecha y hora" class="w-full mt-1" format="DD/MM/YYYY hh:mm A"
                    value-format="YYYY-MM-DD HH:mm:ss" />
                  <InputError :message="form.errors.end_datetime" class="mt-2" />
                </div>
              </div>

              <!-- Descripción -->
              <div>
                <TextInput v-model="form.description" label="Descripción*" :error="form.errors.description"
                  :isTextarea="true" :withMaxLength="true" :maxLength="500"
                  placeholder="Escriba la descripción de tu recordatorio" />
              </div>

              <!-- Campos Condicionales para Evento -->
              <div v-if="form.entry_type === 'Evento'" class="space-y-4 border-t dark:border-gray-700 pt-4">
                <!-- Ubicación -->
                <div>
                  <TextInput v-model="form.location" :label="'Ubicación*'" :error="form.errors.location" type="text"
                    class="mt-1 block w-full" placeholder="Ej. Sala de juntas 1 o Enlace de Meet" />
                </div>
                <!-- URL de conferencia -->
                <div>
                  <TextInput v-model="form.conference_link" :label="'URL de conferencia'"
                    :error="form.errors.conference_link" type="text" class="mt-1 block w-full"
                    placeholder="Pega el link de la conferencia si lo tienes" />
                </div>

                <!-- Participantes -->
                <div>
                  <InputLabel for="participants" value="Participantes*" />
                  <el-select id="participants" v-model="form.participants" multiple filterable
                    placeholder="Busca y selecciona usuarios" class="w-full mt-1">
                    <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                  </el-select>
                  <InputError :message="form.errors.participants" class="mt-2" />
                </div>
              </div>

              <!-- Botón de Envío -->
              <div class="flex justify-end pt-4 border-t dark:border-gray-700">
                <SecondaryButton :loading="form.processing" :disabled="form.processing">
                  Agendar
                </SecondaryButton>
              </div>

            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import { ElMessage } from 'element-plus';
import { Link } from "@inertiajs/vue3";
import { format } from 'date-fns'; // Importamos la función de formato

export default {
  // Usando Options API
  components: {
    AppLayout,
    SecondaryButton,
    InputError,
    InputLabel,
    TextInput,
    Link,
    Back,
  },
  props: {
    users: Array,
    selectedDate: String, // Recibimos la fecha desde el controlador
  },
  data() {
    return {
      form: this.$inertia.form({
        entry_type: 'Evento',
        title: '',
        description: '',
        start_datetime: null,
        end_datetime: null,
        is_full_day: false,
        location: '',
        conference_link: '',
        participants: [],
      }),
    };
  },
  methods: {
    store() {
      this.form.post(route('calendar.store'), {
        onSuccess: () => {
          ElMessage.success('Entrada creada con éxito');
        },
        onError: () => {
          ElMessage.error('Hubo un problema al crear la entrada. Revisa los campos.');
          this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
        }
      });
    },
    /**
     * Inicializa las fechas del formulario si se proporciona una fecha seleccionada.
     */
    initializeDate() {
      if (this.selectedDate) {
        // Establece la fecha de inicio a las 9:00 AM del día seleccionado
        const startDate = new Date(`${this.selectedDate}T09:00:00`);
        // Establece la fecha de fin una hora después
        const endDate = new Date(startDate.getTime() + 60 * 60 * 1000);

        // Formateamos las fechas al formato que espera el backend y el date-picker
        this.form.start_datetime = format(startDate, 'yyyy-MM-dd HH:mm:ss');
        this.form.end_datetime = format(endDate, 'yyyy-MM-dd HH:mm:ss');
      }
    }
  },
  watch: {
    // Limpia los campos de evento si el usuario cambia a Tarea
    'form.entry_type'(newType) {
      if (newType === 'Tarea') {
        this.form.location = '';
        this.form.conference_link = '';
        this.form.participants = [];
      }
    }
  },
  /**
   * Cuando el componente se monta en el DOM, llamamos a la función
   * para inicializar la fecha.
   */
  mounted() {
    this.initializeDate();
  }
};
</script>

<style>
/* Estilos para que el-select y el-date-picker se vean bien con Tailwind */
.el-select,
.el-date-editor {
  width: 100% !important;
}
</style>
