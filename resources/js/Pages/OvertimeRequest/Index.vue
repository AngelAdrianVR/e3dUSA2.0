<template>
    <AppLayout title="Solicitudes de Tiempo Adicional">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Solicitudes de Tiempo Adicional
        </h2>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- Botón para crear nueva solicitud, visible si tiene permiso -->
                    <div class="flex justify-end mb-4" v-if="can.create_requests">
                        <SecondaryButton @click="showCreateModal = true">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nueva Solicitud
                        </SecondaryButton>
                    </div>

                    <el-table :data="requests.data" max-height="600" stripe
                        class="dark:!bg-slate-900 dark:!text-gray-300">
                        <el-table-column prop="id" label="ID" width="70" />
                        <el-table-column prop="employee_detail.user.name" label="Empleado" />
                        <el-table-column prop="date" label="Fecha">
                             <template #default="scope">
                                {{ formatDate(scope.row.date) }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="requested_minutes" label="Minutos Solicitados" align="center" />
                        <el-table-column prop="reason" label="Motivo" />
                        <el-table-column prop="status" label="Estatus" align="center">
                            <template #default="scope">
                                <el-tag :type="getStatusTag(scope.row.status)">
                                    {{ scope.row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="Acciones" align="right" width="150">
                            <template #default="scope">
                                <!-- Acciones visibles si tiene permiso de gestionar -->
                                <div v-if="can.manage_requests && scope.row.status === 'pending'" class="flex items-center justify-end space-x-2">
                                    <el-popconfirm title="¿Aprobar solicitud?" @confirm="updateStatus(scope.row.id, 'approved')">
                                        <template #reference>
                                             <el-button type="success" circle><i class="fa-solid fa-check"></i></el-button>
                                        </template>
                                    </el-popconfirm>
                                    <el-popconfirm title="¿Rechazar solicitud?" @confirm="updateStatus(scope.row.id, 'rejected')">
                                        <template #reference>
                                            <el-button type="danger" circle><i class="fa-solid fa-xmark"></i></el-button>
                                        </template>
                                    </el-popconfirm>
                                </div>
                                <div v-else-if="scope.row.status !== 'pending'" class="text-xs text-gray-500 italic">
                                    Gestionado por {{ scope.row.approver?.name ?? 'N/A' }}
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>

                     <div v-if="requests.total > 0" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="requests.current_page" :page-size="requests.per_page"
                            :total="requests.total" layout="prev, pager, next" background
                            @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Crear Solicitud -->
        <DialogModal :show="showCreateModal" @close="showCreateModal = false">
            <template #title>Crear Solicitud de Tiempo Adicional</template>
            <template #content>
                <form @submit.prevent="submitCreateForm" class="space-y-4">
                    <!-- Selector de Empleado (solo para admins) -->
                    <div v-if="can.manage_requests">
                        <InputLabel value="Empleado*" />
                        <el-select :teleported="false" v-model="createForm.employee_detail_id" filterable placeholder="Selecciona un empleado" class="w-full">
                            <el-option v-for="employee in employeeOptions" :key="employee.value" :label="employee.label" :value="employee.value" />
                        </el-select>
                        <InputError :message="createForm.errors.employee_detail_id" />
                    </div>

                    <div>
                        <InputLabel value="Fecha*" />
                        <el-date-picker :teleported="false" v-model="createForm.date" type="date" placeholder="Selecciona una fecha" format="DD MMMM, YYYY" value-format="YYYY-MM-DD" class="w-full" />
                        <InputError :message="createForm.errors.date" />
                    </div>
                    <div>
                        <TextInput v-model="createForm.requested_minutes" label="Minutos Adicionales Solicitados*" type="number" :error="createForm.errors.requested_minutes" />
                    </div>
                    <div>
                         <TextInput v-model="createForm.reason" label="Motivo de la solicitud*" :isTextarea="true" :error="createForm.errors.reason" />
                    </div>
                </form>
            </template>
            <template #footer>
                 <CancelButton @click="showCreateModal = false" :disabled="createForm.processing">Cancelar</CancelButton>
                 <SecondaryButton @click="submitCreateForm" :loading="createForm.processing">Enviar Solicitud</SecondaryButton>
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
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    requests: Object,
    can: Object,
    employees: Array, // Recibe la lista de empleados (puede ser null)
});

const showCreateModal = ref(false);

const createForm = useForm({
    employee_detail_id: null, // Campo para el selector de admin
    date: null,
    requested_minutes: null,
    reason: '',
});

const updateForm = useForm({
    status: '',
});

// Transforma la lista de empleados para el componente de selección
const employeeOptions = computed(() => {
    if (!props.employees) return [];
    return props.employees.map(emp => ({
        value: emp.id,
        label: emp.user.name,
    }));
});

const getStatusTag = (status) => {
    if (status === 'approved') return 'success';
    if (status === 'rejected') return 'danger';
    return 'warning';
};

const formatDate = (dateString) => new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });

const updateStatus = (id, newStatus) => {
    updateForm.status = newStatus;
    updateForm.put(route('overtime-requests.update', id), {
        onSuccess: () => ElMessage.success('Solicitud actualizada'),
        preserveScroll: true,
    });
};

const submitCreateForm = () => {
    createForm.post(route('overtime-requests.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            ElMessage.success('Solicitud enviada');
        },
        preserveScroll: true,
    });
};

const handlePageChange = (page) => {
    router.get(route('overtime-requests.index', { page }));
};
</script>