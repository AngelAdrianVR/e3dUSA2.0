<template>
    <div class="relative">
        <el-popover placement="bottom-end" :width="350" trigger="click" popper-style="border-radius: 0.75rem;">
            <template #reference>
                <button
                    class="size-12 flex justify-center items-center  rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
                    <img src="/images/clock_3d.webp" alt="Reloj de asistencia" class="w-full">
                </button>
            </template>
            <div class="p-2 text-sm">
                <!-- Estado actual -->
                <div class="text-center mb-4">
                    <p class="font-semibold dark:text-gray-200">
                        <i class="fa-solid fa-sun text-amber-400 mr-2"></i>¡Hola!, {{
                            $page.props.auth.user.name.split(' ')[0]
                        }}
                    </p>
                    <p v-if="statusText" class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ statusText }}</p>
                </div>

                <!-- Botones de acción -->
               <div v-if="isAuthorizedDevice" class="flex items-center space-x-2 justify-center">
                    <el-button v-if="canClockIn" @click="punch('entry')" type="success" class="!w-full" :loading="punchForm.processing">
                        <i class="fa-solid fa-play mr-2"></i>Registrar entrada
                    </el-button>
                    <template v-if="isWorking">
                        <el-button @click="punch('start_break')" type="warning" class="!w-full" :loading="punchForm.processing">
                            <i class="fa-solid fa-pause mr-2"></i>Tomar descanso
                        </el-button>
                         <el-button @click="punch('exit')" type="danger" class="!w-full" :loading="punchForm.processing">
                            <i class="fa-solid fa-stop mr-2"></i>Registrar salida
                        </el-button>
                    </template>
                    <template v-if="isOnBreak">
                        <el-button @click="punch('end_break')" type="primary" class="!w-full" :loading="punchForm.processing">
                            <i class="fa-solid fa-play mr-2"></i>Reanudar trabajo
                        </el-button>
                         <el-button @click="punch('exit')" type="danger" class="!w-full" :loading="punchForm.processing">
                            <i class="fa-solid fa-stop mr-2"></i>Registrar salida
                        </el-button>
                    </template>
                </div>

                <!-- Collapse de asistencia semanal -->
                <el-collapse class="mt-4" v-model="activeCollapse">
                    <el-collapse-item name="1">
                        <template #title>
                            <span class="font-semibold text-xs dark:text-gray-300">Asistencia Semanal</span>
                        </template>
                        <el-select v-model="selectedPayrollId" placeholder="Selecciona un periodo" class="w-full"
                            @change="fetchPayrollDetails" :loading="loadingDetails">
                            <el-option v-for="period in $page.props.payroll_periods" :key="period.id"
                                :label="`Semana ${period.week_number} (${formatDate(period.start_date)} - ${formatDate(period.end_date)})`"
                                :value="period.id" />
                        </el-select>
                    </el-collapse-item>
                </el-collapse>
            </div>
        </el-popover>

        <!-- Modal de detalle de nómina -->
        <PayrollDetailModal :show="showPayrollDetailModal" :employeeData="selectedPayrollData"
            @close="showPayrollDetailModal = false" />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import PayrollDetailModal from './PayrollDetailModal.vue';

const page = usePage();
const attendanceStatus = computed(() => page.props.attendance_status);
const activeCollapse = ref([]);
const selectedPayrollId = ref(null);
const showPayrollDetailModal = ref(false);
const selectedPayrollData = ref(null);
const loadingDetails = ref(false);

const isAuthorizedDevice = computed(() => page.props.is_authorized_device);

const punchForm = useForm({});

const statusText = computed(() => {
    if (!attendanceStatus.value) return null;
    const time = new Date(attendanceStatus.value.timestamp).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    switch (attendanceStatus.value.status) {
        case 'working': return `Estas trabajando desde las ${time}`;
        case 'on_break': return `Estas en descanso desde las ${time}`;
        case 'clocked_out': return `Salida registrada a las ${time}`;
        default: return 'No has iniciado labores';
    }
});

const canClockIn = computed(() => attendanceStatus.value?.status === 'not_clocked_in');
const isWorking = computed(() => attendanceStatus.value?.status === 'working');
const isOnBreak = computed(() => attendanceStatus.value?.status === 'on_break');

const punch = (action) => {
    punchForm.post(route('attendances.punch', { action }), {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Asistencia registrada'),
        onError: () => ElMessage.error('No se pudo registrar la asistencia'),
    });
};

const formatDate = (dateString) => new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', { month: 'short', day: 'numeric' });

const fetchPayrollDetails = async (payrollId) => {
    if (!payrollId) return;
    loadingDetails.value = true;
    try {
        const response = await axios.get(route('payrolls.get-employee-details', { payroll: payrollId }));
        selectedPayrollData.value = response.data;
        showPayrollDetailModal.value = true;
    } catch (error) {
        ElMessage.error('No se pudieron cargar los detalles de la nómina.');
    } finally {
        loadingDetails.value = false;
        selectedPayrollId.value = null; // Resetear el selector
        activeCollapse.value = []; // Cerrar el collapse
    }
};
</script>