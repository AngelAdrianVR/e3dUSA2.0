<template>
    <el-dialog
        :model-value="show"
        title="Detalle de Nómina Semanal"
        width="80%"
        top="5vh"
        @close="$emit('close')"
    >
        <div v-if="employeeData" class="bg-gray-50/50 dark:bg-slate-800/50 p-4 rounded-lg">
            <!-- Cabecera del Empleado -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-3">
                <div class="mb-2 md:mb-0">
                    <h3 class="font-bold text-lg dark:text-gray-200">{{ employeeData.employee.name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ employeeData.employee.job_position }}</p>
                </div>
                <div class="w-full md:w-auto flex text-sm text-center rounded-md overflow-hidden">
                    <div class="flex-1 p-2 bg-blue-100/60 dark:bg-blue-900/40">
                        <p class="font-bold text-blue-800 dark:text-blue-200">{{ formatTime(employeeData.summary.total_worked_seconds ?? 0) }}</p>
                        <p class="text-xs text-blue-600 dark:text-blue-300">Tiempo trabajado</p>
                    </div>
                    <div v-if="employeeData.summary.total_approved_overtime_seconds > 0" class="flex-1 p-2 bg-indigo-100/60 dark:bg-indigo-900/40 border-x border-white/50 dark:border-slate-800/50">
                        <p class="font-bold text-indigo-800 dark:text-indigo-200">{{ formatTime(employeeData.summary.total_approved_overtime_seconds) }}</p>
                        <p class="text-xs text-indigo-600 dark:text-indigo-300">T. Adicional Aprobado</p>
                    </div>
                    <div class="flex-1 p-2 bg-amber-100/60 dark:bg-amber-900/40">
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
                                <div>
                                    <span class="font-semibold">{{ row.day_name }}</span>
                                    <el-tooltip v-if="row.worked_on_holiday" content="Trabajó en día festivo (pago extra)" placement="top">
                                        <i class="fa-solid fa-star text-amber-400 ml-1"></i>
                                    </el-tooltip>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateShort(row.date) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="entry" label="Entrada" align="center" width="120">
                            <template #default="{ row }">
                                <div v-if="row.incident" class="px-1">
                                    <el-tag :type="getIncidentTagType(row.incident.incident_type.name)" class="w-full justify-center" disable-transitions>
                                        {{ row.incident.incident_type.name }}
                                    </el-tag>
                                </div>
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
                        <el-table-column label="T. Adicional" align="center" width="90">
                            <template #default="{ row }">
                                <span v-if="row.approved_overtime_day_seconds > 0" class="text-indigo-600 dark:text-indigo-400 font-semibold">
                                    {{ formatTime(row.approved_overtime_day_seconds) }}
                                </span>
                                <span v-else>--:--</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="total_time" label="T. Total" align="center" width="90">
                            <template #default="{ row }">
                                <el-tooltip v-if="row.unauthorized_overtime_seconds > 0"
                                    :content="`Tiempo adicional no autorizado: ${formatTime(row.unauthorized_overtime_seconds)}`"
                                    placement="top">
                                    <span class="text-red-500 font-bold">{{ row.total_time }}</span>
                                </el-tooltip>
                                <span v-else>{{ row.total_time }}</span>
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
    </el-dialog>
</template>

<script setup>
const props = defineProps({
    show: Boolean,
    employeeData: Object,
});

defineEmits(['close']);

const formatCurrency = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
const formatDateShort = (dateString) => new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }).replace('.', '');
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

const incidentSpanMethod = ({ row, columnIndex }) => {
    if (row.incident) {
        if (columnIndex === 1) { return { rowspan: 1, colspan: 5 }; }
        else if (columnIndex > 1 && columnIndex <= 5) { return { rowspan: 0, colspan: 0 }; }
    }
};

const getIncidentTagType = (incidentName) => {
    const unpaid = ['Falta injustificada', 'Permiso sin goce', 'Falta justificada'];
    const partial = ['Incapacidad general'];
    if (unpaid.includes(incidentName)) return 'danger';
    if (partial.includes(incidentName)) return 'warning';
    return 'success';
};

const getLateIcon = (day) => {
    if (!day || !day.entry) return '';
    if (day.ignore_late) return 'fa-solid fa-face-smile-wink text-green-500';
    if (day.late_minutes > 15) return 'fa-solid fa-face-frown text-red-500';
    if (day.late_minutes > 0) return 'fa-solid fa-face-meh text-amber-500';
    return 'fa-solid fa-face-smile text-green-500';
};
const getLateTooltip = (day) => {
    if (!day || !day.entry) return 'Sin registro de entrada';
    if (day.ignore_late) return 'Retardo ignorado';
    if (day.late_minutes > 0) return `${day.late_minutes} minutos tarde`;
    return 'Llegó a tiempo';
};
</script>