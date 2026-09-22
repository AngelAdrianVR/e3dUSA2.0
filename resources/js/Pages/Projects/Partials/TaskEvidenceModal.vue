<template>
    <DialogModal :show="show" max-width="xl" @close="$emit('close')">
        <template #title>
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-paperclip"></i>
                <span>Finalizar tarea con evidencia</span>
            </div>
        </template>

        <template #content>
            <div class="space-y-4 pt-1">
                <!-- Tarea que se va a finalizar -->
                <div class="bg-gray-50 dark:bg-zinc-800 rounded-lg p-3 border border-gray-200 dark:border-zinc-700">
                    <p class="text-[10px] uppercase font-bold text-gray-400">Tarea</p>
                    <p class="font-semibold text-sm text-gray-800 dark:text-gray-100">{{ task?.title }}</p>
                </div>

                <!-- Retroalimentación de validación (dentro del modal: los toasts quedan detrás del dialog) -->
                <div v-if="evidenceError"
                    class="flex items-start gap-2 rounded-lg border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/30 px-3 py-2.5 text-sm text-red-700 dark:text-red-300">
                    <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                    <span>{{ evidenceError }}</span>
                </div>

                <!-- Evidencia (obligatoria) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Evidencia * <span class="text-gray-400 font-normal">(foto, documento o video · máximo 3 archivos)</span>
                    </label>
                    <FileUploader :multiple="true" format="Todo" :max-files="3" :max-file-size="20" @files-selected="onFilesSelected" />
                </div>

                <!-- Notas opcionales -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas (opcional)</label>
                    <textarea v-model="form.completion_notes" rows="3"
                        class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-y"
                        placeholder="Comentarios sobre el trabajo terminado, pendientes, etc."></textarea>
                    <p v-if="form.errors.completion_notes" class="text-red-500 text-xs mt-1">{{ form.errors.completion_notes }}</p>
                </div>

                <p class="text-[11px] text-gray-500 dark:text-gray-400 flex items-start gap-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/40 rounded-lg p-2.5">
                    <i class="fa-solid fa-lock text-blue-500 mt-0.5"></i>
                    <span>La evidencia y las notas de finalización solo las puede ver el <b>Administrador del proyecto</b>.</span>
                </p>
            </div>
        </template>

        <template #footer>
            <div class="flex items-center gap-3">
                <SecondaryButton @click="$emit('close')" :disabled="form.processing">Cancelar</SecondaryButton>
                <PrimaryButton @click="submit" :disabled="form.processing" :class="{ 'opacity-50': form.processing }">
                    <i class="fa-solid fa-circle-check mr-2"></i> Finalizar tarea
                </PrimaryButton>
            </div>
        </template>
    </DialogModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import FileUploader from '@/Components/MyComponents/FileUploader.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    task: { type: Object, default: null },
    projectId: { type: [Number, String], required: true },
});

const emit = defineEmits(['close', 'finished']);

const form = useForm({
    files: [],
    completion_notes: '',
});

// Mensaje de validación visible DENTRO del modal
const evidenceError = ref('');

const onFilesSelected = (files) => {
    form.files = files;
    evidenceError.value = '';
};

watch(() => props.show, (open) => {
    if (open) {
        form.clearErrors();
        form.files = [];
        form.completion_notes = '';
        evidenceError.value = '';
    }
});

const submit = () => {
    evidenceError.value = '';

    if (!form.files.length) {
        evidenceError.value = 'Debes adjuntar al menos una evidencia (foto, documento o video) para finalizar la tarea.';
        return;
    }

    form.post(route('projects.tasks.finish', [props.projectId, props.task.id]), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            ElMessage.success('Tarea finalizada con evidencia.');
            emit('finished');
            emit('close');
        },
        onError: () => {
            evidenceError.value = form.errors.files
                || form.errors['files.0']
                || form.errors.completion_notes
                || 'No se pudo finalizar la tarea. Revisa la evidencia adjunta.';
        },
    });
};
</script>
