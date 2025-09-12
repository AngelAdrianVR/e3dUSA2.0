<template>
    <AppLayout :title="'Detalle de ' + user.name">
        <!-- Encabezado -->
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <Back :href="route('users.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Detalle de Usuario
                </h2>
            </div>
            <div class="flex items-center space-x-2">
                <el-dropdown trigger="click">
                    <SecondaryButton>
                        Opciones <i class="fa-solid fa-chevron-down ml-2 text-xs"></i>
                    </SecondaryButton>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item v-if="hasPermission('Editar personal')"
                                @click="$inertia.get(route('users.edit', user))">
                                <i class="fa-solid fa-pen-to-square mr-2"></i>Editar
                            </el-dropdown-item>
                            <el-dropdown-item v-if="hasPermission('Editar personal')"
                                @click="showChangeStatusModal = true">
                                <i class="fa-solid fa-user-slash mr-2"></i>{{ user.is_active ? 'Dar de baja' :
                                    'Reactivar' }}
                            </el-dropdown-item>
                            <el-dropdown-item v-if="hasPermission('Crear personal')"
                                @click="$inertia.get(route('users.create'))" divided>
                                <i class="fa-solid fa-plus mr-2"></i>Crear nuevo
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tarjeta de perfil -->
                <div
                    class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 flex items-start space-x-6">
                    <img :src="user.profile_photo_url"
                        class="size-24 rounded-full object-cover border-4 border-white dark:border-slate-800 shadow-lg"
                        :alt="user.name">
                    <div class="flex-1">
                        <div class="flex justify-between items-center">
                            <h1 class="text-2xl font-bold dark:text-gray-200">{{ user.name }}</h1>
                            <el-tag :type="user.is_active ? 'success' : 'danger'" size="large">
                                {{ user.is_active ? 'Activo' : 'Inactivo' }}
                            </el-tag>
                        </div>
                        <p class="text-gray-600 dark:text-gray-400">{{ user.email }}</p>
                        <p class="text-sm text-gray-500 mt-1">Rol: <span class="font-semibold">{{ user.roles[0]?.name ??
                            'N/A'
                        }}</span></p>
                        <!-- Fecha de Nacimiento -->
                        <p v-if="age !== null" class="text-sm text-gray-500 mt-1">
                            <i class="fa-solid fa-cake-candles mr-2"></i>
                            {{ user.employee_detail?.birthdate ? formatDate(user.employee_detail.birthdate) : 'N/A' }}
                            ({{ age }} años)
                        </p>
                    </div>
                </div>

                <!-- Detalles del Empleado y Vacaciones -->
                <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Columna de Datos Laborales -->
                    <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <h3 class="font-bold text-lg dark:text-gray-200 border-b dark:border-slate-700 pb-2 mb-4">Datos
                            Laborales</h3>
                        <div v-if="user.employee_detail" class="space-y-4 text-sm">
                            <div class="grid grid-cols-2">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Puesto:</span>
                                <span class="dark:text-gray-300">{{ user.employee_detail.job_position }}</span>
                            </div>
                            <div class="grid grid-cols-2">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Departamento:</span>
                                <span class="dark:text-gray-300">{{ user.employee_detail.department }}</span>
                            </div>
                            <!-- Fecha de Contratación -->
                            <div class="grid grid-cols-2">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de
                                    Contratación:</span>
                                <span class="dark:text-gray-300">{{ formatDate(user.employee_detail.join_date) }} ({{
                                    seniority
                                }} años)</span>
                            </div>
                            <div class="grid grid-cols-2">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Salario Semanal:</span>
                                <span class="dark:text-gray-300">{{ formatCurrency(user.employee_detail.week_salary)
                                }}</span>
                            </div>
                            <div class="grid grid-cols-2">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Horas por Semana:</span>
                                <span class="dark:text-gray-300">{{ user.employee_detail.hours_per_week }}h</span>
                            </div>
                            <div class="col-span-2 mt-4">
                                <p class="font-semibold text-gray-600 dark:text-gray-400 mb-2">Horario Semanal</p>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left">
                                        <thead class="bg-gray-100 dark:bg-slate-800">
                                            <tr>
                                                <th class="px-2 py-1">Día</th>
                                                <th class="px-2 py-1">Estatus</th>
                                                <th class="px-2 py-1">Entrada</th>
                                                <th class="px-2 py-1">Salida</th>
                                                <th class="px-2 py-1">Comida</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="day in user.employee_detail.work_days" :key="day.day"
                                                class="border-b dark:border-slate-700">
                                                <td class="px-2 py-1 font-semibold dark:text-gray-300">{{ day.day }}
                                                </td>
                                                <td class="px-2 py-1">
                                                    <el-tag :type="day.works ? 'success' : 'info'" size="small">{{
                                                        day.works ?
                                                            'Laboral' : 'Descanso' }}</el-tag>
                                                </td>
                                                <td class="px-2 py-1 dark:text-gray-400">{{ day.works ? day.start_time :
                                                    '--:--'
                                                }}</td>
                                                <td class="px-2 py-1 dark:text-gray-400">{{ day.works ? day.end_time :
                                                    '--:--'
                                                }}</td>
                                                <td class="px-2 py-1 dark:text-gray-400">{{ day.works ?
                                                    `${day.break_minutes}
                                                    min` : 'N/A' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-500 dark:text-gray-400 py-8">
                            <p>Este usuario no es un empleado.</p>
                        </div>
                    </div>

                    <!-- Columna de Historial de Vacaciones -->
                    <div v-if="user.employee_detail"
                        class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex justify-between items-center border-b dark:border-slate-700 pb-2 mb-4">
                            <h3 class="font-bold text-lg dark:text-gray-200">Historial de Vacaciones</h3>
                            <SecondaryButton v-if="hasPermission('Editar personal')" @click="showVacationModal = true">
                                <i class="fa-solid fa-plus mr-2"></i>Registrar Movimiento
                            </SecondaryButton>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4 text-center">
                            <div class="bg-green-100 dark:bg-green-900/50 p-3 rounded-lg">
                                <p class="text-2xl font-bold text-green-700 dark:text-green-200">{{
                                    vacation_summary.available
                                }}</p>
                                <p class="text-sm text-green-600 dark:text-green-300">Días Disponibles</p>
                            </div>
                            <div class="bg-amber-100 dark:bg-amber-900/50 p-3 rounded-lg">
                                <p class="text-2xl font-bold text-amber-700 dark:text-amber-200">{{
                                    vacation_summary.taken }}
                                </p>
                                <p class="text-sm text-amber-600 dark:text-amber-300">Días Tomados</p>
                            </div>
                        </div>
                        <el-table :data="vacation_logs" max-height="300" stripe size="small" class="dark:!bg-slate-900">
                            <el-table-column prop="date" label="Fecha">
                                <template #default="{ row }">{{ formatDate(row.date) }}</template>
                            </el-table-column>
                            <el-table-column prop="type" label="Tipo de Movimiento">
                                <template #default="{ row }">{{ formatVacationType(row.type) }}</template>
                            </el-table-column>
                            <el-table-column prop="days" label="Días" align="center">
                                <template #default="{ row }">
                                    <span :class="row.days > 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ row.days > 0 ? `+${row.days}` : row.days }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="description" label="Descripción" />
                            <el-table-column prop="creator.name" label="Registrado por" />
                        </el-table>
                    </div>

                    <!-- Historial de Bajas -->
                    <div v-if="user.employee_detail"
                        class="mt-6 bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <h3 class="font-bold text-lg dark:text-gray-200 border-b dark:border-slate-700 pb-2 mb-4">
                            Historial de
                            Bajas y Reactivaciones</h3>
                        <div v-if="termination_logs.length">
                            <el-table :data="termination_logs" max-height="300" stripe size="small"
                                class="dark:!bg-slate-900">
                                <el-table-column prop="termination_date" label="Fecha de Baja">
                                    <template #default="{ row }">{{ formatDate(row.termination_date) }}</template>
                                </el-table-column>
                                <el-table-column prop="reason" label="Motivo de Baja" />
                                <el-table-column prop="terminator.name" label="Dado de baja por" />
                                <el-table-column prop="reinstated_at" label="Fecha de Reactivación">
                                    <template #default="{ row }">
                                        <span v-if="row.reinstated_at">{{ formatDateTime(row.reinstated_at) }}</span>
                                        <el-tag v-else type="info" size="small">Actualmente de baja</el-tag>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                        <div v-else class="text-center text-gray-500 dark:text-gray-400 py-4">
                            <p>El empleado no tiene registros de bajas.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Movimiento de Vacaciones -->
        <DialogModal :show="showVacationModal" @close="showVacationModal = false">
            <template #title>Registrar Movimiento de Vacaciones</template>
            <template #content>
                <form @submit.prevent="submitVacationLog" class="space-y-4">
                    <div>
                        <InputLabel value="Tipo de Movimiento*" />
                        <el-select :teleported="false" v-model="vacationForm.type" class="w-full">
                            <el-option label="Saldo Inicial" value="initial_balance" />
                            <el-option label="Ajuste Manual" value="manual_adjustment" />
                            <el-option label="Días Ganados" value="earned" />
                        </el-select>
                        <InputError :message="vacationForm.errors.type" />
                    </div>
                    <div>
                        <InputLabel value="Fecha del movimiento*" />
                        <el-date-picker :teleported="false" v-model="vacationForm.date" class="!w-full" />
                        <InputError :message="vacationForm.errors.date" />
                    </div>
                    <div>
                        <TextInput v-model="vacationForm.days" label="Días*" type="number" :step="0.5"
                            placeholder="Ej: 14 o -2" :error="vacationForm.errors.days" />
                        <p class="text-xs text-gray-500 m-0">Usa un número positivo para añadir días y negativo para
                            quitar.</p>
                    </div>
                    <div>
                        <TextInput v-model="vacationForm.description" label="Descripción (Motivo)" :isTextarea="true"
                            :error="vacationForm.errors.description" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex items-center space-x-1">
                    <CancelButton @click="showVacationModal = false" :disabled="vacationForm.processing">Cancelar
                    </CancelButton>
                    <SecondaryButton @click="submitVacationLog" :loading="vacationForm.processing">Guardar Movimiento
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- NUEVO: Modal para Dar de Baja -->
        <DialogModal :show="showChangeStatusModal" @close="showChangeStatusModal = false">
            <template #title>
                <span v-if="user.is_active">Dar de baja a "{{ user.name }}"</span>
                <span v-else>Reactivar a "{{ user.name }}"</span>
            </template>
            <template #content>
                <div v-if="user.is_active" class="mb-28">
                    <p class="dark:text-gray-300">
                        Se registrará la baja del empleado y se desvinculará de cualquier cliente que tenga asignado.
                    </p>
                    <form @submit.prevent="submitTermination" class="mt-4 space-y-4">
                        <div>
                            <InputLabel value="Fecha de baja*" />
                            <el-date-picker :teleported="false" v-model="statusForm.disabled_at" type="date"
                                class="!w-full" placeholder="Selecciona una fecha" format="DD MMMM, YYYY"
                                value-format="YYYY-MM-DD" />
                            <InputError :message="statusForm.errors.disabled_at" />
                        </div>
                        <div>
                            <TextInput v-model="statusForm.reason" label="Motivo de la baja (opcional)"
                                :isTextarea="true" :error="statusForm.errors.reason" />
                        </div>
                    </form>
                </div>
                <div v-else class="mb-28">
                    <p class="dark:text-gray-300">
                        ¿Estás seguro de que deseas reactivar a este usuario? Se registrará la fecha de reactivación y
                        el usuario podrá acceder de nuevo al sistema.
                    </p>
                </div>
            </template>
            <template #footer>
                <CancelButton @click="showChangeStatusModal = false" :disabled="statusForm.processing">Cancelar
                </CancelButton>
                <SecondaryButton v-if="user.is_active" @click="submitTermination" :loading="statusForm.processing"
                    class="!bg-red-600 hover:!bg-red-700 text-white">
                    Confirmar Baja
                </SecondaryButton>
                <SecondaryButton v-else @click="submitTermination" :loading="statusForm.processing"
                    class="!bg-green-600 hover:!bg-green-700 text-white">
                    Confirmar Reactivación
                </SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    user: Object,
    vacation_logs: Array,
    termination_logs: Array,
    vacation_summary: Object,
    age: Number,
    seniority: Number,
});

const showVacationModal = ref(false);
const showChangeStatusModal = ref(false);

const permissions = usePage().props.auth.user.permissions || [];
const hasPermission = (permission) => {
    return permissions.includes(permission);
};


const vacationForm = useForm({
    employee_detail_id: props.user.employee_detail?.id,
    type: 'manual_adjustment',
    days: null,
    description: '',
    date: new Date(),
});

const statusForm = useForm({
    disabled_at: new Date().toISOString().slice(0, 10), // Fecha de hoy en formato YYYY-MM-DD
    reason: '',
});

const formatCurrency = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
const formatDate = (dateString) => new Date(dateString.split('T')[0] + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
const formatDateTime = (dateString) => new Date(dateString).toLocaleString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

const formatVacationType = (type) => {
    const types = {
        initial_balance: 'Saldo Inicial',
        manual_adjustment: 'Ajuste Manual',
        taken: 'Vacaciones Tomadas',
        earned: 'Días Ganados',
    };
    return types[type] || type;
};

const submitVacationLog = () => {
    vacationForm.post(route('vacation-logs.store'), {
        onSuccess: () => {
            showVacationModal.value = false;
            vacationForm.reset();
            ElMessage.success('Movimiento registrado');
        },
        preserveScroll: true,
    });
};

const submitTermination = () => {
    statusForm.put(route('users.change-status', props.user), {
        onSuccess: () => {
            showChangeStatusModal.value = false;
            statusForm.reset();
            const message = props.user.is_active ? 'Usuario dado de baja' : 'Usuario reactivado';
            ElMessage.success(message);
        },
        preserveScroll: true,
    });
};

</script>