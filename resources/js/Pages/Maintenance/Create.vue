<template>
  <AppLayout title="Registrar Mantenimiento">
    <!-- Encabezado con título y botón para volver -->
    <div class="flex justify-between items-center">
        <Back :href="route('machines.show', machine.id)" />
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Registrar Mantenimiento
        </h2>
    </div>

    <!-- Contenedor principal del formulario -->
    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
        <!-- Formulario -->
        <form @submit.prevent="store">
          <div class="space-y-6">
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
            
            <!-- Opciones para Limpieza -->
            <div v-if="form.maintenance_type === 'Limpieza'" class="space-y-2">
              <InputLabel value="Acciones de limpieza realizadas *" />
              <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                <label v-for="action in cleaningActions" :key="action" class="flex items-center">
                  <input type="checkbox" :value="action" v-model="form.actions" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary dark:bg-slate-900 dark:border-slate-600" />
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ action }}</span>
                </label>
              </div>
              <InputError :message="form.errors.actions" class="mt-2" />
            </div>

            <!-- Problemas para Correctivo -->
            <div v-if="form.maintenance_type === 'Correctivo'">
              <TextInput
                  v-model="form.problems"
                  label="Problemas detectados*"
                  :error="form.errors.problems"
                  :isTextarea="true"
                  :withMaxLength="true"
                  placeholder="Ej. Banda transportadora atascada"
              />
            </div>

            <!-- Acciones para Preventivo y Correctivo -->
            <div v-if="form.maintenance_type !== 'Limpieza'">
              <TextInput
                  v-model="form.actions"
                  label="Acciones realizadas*"
                  :error="form.errors.actions"
                  :isTextarea="true"
                  :withMaxLength="true"
                  placeholder="Ej. Sintonización de servo para la banda"
              />
            </div>
            
            <!-- Refacciones usadas para Preventivo y Correctivo -->
            <div v-if="form.maintenance_type !== 'Limpieza'" class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
              <InputLabel value="Refacciones utilizadas (opcional)" class="!text-base font-semibold" />
              <el-select
                v-model="form.spare_part_ids"
                multiple
                filterable
                placeholder="Busca y selecciona las refacciones"
                class="w-full"
              >
                <el-option
                  v-for="item in spare_parts"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>

              <!-- Lista de refacciones seleccionadas para agregar cantidad -->
              <div v-if="form.spare_parts_used.length" class="mt-4 space-y-3 animate-fade-in">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Especifica la cantidad utilizada:</h4>
                <div v-for="part in form.spare_parts_used" :key="part.id" class="flex items-center justify-between gap-4 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-md">
                    <span class="text-sm text-gray-900 dark:text-gray-200">{{ part.name }}</span>
                    <small v-if="part.quantity == spare_parts.find(sp => sp.id == part.id).quantity" class="text-orange-500">*Ya no tienes más de esa refacción</small>
                    <el-input-number
                        v-model="part.quantity"
                        :min="1"
                        :max="spare_parts.find(sp => sp.id == part.id).quantity"
                        size="small"
                        controls-position="right"
                        class="w-28"
                    />
                </div>
              </div>
              <InputError :message="form.errors.spare_parts_used" class="mt-2" />
            </div>

            <!-- Costo y Responsable -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <TextInput 
                  v-model="form.cost" 
                  :error="form.errors.cost"
                  label="Costo del mantenimiento*"
                  :formatAsNumber="true">
                  <template #icon-left>
                    <i class="fa-solid fa-dollar-sign"></i>
                  </template>
                </TextInput>
              </div>
              <div>
                <TextInput
                    v-model="form.responsible"
                    label="Responsable*"
                    :error="form.errors.responsible"
                    placeholder="Ej. Ing. Juan Pérez"
                />
              </div>
            </div>

            <!-- Carga de Archivos (Evidencia) -->
            <div>
              <InputLabel value="Imágenes de evidencia (máx. 3)" />
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

<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import { watch } from 'vue';

// --- Props ---
const props = defineProps({
    machine: Object,
    spare_parts: Array,
});

// --- State ---
const form = useForm({
  maintenance_type: "Preventivo",
  maintenance_date: new Date().toISOString().split('T')[0], // Formato YYYY-MM-DD
  problems: null,
  actions: [],
  cost: null,
  responsible: null,
  machine_id: props.machine.id,
  media: null,
  spare_part_ids: [], // Array temporal para v-model del select
  spare_parts_used: [], // Array final de objetos {id, name, quantity} para el backend
});

const cleaningActions = [
  'Limpieza externa',
  'Limpieza interna',
  'Lubricación',
  'Inspección general',
];

// --- Watchers ---
// Sincroniza el tipo de mantenimiento con el campo 'actions'
watch(() => form.maintenance_type, (newType) => {
  if (newType === 'Limpieza') {
    form.actions = []; // Resetear a array para los checkboxes
    form.spare_part_ids = []; // Limpiar refacciones si es limpieza
  } else {
    form.actions = ''; // Resetear a string para el textarea
  }
  form.clearErrors('actions');
});

// Sincroniza las refacciones seleccionadas con la lista para agregar cantidades
watch(() => form.spare_part_ids, (newIds) => {
    // 1. Añade las nuevas refacciones que no están en la lista `spare_parts_used`
    newIds.forEach(id => {
        const isAlreadyAdded = form.spare_parts_used.some(p => p.id === id);
        if (!isAlreadyAdded) {
            const sparePart = props.spare_parts.find(p => p.id === id);
            if (sparePart) {
                form.spare_parts_used.push({
                    id: sparePart.id,
                    name: sparePart.name,
                    quantity: 1 // Cantidad por defecto
                });
            }
        }
    });

    // 2. Elimina de `spare_parts_used` aquellas que ya no están seleccionadas en `spare_part_ids`
    form.spare_parts_used = form.spare_parts_used.filter(p => newIds.includes(p.id));
}, { deep: true });


// --- Methods ---
function store() {
  form.transform(data => {
    // Prepara los datos justo antes de enviarlos
    const transformedData = {
      ...data,
      // Convierte el array de acciones a string si es necesario
      actions: Array.isArray(data.actions) ? data.actions.join(', ') : data.actions,
      // Filtra las refacciones para asegurar que tengan cantidad mayor a 0
      spare_parts_used: data.spare_parts_used.filter(p => p.quantity > 0)
    };
    // Elimina el array de IDs temporal, ya no se necesita en el backend
    delete transformedData.spare_part_ids;
    return transformedData;

  }).post(route("maintenances.store"), {
    onSuccess: () => {
      ElMessage({
          type: 'success',
          message: 'Mantenimiento registrado con éxito',
      });
      form.reset();
    },
    onError: (errors) => {
      console.error(errors);
      ElMessage({
          type: 'error',
          message: 'Hubo un problema al registrar. Revisa los campos del formulario.',
      });
    }
  });
}
</script>

<style>
/* Animación para la aparición suave de la lista de refacciones */
.animate-fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
