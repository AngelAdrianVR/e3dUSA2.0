<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>
            {{ contact ? 'Editar Contacto' : 'Crear Nuevo Contacto' }}
        </template>
        <template #content>
            <form @submit.prevent="save">
                <div class="grid grid-cols-2 gap-4">
                    <TextInput v-model="form.name" label="Nombre*" :error="form.errors.name" class="col-span-2" />
                    <TextInput v-model="form.position" label="Puesto" :error="form.errors.position" />
                    <TextInput v-model="form.phone" label="Teléfono" :error="form.errors.phone" type="tel" />
                    <TextInput v-model="form.email" label="Email" :error="form.errors.email" type="email" class="col-span-2"/>
                    <label class="flex items-center mt-2 col-span-2">
                        <Checkbox v-model:checked="form.is_primary" name="is_primary" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">¿Es el contacto principal?</span>
                    </label>
                </div>
            </form>
        </template>
        <template #footer>
            <CancelButton @click="$emit('close')">Cancelar</CancelButton>
            <PrimaryButton @click="save" :loading="form.processing" class="ml-2">{{ contact ? 'Guardar Cambios' : 'Crear' }}</PrimaryButton>
        </template>
    </DialogModal>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    props: {
        show: Boolean,
        contact: Object,
        supplierId: Number,
    },
    components: {
        DialogModal,
        PrimaryButton,
        CancelButton,
        TextInput,
        Checkbox,
    },
    data() {
        return {
            form: useForm({}),
        };
    },
    methods: {
        save() {
            if (this.contact) {
                // Update contact
                this.form.put(route('supplier-contacts.update', this.contact), {
                    preserveScroll: true,
                    onSuccess: () => {
                        ElMessage.success('Contacto actualizado correctamente.');
                        this.$emit('close');
                    },
                    onError: () => {
                        ElMessage.error('Hubo un error al actualizar el contacto.');
                    }
                });
            } else {
                // Create contact
                this.form.post(route('supplier-contacts.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        ElMessage.success('Contacto creado correctamente.');
                        this.$emit('close');
                    },
                     onError: () => {
                        ElMessage.error('Hubo un error al crear el contacto.');
                    }
                });
            }
        },
        initializeForm() {
            this.form = useForm({
                id: this.contact?.id || null,
                name: this.contact?.name || '',
                position: this.contact?.position || '',
                phone: this.contact?.phone || '',
                email: this.contact?.email || '',
                is_primary: this.contact?.is_primary || false,
                supplier_id: this.supplierId,
            });
        }
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.initializeForm();
            }
        }
    }
}
</script>

