<template>
  <AppLayout title="Editar Mantenimiento">
    <!-- Encabezado con título y botón para volver -->
    <div class="flex justify-between items-center">
        <Back :href="route('machines.show', maintenance.machine.id)" />
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Editar Mantenimiento
        </h2>
    </div>

    <!-- Contenedor principal del formulario -->
    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
        <!-- Formulario -->
        <form @submit.prevent="update">
          <div class="space-y-6">
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

              <div v-if="form.spare_parts_used.length" class="mt-4 space-y-3 animate-fade-in">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Especifica la cantidad utilizada:</h4>
                <div v-for="part in form.spare_parts_used" :key="part.id" class="flex items-center justify-between gap-4 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-md">
                    <span class="text-sm text-gray-900 dark:text-gray-200">{{ part.name }}</span>
                    <el-input-number
                        v-model="part.quantity"
                        :min="1"
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
                  label="Costo del mantenimiento*">
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
                />
              </div>
            </div>

            <!-- Archivos adjuntos existentes -->
            <div v-if="maintenance.media?.length" class="col-span-full">
                <InputLabel value="Archivos adjuntos" />
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                    <FileView v-for="file in maintenance.media" :key="file.id" :file="file" :deletable="true"
                        @delete-file="deleteFile($event)" />
                </div>
            </div>

            <!-- Carga de Archivos (Evidencia) -->
            <div v-if="maintenance.media?.length < 3">
              <InputLabel value="Añadir nuevas imágenes (máx. 3 en total)" />
              <FileUploader @files-selected="form.media = $event" acceptedFormat="image/*" :multiple="true" :maxFiles="3 - maintenance.media.length" class="mt-1" />
              <p class="text-xs text-gray-500 mt-1">Puedes subir imágenes como JPG, PNG, GIF. Máx 4MB.</p>
              <InputError :message="form.errors.media" class="mt-2" />
            </div>
            <p class="text-amber-600 text-sm col-span-full" v-else>*Has alcanzado el límite de imágenes. Elimina alguna para poder agregar más.</p>

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

<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { ElMessage, ElMessageBox } from 'element-plus';
import { useForm, router } from "@inertiajs/vue3";
import { watch } from 'vue';

// --- Props ---
const props = defineProps({
    maintenance: Object,
    spare_parts: Array,
});

// --- State ---
const form = useForm({
  _method: 'put',
  maintenance_type: props.maintenance.maintenance_type,
  maintenance_date: props.maintenance.maintenance_date,
  problems: props.maintenance.problems,
  actions: props.maintenance.maintenance_type === 'Limpieza' && props.maintenance.actions
           ? props.maintenance.actions.split(', ').filter(Boolean)
           : props.maintenance.actions || '',
  cost: props.maintenance.cost,
  responsible: props.maintenance.responsible,
  machine_id: props.maintenance.machine.id,
  media: null,
  spare_part_ids: props.maintenance.spare_parts_used?.map(part => part.id) || [],
  spare_parts_used: props.maintenance.spare_parts_used || [],
});

const cleaningActions = [
  'Limpieza externa', 'Limpieza interna', 'Lubricación', 'Inspección general',
];

// --- Watchers ---
watch(() => form.maintenance_type, (newType) => {
  if (newType === 'Limpieza') {
    form.actions = [];
    form.spare_part_ids = [];
  } else if (Array.isArray(form.actions)) {
    form.actions = '';
  }
  form.clearErrors('actions');
});

watch(() => form.spare_part_ids, (newIds) => {
    newIds.forEach(id => {
        const isAlreadyAdded = form.spare_parts_used.some(p => p.id === id);
        if (!isAlreadyAdded) {
            const sparePart = props.spare_parts.find(p => p.id === id);
            if (sparePart) {
                form.spare_parts_used.push({ id: sparePart.id, name: sparePart.name, quantity: 1 });
            }
        }
    });
    form.spare_parts_used = form.spare_parts_used.filter(p => newIds.includes(p.id));
}, { deep: true });

// --- Methods ---
function update() {
  form.transform(data => ({
    ...data,
    actions: Array.isArray(data.actions) ? data.actions.join(', ') : data.actions,
    spare_parts_used: data.spare_parts_used.filter(p => p.quantity > 0),
  })).post(route("maintenances.update", props.maintenance.id), {
    onSuccess: () => ElMessage.success('Mantenimiento actualizado correctamente.'),
    onError: () => ElMessage.error('Hubo un problema. Revisa los campos del formulario.'),
  });
}

function deleteFile(fileId) {
    ElMessageBox.confirm('¿Realmente deseas eliminar este archivo? La acción no se puede deshacer.', 'Confirmar eliminación', {
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        type: 'warning',
    }).then(() => {
        router.delete(route('media.destroy', fileId), {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Archivo eliminado.'),
        });
    });
}
</script>

<style>
.animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
</style>
