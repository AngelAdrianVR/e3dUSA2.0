<template>
    <AppLayout title="Detalle de Nómina">
        <!-- Encabezado -->
        <div class="px-2 md:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('payrolls.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Detalle de Nómina: Semana {{ payroll.week_number }}
                    <span class="text-sm font-normal">({{ formatDate(payroll.start_date) }} - {{ formatDate(payroll.end_date) }})</span>
                </h2>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="py-4 md:py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-4 md:p-6">
                    
                    <!-- Controles y Totales -->
                    <header class="flex flex-col md:flex-row justify-between items-center mb-4 md:mb-6 space-y-2 md:space-y-0">
                        <div class="w-full md:w-1/3">
                            <el-input v-model="searchQuery" placeholder="Buscar empleado por nombre..." clearable :prefix-icon="Search" />
                        </div>
                        <div class="text-right">
                            <el-button @click="showPrintModal = true" type="primary" :disabled="!payrollData.length">
                                <i class="fa-solid fa-print mr-2"></i>
                                Imprimir Nóminas
                            </el-button>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/50 rounded-lg text-right">
                            <p class="text-sm text-green-700 dark:text-green-300">Total a Pagar de Nómina</p>
                            <p class="text-2xl font-bold text-green-800 dark:text-green-200">{{ formatCurrency(grandTotal) }}</p>
                        </div>
                    </header>

                    <!-- Lista de Empleados -->
                    <div class="space-y-6">
                        <div v-for="employeeData in filteredEmployees" :key="employeeData.employee.id" class="bg-gray-50/50 dark:bg-slate-800/50 p-4 rounded-lg">
                            <!-- Cabecera del Empleado -->
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-3">
                                <div class="mb-2 md:mb-0">
                                    <h3 class="font-bold text-lg dark:text-gray-200">{{ employeeData.employee.name }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ employeeData.employee.job_position }}</p>
                                </div>
                                <div class="w-full md:w-auto grid grid-cols-2 md:flex md:space-x-4 text-sm text-center">
                                    <div class="p-2 bg-blue-100/60 dark:bg-blue-900/40 rounded-l-md">
                                        <p class="font-bold text-blue-800 dark:text-blue-200">{{ formatTime(employeeData.summary.total_worked_seconds ?? 0) }}</p>
                                        <p class="text-xs text-blue-600 dark:text-blue-300">Tiempo trabajado</p>
                                    </div>
                                    <div class="p-2 bg-amber-100/60 dark:bg-amber-900/40 rounded-r-md md:rounded-l-md">
                                        <p class="font-bold text-amber-800 dark:text-amber-200">{{ formatTime( (employeeData.employee.hours_per_week * 3600) - employeeData.summary.total_worked_seconds) }}</p>
                                        <p class="text-xs text-amber-600 dark:text-amber-300">Tiempo a completar</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de Asistencias y Resumen -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                <div class="lg:col-span-2 overflow-x-auto">
                                    <el-table :data="employeeData.week_details" stripe class="dark:!bg-slate-800" size="small" :span-method="incidentSpanMethod">
                                        <el-table-column prop="day_name" label="Día" width="130">
                                            <template #default="{ row }">
                                                {{ row.day_name }}
                                                <el-tooltip v-if="row.worked_on_holiday" content="Trabajó en día festivo (pago extra)" placement="top">
                                                        <i class="fa-solid fa-star text-amber-400"></i>
                                                    </el-tooltip>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="entry" label="Entrada" align="center" width="120">
                                            <template #default="{ row }">
                                                <!-- Condición para mostrar incidencia -->
                                                <div v-if="row.incident" class="px-1">
                                                    <el-tag :type="getIncidentTagType(row.incident.incident_type.name)" class="w-full justify-center" disable-transitions>
                                                        {{ row.incident.incident_type.name }}
                                                    </el-tag>
                                                </div>
                                                <!-- Condición para mostrar entrada normal -->
                                                <div v-else-if="row.entry" class="flex items-center justify-center space-x-2">
                                                    <span>{{ format12HourTime(row.entry) }}</span>
                                                    <el-tooltip :content="getLateTooltip(row)" placement="top">
                                                        <i :class="getLateIcon(row)"></i>
                                                    </el-tooltip>
                                                </div>
                                                <span v-else>--:--</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="exit" label="Salida" align="center" width="120">
                                             <template #default="{ row }">
                                                <span>{{ row.exit ? format12HourTime(row.exit) : '--:--' }}</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Descansos" align="center" width="110">
                                            <template #default="{ row }">
                                                <el-tooltip placement="top" v-if="row.breaks_details.length">
                                                    <template #content>
                                                        <div v-for="(br, index) in row.breaks_details" :key="index" class="text-xs">
                                                            <span>{{ br.start }} - {{ br.end }} ({{ br.total }})</span>
                                                        </div>
                                                    </template>
                                                    <span class="cursor-pointer underline decoration-dotted">{{ row.total_break_time }}</span>
                                                </el-tooltip>
                                                <span v-else>Sin descansos</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="total_time" label="T. Total" align="center" width="90" />
                                        <el-table-column label="Opciones" align="right" width="80">
                                            <template #default="{ row }">
                                                <el-dropdown trigger="click" @command="handleCommand">
                                                    <button @click.stop class="el-dropdown-link justify-center items-center size-7 rounded-full text-gray-500 hover:bg-gray-200 dark:hover:bg-slate-700 transition-all">
                                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                                    </button>
                                                    <template #dropdown>
                                                        <el-dropdown-menu>
                                                             <!-- Opciones si NO hay incidencia -->
                                                            <template v-if="!row.incident">
                                                                <el-dropdown-item :command="{ action: 'edit', employeeId: employeeData.employee.id, date: row.date }">
                                                                    <i class="fa-solid fa-clock w-5"></i> Modificar Registro
                                                                </el-dropdown-item>
                                                                <el-dropdown-item v-for="incidentType in incidentTypes" :key="incidentType.id" :command="{ action: 'add_incident', employeeId: employeeData.employee.id, date: row.date, incidentTypeId: incidentType.id }">
                                                                    <i class="fa-solid fa-person-walking-arrow-right w-5"></i> {{ incidentType.name }}
                                                                </el-dropdown-item>
                                                            </template>
                                                            <!-- Opciones si HAY incidencia -->
                                                            <template v-else>
                                                                <!-- Solo mostrar si la incidencia está en la BD (tiene ID) -->
                                                                <el-dropdown-item v-if="row.incident.id" :command="{ action: 'remove_incident', incidentId: row.incident.id }" class="text-red-500">
                                                                    <i class="fa-solid fa-xmark w-5"></i> Quitar Incidencia
                                                                </el-dropdown-item>
                                                                <!-- Siempre mostrar estas opciones si hay una incidencia (detectada o real) -->
                                                                <el-dropdown-item :command="{ action: 'edit', employeeId: employeeData.employee.id, date: row.date }">
                                                                    <i class="fa-solid fa-clock w-5"></i> Modificar Registro
                                                                </el-dropdown-item>
                                                                 <el-dropdown-item v-for="incidentType in incidentTypes" :key="incidentType.id" :command="{ action: 'add_incident', employeeId: employeeData.employee.id, date: row.date, incidentTypeId: incidentType.id }">
                                                                    {{ incidentType.name }}
                                                                </el-dropdown-item>
                                                            </template>

                                                            <el-dropdown-item v-if="row.late_minutes > 0" divided :command="{ action: 'toggle_late', attendanceId: row.entry_id }">
                                                                <i class="fa-solid fa-shield-halved w-5"></i> {{ row.ignore_late ? 'No Ignorar Retardo' : 'Ignorar Retardo' }}
                                                            </el-dropdown-item>
                                                        </el-dropdown-menu>
                                                    </template>
                                                </el-dropdown>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                                <div class="lg:col-span-1 bg-white dark:bg-slate-900 p-3 rounded-md">
                                    <h4 class="font-semibold text-md mb-2 dark:text-gray-200">Resumen Semanal</h4>
                                    <div class="w-full max-w-md space-y-2 dark:text-gray-300">
                                        <div class="flex justify-between"><span>Salario base (calculado):</span> <span>{{ formatCurrency(employeeData.summary.base_salary) }}</span></div>
                                        <div v-if="employeeData.summary.extra_holiday_pay" class="flex justify-between">
                                            <span>Pago Extra Festivo:</span> <span class="text-blue-600 dark:text-blue-400">+ {{ formatCurrency(employeeData.summary.extra_holiday_pay) }}</span>
                                        </div>
                                        <div v-for="bonus in employeeData.summary.bonuses" :key="bonus.name" class="flex justify-between"><span>Bono: {{ bonus.name }}</span> <span class="text-blue-600 dark:text-blue-400">+ {{ formatCurrency(bonus.amount) }}</span></div>
                                        <div v-if="employeeData.summary.vacation_premium" class="flex justify-between"><span>Prima Vacacional:</span> <span class="text-blue-600 dark:text-blue-400">+ {{ formatCurrency(employeeData.summary.vacation_premium) }}</span></div>
                                        <div v-for="discount in employeeData.summary.discounts" :key="discount.name" class="flex justify-between"><span>Descuento: {{ discount.name }}</span> <span class="text-red-600 dark:text-red-400">- {{ formatCurrency(discount.amount) }}</span></div>
                                        <div class="mt-2 pt-2 border-t dark:border-slate-700 flex justify-between items-baseline">
                                            <span class="font-bold">Total a Pagar:</span>
                                            <span class="font-bold text-lg">{{ formatCurrency(employeeData.summary.total_to_pay) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Edición de Asistencia -->
        <el-dialog v-model="showEditModal" title="Modificar Registro de Asistencia" width="500px" @close="resetEditForm">
            <div v-if="editForm.processing" class="absolute inset-0 bg-white/70 dark:bg-slate-900/70 flex items-center justify-center z-20 rounded-lg">
                <LoadingIsoLogo />
            </div>
            <p class="text-sm dark:text-gray-300 mb-4">Empleado: <span class="font-bold">{{ currentEmployeeName }}</span> | Fecha: <span class="font-bold">{{ currentDate }}</span></p>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="el-form-item__label">Entrada</label>
                        <el-time-picker v-model="editForm.entry" placeholder="Hora de entrada" format="hh:mm A" value-format="HH:mm:ss" style="width: 100%;" />
                    </div>
                    <div>
                        <label class="el-form-item__label">Salida</label>
                        <el-time-picker v-model="editForm.exit" placeholder="Hora de salida" format="hh:mm A" value-format="HH:mm:ss" style="width: 100%;" />
                    </div>
                </div>
                <div v-for="(breakItem, index) in editForm.breaks" :key="index" class="p-3 bg-gray-100 dark:bg-slate-800 rounded-md">
                    <div class="flex justify-between items-center mb-2">
                        <h5 class="font-semibold text-sm dark:text-gray-300">Descanso {{ index + 1 }}</h5>
                        <el-button type="danger" plain size="small" @click="removeBreak(index)" circle><i class="fa-solid fa-trash-can"></i></el-button>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="el-form-item__label">Inicio</label>
                            <el-time-picker v-model="breakItem.start_break" placeholder="Inicio" format="hh:mm A" value-format="HH:mm:ss" style="width: 100%;" />
                        </div>
                        <div>
                            <label class="el-form-item__label">Fin</label>
                             <el-time-picker v-model="breakItem.end_break" placeholder="Fin" format="hh:mm A" value-format="HH:mm:ss" style="width: 100%;" />
                        </div>
                    </div>
                </div>
                <el-button @click="addBreak" type="primary" plain class="w-full">
                    <i class="fa-solid fa-plus mr-2"></i>Añadir Descanso
                </el-button>
            </div>
            <template #footer>
                <el-button @click="showEditModal = false">Cancelar</el-button>
                <el-button type="primary" @click="submitEditForm" :loading="editForm.processing">Guardar Cambios</el-button>
            </template>
        </el-dialog>

        <!-- Modal para Impresión de Nóminas -->
        <el-dialog v-model="showPrintModal" title="Seleccionar Empleados para Imprimir" width="600px">
            <div class="mb-4">
                <el-checkbox
                    v-model="isAllSelected"
                    :indeterminate="isIndeterminate"
                    @change="handleSelectAll"
                >
                    Seleccionar Todos / Deseleccionar Todos
                </el-checkbox>
            </div>
            <el-select-v2
                v-model="printForm.employees"
                :options="employeeOptions"
                placeholder="Selecciona uno o más empleados"
                multiple
                filterable
                clearable
                class="w-full"
            />
            <InputError :message="printForm.errors.employees" class="mt-2" />
            <template #footer>
                <el-button @click="showPrintModal = false">Cancelar</el-button>
                <el-button type="primary" @click="submitPrintForm" :disabled="!printForm.employees.length">Generar Vista Previa</el-button>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import InputError from '@/Components/InputError.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import axios from 'axios';

const props = defineProps({
    payroll: Object,
    payrollData: Array,
    incidentTypes: Array,
    grandTotal: Number,
});

const searchQuery = ref('');
const showEditModal = ref(false);
const showPrintModal = ref(false);
const currentEmployeeName = ref('');
const currentDate = ref('');

const filteredEmployees = computed(() => {
    if (!searchQuery.value) {
        return props.payrollData;
    }
    return props.payrollData.filter(employee =>
        employee.employee.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const employeeOptions = computed(() => {
    return props.payrollData.map(emp => ({
        value: emp.employee.id,
        label: emp.employee.name,
    }));
});


// --- Lógica de Formateo ---
const formatCurrency = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
const formatDate = (dateString) => new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'long' });
const format12HourTime = (timeString) => {
    if (!timeString) return null;
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes));
    return date.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
};
const formatTime = (totalSeconds) => {
    if (totalSeconds < 0) totalSeconds = 0;
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
};

// --- Lógica de Incidencias ---
const incidentSpanMethod = ({ row, columnIndex }) => {
    if (row.incident) {
        if (columnIndex === 1) { // Aplicar span SÓLO a la columna 'Entrada'
            return { rowspan: 1, colspan: 4 };
        } else if (columnIndex >= 2 && columnIndex <= 4) { // Ocultar 'Salida', 'Descansos', 'T. Total'
            return { rowspan: 0, colspan: 0 };
        }
    }
};

const getIncidentTagType = (incidentName) => {
    const unpaid = ['Falta injustificada', 'Permiso sin goce', 'Falta justificada'];
    const partial = ['Incapacidad general'];
    
    if (unpaid.includes(incidentName)) return 'danger';
    if (partial.includes(incidentName)) return 'warning';
    return 'success'; // Pagadas y Descansos
};


// --- Lógica de Retardos ---
const getLateIcon = (day) => {
    if (!day || !day.entry) return '';
    if (day.ignore_late) return 'fa-solid fa-face-smile-wink text-green-500';
    if (day.late_minutes > 15) return 'fa-solid fa-face-frown text-red-500';
    if (day.late_minutes > 0) return 'fa-solid fa-face-meh text-amber-500';
    return 'fa-solid fa-face-smile text-green-500';
};
const getLateTooltip = (day) => {
    if (!day || !day.entry) return 'Sin registro de entrada';
    if (day.ignore_late) return 'Retardo ignorado por administrador';
    if (day.late_minutes > 0) return `${day.late_minutes} minutos tarde`;
    return 'Llegó a tiempo';
};

// --- Lógica del Menú de Acciones ---
const incidentForm = useForm({
    employee_detail_id: null,
    date: null,
    incident_type_id: null,
    payroll_id: null,
});
const toggleLateForm = useForm({});

const handleCommand = (command) => {
    const { action, ...params } = command;
    switch (action) {
        case 'edit':
            openEditModal(params.employeeId, params.date);
            break;
        case 'add_incident':
            addIncident(params.employeeId, params.date, params.incidentTypeId);
            break;
        case 'remove_incident':
            removeIncident(params.incidentId);
            break;
        case 'toggle_late':
            toggleIgnoreLate(params.attendanceId);
            break;
    }
};

const addIncident = (employeeId, date, incidentTypeId) => {
    incidentForm.employee_detail_id = employeeId;
    incidentForm.date = date;
    incidentForm.incident_type_id = incidentTypeId;
    incidentForm.payroll_id = props.payroll.id;

    incidentForm.post(route('incidents.store'), {
        onSuccess: () => {
            ElMessage.success('Incidencia registrada');
            incidentForm.reset();
        },
        preserveScroll: true,
    });
};

const removeIncident = (incidentId) => {
    incidentForm.delete(route('incidents.destroy', incidentId), {
        onSuccess: () => ElMessage.success('Incidencia removida'),
        preserveScroll: true,
    });
};

const toggleIgnoreLate = (attendanceId) => {
    toggleLateForm.put(route('attendances.toggle-ignore-late', attendanceId), {
        onSuccess: () => ElMessage.success('Estado de retardo actualizado'),
        preserveScroll: true,
    });
};


// --- Lógica del Modal de Edición ---
const editForm = useForm({
    entry: null,
    exit: null,
    breaks: [],
});
let editUrl = ref('');

const openEditModal = async (employeeId, date) => {
    const employee = props.payrollData.find(e => e.employee.id === employeeId)?.employee;
    if (!employee) return;

    currentEmployeeName.value = employee.name;
    currentDate.value = formatDate(date);
    editUrl.value = route('attendances.update', { employee: employeeId, date: date });
    
    showEditModal.value = true;
    editForm.processing = true;
    try {
        const response = await axios.get(route('attendances.get-for-day', { employee: employeeId, date: date }));
        editForm.entry = response.data.entry;
        editForm.exit = response.data.exit;
        editForm.breaks = response.data.breaks || [];
    } catch (error) {
        ElMessage.error('No se pudieron cargar los datos del día.');
        showEditModal.value = false;
    } finally {
        editForm.processing = false;
    }
};

const addBreak = () => {
    editForm.breaks.push({ start_break: '', end_break: '' });
};

const removeBreak = (index) => {
    editForm.breaks.splice(index, 1);
};

const submitEditForm = () => {
    editForm.put(editUrl.value, {
        onSuccess: () => {
            showEditModal.value = false;
            ElMessage.success('Registro actualizado');
        },
        onError: () => ElMessage.error('Hubo un error al guardar'),
        preserveScroll: true,
    });
};

const resetEditForm = () => {
    editForm.reset();
    editUrl.value = '';
    currentEmployeeName.value = '';
    currentDate.value = '';
};

// --- Lógica del Modal de Impresión ---
const printForm = useForm({
    employees: [],
});

const isAllSelected = computed(() => {
    return props.payrollData.length > 0 && printForm.employees.length === props.payrollData.length;
});

const isIndeterminate = computed(() => {
    return printForm.employees.length > 0 && printForm.employees.length < props.payrollData.length;
});

const handleSelectAll = (val) => {
    if (val) {
        printForm.employees = props.payrollData.map(emp => emp.employee.id);
    } else {
        printForm.employees = [];
    }
};

const submitPrintForm = () => {
    printForm.get(route('payrolls.print', { payroll: props.payroll.id }), {
        preserveState: false, // Forzar recarga de página a la vista de impresión
    });
};

</script>