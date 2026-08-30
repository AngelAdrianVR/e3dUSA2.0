<template>
    <DialogModal :show="show" max-width="3xl" @close="$emit('close')">
        <template #title>
            <div class="flex items-center gap-2">
                <i class="fa-solid" :class="isEditing ? 'fa-pen-to-square' : 'fa-diagram-project'"></i>
                <span>{{ isEditing ? 'Editar proyecto' : 'Nuevo proyecto' }}</span>
            </div>
        </template>

        <template #content>
            <div class="space-y-4 pt-1">
                <!-- Nombre -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del proyecto *</label>
                    <input v-model="form.name" type="text"
                        class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        placeholder="Ej. Rediseño de línea de emblemas" />
                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                    <textarea v-model="form.description" rows="3"
                        class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-y"
                        placeholder="Objetivo y alcance del proyecto"></textarea>
                    <p v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</p>
                </div>

                <!-- Presupuesto y moneda -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Presupuesto de inversión</label>
                        <input v-model="form.budget" type="number" min="0" step="0.01"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="0.00" />
                        <p v-if="form.errors.budget" class="text-red-500 text-xs mt-1">{{ form.errors.budget }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Moneda</label>
                        <el-select v-model="form.currency" :teleported="false" class="w-full">
                            <el-option label="MXN — Peso mexicano" value="MXN" />
                            <el-option label="USD — Dólar americano" value="USD" />
                        </el-select>
                        <p v-if="form.errors.currency" class="text-red-500 text-xs mt-1">{{ form.errors.currency }}</p>
                    </div>
                </div>

                <!-- Fechas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de inicio *</label>
                        <el-date-picker v-model="form.start_date" :teleported="false" type="date" value-format="YYYY-MM-DD" class="w-full" placeholder="Fecha de inicio" />
                        <p v-if="form.errors.start_date" class="text-red-500 text-xs mt-1">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha tentativa de finalización</label>
                        <el-date-picker v-model="form.tentative_end_date" :teleported="false" type="date" value-format="YYYY-MM-DD" class="w-full" placeholder="Fecha tentativa" />
                        <p v-if="form.errors.tentative_end_date" class="text-red-500 text-xs mt-1">{{ form.errors.tentative_end_date }}</p>
                    </div>
                </div>

                <!-- Miembros con roles -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Miembros del proyecto</label>
                        <el-tooltip placement="top" :content="permissionHelp" :teleported="false" effect="dark" raw-content>
                            <span class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 cursor-help">
                                <i class="fa-solid fa-circle-info"></i> ¿Qué hace cada permiso?
                            </span>
                        </el-tooltip>
                    </div>

                    <!-- Creador (fijo, siempre Administrador) -->
                    <div class="flex items-center gap-2 p-2 rounded-md bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 mb-2">
                        <img :src="creatorAvatar" class="size-7 rounded-full object-cover" alt="Creador" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ creatorName }}</span>
                        <span class="ml-auto text-[10px] font-semibold text-blue-600 bg-blue-100 dark:bg-blue-900/40 px-2 py-0.5 rounded-full shrink-0">Creador · Administrador</span>
                    </div>

                    <!-- Miembros editables -->
                    <div v-for="(member, index) in form.members" :key="index" class="flex items-center gap-2 mb-2">
                        <el-select v-model="member.user_id" :teleported="false" filterable placeholder="Selecciona un usuario" class="!w-48">
                            <el-option v-for="u in availableUsersFor(index)" :key="u.id" :label="u.name" :value="u.id" />
                        </el-select>
                        <el-select v-model="member.role" :teleported="false" class="w-44">
                            <el-option label="Colaborador" value="Colaborador" />
                            <el-option label="Administrador" value="Administrador" />
                        </el-select>
                        <button type="button" @click="form.members.splice(index, 1)"
                            class="text-red-500 hover:text-red-700 p-1.5 rounded-full hover:bg-red-50 dark:hover:bg-red-900/30 transition shrink-0"
                            title="Quitar miembro">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>

                    <button type="button" @click="addMember"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition">
                        <i class="fa-solid fa-user-plus"></i> Agregar miembro
                    </button>

                    <p v-if="form.errors['members.0.user_id']" class="text-red-500 text-xs mt-1">{{ form.errors['members.0.user_id'] }}</p>
                </div>

                <!-- Archivos -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivos / imágenes del proyecto</label>
                    <FileUploader :multiple="true" format="Todo" :max-files="10" :max-file-size="10" @files-selected="form.files = $event" />
                    <p v-if="form.errors.files" class="text-red-500 text-xs mt-1">{{ form.errors.files }}</p>
                </div>

                <!-- Archivos existentes (solo edición) -->
                <div v-if="isEditing && project.media?.length" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Archivos actuales</label>
                    <FileView v-for="file in project.media" :key="file.id" :file="file" deletable @delete-file="removeProjectFile(file.id)" />
                </div>
            </div>
        </template>

        <template #footer>
            <SecondaryButton @click="$emit('close')" class="mr-3">Cancelar</SecondaryButton>
            <PrimaryButton @click="submit" :disabled="form.processing" :class="{ 'opacity-50': form.processing }">
                <i class="fa-solid fa-floppy-disk mr-2"></i>
                {{ isEditing ? 'Guardar cambios' : 'Crear proyecto' }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import FileUploader from '@/Components/MyComponents/FileUploader.vue';
import FileView from '@/Components/MyComponents/FileView.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    project: { type: Object, default: null }, // null = crear
    users: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const page = usePage();
const currentUser = page.props.auth.user;

const isEditing = computed(() => !!props.project);

const creatorName = computed(() => props.project?.creator?.name || currentUser?.name || '');
const creatorAvatar = computed(() => props.project?.creator?.profile_photo_url || currentUser?.profile_photo_url || '/images/default-avatar.png');
const creatorId = computed(() => props.project?.created_by || currentUser?.id || null);

const permissionHelp = '🟢 <b>Colaborador:</b> puede ver el proyecto y comentar en las tareas.<br/>🔵 <b>Administrador:</b> además puede editar los datos del proyecto y crear, editar o mover tareas.<br/><br/>Todos los miembros pueden comentar, sin importar su permiso.';

const emptyForm = () => ({
    name: '',
    description: '',
    budget: null,
    currency: 'MXN',
    start_date: '',
    tentative_end_date: '',
    members: [], // [{ user_id, role }]
    files: [],
});

const form = useForm(emptyForm());

const resetForm = () => {
    form.clearErrors();
    Object.assign(form, emptyForm());
    form.files = [];

    if (props.project) {
        form.name = props.project.name;
        form.description = props.project.description || '';
        form.budget = props.project.budget !== null ? Number(props.project.budget) : null;
        form.currency = props.project.currency || 'MXN';
        form.start_date = props.project.start_date ? String(props.project.start_date).slice(0, 10) : '';
        form.tentative_end_date = props.project.tentative_end_date ? String(props.project.tentative_end_date).slice(0, 10) : '';
        // El creador no se edita (siempre Administrador); el resto de miembros con su rol
        form.members = (props.project.members || [])
            .filter(m => m.id !== props.project.created_by)
            .map(m => ({ user_id: m.id, role: m.pivot?.role || 'Colaborador' }));
    }
};

watch(() => props.show, (open) => {
    if (open) resetForm();
});

const addMember = () => {
    form.members.push({ user_id: null, role: 'Colaborador' });
};

// Usuarios disponibles: activos, excluyendo al creador y a los ya seleccionados en otras filas
const availableUsersFor = (index) => {
    const selected = form.members
        .filter((m, i) => i !== index && m.user_id)
        .map(m => m.user_id);

    return props.users.filter(u => u.id !== creatorId.value && !selected.includes(u.id));
};

const removeProjectFile = (id) => {
    const idx = props.project.media.findIndex(f => f.id === id);
    if (idx !== -1) props.project.media.splice(idx, 1);
};

const submit = () => {
    // Filtra filas de miembros sin usuario seleccionado
    form.members = form.members.filter(m => m.user_id);

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(isEditing.value ? 'Proyecto actualizado correctamente.' : 'Proyecto creado correctamente.');
            emit('close');
            emit('saved');
        },
        onError: () => {
            ElMessage.error('Ocurrió un error al guardar el proyecto. Revisa los campos marcados.');
        },
    };

    if (isEditing.value) {
        // POST + _method=put: necesario para subir archivos con Inertia
        form.transform(data => ({ ...data, _method: 'put' }))
            .post(route('projects.update', props.project.id), options);
    } else {
        form.post(route('projects.store'), options);
    }
};
</script>
