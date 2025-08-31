<template>
    <!-- Contenedor principal para el componente de notas -->
    <div class="fixed top-40 right-0 z-30 flex items-center pointer-events-none">
        <!-- Pestaña para abrir el panel de notas -->
        <transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="!isOpen" class="pointer-events-auto">
                <button 
                    @click="isOpen = true" 
                    class="group relative flex items-center justify-center w-8 h-16 bg-slate-800/80 backdrop-blur-sm text-white rounded-l-2xl shadow-2xl hover:bg-slate-700/90 transition-all transform hover:-translate-x-1 focus:outline-none focus:ring-2 focus:ring-cyan-400"
                    aria-label="Mostrar notas"
                >
                    <!-- Icono -->
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>

                    <!-- Tooltip -->
                    <span class="absolute right-full mr-2 w-max px-2 py-1 text-xs font-medium text-white bg-slate-900 rounded-md shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        Notas importantes
                    </span>
                </button>
            </div>
        </transition>
    </div>


    <div class="fixed top-40 right-0 bottom-0 z-30 flex items-center pointer-events-none">
        <!-- Panel de Notas -->
        <div class="w-96 h-[calc(80vh-7rem)] bg-slate-900/60 backdrop-blur-xl border border-slate-700 rounded-l-2xl shadow-2xl flex flex-col transition-transform duration-500 ease-in-out pointer-events-auto"
             :class="isOpen ? 'translate-x-0' : 'translate-x-full'">
            
            <!-- Encabezado del Panel -->
            <div class="flex justify-between items-center p-4 border-b border-slate-700 flex-shrink-0">
                <h3 class="font-bold text-lg flex items-center text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 text-cyan-400"><path d="M15.5 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z"></path><path d="M15 3v6h6"></path></svg>
                    Notas del Cliente
                </h3>
                <button @click="isOpen = false" class="text-slate-400 hover:text-white transition-colors" aria-label="Cerrar notas">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <!-- Contenido del Panel (Lista de Notas) -->
            <div class="p-4 space-y-4 flex-grow overflow-y-auto">
                <LoadingIsoLogo v-if="isLoading" />
                <!-- Lista de Notas -->
                <div v-else v-for="note in notes" :key="note.id" class="group flex items-start space-x-3">
                    <!-- Avatar del usuario -->
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-sm font-bold text-cyan-400 ring-2 ring-slate-700">
                        <img v-if="note.user?.profile_photo_url" :src="note.user.profile_photo_url" :alt="note.user.name" class="w-full h-full rounded-full object-cover">
                        <span v-else>{{ note.user?.name.charAt(0).toUpperCase() }}</span>
                    </div>

                    <!-- Contenido de la nota -->
                    <div class="flex-1">
                        <!-- Vista de la nota -->
                        <div v-if="editingNoteId !== note.id" class="text-sm bg-slate-800 p-3 rounded-lg relative">
                            <p class="whitespace-pre-wrap text-slate-200">{{ note.content }}</p>
                            <div class="flex justify-between items-center mt-2 text-xs text-slate-500">
                                <span class="font-semibold">{{ note.user?.name }} &bull; {{ formatRelative(note.created_at) }}</span>
                                <div class="flex space-x-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <button @click="startEdit(note)" class="hover:text-cyan-400">Editar</button>
                                    <el-popconfirm title="¿Eliminar esta nota?" @confirm="deleteNote(note.id)">
                                        <template #reference>
                                            <button class="hover:text-red-500">Eliminar</button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                            </div>
                        </div>
                        <!-- Editor de la nota -->
                        <div v-else>
                             <textarea v-model="editingNoteContent" rows="3" class="w-full text-sm bg-slate-700 text-white border-slate-600 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500 transition"></textarea>
                            <div class="flex space-x-2 justify-end text-xs mt-1">
                                <button @click="cancelEdit" class="hover:underline text-slate-400">Cancelar</button>
                                <button @click="updateNote(note.id)" class="text-cyan-400 hover:underline font-semibold">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>
                 <p v-if="!notes.length && !isLoading" class="text-sm text-center text-slate-900 dark:text-white py-8">No hay notas registradas.</p>
            </div>

             <!-- Input para nueva nota -->
            <div class="p-4 border-t border-slate-700 flex-shrink-0">
                 <textarea v-model="newNoteContent" @keydown.enter.prevent="storeNote" rows="3" class="w-full text-sm bg-slate-800 text-white border-slate-700 rounded-lg shadow-sm placeholder-slate-500 focus:ring-cyan-500 focus:border-cyan-500 transition" placeholder="Escribe una nota..."></textarea>
                 <div class="flex justify-end mt-2">
                     <button @click="storeNote" :disabled="!newNoteContent.trim()" class="px-4 py-2 text-sm font-semibold text-white bg-cyan-600 rounded-md hover:bg-cyan-500 disabled:bg-slate-700 disabled:text-slate-400 disabled:cursor-not-allowed transition-colors">
                        Guardar Nota
                     </button>
                 </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import { ref, onMounted, watch  } from 'vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';

// No es necesario importar SecondaryButton con el nuevo diseño
// import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    branchId: { type: Number, required: true },
    // initialNotes: { type: Array, default: () => [] }
});

const isLoading = ref(false);
const notes = ref([]);
const isOpen = ref(false); // El panel inicia cerrado por defecto
const newNoteContent = ref('');
const editingNoteId = ref(null);
const editingNoteContent = ref('');

onMounted(() => {
    fetchNotes();
});

watch(
  () => props.branchId,
  () => {
    fetchNotes()
  }
)

const fetchNotes = async () => {
  if (isLoading.value) return // evita llamadas múltiples
  isLoading.value = true
  try {
    const response = await axios.get(route('branch-notes.index', props.branchId))
    notes.value = response.data

    if (notes.value.length > 0) {
      isOpen.value = true // abre el panel si hay notas
    }
  } catch (error) {
    console.error("Error al recuperar las notas:", error)
    ElMessage.error('No se pudieron recuperar las notas.')
  } finally {
    isLoading.value = false
  }
}

const formatRelative = (dateStr) => dateStr ? formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: es }) : '';

const storeNote = async () => {
    if (!newNoteContent.value.trim()) return;
    try {
        const res = await axios.post(route('branch-notes.store'), {
            branch_id: props.branchId,
            content: newNoteContent.value.trim(),
        });
        // Asumimos que la respuesta incluye el objeto de usuario con la nota
        // Si no es así, necesitarás obtener los datos del usuario actual de otra fuente
        notes.value.unshift(res.data);
        newNoteContent.value = '';
        ElMessage({
            message: 'Nota guardada',
            type: 'success',
            customClass: 'dark-message'
        });
    } catch (error) {
        console.error("Error al guardar la nota:", error);
        ElMessage.error('No se pudo guardar la nota.');
    }
};

const startEdit = (note) => {
    editingNoteId.value = note.id;
    editingNoteContent.value = note.content;
};

const cancelEdit = () => {
    editingNoteId.value = null;
    editingNoteContent.value = '';
};

const updateNote = async (noteId) => {
    if (!editingNoteContent.value.trim()) return;
    try {
        const res = await axios.put(route('branch-notes.update', noteId), { content: editingNoteContent.value.trim() });
        const index = notes.value.findIndex(n => n.id === noteId);
        if (index !== -1) notes.value[index] = res.data;
        cancelEdit();
        ElMessage.success('Nota actualizada');
    } catch (error) {
        console.error("Error al actualizar la nota:", error);
        ElMessage.error('No se pudo actualizar la nota.');
    }
};

const deleteNote = async (noteId) => {
    try {
        await axios.delete(route('branch-notes.destroy', noteId));
        notes.value = notes.value.filter(n => n.id !== noteId);
        ElMessage.success('Nota eliminada');
        // No cerramos el panel al eliminar la última nota, para que el usuario pueda agregar una nueva si lo desea.
        // if (notes.value.length === 0) isOpen.value = false;
    } catch (error) {
        console.error("Error al eliminar la nota:", error);
        ElMessage.error('No se pudo eliminar la nota.');
    }
};
</script>

<style>
/* Estilos para notificaciones de Element Plus en modo oscuro (opcional) */
.el-message.dark-message {
    --el-message-bg-color: #2d3748; /* bg-slate-800 */
    --el-message-text-color: #e2e8f0; /* text-slate-200 */
    --el-message-border-color: #4a5568; /* border-slate-600 */
}
</style>

