<template>
    <DialogModal :show="show" @close="closeModal">
        <template #title>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                {{ form.id ? 'Editar Contacto' : 'Crear Nuevo Contacto' }}
            </h2>
        </template>
        <template #content>
            <form @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <div class="col-span-2">
                        <InputLabel value="Nombre Completo*" />
                        <TextInput v-model="form.name" required class="w-full" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Cargo*" />
                        <TextInput v-model="form.charge" required class="w-full" />
                        <InputError :message="form.errors.charge" />
                    </div>
                    <div>
                        <InputLabel value="Fecha de Nacimiento" />
                         <el-date-picker
                            v-model="form.birthdate"
                            :teleported="false"
                            type="date"
                            placeholder="Seleccione una fecha"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                            class="!w-full"
                        />
                        <InputError :message="form.errors.birthdate" />
                    </div>

                    <!-- Detalles de Contacto -->
                    <div class="col-span-2 mt-4">
                        <h3 class="text-md font-semibold mb-4">Detalles de Contacto</h3>
                        <div v-for="(detail, index) in form.details" :key="index" class="flex items-center space-x-2 mb-2">
                             <el-select v-model="detail.type" placeholder="Tipo" :teleported="false" class="!w-1/4">
                                <el-option label="Teléfono" value="Teléfono" />
                                <el-option label="Correo" value="Correo" />
                            </el-select>
                            <TextInput v-model="detail.value" :label="'Valor'" placeholder="Valor" class="flex-grow" />
                            <div class="flex items-center">
                                <el-switch v-model="detail.is_primary" @change="setAsPrimary(index)" title="Marcar como principal" />
                            </div>
                            <button @click="removeDetail(index)" type="button" class="text-red-500 hover:text-red-700">
                                <i class="fa-solid fa-circle-minus"></i>
                            </button>
                        </div>
                         <button @click="addDetail" type="button" class="text-primary hover:underline text-sm mt-2">
                            <i class="fa-solid fa-plus mr-1"></i> Agregar detalle
                        </button>
                         <InputError :message="form.errors.details" />
                    </div>
                </div>
            </form>
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="closeModal">Cancelar</CancelButton>
                <PrimaryButton @click="submit" :disabled="form.processing">
                    <span v-if="form.processing">Guardando...</span>
                    <span v-else>{{ form.id ? 'Actualizar' : 'Guardar' }}</span>
                </PrimaryButton>
            </div>
        </template>
    </DialogModal>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { ElMessage } from 'element-plus';

export default {
    components: {
        DialogModal,
        PrimaryButton,
        CancelButton,
        TextInput,
        InputLabel,
        InputError,
    },
    props: {
        show: Boolean,
        contact: Object,
        branchId: Number,
    },
    data() {
        return {
            form: useForm({
                id: null,
                branch_id: this.branchId,
                name: '',
                charge: '',
                birthdate: '',
                details: [],
            }),
        };
    },
    methods: {
        submit() {
            if (this.form.id) {
                // Actualizar
                this.form.put(route('contacts.update', this.form.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        ElMessage.success('Contacto actualizado');
                        this.closeModal();
                    },
                     onError: () => {
                        ElMessage.error('Hubo un error al actualizar');
                    }
                });
            } else {
                // Crear
                this.form.post(route('contacts.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        ElMessage.success('Contacto creado');
                        this.closeModal();
                    },
                    onError: () => {
                        ElMessage.error('Hubo un error al crear el contacto');
                    }
                });
            }
        },
        addDetail() {
            this.form.details.push({ type: 'Teléfono', value: '', is_primary: false });
        },
        removeDetail(index) {
            this.form.details.splice(index, 1);
        },
        setAsPrimary(selectedIndex) {
            const currentType = this.form.details[selectedIndex].type;
            this.form.details.forEach((detail, index) => {
                if (detail.type === currentType && index !== selectedIndex) {
                    detail.is_primary = false;
                }
            });
        },
        closeModal() {
            this.form.reset();
            this.$emit('close');
        }
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.form.branch_id = this.branchId;
                if (this.contact) {
                    this.form.id = this.contact.id;
                    this.form.name = this.contact.name;
                    this.form.charge = this.contact.charge;
                    this.form.birthdate = this.contact.birthdate;
                    // Clona profundamente los detalles para evitar mutaciones no deseadas
                    this.form.details = JSON.parse(JSON.stringify(this.contact.details || []));
                } else {
                    this.form.reset();
                    this.form.branch_id = this.branchId;
                    // Añadir un detalle por defecto al crear
                    if (this.form.details.length === 0) {
                        this.addDetail();
                    }
                }
            }
        }
    }
}
</script>
