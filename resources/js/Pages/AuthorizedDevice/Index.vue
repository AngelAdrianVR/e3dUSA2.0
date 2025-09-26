<template>
    <AppLayout title="Dispositivos Autorizados">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Dispositivos Autorizados para Registrar Asistencia
        </h2>

        <div class="py-7">
            <p class="text-gray-600 dark:text-gray-400 text-sm text-center my-5">
                Desde aquí puedes autorizar o remover los dispositivos que pueden registrar asistencias.
            </p>
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <SecondaryButton @click="showAuthModal = true">
                        <i class="fa-solid fa-plus mr-2"></i>
                        Autorizar este Dispositivo
                    </SecondaryButton>

                    <el-table :data="devices" max-height="550" stripe class="dark:!bg-slate-900 mt-6">
                        <el-table-column prop="name" label="Nombre del Dispositivo" />
                        <el-table-column prop="creator.name" label="Autorizado por" />
                        <el-table-column prop="created_at" label="Fecha de Autorización">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column label="Acciones" align="right">
                            <template #default="{ row }">
                                <el-popconfirm title="¿Desautorizar este dispositivo?" @confirm="removeDevice(row)">
                                    <template #reference>
                                        <el-button type="danger" plain>Remover</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
        </div>

        <DialogModal :show="showAuthModal" @close="showAuthModal = false">
            <template #title>Autorizar este Dispositivo</template>
            <template #content>
                <p class="dark:text-gray-300">
                    Estás a punto de autorizar este navegador para que cualquier empleado pueda registrar su asistencia
                    desde aquí.
                </p>
                <form @submit.prevent="authorizeDevice" class="mt-4">
                    <TextInput v-model="authForm.name" label="Nombre o ubicación del dispositivo*"
                        placeholder="Ej. Tablet de Recepción" :error="authForm.errors.name" />
                </form>
            </template>
            <template #footer>
                <div class="flex items-center space-x-1">
                    <CancelButton @click="showAuthModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="authorizeDevice" :loading="authForm.processing">Autorizar</SecondaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ElMessage } from 'element-plus';

defineProps({
    devices: Array,
});

const page = usePage();
const showAuthModal = ref(false);

const authForm = useForm({
    name: '',
});

const authorizeDevice = () => {
    authForm.post(route('authorized-devices.store'), {
        onSuccess: () => {
            ElMessage.success('Este dispositivo ha sido autorizado.');
            showAuthModal.value = false;
            authForm.reset();
            // No necesitamos hacer nada con el token, el servidor ya lo guardó como cookie.
            // Simplemente recargamos para que Inertia actualice el estado global.
            router.reload();
        },
        onError: (errors) => {
            console.error(errors);
            ElMessage.error('No se pudo autorizar el dispositivo.');
        }
    });
};

const removeDevice = (device) => {
    router.delete(route('authorized-devices.destroy', device), {
        onSuccess: () => {
            ElMessage.success('Dispositivo desautorizado.');
            // Recargamos para que el servidor nos envíe la cookie de borrado y se actualice el estado.
            router.reload();
        },
    });
};

const formatDate = (dateString) => new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

</script>