<template>
  <div class="relative">
    <textarea
      ref="textareaRef"
      :value="modelValue"
      rows="3"
      :placeholder="placeholder"
      class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-y"
      @input="handleInput"
      @keydown="handleKeydown"
      @blur="handleBlur"
    ></textarea>

    <!-- Dropdown de menciones a integrantes del proyecto -->
    <div
      v-if="showMentions && filteredMembers.length"
      class="absolute z-20 left-0 right-0 bottom-full mb-1 max-h-44 overflow-y-auto bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-600 rounded-lg shadow-xl py-1"
    >
      <button
        v-for="(member, idx) in filteredMembers"
        :key="member.id"
        type="button"
        @mousedown.prevent="selectMember(member)"
        class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-zinc-700 transition-colors"
        :class="idx === highlightedIndex ? 'bg-blue-50 dark:bg-zinc-700' : ''"
      >
        <img :src="member.profile_photo_url" alt="" class="size-6 rounded-full object-cover" />
        <span class="font-medium text-gray-700 dark:text-gray-200 truncate">{{ member.name }}</span>
        <span class="ml-auto text-[10px] uppercase text-gray-400 shrink-0">@mencionar</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    members: { type: Array, default: () => [] }, // [{ id, name, profile_photo_url }]
    placeholder: {
        type: String,
        default: 'Escribe un comentario... Usa @ para mencionar a un integrante del proyecto',
    },
    mentionedIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'update:mentionedIds']);

const textareaRef = ref(null);
const showMentions = ref(false);
const mentionQuery = ref('');
const mentionStart = ref(0);
const mentionEnd = ref(0);
const highlightedIndex = ref(0);
const localMentioned = ref([...props.mentionedIds]);

// Mantiene en sincronía los ids mencionados si el padre los resetea
watch(() => props.mentionedIds, (val) => {
    localMentioned.value = [...(val || [])];
});

const filteredMembers = computed(() => {
    const q = mentionQuery.value.toLowerCase().trim();
    if (!q) return props.members;
    return props.members.filter(m => m.name.toLowerCase().includes(q));
});

const handleInput = (e) => {
    const value = e.target.value;
    emit('update:modelValue', value);
    detectMention(value, e.target.selectionStart);
};

const detectMention = (value, cursor) => {
    const textBeforeCursor = value.slice(0, cursor);
    const atIndex = textBeforeCursor.lastIndexOf('@');

    if (atIndex === -1) { closeMentions(); return; }

    const afterAt = textBeforeCursor.slice(atIndex + 1);
    // Si hay espacios o ya es muy largo, no es una mención en curso
    if (/\s/.test(afterAt) || afterAt.length > 60) { closeMentions(); return; }
    // Evita falsos positivos (ej: dentro de un correo)
    if (atIndex > 0 && /[\w]/.test(value[atIndex - 1])) { closeMentions(); return; }

    mentionStart.value = atIndex;
    mentionEnd.value = cursor;
    mentionQuery.value = afterAt;
    highlightedIndex.value = 0;
    showMentions.value = true;
};

const closeMentions = () => {
    showMentions.value = false;
    mentionQuery.value = '';
};

const selectMember = (member) => {
    const value = props.modelValue;
    const before = value.slice(0, mentionStart.value);
    const after = value.slice(mentionEnd.value);
    const insertion = '@' + member.name + ' ';
    const newText = before + insertion + after;
    emit('update:modelValue', newText);

    if (!localMentioned.value.includes(member.id)) {
        localMentioned.value = [...localMentioned.value, member.id];
        emit('update:mentionedIds', localMentioned.value);
    }

    closeMentions();

    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.focus();
            const newCursor = mentionStart.value + insertion.length;
            textareaRef.value.setSelectionRange(newCursor, newCursor);
        }
    });
};

const handleKeydown = (e) => {
    if (!showMentions.value) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightedIndex.value = (highlightedIndex.value + 1) % filteredMembers.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightedIndex.value = (highlightedIndex.value - 1 + filteredMembers.value.length) % filteredMembers.value.length;
    } else if (e.key === 'Enter' || e.key === 'Tab') {
        if (filteredMembers.value.length) {
            e.preventDefault();
            selectMember(filteredMembers.value[highlightedIndex.value]);
        }
    } else if (e.key === 'Escape') {
        closeMentions();
    }
};

const handleBlur = () => {
    // Pequeño retraso para permitir el clic en el dropdown
    setTimeout(closeMentions, 150);
};
</script>
