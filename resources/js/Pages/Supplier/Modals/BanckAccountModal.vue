<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>
            {{ bankAccount ? 'Editar Cuenta Bancaria' : 'Agregar Nueva Cuenta' }}
        </template>
        <template #content>
            <form @submit.prevent="save">
                <div class="grid grid-cols-2 gap-4">
                    <TextInput v-model="form.bank_name" label="Banco*" :error="form.errors.bank_name" class="col-span-2" />
                    <TextInput v-model="form.account_holder" label="Titular de la cuenta*" :error="form.errors.account_holder" class="col-span-2" />
                    <TextInput v-model="form.account_number" label="Número de cuenta*" :error="form.errors.account_number" />
                    <TextInput v-model="form.clabe" label="CLABE" :error="form.errors.clabe" />
                    <div>
                       <InputLabel value="Moneda*" />
                       <el-select v-model="form.currency" :teleported="false" placeholder="Selecciona" class="w-full">
                           <el-option label="MXN" value="MXN" />
                           <el-option label="USD" value="USD" />
                       </el-select>
                    </div>
                </div>
            </form>
        </template>
        <template #footer>
            <CancelButton @click="$emit('close')">Cancelar</CancelButton>
            <PrimaryButton @click="save" :loading="form.processing" class="ml-2">{{ bankAccount ? 'Guardar Cambios' : 'Agregar' }}</PrimaryButton>
        </template>
    </DialogModal>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    props: {
        show: Boolean,
        bankAccount: Object,
        supplierId: Number,
    },
    components: {
        DialogModal,
        PrimaryButton,
        CancelButton,
        TextInput,
        InputLabel,
    },
    data() {
        return {
            form: useForm({}),
        };
    },
    methods: {
        save() {
            if (this.bankAccount) {
                this.form.put(route('supplier-bank-accounts.update', this.bankAccount.id), {
                    onSuccess: () => {
                        ElMessage.success('Cuenta actualizada.');
                        this.$emit('close');
                    }
                });
            } else {
                this.form.post(route('supplier-bank-accounts.store'), {
                    onSuccess: () => {
                        ElMessage.success('Cuenta creada.');
                        this.$emit('close');
                    }
                });
            }
            this.$emit('close');
        },
        initializeForm() {
            this.form = useForm({
                id: this.bankAccount?.id || null,
                bank_name: this.bankAccount?.bank_name || '',
                account_holder: this.bankAccount?.account_holder || '',
                account_number: this.bankAccount?.account_number || '',
                clabe: this.bankAccount?.clabe || '',
                currency: this.bankAccount?.currency || 'MXN',
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
