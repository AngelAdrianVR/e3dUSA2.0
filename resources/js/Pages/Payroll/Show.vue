<template>
    <AppLayout title="Detalle de Nómina">
        <!-- Encabezado de la página -->
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('payrolls.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nómina Semana {{ payroll.week_number }}: {{ formatDate(payroll.start_date) }} - {{ formatDate(payroll.end_date) }}
                </h2>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="py-7 space-y-7">
            <!-- Controles Generales y Totales -->
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <SecondaryButton @click="showPrintModal = true">
                            <i class="fa-solid fa-print mr-2"></i>
                            Imprimir Nóminas
                        </SecondaryButton>
                        <div class="md:col-span-1">
                             <el-input v-model="searchQuery" placeholder="Buscar empleado por nombre..." clearable />
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500 dark:text-gray-400">Total a Pagar (Todos los empleados)</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-500">{{ formatCurrency(grandTotal) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Iteración sobre cada empleado filtrado -->
            <div v-for="employeeData in filteredPayrollData" :key="employeeData.employee.id" class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <el-card class="!rounded-lg !border-0 !shadow-xl !bg-white dark:!bg-slate-900">
                    <template #header>
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ employeeData.employee.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ employeeData.employee.job_position }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Tiempo restante</p>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ timeToComplete(employeeData.employee.hours_per_week, employeeData.employee.total_worked_seconds) }}</p>
                            </div>
                        </div>
                    </template>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2">
                            <el-table :data="employeeData.week_details" :span-method="incidentSpanMethod" stripe class="dark:!bg-slate-900" style="width: 100%" size="small">
                                <el-table-column label="Día" prop="date" width="160">
                                    <template #default="scope">
                                        <span class="font-semibold">{{ scope.row.day_name }}</span>
                                        <span class="text-gray-500 block text-xs">{{ formatDate(scope.row.date) }}</span>
                                    </template>
                                </el-table-column>
                                
                                <el-table-column label="Entrada" align="center" width="100">
                                     <template #default="scope">
                                        <div v-if="scope.row.incident" class="flex items-center justify-center h-full">
                                            <el-tag type="warning" effect="light">{{ scope.row.incident.incident_type.name }}</el-tag>
                                        </div>
                                        <div v-else class="flex items-center justify-center space-x-2 font-mono">
                                            <span>{{ format12HourTime(scope.row.entry) }}</span>
                                            <el-tooltip v-if="scope.row.entry" :content="getLateTooltip(scope.row)" placement="top">
                                                <i :class="getLateIcon(scope.row)"></i>
                                            </el-tooltip>
                                        </div>
                                     </template>
                                </el-table-column>

                                <el-table-column label="Salida" prop="exit" align="center" width="100">
                                    <template #default="scope">
                                        <span v-if="!scope.row.incident" class="font-mono">{{ format12HourTime(scope.row.exit) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Descansos" prop="total_break_time" align="center" width="120">
                                    <template #default="scope">
                                        <el-tooltip v-if="!scope.row.incident && scope.row.breaks_breakdown.length" placement="top">
                                            <template #content>
                                                <div v-for="(br, index) in scope.row.breaks_breakdown" :key="index" class="text-xs">
                                                    De {{ format12HourTime(br.start) }} a {{ format12HourTime(br.end) }}
                                                </div>
                                            </template>
                                            <span class="font-mono cursor-pointer underline decoration-dotted">{{ scope.row.total_break_time }}</span>
                                        </el-tooltip>
                                        <span v-if="!scope.row.incident && !scope.row.breaks_breakdown.length && scope.row.entry" class="text-gray-400 text-xs">Sin descansos</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Tiempo Total" prop="total_time" align="center">
                                     <template #default="scope">
                                        <span v-if="!scope.row.incident" class="font-mono font-semibold">{{ scope.row.total_time }}</span>
                                     </template>
                                </el-table-column>
                                
                                <el-table-column label="Acciones" align="right" width="80">
                                    <template #default="scope">
                                        <el-dropdown trigger="click" @command="handleCommand">
                                            <button @click.stop
                                                class="el-dropdown-link justify-center items-center size-8 rounded-full text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-700 transition-all duration-200 ease-in-out">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <template #dropdown>
                                                <el-dropdown-menu>
                                                    <div v-if="scope.row.incident">
                                                         <el-dropdown-item :command="{ action: 'remove_incident', incident_id: scope.row.incident.id }">
                                                            <i class="fa-solid fa-eraser w-5"></i> Quitar Incidencia
                                                        </el-dropdown-item>
                                                    </div>
                                                    <div v-else>
                                                        <el-dropdown-item :command="{ action: 'edit_attendance', employee_id: employeeData.employee.id, day: scope.row }">
                                                            <i class="fa-solid fa-clock w-5"></i> Modificar Registro
                                                        </el-dropdown-item>
                                                         <el-dropdown-item v-if="scope.row.late_minutes > 0" 
                                                            :command="{ action: 'toggle_late', attendance_id: scope.row.entry_id }">
                                                            <i class="fa-solid fa-shield-halved w-5"></i> 
                                                            {{ scope.row.ignore_late ? 'No ignorar retardo' : 'Ignorar retardo' }}
                                                        </el-dropdown-item>
                                                        <el-dropdown-item divided disabled><span class="text-xs text-gray-400">Asignar Incidencia</span></el-dropdown-item>
                                                        <el-dropdown-item v-for="type in incidentTypes" :key="type.id" :command="{ action: 'add_incident', employee_detail_id: employeeData.employee.id, date: scope.row.date, incident_type_id: type.id }">
                                                            {{ type.name }}
                                                        </el-dropdown-item>
                                                    </div>
                                                </el-dropdown-menu>
                                            </template>
                                        </el-dropdown>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                        <div class="lg:col-span-1 bg-gray-50 dark:bg-slate-800 p-4 rounded-lg space-y-2 text-sm">
                            <h4 class="font-bold text-gray-800 dark:text-gray-200 border-b pb-2 mb-3">Resumen Semanal</h4>
                             <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Salario base (calculado):</span>
                                <span class="font-semibold">{{ formatCurrency(employeeData.summary.base_salary) }}</span>
                            </div>
                            <div v-for="bonus in employeeData.summary.bonuses" :key="bonus.name" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Bono: {{ bonus.name }}</span>
                                <span class="font-semibold text-blue-600">+ {{ formatCurrency(bonus.amount) }}</span>
                            </div>
                            <div v-for="discount in employeeData.summary.discounts" :key="discount.name" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Descuento: {{ discount.name }}</span>
                                <span class="font-semibold text-red-600">- {{ formatCurrency(discount.amount) }}</span>
                            </div>
                            <div class="mt-4 pt-3 border-t flex justify-between items-baseline">
                                <span class="font-bold text-lg dark:text-gray-200">Total a Pagar:</span>
                                <span class="font-bold text-xl text-green-700 dark:text-green-500">{{ formatCurrency(employeeData.summary.total_to_pay) }}</span>
                            </div>
                        </div>
                    </div>
                </el-card>
            </div>
            <div v-if="!filteredPayrollData.length" class="text-center text-gray-500 dark:text-gray-400 py-10">
                <p>No se encontraron empleados con ese nombre.</p>
            </div>
        </div>

        <!-- Modal para Editar Asistencia -->
        <el-dialog v-model="showEditModal" :title="'Modificar Registro de ' + (editingData.employee?.name || '')" width="500px" @closed="attendanceForm.reset()">
            <div v-if="editingData.day">
                <p class="text-center font-semibold mb-6 dark:text-gray-300">{{ formatDate(editingData.day.date) }} - {{ editingData.day.day_name }}</p>
                <form @submit.prevent="submitAttendanceUpdate" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="el-form-item__label">Entrada</label>
                            <el-time-picker v-model="attendanceForm.entry" placeholder="Hora de entrada" format="HH:mm:ss" value-format="HH:mm:ss" class="!w-full" />
                            <InputError :message="attendanceForm.errors.entry" />
                        </div>
                        <div>
                            <label class="el-form-item__label">Salida</label>
                            <el-time-picker v-model="attendanceForm.exit" placeholder="Hora de salida" format="HH:mm:ss" value-format="HH:mm:ss" class="!w-full" />
                            <InputError :message="attendanceForm.errors.exit" />
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Descansos</h4>
                        <div v-for="(br, index) in attendanceForm.breaks" :key="index" class="flex items-center gap-2 mb-2">
                            <el-time-picker v-model="br.start_break" placeholder="Inicio descanso" format="HH:mm:ss" value-format="HH:mm:ss" class="!w-full" />
                            <el-time-picker v-model="br.end_break" placeholder="Fin descanso" format="HH:mm:ss" value-format="HH:mm:ss" class="!w-full" />
                            <button type="button" @click="removeBreak(index)" class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                        <InputError v-for="(error, index) in Object.keys(attendanceForm.errors).filter(k => k.startsWith('breaks.'))" :key="index" :message="attendanceForm.errors[error]" />
                        <SecondaryButton type="button" @click="addBreak" class="mt-2"><i class="fa-solid fa-plus mr-2"></i>Añadir Descanso</SecondaryButton>
                    </div>
                </form>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showEditModal = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitAttendanceUpdate" :loading="attendanceForm.processing">Guardar Cambios</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- Modal para Selección de Impresión de Nóminas -->
        <el-dialog v-model="showPrintModal" title="Seleccionar Nóminas para Imprimir" width="500px" @closed="resetPrintModal">
            <div class="max-h-[60vh] overflow-y-auto">
                <el-checkbox-group v-model="selectedEmployeesForPrint" class="flex flex-col space-y-2">
                    <el-checkbox v-for="employee in payrollData" :key="employee.employee.id" :label="employee.employee.id" size="large" border>
                        {{ employee.employee.name }}
                    </el-checkbox>
                </el-checkbox-group>
            </div>
            <template #footer>
                <div class="w-full flex items-center">
                    <el-checkbox v-model="selectAllForPrint" @change="handleSelectAll" :indeterminate="isIndeterminate">
                        Seleccionar todos
                    </el-checkbox>
                    <span class="dialog-footer ml-auto">
                        <el-button @click="showPrintModal = false">Cancelar</el-button>
                        <el-button type="primary" @click="goToPrintView" :disabled="!selectedEmployeesForPrint.length">
                            <i class="fa-solid fa-file-lines mr-2"></i>
                            Generar Vista Previa
                        </el-button>
                    </span>
                </div>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { ref, computed } from 'vue';
// const { Search } = require('@element-plus/icons-vue');
import axios from 'axios';

const props = defineProps({
    payroll: Object,
    payrollData: Array,
    incidentTypes: Array,
    grandTotal: Number,
});

const searchQuery = ref('');
const showEditModal = ref(false);
const editingData = ref({ employee: null, day: null });
const showPrintModal = ref(false);
const selectedEmployeesForPrint = ref([]);
const selectAllForPrint = ref(false);

const attendanceForm = useForm({
    entry: null,
    exit: null,
    breaks: [],
});

const filteredPayrollData = computed(() => {
    if (!searchQuery.value) return props.payrollData;
    return props.payrollData.filter(employeeData => employeeData.employee.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const isIndeterminate = computed(() => {
    const selectedCount = selectedEmployeesForPrint.value.length;
    return selectedCount > 0 && selectedCount < props.payrollData.length;
});

const handleSelectAll = (val) => {
    selectedEmployeesForPrint.value = val ? props.payrollData.map(e => e.employee.id) : [];
};

const goToPrintView = () => {
    if (selectedEmployeesForPrint.value.length > 0) {
        router.get(route('payrolls.print', props.payroll.id), {
            employees: selectedEmployeesForPrint.value
        });
    } else {
        ElMessage.warning('Debes seleccionar al menos un empleado.');
    }
};

const resetPrintModal = () => {
    selectedEmployeesForPrint.value = [];
    selectAllForPrint.value = false;
};

// ... (resto del script setup sin cambios) ...
const incidentSpanMethod = ({ row, column, rowIndex, columnIndex }) => {
  if (row.incident) {
    if (columnIndex === 1) { // Columna "Entrada"
      return { rowspan: 1, colspan: 4 }; // Une 4 columnas
    } else if (columnIndex >= 2 && columnIndex <= 4) {
      return { rowspan: 0, colspan: 0 }; // Oculta las celdas unidas
    }
  }
};

const formatCurrency = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
const formatDate = (dateString) => new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });

const format12HourTime = (timeString) => {
    if (!timeString) return '--:--';
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes));
    return date.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
};

const getLateIcon = (day) => {
    if (!day) return '';
    if (day.ignore_late) return 'fa-solid fa-face-smile-wink text-green-500';
    if (day.late_minutes > 15) return 'fa-solid fa-face-frown text-red-500';
    if (day.late_minutes > 0) return 'fa-solid fa-face-meh text-amber-500';
    return 'fa-solid fa-face-smile text-green-500';
};

const getLateTooltip = (day) => {
    if (!day) return '';
    if (day.ignore_late) return 'Retardo ignorado';
    if (day.late_minutes > 0) return `${day.late_minutes} minutos tarde`;
    return 'Llegó a tiempo';
};

const formatSecondsToHms = (seconds) => {
    if (seconds < 0) seconds = 0;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${String(m).padStart(2, '0')}m`;
};

const timeToComplete = (totalHours, workedSeconds) => {
    const totalSeconds = totalHours * 3600;
    const remainingSeconds = totalSeconds - workedSeconds;
    return formatSecondsToHms(remainingSeconds);
};

const handleCommand = (command) => {
    if (command.action === 'add_incident') addIncident(command.employee_detail_id, command.date, command.incident_type_id);
    else if (command.action === 'remove_incident') removeIncident(command.incident_id);
    else if (command.action === 'edit_attendance') openEditModal(command.employee_id, command.day);
    else if (command.action === 'toggle_late') toggleIgnoreLate(command.attendance_id);
};

const addIncident = (employeeDetailId, date, incidentTypeId) => {
    const form = useForm({ employee_detail_id: employeeDetailId, payroll_id: props.payroll.id, incident_type_id: incidentTypeId, date: date });
    form.post(route('incidents.store'), { preserveScroll: true, onSuccess: () => ElMessage.success('Incidencia asignada.') });
};

const removeIncident = (incidentId) => {
    router.delete(route('incidents.destroy', incidentId), { preserveScroll: true, onSuccess: () => ElMessage.success('Incidencia eliminada.') });
};

const toggleIgnoreLate = (attendanceId) => {
    router.post(route('attendances.toggleIgnoreLate', attendanceId), {}, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Estado de retardo actualizado.'),
        onError: () => ElMessage.error('No se pudo actualizar el estado del retardo.'),
    });
};

const openEditModal = async (employeeId, day) => {
    const employeeData = props.payrollData.find(e => e.employee.id === employeeId);
    if (!employeeData) return;

    editingData.value = { employee: employeeData.employee, day: day };
    
    try {
        const response = await axios.get(route('attendances.getForDay', { employee: employeeId, date: day.date }));
        attendanceForm.entry = response.data.entry;
        attendanceForm.exit = response.data.exit;
        attendanceForm.breaks = response.data.breaks || [];
        showEditModal.value = true;
    } catch (error) {
        ElMessage.error('No se pudo cargar el registro de asistencia.');
    }
};

const addBreak = () => attendanceForm.breaks.push({ start_break: '', end_break: '' });
const removeBreak = (index) => attendanceForm.breaks.splice(index, 1);

const submitAttendanceUpdate = () => {
    if (!editingData.value.employee || !editingData.value.day) return;
    
    attendanceForm.put(route('attendances.update', { employee: editingData.value.employee.id, date: editingData.value.day.date }), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
        onError: () => {
            ElMessage.error('Hubo errores al guardar. Revisa el formulario.');
        }
    });
};
</script>