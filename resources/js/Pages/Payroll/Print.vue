<template>
    <Head :title="'Nóminas semana ' + payroll.week_number" />
    <div class="print-container bg-gray-100 dark:bg-slate-800 min-h-screen">
        <!-- Controles de impresión, se ocultan al imprimir -->
        <div class="print-controls no-print p-4 bg-white dark:bg-slate-900 flex justify-between items-center sticky top-0 z-10 shadow-md">
            <h3 class="font-bold text-lg dark:text-gray-200">Vista Previa de Impresión</h3>
            <div>
                <el-button @click="$inertia.visit(route('payrolls.show', payroll.id))">
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    Regresar
                </el-button>
                <el-button type="primary" @click="triggerPrint">
                    <i class="fa-solid fa-print mr-2"></i>
                    Imprimir / Guardar PDF
                </el-button>
            </div>
        </div>

        <!-- Contenedor de las nóminas a imprimir -->
        <div class="py-4 px-2">
            <div v-for="employeeData in employeesToPrint" :key="employeeData.employee.id" class="payroll-slip break-after-page bg-white dark:bg-slate-900 shadow-lg rounded-lg p-6 mx-auto max-w-3xl mb-4">
                <!-- Encabezado de la nómina -->
                <header class="flex justify-between items-start pb-3 border-b dark:border-slate-700">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 dark:text-gray-200">Recibo de Nómina</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Periodo: {{ formatDate(payroll.start_date) }} - {{ formatDate(payroll.end_date) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-sm dark:text-gray-200">{{ employeeData.employee.name }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ employeeData.employee.job_position }}</p>
                    </div>
                </header>

                <!-- Resumen de Horas -->
                <section class="my-4 flex text-sm text-center rounded-md overflow-hidden">
                    <div class="flex-1 p-2 bg-blue-50 dark:bg-blue-900/40">
                        <p class="font-bold text-sm text-blue-800 dark:text-blue-200">{{ formatTime(employeeData.summary.total_worked_seconds ?? 0) }}</p>
                        <p class="text-[11px] text-blue-600 dark:text-blue-300">Tiempo trabajado</p>
                    </div>
                    <div v-if="employeeData.summary.total_approved_overtime_seconds > 0" class="flex-1 p-2 bg-indigo-50 dark:bg-indigo-900/40 border-x border-white/50 dark:border-slate-800/50">
                        <p class="font-bold text-sm text-indigo-800 dark:text-indigo-200">{{ formatTime(employeeData.summary.total_approved_overtime_seconds) }}</p>
                        <p class="text-[11px] text-indigo-600 dark:text-indigo-300">T. Adicional Aprobado</p>
                    </div>
                    <div class="flex-1 p-2 bg-amber-50 dark:bg-amber-900/40">
                        <p class="font-bold text-sm text-amber-800 dark:text-amber-200">{{ formatTime( (employeeData.employee.hours_per_week * 3600) - (employeeData.summary.total_worked_seconds ?? 0)) }}</p>
                        <p class="text-[11px] text-amber-600 dark:text-amber-300">Tiempo por completar</p>
                    </div>
                </section>


                <!-- Tabla de asistencias -->
                <section class="my-4">
                    <h2 class="font-semibold text-sm mb-2 dark:text-gray-300">Detalle de Asistencias</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-2">Día</th>
                                    <th class="p-2 text-center">Entrada</th>
                                    <th class="p-2 text-center">Salida</th>
                                    <th class="p-2 text-center">Descansos</th>
                                    <th class="p-2 text-center">T. Adicional</th>
                                    <th class="p-2 text-center">Tiempo Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="day in employeeData.week_details" :key="day.date" class="border-b dark:border-slate-700">
                                    <td class="p-2 font-semibold">{{ day.day_name }} <span class="font-normal text-gray-500 text-[11px]">{{ formatDate(day.date) }}</span></td>
                                    <td v-if="day.incident" colspan="5" class="p-2 text-center bg-amber-50 dark:bg-amber-900/50">{{ day.incident.incident_type.name }}</td>
                                    <template v-else>
                                        <td class="p-2 text-center font-mono">
                                            <div class="flex items-center justify-center space-x-2">
                                                <span>{{ format12HourTime(day.entry) }}</span>
                                                <i v-if="day.entry" :class="getLateIcon(day)"></i>
                                                 <i v-if="day.worked_on_holiday" class="fa-solid fa-star text-amber-400" title="Trabajó en día festivo"></i>
                                            </div>
                                        </td>
                                        <td class="p-2 text-center font-mono">{{ format12HourTime(day.exit) }}</td>
                                        <td class="p-2 text-center font-mono">{{ day.total_break_time ?? '0h 0m' }}</td>
                                        <td class="p-2 text-center font-mono">
                                             <span v-if="day.approved_overtime_day_seconds > 0" class="text-indigo-600 dark:text-indigo-400 font-semibold">
                                                {{ formatTime(day.approved_overtime_day_seconds) }}
                                            </span>
                                            <span v-else>--:--</span>
                                        </td>
                                        <td class="p-2 text-center font-mono font-bold">
                                            <span v-if="day.unauthorized_overtime_seconds > 0" class="text-red-500" :title="`Tiempo adicional no autorizado: ${formatTime(day.unauthorized_overtime_seconds)}`">
                                                {{ day.total_time ?? '0h 0m' }}
                                            </span>
                                            <span v-else>{{ day.total_time ?? '0h 0m' }}</span>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Resumen de pago -->
                <section class="my-4 flex justify-end">
                     <div class="w-full max-w-xs space-y-1 dark:text-gray-300 text-xs">
                        <h2 class="font-semibold text-sm mb-2">Resumen de Pago</h2>
                        <div class="flex justify-between"><span>Salario base (calculado):</span> <span>{{ formatCurrency(employeeData.summary.base_salary) }}</span></div>
                        <div v-if="employeeData.summary.extra_holiday_pay" class="flex justify-between"><span>Pago Extra Festivo:</span> <span class="text-blue-600 dark:text-blue-400">+ {{ formatCurrency(employeeData.summary.extra_holiday_pay) }}</span></div>
                        <div v-for="bonus in employeeData.summary.bonuses" :key="bonus.name" class="flex justify-between"><span>Bono: {{ bonus.name }}</span> <span class="text-blue-600 dark:text-blue-400">+ {{ formatCurrency(bonus.amount) }}</span></div>
                        <div v-if="employeeData.summary.vacation_premium" class="flex justify-between"><span>Prima Vacacional:</span> <span class="text-blue-600 dark:text-blue-400">+ {{ formatCurrency(employeeData.summary.vacation_premium) }}</span></div>
                        <div v-for="discount in employeeData.summary.discounts" :key="discount.name" class="flex justify-between"><span>Descuento: {{ discount.name }}</span> <span class="text-red-600 dark:text-red-400">- {{ formatCurrency(discount.amount) }}</span></div>
                        <div class="mt-2 pt-2 border-t dark:border-slate-700 flex justify-between items-baseline text-sm">
                            <span class="font-bold">Total a Pagar:</span>
                            <span class="font-bold text-base">{{ formatCurrency(employeeData.summary.total_to_pay) }}</span>
                        </div>
                    </div>
                </section>

                <!-- Pie de página con firma -->
                <footer class="pt-10 text-center dark:text-gray-400">
                    <div class="inline-block border-t-2 pt-2 px-12 dark:border-slate-700 text-xs">
                        <p>Firma de Recibido del Empleado</p>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    payroll: Object,
    employeesToPrint: Array,
});

const formatCurrency = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
const formatDate = (dateString) => new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', { month: 'short', day: 'numeric' });

const format12HourTime = (timeString) => {
    if (!timeString) return '--:--';
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

const getLateIcon = (day) => {
    if (!day) return '';
    if (day.ignore_late) return 'fa-solid fa-face-smile-wink text-green-500';
    if (day.late_minutes > 15) return 'fa-solid fa-face-frown text-red-500';
    if (day.late_minutes > 0) return 'fa-solid fa-face-meh text-amber-500';
    return 'fa-solid fa-face-smile text-green-500';
};

const triggerPrint = () => {
    window.print();
};
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .payroll-slip {
        box-shadow: none !important;
        margin-bottom: 1rem !important; /* Espacio entre recibos en la misma hoja */
        page-break-after: auto; /* El navegador decide cuándo saltar de página */
    }
    body {
        background-color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
.break-after-page {
    break-inside: avoid; /* Intenta no cortar un recibo a la mitad */
    page-break-inside: avoid;
}
</style>

