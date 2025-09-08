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
                        <SecondaryButton>
                            <i class="fa-solid fa-print mr-2"></i>
                            Imprimir Nóminas
                        </SecondaryButton>
                        
                        <div class="md:col-span-1">
                             <el-input
                                v-model="searchQuery"
                                placeholder="Buscar empleado por nombre..."
                                :prefix-icon="Search"
                                clearable
                            />
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
                                <p class="text-sm text-gray-500 dark:text-gray-400">Tiempo a completar</p>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ employeeData.employee.hours_per_week }} hrs</p>
                            </div>
                        </div>
                    </template>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Tabla de Asistencias -->
                        <div class="lg:col-span-2">
                            <el-table :data="employeeData.week_details" stripe class="dark:!bg-slate-900" style="width: 100%">
                                <el-table-column label="Día" prop="date" width="160">
                                    <template #default="scope">
                                        <span class="font-semibold">{{ scope.row.day_name }}</span>
                                        <span class="text-gray-500 block text-xs">{{ formatDate(scope.row.date) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Registro del Día">
                                    <template #default="scope">
                                        <div v-if="scope.row.incident" class="flex items-center h-full">
                                            <el-tag type="warning" effect="light" class="!text-sm">
                                                {{ scope.row.incident.incident_type.name }}
                                            </el-tag>
                                        </div>
                                        <div v-else class="grid grid-cols-3 gap-2 text-center">
                                            <div>
                                                <p class="text-xs text-gray-400">Entrada</p>
                                                <p class="font-mono text-sm">{{ scope.row.entry ?? '--:--' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-400">Salida</p>
                                                <p class="font-mono text-sm">{{ scope.row.exit ?? '--:--' }}</p>
                                            </div>
                                             <div>
                                                <p class="text-xs text-gray-400">Tiempo Total</p>
                                                <p class="font-mono text-sm font-semibold">{{ scope.row.total_time ?? '0h 0m' }}</p>
                                            </div>
                                        </div>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Opciones" width="100" align="right">
                                    <template #default="scope">
                                        <el-dropdown trigger="click" @command="handleCommand">
                                             <button @click.stop class="el-dropdown-link justify-center items-center size-8 rounded-full text-gray-500 hover:bg-gray-200 dark:hover:bg-slate-700 transition-all duration-200 ease-in-out">
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
                                                        <el-dropdown-item :command="{ action: 'edit_attendance' }">
                                                            <i class="fa-solid fa-clock w-5"></i> Modificar Registro
                                                        </el-dropdown-item>
                                                        <el-dropdown-item divided disabled>
                                                            <span class="text-xs text-gray-400">Asignar Incidencia</span>
                                                        </el-dropdown-item>
                                                        <el-dropdown-item v-for="type in incidentTypes" :key="type.id" 
                                                            :command="{ action: 'add_incident', employee_detail_id: employeeData.employee.id, date: scope.row.date, incident_type_id: type.id }">
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

                        <!-- Resumen del Empleado -->
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
             <!-- Mensaje si no hay resultados -->
            <div v-if="!filteredPayrollData.length" class="text-center text-gray-500 dark:text-gray-400 py-10">
                <p>No se encontraron empleados con ese nombre.</p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Back from '@/Components/MyComponents/Back.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { ref, computed } from 'vue';
import { Search } from '@element-plus/icons-vue';

const props = defineProps({
    payroll: Object,
    payrollData: Array,
    incidentTypes: Array,
    grandTotal: Number,
});

const searchQuery = ref('');

const filteredPayrollData = computed(() => {
    if (!searchQuery.value) {
        return props.payrollData;
    }
    return props.payrollData.filter(employeeData =>
        employeeData.employee.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const incidentForm = useForm({
    employee_detail_id: null,
    payroll_id: props.payroll.id,
    incident_type_id: null,
    date: null,
    comments: '',
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value);
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString + 'T00:00:00').toLocaleDateString('es-MX', options);
};

const handleCommand = (command) => {
    if (command.action === 'add_incident') {
        addIncident(command.employee_detail_id, command.date, command.incident_type_id);
    } else if (command.action === 'remove_incident') {
        removeIncident(command.incident_id);
    } else if (command.action === 'edit_attendance') {
        ElMessage.info('La función para editar registros estará disponible pronto.');
    }
};

const addIncident = (employeeDetailId, date, incidentTypeId) => {
    incidentForm.employee_detail_id = employeeDetailId;
    incidentForm.date = date;
    incidentForm.incident_type_id = incidentTypeId;

    incidentForm.post(route('incidents.store'), {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Incidencia asignada correctamente.'),
        onError: () => ElMessage.error('No se pudo asignar la incidencia.')
    });
};

const removeIncident = (incidentId) => {
    $inertia.delete(route('incidents.destroy', incidentId), {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Incidencia eliminada.'),
        onError: () => ElMessage.error('No se pudo eliminar la incidencia.')
    });
};
</script>