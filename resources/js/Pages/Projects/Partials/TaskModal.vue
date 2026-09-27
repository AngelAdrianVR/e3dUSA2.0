<template>
    <DialogModal :show="show" max-width="4xl" @close="$emit('close')">
        <template #title>
            <div class="flex items-center gap-2">
                <i class="fa-solid" :class="isEditing ? 'fa-list-check' : 'fa-circle-plus'"></i>
                <span>{{ isEditing ? (canEdit ? 'Editar tarea' : 'Detalle de tarea') : 'Nueva tarea' }}</span>
            </div>
        </template>

        <template #content>
            <div class="space-y-4 pt-1">
                <!-- ===== FORMULARIO (solo si tiene permiso de escritura) ===== -->
                <template v-if="canEdit">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Título *</label>
                        <input v-model="form.title" type="text"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Ej. Diseñar molde del emblema" />
                        <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                        <textarea v-model="form.description" rows="2"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-y"
                            placeholder="Detalle de la tarea"></textarea>
                        <p v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de inicio *</label>
                            <el-date-picker v-model="form.start_date" :teleported="false" type="date" value-format="YYYY-MM-DD" class="w-full" placeholder="Inicio" />
                            <p v-if="form.errors.start_date" class="text-red-500 text-xs mt-1">{{ form.errors.start_date }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha límite de finalización</label>
                            <el-date-picker v-model="form.due_date" :teleported="false" type="date" value-format="YYYY-MM-DD" class="w-full" placeholder="Fecha límite" />
                            <p v-if="form.errors.due_date" class="text-red-500 text-xs mt-1">{{ form.errors.due_date }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estatus</label>
                            <el-select v-model="form.status" :teleported="false" class="w-full">
                                <el-option v-for="s in statuses" :key="s" :label="s" :value="s" />
                            </el-select>
                            <p v-if="form.errors.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Asignado a <span class="text-gray-400 font-normal">(solo miembros)</span>
                            </label>
                            <el-select v-model="form.assigned_to" :teleported="false" filterable clearable placeholder="Sin asignar" class="w-full">
                                <el-option v-for="m in projectMembers" :key="m.id" :label="m.name" :value="m.id" />
                            </el-select>
                            <p v-if="form.errors.assigned_to" class="text-red-500 text-xs mt-1">{{ form.errors.assigned_to }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivos / imágenes</label>
                        <FileUploader :multiple="true" format="Todo" :max-files="10" :max-file-size="10" @files-selected="form.files = $event" />
                        <p v-if="form.errors.files" class="text-red-500 text-xs mt-1">{{ form.errors.files }}</p>
                    </div>

                    <!-- Tiempo invertido: solo Administradores del proyecto -->
                    <div v-if="isEditing && canSeeTaskTime"
                        class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-900/40 rounded-lg px-3 py-2">
                        <i class="fa-solid fa-stopwatch text-emerald-600 dark:text-emerald-400"></i>
                        <p class="text-sm">
                            <span class="font-semibold text-emerald-700 dark:text-emerald-300">Tiempo invertido:</span>
                            <span class="font-bold ml-1">{{ formattedTime }}</span>
                            <span v-if="task.is_timer_running" class="ml-2 text-[11px] text-emerald-600 dark:text-emerald-400">(cronómetro en marcha)</span>
                        </p>
                    </div>

                    <!-- Evidencia obligatoria al finalizar (solo si aún no existe) -->
                    <div v-if="requiresEvidence"
                        class="space-y-2 border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
                        <label class="block text-sm font-medium text-amber-800 dark:text-amber-300">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            Evidencia de finalización * <span class="font-normal">(foto, documento o video · máx. 3)</span>
                        </label>
                        <FileUploader :multiple="true" format="Todo" :max-files="3" :max-file-size="20" @files-selected="onEvidenceSelected" />
                        <!-- Mensaje de validación dentro del modal (los toasts quedan detrás del dialog) -->
                        <div v-if="evidenceFeedback"
                            class="flex items-start gap-2 rounded-lg border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/30 px-3 py-2 text-xs text-red-700 dark:text-red-300">
                            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                            <span>{{ evidenceFeedback }}</span>
                        </div>
                        <textarea v-model="form.completion_notes" rows="2"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-sm resize-y"
                            placeholder="Notas de finalización (opcional)"></textarea>
                    </div>

                    <div v-if="isEditing && filesMedia.length" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Archivos actuales</label>
                        <FileView v-for="file in filesMedia" :key="file.id" :file="file" deletable @delete-file="removeTaskFile(file.id)" />
                    </div>

                    <!-- Evidencia de finalización: solo Administradores del proyecto -->
                    <div v-if="isEditing && canSeeTaskTime && (evidenceMedia.length || task.completion_notes)"
                        class="space-y-2 border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/60 dark:bg-emerald-900/10 rounded-lg p-3">
                        <label class="block text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                            <i class="fa-solid fa-lock mr-1"></i> Evidencia de finalización
                            <span class="font-normal text-[11px]">(visible solo para Administradores)</span>
                        </label>
                        <FileView v-for="file in evidenceMedia" :key="file.id" :file="file" />
                        <p v-if="task.completion_notes" class="text-xs text-gray-600 dark:text-gray-300 whitespace-pre-wrap">
                            <span class="font-semibold">Notas de finalización:</span> {{ task.completion_notes }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <PrimaryButton @click="submit" :disabled="form.processing" :class="{ 'opacity-50': form.processing }">
                            <i class="fa-solid fa-floppy-disk mr-2"></i>
                            {{ isEditing ? 'Guardar cambios' : 'Crear tarea' }}
                        </PrimaryButton>
                        <el-popconfirm v-if="isEditing" title="¿Eliminar esta tarea?" :teleported="false" confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444" @confirm="deleteTask">
                            <template #reference>
                                <button type="button" class="text-red-500 hover:text-red-700 text-sm font-medium inline-flex items-center gap-1 px-2 py-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                    <i class="fa-solid fa-trash-can"></i> Eliminar tarea
                                </button>
                            </template>
                        </el-popconfirm>
                    </div>
                </template>

                <!-- ===== VISTA COLABORADOR (miembros sin permiso de escritura) ===== -->
                <template v-else-if="isEditing">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <p><span class="font-semibold text-gray-500">Estatus:</span> <el-tag size="small" :type="statusTagType">{{ task.status }}</el-tag></p>
                        <p><span class="font-semibold text-gray-500">Asignado a:</span> {{ task.assignee?.name || 'Sin asignar' }}</p>
                        <p><span class="font-semibold text-gray-500">Inicio:</span> {{ formatDate(task.start_date) }}</p>
                        <p><span class="font-semibold text-gray-500">Fecha límite:</span> {{ task.due_date ? formatDate(task.due_date) : '—' }}</p>
                        <p v-if="task.finished_at" class="col-span-2"><span class="font-semibold text-gray-500">Finalizada el:</span> {{ formatDate(task.finished_at) }}</p>
                        <p v-if="task.description" class="col-span-2 text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ task.description }}</p>
                    </div>
                    <div v-if="task.media?.length" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Archivos</label>
                        <FileView v-for="file in task.media" :key="file.id" :file="file" />
                    </div>
                </template>

                <!-- ===== CALIFICACIÓN DEL DESEMPEÑO ===== 
                     Todos los miembros la ven; solo quien creó el proyecto puede calificar/editar. -->
                <div v-if="isEditing" class="border-t border-gray-200 dark:border-zinc-700 pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2 flex items-center gap-2">
                        <i class="fa-solid fa-star text-amber-400"></i> Calificación del desempeño
                        <span class="text-xs text-gray-400 font-normal">(tiempo, resultados y eficiencia)</span>
                    </h4>

                    <div class="bg-amber-50/70 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-900/40 rounded-lg p-3 space-y-2">
                        <!-- Estrellas -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="flex items-center gap-1">
                                <button v-for="star in 5" :key="star" type="button" :disabled="!canRate"
                                    @click="setRating(star)" @mouseenter="hoverRating = star" @mouseleave="hoverRating = 0"
                                    class="text-xl leading-none transition-transform"
                                    :class="[
                                        canRate ? 'hover:scale-110 cursor-pointer' : 'cursor-default',
                                        (hoverRating || displayRating) >= star ? 'text-amber-400' : 'text-gray-300 dark:text-slate-600'
                                    ]"
                                    :title="canRate ? `Calificar con ${star} estrellas` : 'Solo el jefe de proyecto puede calificar'">
                                    <i class="fa-solid fa-star"></i>
                                </button>
                            </div>
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-200">
                                {{ displayRating ? displayRating + '/5' : 'Sin calificar' }}
                            </span>
                            <span v-if="canRate" class="text-[11px] text-amber-700 dark:text-amber-400">
                                <i class="fa-solid fa-crown mr-1"></i>Eres el jefe de proyecto
                            </span>
                        </div>

                        <!-- Edición (solo creador del proyecto) -->
                        <template v-if="canRate">
                            <textarea v-model="ratingForm.rating_note" rows="2"
                                class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-sm resize-y"
                                placeholder="Nota opcional sobre el desempeño del colaborador"></textarea>
                            <p v-if="ratingForm.errors.rating" class="text-red-500 text-xs">{{ ratingForm.errors.rating }}</p>
                            <div class="flex items-center gap-3 flex-wrap">
                                <PrimaryButton @click="submitRating" :disabled="ratingForm.processing || !ratingForm.rating"
                                    :class="{ 'opacity-50': ratingForm.processing || !ratingForm.rating }">
                                    <i class="fa-solid fa-star mr-2"></i>{{ task.rating ? 'Actualizar calificación' : 'Guardar calificación' }}
                                </PrimaryButton>
                                <!-- Confirmación dentro del modal (los toasts quedan detrás del dialog) -->
                                <span v-if="ratingFeedback === 'ok'" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                    <i class="fa-solid fa-circle-check mr-1"></i>Calificación guardada
                                </span>
                                <span v-else-if="ratingFeedback === 'error'" class="text-[11px] font-semibold text-red-600 dark:text-red-400">
                                    <i class="fa-solid fa-circle-exclamation mr-1"></i>No se pudo guardar la calificación
                                </span>
                                <span v-if="task.rating" class="text-[11px] text-gray-500">
                                    Calificada por {{ task.rated_by?.name || 'el jefe de proyecto' }}
                                    <template v-if="task.rated_at"> el {{ formatDateTime(task.rated_at) }}</template>
                                </span>
                            </div>
                        </template>

                        <!-- Vista de solo lectura para el resto de usuarios -->
                        <template v-else>
                            <p v-if="task.rating_note" class="text-xs text-gray-600 dark:text-gray-300 italic whitespace-pre-wrap">
                                “{{ task.rating_note }}”
                            </p>
                            <p v-else class="text-xs text-gray-400">Sin nota del jefe de proyecto.</p>
                            <p v-if="!task.rating" class="text-xs text-gray-400">El jefe de proyecto aún no ha calificado esta tarea.</p>
                        </template>
                    </div>
                </div>

                <!-- ===== COMENTARIOS (solo en tareas existentes; todos los miembros pueden comentar) ===== -->
                <div v-if="isEditing" class="border-t border-gray-200 dark:border-zinc-700 pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3 flex items-center gap-2">
                        <i class="fa-regular fa-comments"></i> Comentarios
                        <span class="text-xs text-gray-400">({{ task.comments?.length || 0 }})</span>
                    </h4>

                    <div class="space-y-3 max-h-64 overflow-y-auto pr-1 custom-scrollbar mb-3">
                        <div v-if="!task.comments?.length" class="text-center text-gray-400 text-xs py-6">
                            Aún no hay comentarios. ¡Sé el primero en comentar!
                        </div>
                        <div v-for="comment in task.comments" :key="comment.id" class="flex gap-2.5">
                            <img :src="comment.author?.profile_photo_url" class="size-8 rounded-full object-cover shrink-0" :alt="comment.author?.name" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-gray-800 dark:text-gray-100">{{ comment.author?.name }}</span>
                                    <span class="text-[10px] text-gray-400">{{ formatDateTime(comment.created_at) }}</span>
                                </div>
                                <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words" v-html="highlightMentions(comment.body)"></div>

                                <!-- Recibos de lectura de las menciones -->
                                <div v-if="commentRecipients(comment).length" class="mt-1.5 flex items-center gap-2 flex-wrap text-[10px]">
                                    <span
                                        v-for="recipient in commentRecipients(comment)"
                                        :key="recipient.id"
                                        class="inline-flex items-center gap-1"
                                        :class="isReadBy(comment, recipient.id) ? 'text-emerald-600' : 'text-gray-400'"
                                        :title="`${recipient.name}: ${isReadBy(comment, recipient.id) ? 'leído' : 'no leído'}`"
                                    >
                                        <img :src="recipient.profile_photo_url" class="size-4 rounded-full object-cover" :alt="recipient.name" />
                                        <i class="fa-solid" :class="isReadBy(comment, recipient.id) ? 'fa-circle-check' : 'fa-regular fa-circle'"></i>
                                    </span>
                                    <span v-if="allRead(comment)" class="text-emerald-600 font-semibold">
                                        <i class="fa-solid fa-check-double mr-0.5"></i> Leído
                                    </span>
                                    <span v-else-if="readCount(comment) > 0" class="text-gray-400">
                                        Leído {{ readCount(comment) }}/{{ commentRecipients(comment).length }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="isMember">
                        <MentionInput
                            v-model="commentForm.body"
                            v-model:mentioned-ids="commentForm.mentioned_user_ids"
                            :members="projectMembers"
                        />
                        <p v-if="commentForm.bodyError" class="text-red-500 text-xs mt-1">{{ commentForm.bodyError }}</p>
                        <div class="flex justify-end mt-2">
                            <button @click="submitComment" :disabled="commenting"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-xs font-semibold uppercase tracking-widest rounded-md shadow hover:bg-blue-500 disabled:opacity-50 transition">
                                <i class="fa-regular fa-paper-plane"></i> Comentar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </DialogModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import axios from 'axios';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FileUploader from '@/Components/MyComponents/FileUploader.vue';
import FileView from '@/Components/MyComponents/FileView.vue';
import MentionInput from './MentionInput.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    task: { type: Object, default: null }, // null = crear tarea
    project: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    isMember: { type: Boolean, default: false },
    // Solo los Administradores del proyecto ven el tiempo invertido y la evidencia
    canSeeTaskTime: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'task-saved']);

const page = usePage();
const currentUser = page.props.auth.user;

const isEditing = computed(() => !!props.task);

const statuses = ['Pendiente', 'En proceso', 'Pausada', 'Terminada'];

const projectMembers = computed(() => {
    const members = props.project?.members || [];
    // Incluye al creador si no está en la lista (siempre es miembro)
    if (props.project?.creator && !members.some(m => m.id === props.project.creator.id)) {
        return [...members, props.project.creator];
    }
    return members;
});

const statusTagType = computed(() => {
    const map = { 'Pendiente': 'info', 'En proceso': 'warning', 'Pausada': 'warning', 'Terminada': 'success' };
    return map[props.task?.status] || 'info';
});

// Archivos normales vs evidencia de finalización (colecciones separadas de Media Library)
const filesMedia = computed(() => (props.task?.media || []).filter(m => m.collection_name !== 'evidence'));
const evidenceMedia = computed(() => (props.task?.media || []).filter(m => m.collection_name === 'evidence'));

const hasEvidence = computed(() => Number(props.task?.evidence_count || 0) > 0 || evidenceMedia.value.length > 0);

// Se pide evidencia al finalizar solo si la tarea aún no tiene ninguna
const requiresEvidence = computed(() => isEditing.value && props.canEdit && form.status === 'Terminada' && !hasEvidence.value);

// Mensaje de validación de evidencia visible DENTRO del modal
const evidenceError = ref('');

const evidenceFeedback = computed(() => evidenceError.value
    || form.errors.evidence
    || form.errors['evidence.0']
    || form.errors.status);

const onEvidenceSelected = (files) => {
    form.evidence = files;
    evidenceError.value = '';
};

// ===== Calificación del desempeño =====
// Solo puede calificar quien creó el proyecto (los demás solo la ven)
const canRate = computed(() => isEditing.value
    && !!props.project?.created_by
    && Number(props.project.created_by) === Number(currentUser?.id));

const hoverRating = ref(0);
const ratingFeedback = ref(null); // 'ok' | 'error'

const ratingForm = useForm({
    rating: 0,
    rating_note: '',
});

const displayRating = computed(() => hoverRating.value || ratingForm.rating || props.task?.rating || 0);

const setRating = (star) => {
    if (!canRate.value) return;
    ratingForm.rating = star;
    ratingFeedback.value = null;
};

const submitRating = () => {
    if (!ratingForm.rating) {
        ratingForm.setError('rating', 'Selecciona de 1 a 5 estrellas.');
        return;
    }

    ratingForm.post(route('projects.tasks.rating', [props.project.id, props.task.id]), {
        preserveScroll: true,
        onSuccess: () => {
            ratingFeedback.value = 'ok';
        },
        onError: () => {
            ratingFeedback.value = 'error';
        },
    });
};

// Tiempo invertido en formato legible
const formattedTime = computed(() => formatSeconds(props.task?.total_time_seconds || 0));

const formatSeconds = (totalSeconds) => {
    const seconds = Math.max(0, Number(totalSeconds) || 0);
    if (seconds < 60) return '<1m';

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m`;

    const hours = Math.floor(minutes / 60);
    const restMinutes = minutes % 60;
    if (hours < 24) return restMinutes ? `${hours}h ${restMinutes}m` : `${hours}h`;

    const days = Math.floor(hours / 24);
    return `${days}d ${hours % 24}h`;
};

// ===== Formulario de la tarea =====
const emptyTaskForm = () => ({
    title: '',
    description: '',
    start_date: '',
    due_date: '',
    status: 'Pendiente',
    assigned_to: null,
    files: [],
    evidence: [],
    completion_notes: '',
});

const form = useForm(emptyTaskForm());

const resetForm = () => {
    form.clearErrors();
    Object.assign(form, emptyTaskForm());
    form.files = [];
    form.evidence = [];

    if (props.task) {
        form.title = props.task.title;
        form.description = props.task.description || '';
        form.start_date = props.task.start_date ? String(props.task.start_date).slice(0, 10) : '';
        form.due_date = props.task.due_date ? String(props.task.due_date).slice(0, 10) : '';
        form.status = props.task.status || 'Pendiente';
        form.assigned_to = props.task.assigned_to || null;
        form.completion_notes = props.task.completion_notes || '';
        ratingForm.rating = props.task.rating || 0;
        ratingForm.rating_note = props.task.rating_note || '';
        ratingForm.clearErrors();
        ratingFeedback.value = null;
    }
};

watch(() => props.show, (open) => {
    if (open) {
        resetForm();
        resetCommentForm();
        markReadOnOpen();
    }
});

const submit = () => {
    evidenceError.value = '';

    // Al finalizar una tarea se exige evidencia (foto, documento o video)
    if (isEditing.value && form.status === 'Terminada' && !hasEvidence.value) {
        if (!form.evidence.length) {
            evidenceError.value = 'Debes adjuntar al menos una evidencia (foto, documento o video) para finalizar la tarea.';
            return;
        }
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(isEditing.value ? 'Tarea actualizada.' : 'Tarea creada.');
            emit('close');
            emit('task-saved');
        },
        onError: () => ElMessage.error('Ocurrió un error al guardar la tarea.'),
    };

    if (isEditing.value) {
        form.transform(data => ({ ...data, _method: 'put' }))
            .post(route('projects.tasks.update', [props.project.id, props.task.id]), options);
    } else {
        form.post(route('projects.tasks.store', props.project.id), options);
    }
};

const deleteTask = () => {
    // router.delete (no form.delete): evita que el transform _method:put de una edición previa interfiera
    router.delete(route('projects.tasks.destroy', [props.project.id, props.task.id]), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Tarea eliminada.');
            emit('close');
            emit('task-saved');
        },
        onError: () => ElMessage.error('No se pudo eliminar la tarea.'),
    });
};

const removeTaskFile = (id) => {
    const idx = props.task.media.findIndex(f => f.id === id);
    if (idx !== -1) props.task.media.splice(idx, 1);
};

// ===== Comentarios con menciones =====
const commenting = ref(false);
const commentForm = ref({
    body: '',
    mentioned_user_ids: [],
    bodyError: null,
});

const resetCommentForm = () => {
    commentForm.value = { body: '', mentioned_user_ids: [], bodyError: null };
};

const submitComment = async () => {
    if (!commentForm.value.body.trim()) {
        commentForm.value.bodyError = 'Escribe un comentario antes de enviar.';
        return;
    }

    commenting.value = true;
    commentForm.value.bodyError = null;

    try {
        const { data } = await axios.post(
            route('projects.tasks.comments.store', [props.project.id, props.task.id]),
            {
                body: commentForm.value.body,
                mentioned_user_ids: commentForm.value.mentioned_user_ids,
            }
        );

        // Inserta el comentario localmente (sin recargar la página)
        if (!props.task.comments) props.task.comments = [];
        props.task.comments.push(data.comment);

        ElMessage.success('Comentario agregado.');
        resetCommentForm();
    } catch (error) {
        const msg = error.response?.data?.errors?.body?.[0] || 'No se pudo enviar el comentario.';
        commentForm.value.bodyError = msg;
        ElMessage.error(msg);
    } finally {
        commenting.value = false;
    }
};

// Resalta las menciones @Nombre dentro del texto del comentario
const escapeHtml = (str) => String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const escapeRegExp = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const highlightMentions = (body) => {
    let html = escapeHtml(body);
    [...projectMembers.value, currentUser].forEach(m => {
        const name = m?.name;
        if (!name) return;
        const regex = new RegExp(`@${escapeRegExp(name)}(?=\\b|$)`, 'g');
        html = html.replace(regex, `<span class="text-blue-600 font-semibold bg-blue-50 dark:bg-blue-900/40 px-1 rounded">@${escapeHtml(name)}</span>`);
    });
    return html;
};

const formatDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

// ===== Recibos de lectura de las menciones =====
// Destinatarios de un comentario (los usuarios mencionados)
const commentRecipients = (comment) => {
    const ids = (comment.mentioned_user_ids || []).map(id => Number(id));
    if (!ids.length) return [];
    const all = [...projectMembers.value];
    if (currentUser && !all.some(m => Number(m.id) === Number(currentUser.id))) {
        all.push(currentUser);
    }
    return all.filter(m => ids.includes(Number(m.id)));
};

const isReadBy = (comment, userId) =>
    (comment.read_by || []).some(r => Number(r.user_id) === Number(userId));

const readCount = (comment) => commentRecipients(comment).filter(r => isReadBy(comment, r.id)).length;

const allRead = (comment) => {
    const recipients = commentRecipients(comment);
    return recipients.length > 0 && recipients.every(r => isReadBy(comment, r.id));
};

// Al abrir la tarea, marca como leídos los comentarios donde fui mencionado (sin recargar)
const markReadOnOpen = () => {
    if (!props.task || !props.isMember) return;

    const hasUnreadMention = (props.task.comments || []).some(c =>
        (c.mentioned_user_ids || []).some(id => Number(id) === Number(currentUser.id)) &&
        !(c.read_by || []).some(r => Number(r.user_id) === Number(currentUser.id))
    );
    if (!hasUnreadMention) return;

    axios.post(route('projects.tasks.comments.read', [props.project.id, props.task.id]))
        .then(({ data }) => {
            if (Array.isArray(data.comments)) {
                props.task.comments = data.comments;
            }
        })
        .catch(() => {});
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #475569;
}
</style>
